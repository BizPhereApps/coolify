<?php

use App\Actions\Provisioning\RefundManagedServerProRata;
use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_refund');
});

function managedServerOnPaidInvoice(int $priceNgn = 12_750): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $server = Server::factory()->create(['team_id' => $team->id]);

    $managed = NolbaseManagedServer::create([
        'server_id' => $server->id,
        'provider' => 'hetzner',
        'provider_resource_id' => '9300',
        'plan_slug' => 'cax11',
        'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => $priceNgn,
        'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
    ]);

    $invoice = NolbaseManagedInvoice::create([
        'team_id' => $team->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'total_ngn' => $priceNgn,
        'line_items' => [[
            'managed_server_id' => $managed->id,
            'server_id' => $server->id,
            'plan_slug' => 'cax11',
            'price_ngn' => $priceNgn,
        ]],
        'paystack_reference' => 'paid_ref_001',
        'status' => NolbaseManagedInvoice::STATUS_SUCCESS,
        'billed_at' => now()->startOfMonth(),
    ]);

    return [$managed, $invoice];
}

function fakePaystackRefundOk(): void
{
    Http::fake([
        'api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => ['id' => 1, 'status' => 'pending', 'amount' => 1, 'currency' => 'NGN'],
        ], 200),
    ]);
}

test('refunds the unused portion of the month pro-rata', function () {
    // Mid-month delete: day 15 of a 30-day month → 16 of 30 days remaining → 53.3%
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
    [$managed, $invoice] = managedServerOnPaidInvoice(priceNgn: 12_750);
    fakePaystackRefundOk();

    $result = RefundManagedServerProRata::run($managed);

    // 12750 * 16 / 30 = 6800
    expect($result['refunded_ngn'])->toBe(6_800)
        ->and($invoice->fresh()->refunded_ngn)->toBe(6_800)
        ->and($invoice->fresh()->refunded_at)->not->toBeNull();

    Http::assertSent(fn ($r) => $r['transaction'] === 'paid_ref_001' && $r['amount'] === 680_000);

    Carbon::setTestNow();
});

test('no refund when no successful invoice exists for the month', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15));
    [$managed] = managedServerOnPaidInvoice();
    NolbaseManagedInvoice::query()->update(['status' => NolbaseManagedInvoice::STATUS_FAILED]);
    Http::fake();

    $result = RefundManagedServerProRata::run($managed);

    expect($result['refunded_ngn'])->toBe(0)
        ->and($result['reason'])->toBe('no_paid_invoice');
    Http::assertNothingSent();
    Carbon::setTestNow();
});

test('no refund when server is not on the invoice line items', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15));
    [$managed, $invoice] = managedServerOnPaidInvoice();
    $invoice->update(['line_items' => [[
        'managed_server_id' => 99999, 'server_id' => 1, 'plan_slug' => 'x', 'price_ngn' => 100,
    ]]]);
    Http::fake();

    $result = RefundManagedServerProRata::run($managed);

    expect($result['reason'])->toBe('server_not_on_invoice');
    Carbon::setTestNow();
});

test('does not double-refund: subsequent calls see the prior refund cap', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15));
    [$managed, $invoice] = managedServerOnPaidInvoice(priceNgn: 12_750);
    fakePaystackRefundOk();

    RefundManagedServerProRata::run($managed);
    $second = RefundManagedServerProRata::run($managed);

    // Second call refunds another 6800, total 13600, but the cap is total_ngn=12750
    expect($second['refunded_ngn'])->toBe(12_750 - 6_800)
        ->and($invoice->fresh()->refunded_ngn)->toBe(12_750);
    Carbon::setTestNow();
});

test('deleting a Server triggers a pro-rata refund via the deleting hook', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
    [$managed, $invoice] = managedServerOnPaidInvoice(priceNgn: 12_750);
    Http::fake([
        'api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => ['id' => 1, 'status' => 'pending', 'amount' => 1, 'currency' => 'NGN'],
        ], 200),
        'api.hetzner.cloud/*' => Http::response(null, 204),
    ]);
    NolbaseSetting::write('nolbase_hetzner_api_token', 'hz_test');

    $managed->server->delete();

    expect($invoice->fresh()->refunded_ngn)->toBe(6_800)
        ->and($managed->fresh()->billing_status)
        ->toBe(NolbaseManagedServer::BILLING_DECOMMISSIONED);
    Carbon::setTestNow();
});

test('paystack refund failures do not mark the invoice as refunded', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 15));
    [$managed, $invoice] = managedServerOnPaidInvoice();
    Http::fake([
        'api.paystack.co/refund' => Http::response(['status' => false, 'message' => 'declined'], 400),
    ]);

    $result = RefundManagedServerProRata::run($managed);

    expect($result['reason'])->toBe('paystack_error')
        ->and($invoice->fresh()->refunded_ngn)->toBeNull();
    Carbon::setTestNow();
});
