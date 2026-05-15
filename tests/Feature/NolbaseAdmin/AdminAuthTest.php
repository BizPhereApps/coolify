<?php

use App\Models\NolbaseAdmin;
use App\Models\NolbaseAdminAudit;
use Database\Seeders\InstanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
});

test('valid credentials log a nolbase admin in and audit-log it', function () {
    NolbaseAdmin::create([
        'name' => 'Tester',
        'email' => 'tester@nolbase.test',
        'password' => Hash::make('secret-password'),
        'role' => NolbaseAdmin::ROLE_STAFF,
    ]);

    Livewire::test(\App\Livewire\Nolbase\Admin\Login::class)
        ->set('email', 'tester@nolbase.test')
        ->set('password', 'secret-password')
        ->call('submit')
        ->assertRedirect(route('nolbase.admin.dashboard'));

    expect(auth('nolbase')->check())->toBeTrue();
    expect(NolbaseAdminAudit::where('action', 'login')->count())->toBe(1);
});

test('invalid credentials are rejected with an error', function () {
    NolbaseAdmin::create([
        'name' => 'Tester',
        'email' => 'tester@nolbase.test',
        'password' => Hash::make('right-password'),
        'role' => NolbaseAdmin::ROLE_STAFF,
    ]);

    Livewire::test(\App\Livewire\Nolbase\Admin\Login::class)
        ->set('email', 'tester@nolbase.test')
        ->set('password', 'wrong-password')
        ->call('submit')
        ->assertHasErrors('email');

    expect(auth('nolbase')->check())->toBeFalse();
});

test('the nolbase guard is independent of the tenant web guard', function () {
    $admin = NolbaseAdmin::create([
        'name' => 'Dual',
        'email' => 'dual@nolbase.test',
        'password' => Hash::make('pw'),
        'role' => NolbaseAdmin::ROLE_SUPERADMIN,
    ]);
    auth('nolbase')->login($admin);

    expect(auth('nolbase')->check())->toBeTrue();
    expect(auth('web')->check())->toBeFalse();
});
