<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\PlanQuota;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    // Treat tests as running in cloud mode so quotas apply.
    config()->set('constants.coolify.self_hosted', false);
});

function teamWithPlan(string $code): Team
{
    $team = Team::factory()->create();
    $plan = Plan::firstWhere('code', $code);
    Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $plan->id,
        'status' => Subscription::STATUS_ACTIVE,
        'period' => Subscription::PERIOD_MONTHLY,
    ]);

    return $team->fresh();
}

test('free plan caps servers at 1, apps at 3, databases at 1', function () {
    $team = teamWithPlan('free');

    expect(PlanQuota::serverLimit($team))->toBe(1)
        ->and(PlanQuota::appLimit($team))->toBe(3)
        ->and(PlanQuota::databaseLimit($team))->toBe(1);
});

test('pro and business plans report unlimited as 0', function () {
    $pro = teamWithPlan('pro');
    $business = teamWithPlan('business');

    expect(PlanQuota::appLimit($pro))->toBe(PlanQuota::UNLIMITED)
        ->and(PlanQuota::serverLimit($business))->toBe(PlanQuota::UNLIMITED)
        ->and(PlanQuota::databaseLimit($pro))->toBe(PlanQuota::UNLIMITED);
});

test('a team with no subscription falls back to Free plan limits', function () {
    $team = Team::factory()->create();
    expect($team->subscription)->toBeNull();

    expect(PlanQuota::serverLimit($team))->toBe(1)
        ->and(PlanQuota::appLimit($team))->toBe(3);
});

test('canAddServer returns false at the limit', function () {
    $team = teamWithPlan('free');

    expect(PlanQuota::canAddServer($team))->toBeTrue();

    \App\Models\Server::factory()->create(['team_id' => $team->id]);
    $team->refresh();

    expect(PlanQuota::canAddServer($team))->toBeFalse()
        ->and(Team::serverLimitReached($team))->toBeTrue();
});

test('unlimited plans never report limit reached', function () {
    $team = teamWithPlan('business');

    \App\Models\Server::factory()->count(20)->create(['team_id' => $team->id]);

    expect(PlanQuota::canAddServer($team->fresh()))->toBeTrue()
        ->and(Team::serverLimitReached($team->fresh()))->toBeFalse();
});

test('self-hosted mode disables all quotas', function () {
    config()->set('constants.coolify.self_hosted', true);
    $team = teamWithPlan('free');

    expect(PlanQuota::serverLimit($team))->toBe(PlanQuota::UNLIMITED)
        ->and(PlanQuota::canAddServer($team))->toBeTrue();
});

test('root team (id=0) is always unlimited', function () {
    $rootTeam = Team::factory()->create(['id' => 0]);

    expect(PlanQuota::serverLimit($rootTeam))->toBe(PlanQuota::UNLIMITED)
        ->and(PlanQuota::appLimit($rootTeam))->toBe(PlanQuota::UNLIMITED);
});

test('usage payloads include used / limit / unlimited / percent', function () {
    $team = teamWithPlan('free');
    \App\Models\Server::factory()->create(['team_id' => $team->id]);

    $usage = PlanQuota::serversUsage($team->fresh());

    expect($usage)->toMatchArray([
        'used' => 1,
        'limit' => 1,
        'unlimited' => false,
        'percent' => 100,
    ]);
});

test('unlimited usage payload reports percent as null', function () {
    $team = teamWithPlan('pro');
    expect(PlanQuota::appsUsage($team)['percent'])->toBeNull();
});
