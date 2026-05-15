<?php

use App\Models\ClientInvitation;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\Server;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('constants.coolify.self_hosted', false);
    config()->set('paystack.secret_key', 'sk_test_marketplace');
});

function makeOfferAndInvitation(array $invitationOverrides = []): ClientInvitation
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id,
        'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => Subscription::PERIOD_MONTHLY,
    ]);
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id,
        'server_id' => $server->id,
        'name' => 'Starter',
        'ram_mb' => 512,
        'disk_gb' => 5,
        'max_apps' => 1,
        'max_databases' => 0,
        'price_ngn_monthly' => 10000,
        'price_ngn_annual' => 100000,
        'is_active' => true,
    ]);

    return ClientInvitation::create(array_merge([
        'hosting_offer_id' => $offer->id,
        'developer_team_id' => $devTeam->id,
        'email' => 'client@example.com',
        'project_name' => "Bola's Shop",
        'invitation_token' => ClientInvitation::generateToken(),
        'sent_at' => now(),
        'expires_at' => now()->addDays(7),
    ], $invitationOverrides));
}

test('a pending invitation page can be loaded by anyone with the token', function () {
    $invitation = makeOfferAndInvitation();

    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => $invitation->invitation_token])
        ->assertSee("Bola's Shop")
        ->assertSee('Starter');
});

test('an unknown token 404s', function () {
    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => str_repeat('x', 64)]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('clicking pay redirects to the Paystack hosted checkout URL', function () {
    $invitation = makeOfferAndInvitation();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'access_abc',
                'reference' => 'ref_marketplace_1',
            ],
        ], 200),
    ]);

    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => $invitation->invitation_token])
        ->call('pay')
        ->assertRedirect('https://checkout.paystack.com/abc');

    // Confirm the metadata we set on the Paystack call carried the invitation_id.
    Http::assertSent(function ($request) use ($invitation) {
        return str_contains($request->url(), '/transaction/initialize')
            && data_get($request->data(), 'metadata.invitation_id') === $invitation->id
            && data_get($request->data(), 'metadata.marketplace') === true
            && $request['amount'] === 1000000; // ₦10,000 monthly in kobo
    });
});

test('annual period sends the annual price', function () {
    $invitation = makeOfferAndInvitation();
    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'x', 'access_code' => 'y', 'reference' => 'z'],
        ], 200),
    ]);

    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => $invitation->invitation_token])
        ->call('setPeriod', 'annual')
        ->call('pay');

    Http::assertSent(fn ($r) => $r['amount'] === 10000000); // ₦100,000 in kobo
});

test('an expired invitation cannot be paid', function () {
    $invitation = makeOfferAndInvitation([
        'expires_at' => now()->subDays(1),
    ]);

    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => $invitation->invitation_token])
        ->call('pay')
        ->assertHasErrors('pay');
});

test('an already-accepted invitation cannot be paid again', function () {
    $invitation = makeOfferAndInvitation([
        'accepted_at' => now()->subDays(1),
    ]);

    Livewire::test(\App\Livewire\Marketplace\AcceptInvitation::class, ['token' => $invitation->invitation_token])
        ->call('pay')
        ->assertHasErrors('pay');
});
