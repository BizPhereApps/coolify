<?php

use App\Actions\Paystack\VerifyClientTransaction;
use App\Models\ClientInvitation;
use App\Models\ClientSubscription;
use App\Models\HostingOffer;
use App\Models\MarketplaceTransaction;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Server;
use App\Models\SubTeam;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_marketplace_verify');
    config()->set('paystack.marketplace_fee_pct', 10);
});

function setupInvitation(): array
{
    $devTeam = Team::factory()->create(['name' => 'Adaeze Agency']);
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0,
        'price_ngn_monthly' => 10000,
    ]);
    $invitation = ClientInvitation::create([
        'hosting_offer_id' => $offer->id, 'developer_team_id' => $devTeam->id,
        'email' => 'bola@example.com', 'project_name' => "Bola's Shop",
        'invitation_token' => ClientInvitation::generateToken(),
        'sent_at' => now(), 'expires_at' => now()->addDays(7),
    ]);

    return [$devTeam, $offer, $invitation];
}

function paystackSuccessResponse(int $invitationId, int $offerId, int $devTeamId): array
{
    return [
        'status' => true,
        'data' => [
            'status' => 'success',
            'reference' => 'ref_marketplace_ok',
            'amount' => 1000000, // ₦10,000 in kobo
            'metadata' => [
                'marketplace' => true,
                'invitation_id' => $invitationId,
                'hosting_offer_id' => $offerId,
                'developer_team_id' => $devTeamId,
                'period' => 'monthly',
            ],
            'customer' => [
                'email' => 'bola@example.com',
                'customer_code' => 'CUS_bola_ok',
            ],
            'authorization' => ['subscription_code' => 'SUB_bola_ok'],
        ],
    ];
}

test('a successful verify creates SubTeam, Project, ClientSubscription, and ledger entries', function () {
    [$devTeam, $offer, $invitation] = setupInvitation();
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(paystackSuccessResponse($invitation->id, $offer->id, $devTeam->id), 200),
    ]);

    $sub = VerifyClientTransaction::run('ref_marketplace_ok');

    expect($sub)->toBeInstanceOf(ClientSubscription::class)
        ->and($sub->status)->toBe(ClientSubscription::STATUS_ACTIVE)
        ->and($sub->paystack_subscription_code)->toBe('SUB_bola_ok')
        ->and($sub->paystack_customer_code)->toBe('CUS_bola_ok');

    // SubTeam created with the right parent + client user
    $subTeam = SubTeam::first();
    expect($subTeam->parent_team_id)->toBe($devTeam->id)
        ->and($subTeam->hosting_offer_id)->toBe($offer->id)
        ->and($subTeam->clientUser->email)->toBe('bola@example.com');

    // Project under the Developer's team, named after the invitation
    $project = Project::where('team_id', $devTeam->id)->first();
    expect($project->name)->toBe("Bola's Shop");
    expect($subTeam->project_id)->toBe($project->id);

    // Money ledger: a charge row + a fee row
    $charge = MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_CHARGE)->first();
    $fee = MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_FEE)->first();
    expect($charge->amount_ngn)->toBe(10000)
        ->and($charge->fee_ngn)->toBe(1000) // 10% of 10000
        ->and($charge->net_ngn)->toBe(9000)
        ->and($charge->paystack_reference)->toBe('ref_marketplace_ok')
        ->and($fee->amount_ngn)->toBe(1000);

    // Invitation marked accepted
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('Paystack-reported failure aborts the verification', function () {
    [$devTeam, $offer, $invitation] = setupInvitation();
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'failed', 'metadata' => [
                'invitation_id' => $invitation->id,
                'hosting_offer_id' => $offer->id,
            ]],
        ], 200),
    ]);

    expect(fn () => VerifyClientTransaction::run('ref_fail'))
        ->toThrow(\RuntimeException::class, 'did not succeed');

    expect(SubTeam::count())->toBe(0)
        ->and(ClientSubscription::count())->toBe(0)
        ->and(MarketplaceTransaction::count())->toBe(0);
});

test('an already-accepted invitation cannot be verified twice', function () {
    [$devTeam, $offer, $invitation] = setupInvitation();
    $invitation->update(['accepted_at' => now()->subHour()]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(paystackSuccessResponse($invitation->id, $offer->id, $devTeam->id), 200),
    ]);

    expect(fn () => VerifyClientTransaction::run('ref_again'))
        ->toThrow(\RuntimeException::class, 'no longer pending');
});

test('a returning client (existing user) reuses their User row', function () {
    [$devTeam, $offer, $invitation] = setupInvitation();
    $existing = User::create([
        'name' => 'Bola',
        'email' => 'bola@example.com',
        'password' => bcrypt('whatever'),
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(paystackSuccessResponse($invitation->id, $offer->id, $devTeam->id), 200),
    ]);

    $sub = VerifyClientTransaction::run('ref_returning');

    expect(User::where('email', 'bola@example.com')->count())->toBe(1)
        ->and($sub->subTeam->client_user_id)->toBe($existing->id);
});
