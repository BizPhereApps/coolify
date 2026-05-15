<?php

use App\Models\NolbaseAdmin;
use App\Models\NolbaseAdminAudit;
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

    $this->staffAdmin = NolbaseAdmin::create([
        'name' => 'Staff',
        'email' => 'staff@nolbase.test',
        'password' => Hash::make('pw'),
        'role' => NolbaseAdmin::ROLE_STAFF,
    ]);

    $this->supportAdmin = NolbaseAdmin::create([
        'name' => 'Support',
        'email' => 'support@nolbase.test',
        'password' => Hash::make('pw'),
        'role' => NolbaseAdmin::ROLE_SUPPORT,
    ]);
});

test('a staff admin can suspend a tenant', function () {
    auth('nolbase')->login($this->staffAdmin);
    $team = Team::factory()->create(['nolbase_status' => 'active']);

    Livewire::test(\App\Livewire\Nolbase\Admin\Tenants\Show::class, ['team' => $team])
        ->call('suspend');

    expect($team->fresh()->nolbase_status)->toBe('suspended');
    expect(NolbaseAdminAudit::where('action', 'tenant.suspend')->count())->toBe(1);
});

test('a staff admin can unsuspend a tenant', function () {
    auth('nolbase')->login($this->staffAdmin);
    $team = Team::factory()->create(['nolbase_status' => 'suspended']);

    Livewire::test(\App\Livewire\Nolbase\Admin\Tenants\Show::class, ['team' => $team])
        ->call('unsuspend');

    expect($team->fresh()->nolbase_status)->toBe('active');
    expect(NolbaseAdminAudit::where('action', 'tenant.unsuspend')->count())->toBe(1);
});

test('a support admin cannot suspend tenants', function () {
    auth('nolbase')->login($this->supportAdmin);
    $team = Team::factory()->create(['nolbase_status' => 'active']);

    Livewire::test(\App\Livewire\Nolbase\Admin\Tenants\Show::class, ['team' => $team])
        ->call('suspend')
        ->assertHasErrors('action');

    expect($team->fresh()->nolbase_status)->toBe('active');
    expect(NolbaseAdminAudit::where('action', 'tenant.suspend')->count())->toBe(0);
});
