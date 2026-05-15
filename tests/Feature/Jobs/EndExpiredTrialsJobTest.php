<?php

use App\Jobs\EndExpiredTrialsJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('downgrades expired Pro trials with no Paystack subscription to Free', function () {
    $pro = Plan::pro();
    $free = Plan::free();

    $team = Team::factory()->create();
    $sub = Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $pro->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->subHours(2),
    ]);

    (new EndExpiredTrialsJob)->handle();

    $sub->refresh();
    expect($sub->plan_id)->toBe($free->id)
        ->and($sub->status)->toBe(Subscription::STATUS_ACTIVE);
});

test('leaves trialing subscriptions with a Paystack subscription_code alone', function () {
    $pro = Plan::pro();

    $team = Team::factory()->create();
    $sub = Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $pro->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->subHours(2),
        'paystack_subscription_code' => 'SUB_already_paying',
    ]);

    (new EndExpiredTrialsJob)->handle();

    $sub->refresh();
    expect($sub->plan_id)->toBe($pro->id)
        ->and($sub->status)->toBe(Subscription::STATUS_TRIALING);
});

test('leaves future trials alone', function () {
    $pro = Plan::pro();

    $team = Team::factory()->create();
    $sub = Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $pro->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->addDays(10),
    ]);

    (new EndExpiredTrialsJob)->handle();

    $sub->refresh();
    expect($sub->status)->toBe(Subscription::STATUS_TRIALING)
        ->and($sub->plan_id)->toBe($pro->id);
});
