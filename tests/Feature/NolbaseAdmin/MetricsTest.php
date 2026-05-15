<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\NolbaseMetrics;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

function activeSub(Team $team, Plan $plan, string $period): Subscription
{
    return Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => $period,
    ]);
}

test('MRR sums monthly plan prices', function () {
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_MONTHLY);
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_MONTHLY);
    activeSub(Team::factory()->create(), Plan::business(), Subscription::PERIOD_MONTHLY);

    // 15000 + 15000 + 50000 = 80000
    expect(NolbaseMetrics::mrr())->toBe(80_000);
});

test('annual subscriptions are amortized to monthly in MRR', function () {
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_ANNUAL);

    // Pro annual = 150000 / 12 = 12500
    expect(NolbaseMetrics::mrr())->toBe(12_500);
});

test('trialing and past_due subscriptions do NOT count toward MRR', function () {
    $pro = Plan::pro();
    Subscription::create([
        'team_id' => Team::factory()->create()->id,
        'plan_id' => $pro->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->addDays(10),
    ]);

    expect(NolbaseMetrics::mrr())->toBe(0)
        ->and(NolbaseMetrics::activeSubscriptions())->toBe(0)
        ->and(NolbaseMetrics::trialingSubscriptions())->toBe(1);
});

test('ARR is MRR times 12', function () {
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_MONTHLY);

    expect(NolbaseMetrics::arr())->toBe(180_000);
});

test('plan distribution counts active subscriptions by plan code', function () {
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_MONTHLY);
    activeSub(Team::factory()->create(), Plan::pro(), Subscription::PERIOD_MONTHLY);
    activeSub(Team::factory()->create(), Plan::business(), Subscription::PERIOD_MONTHLY);

    expect(NolbaseMetrics::planDistribution())->toMatchArray([
        'pro' => 2,
        'business' => 1,
    ]);
});
