<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Plan;
use App\Models\Subscription;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    instanceSettings()->update(['is_registration_enabled' => true]);
});

test('a new (non-root) user gets a 14-day Pro trial subscription when running as cloud', function () {
    // Make sure isCloud() returns true for this test
    config()->set('constants.coolify.self_hosted', false);

    // Seed a root user first so the next signup is NOT the first user
    (new CreateNewUser)->create([
        'name' => 'Root',
        'email' => 'root@nolbase.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    instanceSettings()->update(['is_registration_enabled' => true]);

    $user = (new CreateNewUser)->create([
        'name' => 'Trial User',
        'email' => 'trial@nolbase.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $team = $user->teams()->first();
    $subscription = $team->subscription;

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(Subscription::STATUS_TRIALING)
        ->and($subscription->plan->code)->toBe('pro')
        ->and($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->trial_ends_at->isAfter(now()->addDays(13)))->toBeTrue()
        ->and($subscription->trial_ends_at->isBefore(now()->addDays(15)))->toBeTrue();
});

test('the root user (first signup) does NOT get a trial subscription', function () {
    config()->set('constants.coolify.self_hosted', false);

    $user = (new CreateNewUser)->create([
        'name' => 'Root',
        'email' => 'root@nolbase.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $team = $user->teams()->first();
    expect($team->subscription)->toBeNull();
});

test('in self-hosted mode, no trial subscription is created', function () {
    config()->set('constants.coolify.self_hosted', true);

    // Seed root first so we're past the first-user branch
    (new CreateNewUser)->create([
        'name' => 'Root',
        'email' => 'root@nolbase.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    instanceSettings()->update(['is_registration_enabled' => true]);

    $user = (new CreateNewUser)->create([
        'name' => 'Self-Hosted',
        'email' => 'selfhosted@nolbase.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($user->teams()->first()->subscription)->toBeNull();
});
