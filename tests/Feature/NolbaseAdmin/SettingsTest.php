<?php

use App\Models\NolbaseAdmin;
use App\Models\NolbaseAdminAudit;
use App\Models\NolbaseSetting;
use Database\Seeders\InstanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
});

function makeAdmin(string $role = NolbaseAdmin::ROLE_STAFF): NolbaseAdmin
{
    return NolbaseAdmin::create([
        'name' => 'Admin',
        'email' => $role.'@nolbase.test',
        'password' => Hash::make('pw'),
        'role' => $role,
    ]);
}

test('NolbaseSetting::write persists and read returns it', function () {
    $admin = makeAdmin();
    NolbaseSetting::write('digitalocean_referral_url', 'https://m.do.co/c/abc', $admin);

    expect(NolbaseSetting::read('digitalocean_referral_url'))->toBe('https://m.do.co/c/abc');
});

test('NolbaseSetting::read falls back to default for an unknown key', function () {
    expect(NolbaseSetting::read('unknown_key', 'fallback'))->toBe('fallback')
        ->and(NolbaseSetting::read('unknown_key'))->toBeNull();
});

test('a staff admin can save settings and the change is audit-logged', function () {
    auth('nolbase')->login(makeAdmin(NolbaseAdmin::ROLE_STAFF));

    Livewire::test(\App\Livewire\Nolbase\Admin\Settings::class)
        ->set('values.digitalocean_referral_url', 'https://m.do.co/c/nolbase')
        ->set('values.support_email', 'help@nolbase.com')
        ->call('save');

    expect(NolbaseSetting::read('digitalocean_referral_url'))->toBe('https://m.do.co/c/nolbase')
        ->and(NolbaseSetting::read('support_email'))->toBe('help@nolbase.com');

    $audit = NolbaseAdminAudit::where('action', 'settings.updated')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload['keys'])->toContain('digitalocean_referral_url')
        ->and($audit->payload['keys'])->toContain('support_email');
});

test('a support admin cannot save settings', function () {
    auth('nolbase')->login(makeAdmin(NolbaseAdmin::ROLE_SUPPORT));

    Livewire::test(\App\Livewire\Nolbase\Admin\Settings::class)
        ->set('values.support_email', 'spoof@evil.com')
        ->call('save')
        ->assertHasErrors('save');

    expect(NolbaseSetting::read('support_email'))->toBeNull();
});

test('saving with no changes does NOT create an audit entry', function () {
    auth('nolbase')->login(makeAdmin());

    Livewire::test(\App\Livewire\Nolbase\Admin\Settings::class)
        ->call('save');

    expect(NolbaseAdminAudit::where('action', 'settings.updated')->count())->toBe(0);
});
