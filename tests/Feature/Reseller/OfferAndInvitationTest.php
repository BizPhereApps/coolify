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
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('constants.coolify.self_hosted', false);
});

function makeProTeamAndSignIn(): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team->id, ['role' => 'owner']);
    Subscription::create([
        'team_id' => $team->id,
        'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => Subscription::PERIOD_MONTHLY,
    ]);
    auth()->login($user);
    session(['currentTeam' => $user->currentTeam = $team]);

    return $team->fresh()->load('subscription.plan');
}

function makeFreeTeamAndSignIn(): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team->id, ['role' => 'owner']);
    Subscription::create([
        'team_id' => $team->id,
        'plan_id' => Plan::free()->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => Subscription::PERIOD_MONTHLY,
    ]);
    auth()->login($user);
    session(['currentTeam' => $user->currentTeam = $team]);

    return $team->fresh()->load('subscription.plan');
}

test('Pro team is allowed to resell, Free team is not', function () {
    $pro = makeProTeamAndSignIn();
    expect($pro->canResell())->toBeTrue();

    $free = makeFreeTeamAndSignIn();
    expect($free->canResell())->toBeFalse();
});

test('a Pro Developer can create a hosting offer', function () {
    $team = makeProTeamAndSignIn();
    $server = Server::factory()->create(['team_id' => $team->id]);

    Livewire::test(\App\Livewire\Reseller\OfferForm::class)
        ->set('server_id', $server->id)
        ->set('name', 'Starter')
        ->set('ram_mb', 512)
        ->set('disk_gb', 5)
        ->set('max_apps', 1)
        ->set('max_databases', 0)
        ->set('price_ngn_monthly', 10000)
        ->call('save');

    $offer = HostingOffer::where('team_id', $team->id)->first();
    expect($offer)->not->toBeNull()
        ->and($offer->name)->toBe('Starter')
        ->and($offer->price_ngn_monthly)->toBe(10000);
});

test('a Developer cannot pick a server they do not own', function () {
    $team = makeProTeamAndSignIn();
    $otherTeam = Team::factory()->create();
    $stolenServer = Server::factory()->create(['team_id' => $otherTeam->id]);

    Livewire::test(\App\Livewire\Reseller\OfferForm::class)
        ->set('server_id', $stolenServer->id)
        ->set('name', 'Bad')
        ->set('ram_mb', 512)->set('disk_gb', 5)->set('max_apps', 1)->set('max_databases', 0)
        ->set('price_ngn_monthly', 10000)
        ->call('save')
        ->assertHasErrors('server_id');

    expect(HostingOffer::count())->toBe(0);
});

test('a Developer can send a client invitation that gets a signed token + 7-day expiry', function () {
    $team = makeProTeamAndSignIn();
    $server = Server::factory()->create(['team_id' => $team->id]);
    $offer = HostingOffer::create([
        'team_id' => $team->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);

    Livewire::test(\App\Livewire\Reseller\InvitationForm::class)
        ->set('hosting_offer_id', $offer->id)
        ->set('email', 'bola@example.com')
        ->set('project_name', "Bola's Shop")
        ->call('send');

    $inv = ClientInvitation::first();
    expect($inv)->not->toBeNull()
        ->and($inv->email)->toBe('bola@example.com')
        ->and($inv->project_name)->toBe("Bola's Shop")
        ->and(strlen($inv->invitation_token))->toBe(64)
        ->and($inv->expires_at->isAfter(now()->addDays(6)))->toBeTrue()
        ->and($inv->expires_at->isBefore(now()->addDays(8)))->toBeTrue()
        ->and($inv->isPending())->toBeTrue();
});

test('a Developer cannot send an invitation for an offer they do not own', function () {
    $team = makeProTeamAndSignIn();
    $otherTeam = Team::factory()->create();
    $otherServer = Server::factory()->create(['team_id' => $otherTeam->id]);
    $otherOffer = HostingOffer::create([
        'team_id' => $otherTeam->id, 'server_id' => $otherServer->id,
        'name' => 'Stolen', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);

    Livewire::test(\App\Livewire\Reseller\InvitationForm::class)
        ->set('hosting_offer_id', $otherOffer->id)
        ->set('email', 'bola@example.com')
        ->set('project_name', 'X')
        ->call('send');

    expect(ClientInvitation::count())->toBe(0);
});

test('isPending / isExpired behave correctly across the invitation lifecycle', function () {
    $team = makeProTeamAndSignIn();
    $server = Server::factory()->create(['team_id' => $team->id]);
    $offer = HostingOffer::create([
        'team_id' => $team->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);

    $pending = ClientInvitation::create([
        'hosting_offer_id' => $offer->id, 'developer_team_id' => $team->id,
        'email' => 'a@x', 'project_name' => 'a',
        'invitation_token' => ClientInvitation::generateToken(),
        'sent_at' => now(), 'expires_at' => now()->addDays(5),
    ]);
    $expired = ClientInvitation::create([
        'hosting_offer_id' => $offer->id, 'developer_team_id' => $team->id,
        'email' => 'b@x', 'project_name' => 'b',
        'invitation_token' => ClientInvitation::generateToken(),
        'sent_at' => now()->subDays(10), 'expires_at' => now()->subDays(3),
    ]);

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isExpired())->toBeFalse()
        ->and($expired->isPending())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue();
});
