<?php

use App\Actions\Provisioning\SuspendPastDueManagedServers;
use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    NolbaseSetting::write('nolbase_hetzner_api_token', 'hz_test_token');
});

function pastDueManaged(int $daysAgo, int $providerResourceId = 9123): NolbaseManagedServer
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner->id, ['role' => 'owner']);
    $server = Server::factory()->create(['team_id' => $team->id, 'name' => 'mgd-'.$providerResourceId]);

    return NolbaseManagedServer::create([
        'server_id' => $server->id,
        'provider' => 'hetzner',
        'provider_resource_id' => (string) $providerResourceId,
        'plan_slug' => 'cax11',
        'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => 12750,
        'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_PAST_DUE,
        'past_due_since' => now()->subDays($daysAgo),
    ]);
}

test('past_due servers older than the grace window are suspended and powered off', function () {
    Mail::fake();
    Http::fake([
        'api.hetzner.cloud/v1/servers/*/actions/poweroff' => Http::response(['action' => ['id' => 1]], 200),
    ]);
    $managed = pastDueManaged(daysAgo: 8);

    $result = SuspendPastDueManagedServers::run();

    expect($result['suspended'])->toBe(1)
        ->and($managed->fresh()->billing_status)->toBe(NolbaseManagedServer::BILLING_SUSPENDED)
        ->and($managed->fresh()->suspended_at)->not->toBeNull();

    Http::assertSent(fn ($r) => str_contains($r->url(), '/servers/9123/actions/poweroff'));
});

test('past_due servers still inside the grace window are left alone', function () {
    Mail::fake();
    Http::fake();
    $managed = pastDueManaged(daysAgo: 3);

    $result = SuspendPastDueManagedServers::run();

    expect($result['suspended'])->toBe(0)
        ->and($managed->fresh()->billing_status)->toBe(NolbaseManagedServer::BILLING_PAST_DUE);

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('grace window is configurable via NolbaseSetting', function () {
    Mail::fake();
    Http::fake([
        'api.hetzner.cloud/v1/servers/*/actions/poweroff' => Http::response(['action' => ['id' => 1]], 200),
    ]);
    NolbaseSetting::write('managed_past_due_grace_days', '14');
    $within = pastDueManaged(daysAgo: 10, providerResourceId: 9001);
    $beyond = pastDueManaged(daysAgo: 15, providerResourceId: 9002);

    $result = SuspendPastDueManagedServers::run();

    expect($result['suspended'])->toBe(1)
        ->and($within->fresh()->billing_status)->toBe(NolbaseManagedServer::BILLING_PAST_DUE)
        ->and($beyond->fresh()->billing_status)->toBe(NolbaseManagedServer::BILLING_SUSPENDED);
});

test('hetzner power-off failures still flip the row to suspended', function () {
    Mail::fake();
    Http::fake([
        'api.hetzner.cloud/v1/servers/*/actions/poweroff' => Http::response(['error' => ['message' => 'kaput']], 500),
    ]);
    $managed = pastDueManaged(daysAgo: 10);

    $result = SuspendPastDueManagedServers::run();

    expect($result['suspended'])->toBe(1)
        ->and($managed->fresh()->billing_status)->toBe(NolbaseManagedServer::BILLING_SUSPENDED);
});

test('re-running is idempotent — already-suspended rows are not touched again', function () {
    Mail::fake();
    Http::fake([
        'api.hetzner.cloud/v1/servers/*/actions/poweroff' => Http::response(['action' => ['id' => 1]], 200),
    ]);
    pastDueManaged(daysAgo: 9);

    SuspendPastDueManagedServers::run();
    $second = SuspendPastDueManagedServers::run();

    expect($second['examined'])->toBe(0)
        ->and($second['suspended'])->toBe(0);
});
