<?php

use App\Models\ClientInvitation;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\Server;
use App\Models\SubTeam;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('constants.coolify.self_hosted', false);
});

function devTeamWithServer(): array
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    $owner = User::factory()->create();
    $devTeam->members()->attach($owner->id, ['role' => 'owner']);
    auth()->login($owner);
    session(['currentTeam' => $owner->currentTeam = $devTeam]);
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);

    return [$devTeam, $server, $offer];
}

// I5
test('sending an invitation to an email already pending on the same offer cancels the old and issues a fresh token', function () {
    [$devTeam, $server, $offer] = devTeamWithServer();
    Mail::fake();

    $first = ClientInvitation::create([
        'hosting_offer_id' => $offer->id, 'developer_team_id' => $devTeam->id,
        'email' => 'bola@example.com', 'project_name' => 'Bola Shop',
        'invitation_token' => ClientInvitation::generateToken(),
        'sent_at' => now()->subMinutes(5), 'expires_at' => now()->addDays(7),
    ]);

    Livewire::test(\App\Livewire\Reseller\InvitationForm::class)
        ->set('hosting_offer_id', $offer->id)
        ->set('email', 'bola@example.com')
        ->set('project_name', 'Bola Shop (resent)')
        ->call('send');

    $first->refresh();
    expect($first->cancelled_at)->not->toBeNull();

    $invitations = ClientInvitation::where('email', 'bola@example.com')->get();
    expect($invitations->count())->toBe(2);
    $latest = $invitations->where('cancelled_at', null)->first();
    expect($latest->invitation_token)->not->toBe($first->invitation_token);
});

// I4
test('sending an invitation persists the row and the mail dispatch does not throw', function () {
    [$devTeam, $server, $offer] = devTeamWithServer();

    Livewire::test(\App\Livewire\Reseller\InvitationForm::class)
        ->set('hosting_offer_id', $offer->id)
        ->set('email', 'bola@example.com')
        ->set('project_name', 'Bola Shop')
        ->call('send')
        ->assertHasNoErrors();

    $invitation = ClientInvitation::where('email', 'bola@example.com')->latest()->first();
    expect($invitation)->not->toBeNull()
        ->and($invitation->offer->name)->toBe('Starter')
        ->and($invitation->isPending())->toBeTrue();
    // The mail dispatch is wrapped in try/catch + Log::warning, so the test
    // primarily asserts that send() completes without bubbling an exception.
});

// I6
test('deleting a server with active Clients throws and leaves the server alive', function () {
    [$devTeam, $server, $offer] = devTeamWithServer();
    $client = User::factory()->create();
    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
    ]);

    expect(fn () => $server->delete())
        ->toThrow(\RuntimeException::class, 'active Client');

    expect(Server::find($server->id))->not->toBeNull();
});

test('deleting a server with no active Clients succeeds normally', function () {
    [$devTeam, $server, $offer] = devTeamWithServer();
    // No SubTeam — server is empty.

    $server->delete();

    expect(Server::find($server->id))->toBeNull();
});

test('deleting a server with terminated Clients is allowed', function () {
    [$devTeam, $server, $offer] = devTeamWithServer();
    $client = User::factory()->create();
    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'terminated_at' => now()->subDay(),
    ]);

    $server->delete();
    expect(Server::find($server->id))->toBeNull();
});
