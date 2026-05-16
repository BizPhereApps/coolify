<?php

use App\Models\Plan;
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
});

test('the PricingPlans Livewire renders for unauth visitors', function () {
    // No login. The component should still load and list the 3 seeded plans.
    Livewire::test(\App\Livewire\Subscription\PricingPlans::class)
        ->assertSee('Free')
        ->assertSee('Pro')
        ->assertSee('Business');
});

test('clicking choose() as an unauth visitor redirects to /login', function () {
    Livewire::test(\App\Livewire\Subscription\PricingPlans::class)
        ->call('choose', Plan::pro()->id)
        ->assertRedirect(route('login'));
});

test('currentPlanCode is null when no team is in session', function () {
    $component = Livewire::test(\App\Livewire\Subscription\PricingPlans::class);
    expect($component->get('currentPlanCode'))->toBeNull();
});

test('only is_public plans are listed', function () {
    Plan::where('code', 'business')->update(['is_public' => false]);

    $component = Livewire::test(\App\Livewire\Subscription\PricingPlans::class);
    $codes = collect($component->get('plans'))->pluck('code')->all();
    expect($codes)->toContain('free', 'pro')
        ->and($codes)->not->toContain('business');
});

test('plans are returned in sort_order', function () {
    Plan::where('code', 'free')->update(['sort_order' => 30]);
    Plan::where('code', 'pro')->update(['sort_order' => 20]);
    Plan::where('code', 'business')->update(['sort_order' => 10]);

    $component = Livewire::test(\App\Livewire\Subscription\PricingPlans::class);
    $codes = collect($component->get('plans'))->pluck('code')->all();
    expect($codes)->toBe(['business', 'pro', 'free']);
});
