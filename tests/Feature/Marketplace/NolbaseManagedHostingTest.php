<?php

use App\Actions\Provisioning\ProvisionNolbaseManagedServer;
use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Models\Server;
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
});

function setNolbaseHetznerConfig(): void
{
    NolbaseSetting::write('nolbase_hetzner_api_token', 'nb-hetzner-token');
    NolbaseSetting::write('nolbase_hetzner_ssh_pubkey', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5fakefake nolbase-master');

    // Seed the shared "nolbase-managed" PrivateKey via raw DB insert so we
    // bypass PrivateKey::saving validation (which requires a real OpenSSH key).
    \DB::table('private_keys')->insertGetId([
        'team_id' => 0,
        'name' => 'nolbase-managed',
        'private_key' => 'encrypted-placeholder',
        'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function fakeHetznerOk(int $hetznerServerId = 9001, string $ip = '203.0.113.99'): void
{
    Http::fake([
        'api.hetzner.cloud/v1/ssh_keys*' => Http::response(['ssh_keys' => [], 'meta' => ['pagination' => ['total_entries' => 0]]], 200),
        'api.hetzner.cloud/v1/ssh_keys' => Http::sequence()
            ->push(['ssh_keys' => [], 'meta' => ['pagination' => ['total_entries' => 0]]], 200)
            ->push(['ssh_key' => ['id' => 7, 'name' => 'nolbase-master', 'public_key' => 'ssh-ed25519 X']], 201),
        'api.hetzner.cloud/v1/servers' => Http::response([
            'server' => [
                'id' => $hetznerServerId,
                'name' => 'test',
                'public_net' => ['ipv4' => ['ip' => $ip]],
            ],
        ], 201),
    ]);
}

test('priceFromCostBasis applies the markup correctly', function () {
    expect(NolbaseManagedServer::priceFromCostBasis(8500, 50))->toBe(12_750)
        ->and(NolbaseManagedServer::priceFromCostBasis(10_000, 0))->toBe(10_000)
        ->and(NolbaseManagedServer::priceFromCostBasis(10_000, 100))->toBe(20_000);
});

test('Nolbase-managed Livewire is unavailable when no Hetzner token is set', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    auth()->login($user);
    session(['currentTeam' => $user->currentTeam = $team]);

    $component = Livewire::test(\App\Livewire\Server\New\NolbaseManaged::class);
    expect($component->get('available'))->toBeFalse();

    $component->call('provision')->assertHasErrors('provision');
});

test('Nolbase-managed Livewire shows availability when token is configured', function () {
    setNolbaseHetznerConfig();

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user->id, ['role' => 'owner']);
    auth()->login($user);
    session(['currentTeam' => $user->currentTeam = $team]);

    $component = Livewire::test(\App\Livewire\Server\New\NolbaseManaged::class);
    expect($component->get('available'))->toBeTrue()
        ->and($component->get('estimated_price_ngn'))->toBe(12_750); // default 8500 + 50% markup
});

test('Provision action refuses if no Hetzner token is configured', function () {
    $team = Team::factory()->create();
    expect(fn () => ProvisionNolbaseManagedServer::run($team))
        ->toThrow(\RuntimeException::class, 'nolbase_hetzner_api_token');
});

test('Provision action refuses if no public SSH key is configured', function () {
    NolbaseSetting::write('nolbase_hetzner_api_token', 'nb-token');
    $team = Team::factory()->create();
    expect(fn () => ProvisionNolbaseManagedServer::run($team))
        ->toThrow(\RuntimeException::class, 'SSH public key');
});

test('Provision happy path: calls Hetzner, creates Server + NolbaseManagedServer', function () {
    setNolbaseHetznerConfig();
    $team = Team::factory()->create();
    fakeHetznerOk(hetznerServerId: 9001, ip: '203.0.113.99');

    $server = ProvisionNolbaseManagedServer::run($team, ['plan_slug' => 'cax11', 'location_slug' => 'fsn1']);

    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->team_id)->toBe($team->id)
        ->and($server->ip)->toBe('203.0.113.99');

    $managed = NolbaseManagedServer::where('server_id', $server->id)->first();
    expect($managed)->not->toBeNull()
        ->and($managed->provider)->toBe('hetzner')
        ->and($managed->provider_resource_id)->toBe('9001')
        ->and($managed->plan_slug)->toBe('cax11')
        ->and($managed->location_slug)->toBe('fsn1')
        ->and($managed->billing_status)->toBe(NolbaseManagedServer::BILLING_ACTIVE)
        ->and($managed->price_ngn_monthly)->toBe(12_750);
});

test('NolbaseManagedServer::active scope filters out decommissioned + suspended', function () {
    setNolbaseHetznerConfig();
    $team = Team::factory()->create();
    fakeHetznerOk();

    $a = ProvisionNolbaseManagedServer::run($team, ['plan_slug' => 'cax11', 'name' => 'alpha']);
    $managedA = NolbaseManagedServer::where('server_id', $a->id)->first();

    // Simulate a second managed server in suspended state (via DB update).
    fakeHetznerOk(hetznerServerId: 9002, ip: '203.0.113.100');
    $b = ProvisionNolbaseManagedServer::run($team, ['plan_slug' => 'cax11', 'name' => 'beta']);
    $managedB = NolbaseManagedServer::where('server_id', $b->id)->first();
    $managedB->update(['billing_status' => NolbaseManagedServer::BILLING_SUSPENDED, 'suspended_at' => now()]);

    $activeIds = NolbaseManagedServer::active()->pluck('id')->all();
    expect($activeIds)->toContain($managedA->id)
        ->and($activeIds)->not->toContain($managedB->id);

    expect($managedA->isActive())->toBeTrue()
        ->and($managedB->fresh()->isActive())->toBeFalse();
});
