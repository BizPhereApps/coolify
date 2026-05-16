<?php

use App\Livewire\Nolbase\Admin\ManagedFleet;
use App\Models\NolbaseAdmin;
use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use App\Models\Server;
use App\Models\Team;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);

    $this->admin = NolbaseAdmin::create([
        'name' => 'Staff',
        'email' => 'staff@nolbase.test',
        'password' => Hash::make('pw'),
        'role' => NolbaseAdmin::ROLE_STAFF,
    ]);
    auth('nolbase')->login($this->admin);
});

test('fleet dashboard aggregates active servers, revenue, cost and margin', function () {
    $team = Team::factory()->create();
    $s1 = Server::factory()->create(['team_id' => $team->id]);
    $s2 = Server::factory()->create(['team_id' => $team->id]);

    NolbaseManagedServer::create([
        'server_id' => $s1->id, 'provider' => 'hetzner', 'provider_resource_id' => '1',
        'plan_slug' => 'cax11', 'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => 12750, 'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
    ]);
    NolbaseManagedServer::create([
        'server_id' => $s2->id, 'provider' => 'hetzner', 'provider_resource_id' => '2',
        'plan_slug' => 'cax21', 'cost_basis_ngn_monthly' => 17000,
        'price_ngn_monthly' => 25500, 'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
    ]);

    Livewire::test(ManagedFleet::class)
        ->assertSet('totalServers', 2)
        ->assertSet('activeServers', 2)
        ->assertSet('totalMonthlyRevenueNgn', 12_750 + 25_500)
        ->assertSet('totalMonthlyCostNgn', 8_500 + 17_000)
        ->assertSet('totalMonthlyMarginNgn', (12_750 + 25_500) - (8_500 + 17_000))
        ->assertSee('cax11')
        ->assertSee('cax21');
});

test('decommissioned servers are excluded from the fleet view', function () {
    $team = Team::factory()->create();
    $server = Server::factory()->create(['team_id' => $team->id]);
    NolbaseManagedServer::create([
        'server_id' => $server->id, 'provider' => 'hetzner', 'provider_resource_id' => '99',
        'plan_slug' => 'cax11', 'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => 12750, 'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_DECOMMISSIONED,
        'decommissioned_at' => now()->subDay(),
    ]);

    Livewire::test(ManagedFleet::class)
        ->assertSet('totalServers', 0);
});

test('fleet view shows the latest invoice status per row', function () {
    $team = Team::factory()->create();
    $server = Server::factory()->create(['team_id' => $team->id]);
    NolbaseManagedServer::create([
        'server_id' => $server->id, 'provider' => 'hetzner', 'provider_resource_id' => '7',
        'plan_slug' => 'cax11', 'cost_basis_ngn_monthly' => 8500,
        'price_ngn_monthly' => 12750, 'markup_pct' => 50,
        'billing_status' => NolbaseManagedServer::BILLING_PAST_DUE,
        'past_due_since' => now()->subDays(2),
    ]);
    NolbaseManagedInvoice::create([
        'team_id' => $team->id,
        'billing_month' => now()->startOfMonth()->toDateString(),
        'total_ngn' => 12750, 'line_items' => [], 'status' => NolbaseManagedInvoice::STATUS_FAILED,
        'failure_reason' => 'Insufficient funds',
    ]);

    Livewire::test(ManagedFleet::class)
        ->assertSee('past due')
        ->assertSee('failed');
});
