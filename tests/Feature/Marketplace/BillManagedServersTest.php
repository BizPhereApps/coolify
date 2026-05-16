<?php

use App\Actions\Provisioning\BillManagedServers;
use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_billing');
});

function teamWithManagedServer(int $priceNgn = 12750, ?string $authCode = 'AUTH_test_xyz'): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner->id, ['role' => 'owner']);

    Subscription::create([
        'team_id' => $team->id,
        'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => Subscription::PERIOD_MONTHLY,
        'paystack_authorization_code' => $authCode,
        'paystack_customer_code' => 'CUS_'.$team->id,
    ]);

    $server = Server::factory()->create(['team_id' => $team->id]);
    NolbaseManagedServer::create([
        'server_id' => $server->id,
        'provider' => 'hetzner',
        'provider_resource_id' => (string) (9000 + $team->id),
        'plan_slug' => 'cax11',
        'location_slug' => 'fsn1',
        'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => $priceNgn,
        'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
    ]);

    return [$team, $server];
}

function fakePaystackChargeOk(): void
{
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'paid_ok',
                'amount' => 1275000,
            ],
        ], 200),
    ]);
}

function fakePaystackChargeFailed(): void
{
    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'failed',
                'gateway_response' => 'Insufficient funds',
            ],
        ], 200),
    ]);
}

test('happy path: charges the tenant and records an invoice', function () {
    [$team] = teamWithManagedServer(priceNgn: 12_750);
    fakePaystackChargeOk();

    $result = BillManagedServers::run();

    expect($result['billed'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($result['total_ngn'])->toBe(12_750);

    $invoice = NolbaseManagedInvoice::where('team_id', $team->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(NolbaseManagedInvoice::STATUS_SUCCESS)
        ->and($invoice->total_ngn)->toBe(12_750)
        ->and($invoice->line_items)->toHaveCount(1)
        ->and($invoice->billed_at)->not->toBeNull();
});

test('tenant with multiple managed servers pays the sum', function () {
    [$team] = teamWithManagedServer(priceNgn: 12_750);

    // Add a second server to the same team
    $server2 = Server::factory()->create(['team_id' => $team->id]);
    NolbaseManagedServer::create([
        'server_id' => $server2->id, 'provider' => 'hetzner',
        'provider_resource_id' => '9999', 'plan_slug' => 'cax21',
        'cost_basis_ngn_monthly' => 17000, 'price_ngn_monthly' => 25500,
        'markup_pct' => 50, 'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
    ]);

    fakePaystackChargeOk();
    BillManagedServers::run();

    $invoice = NolbaseManagedInvoice::where('team_id', $team->id)->first();
    expect($invoice->total_ngn)->toBe(38_250)
        ->and($invoice->line_items)->toHaveCount(2);

    Http::assertSent(fn ($r) => $r['amount'] === 3_825_000); // ₦38,250 in kobo
});

test('past-due: when Paystack reports failure, managed servers go past_due and notify the team', function () {
    Mail::fake();
    [$team, $server] = teamWithManagedServer();
    fakePaystackChargeFailed();

    $result = BillManagedServers::run();

    expect($result['failed'])->toBe(1)
        ->and($result['billed'])->toBe(0);

    $invoice = NolbaseManagedInvoice::where('team_id', $team->id)->first();
    expect($invoice->status)->toBe(NolbaseManagedInvoice::STATUS_FAILED)
        ->and($invoice->failure_reason)->toContain('Insufficient funds');

    $managed = NolbaseManagedServer::where('server_id', $server->id)->first();
    expect($managed->billing_status)->toBe(NolbaseManagedServer::BILLING_PAST_DUE)
        ->and($managed->past_due_since)->not->toBeNull();
});

test('successful recharge clears past_due_since and flips status back to active', function () {
    [$team, $server] = teamWithManagedServer();
    // Simulate a previously past-due server that the team is now retrying to
    // pay (e.g. they updated their card and operator triggered a re-run).
    NolbaseManagedServer::where('server_id', $server->id)->update([
        'billing_status' => NolbaseManagedServer::BILLING_PAST_DUE,
        'past_due_since' => now()->subDays(3),
    ]);
    fakePaystackChargeOk();

    BillManagedServers::run();

    $managed = NolbaseManagedServer::where('server_id', $server->id)->first();
    expect($managed->billing_status)->toBe(NolbaseManagedServer::BILLING_ACTIVE)
        ->and($managed->past_due_since)->toBeNull();
});

test('idempotent: re-running in the same month does not double-charge', function () {
    [$team] = teamWithManagedServer();
    fakePaystackChargeOk();

    BillManagedServers::run();
    $secondResult = BillManagedServers::run();

    expect($secondResult['skipped'])->toBe(1)
        ->and(NolbaseManagedInvoice::where('team_id', $team->id)->count())->toBe(1);
});

test('tenant without a saved authorization code is recorded as pending', function () {
    [$team] = teamWithManagedServer(authCode: null);
    Http::fake(); // should NOT be called

    $result = BillManagedServers::run();

    expect($result['skipped'])->toBe(1);

    $invoice = NolbaseManagedInvoice::where('team_id', $team->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(NolbaseManagedInvoice::STATUS_PENDING)
        ->and($invoice->failure_reason)->toContain('authorization');

    Http::assertNothingSent();
});

test('decommissioned and suspended managed servers are excluded from billing', function () {
    [$team, $server] = teamWithManagedServer();
    NolbaseManagedServer::where('server_id', $server->id)->update([
        'billing_status' => NolbaseManagedServer::BILLING_DECOMMISSIONED,
        'decommissioned_at' => now()->subDay(),
    ]);

    Http::fake();
    $result = BillManagedServers::run();

    expect($result['billed'])->toBe(0)
        ->and(NolbaseManagedInvoice::count())->toBe(0);
});

test('billing for a specific historical month uses that month as billing_month', function () {
    [$team] = teamWithManagedServer();
    fakePaystackChargeOk();

    $forMonth = Carbon::create(2026, 1, 1);
    BillManagedServers::run($forMonth);

    $invoice = NolbaseManagedInvoice::where('team_id', $team->id)->first();
    expect($invoice->billing_month->toDateString())->toBe('2026-01-01');
});
