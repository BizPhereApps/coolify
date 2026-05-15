<?php

use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    // Run as cloud so the suspension block actually applies.
    config()->set('constants.coolify.self_hosted', false);
});

test('suspended-team detection uses currentTeam() method, not the transient attribute', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['nolbase_status' => 'suspended']);
    $user->teams()->attach($team->id, ['role' => 'owner']);

    // Simulate the user having only this (suspended) team and a fresh request
    // where the transient $user->currentTeam attribute has NOT been set yet.
    auth()->login($user);
    session(['currentTeam' => $team]);

    expect($user->currentTeam()?->nolbase_status)->toBe('suspended');
});

test('a team with no nolbase_status set is treated as not-suspended', function () {
    // Postgres applies the migration default ('active') on insert; SQLite (the test
    // driver) leaves it null. Either way, the middleware only blocks on the literal
    // string 'suspended' — null or 'active' both pass through.
    $team = Team::factory()->create();

    expect($team->nolbase_status)->not->toBe('suspended');
});

test('Team::nolbase_status accepts suspended and active', function () {
    $team = Team::factory()->create();

    $team->update(['nolbase_status' => 'suspended']);
    expect($team->fresh()->nolbase_status)->toBe('suspended');

    $team->update(['nolbase_status' => 'active']);
    expect($team->fresh()->nolbase_status)->toBe('active');
});
