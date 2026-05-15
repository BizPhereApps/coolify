<?php

use App\Actions\Paystack\VerifyTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_verify');
});

test('verifies a successful transaction and activates the team subscription', function () {
    $team = Team::factory()->create();
    $pro = Plan::pro();

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'ref_verify_ok',
                'metadata' => [
                    'team_id' => $team->id,
                    'plan_id' => $pro->id,
                    'period' => Subscription::PERIOD_MONTHLY,
                ],
                'customer' => ['customer_code' => 'CUS_verify_ok'],
                'authorization' => ['subscription_code' => 'SUB_verify_ok'],
            ],
        ], 200),
    ]);

    $subscription = VerifyTransaction::run('ref_verify_ok');

    expect($subscription->team_id)->toBe($team->id)
        ->and($subscription->plan_id)->toBe($pro->id)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->paystack_customer_code)->toBe('CUS_verify_ok')
        ->and($subscription->paystack_subscription_code)->toBe('SUB_verify_ok')
        ->and($subscription->current_period_end)->not->toBeNull();
});

test('throws when Paystack reports the transaction did not succeed', function () {
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'failed',
                'metadata' => ['team_id' => 1, 'plan_id' => 1],
            ],
        ], 200),
    ]);

    expect(fn () => VerifyTransaction::run('ref_fail'))
        ->toThrow(\RuntimeException::class, 'did not succeed');
});

test('upgrades an existing trial subscription on successful verification', function () {
    $team = Team::factory()->create();
    $pro = Plan::pro();

    $existing = Subscription::create([
        'team_id' => $team->id,
        'plan_id' => $pro->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->addDays(5),
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'ref_upgrade',
                'metadata' => [
                    'team_id' => $team->id,
                    'plan_id' => $pro->id,
                    'period' => Subscription::PERIOD_MONTHLY,
                ],
                'customer' => ['customer_code' => 'CUS_upgrade'],
                'authorization' => ['subscription_code' => 'SUB_upgrade'],
            ],
        ], 200),
    ]);

    $subscription = VerifyTransaction::run('ref_upgrade');

    expect($subscription->id)->toBe($existing->id)
        ->and($subscription->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($subscription->paystack_subscription_code)->toBe('SUB_upgrade');
});
