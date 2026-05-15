<?php

use App\Actions\Paystack\InitializeSubscription;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_init');
});

test('refuses to initialize for the Free plan', function () {
    $team = Team::factory()->create();
    Http::fake();

    expect(fn () => InitializeSubscription::run($team, Plan::free(), Subscription::PERIOD_MONTHLY, 'owner@nolbase.test'))
        ->toThrow(\RuntimeException::class, 'Free');

    Http::assertNothingSent();
});

test('initializes a Paystack transaction and returns the authorization_url', function () {
    $team = Team::factory()->create();

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'access_abc',
                'reference' => 'ref_init_001',
            ],
        ], 200),
    ]);

    $result = InitializeSubscription::run(
        team: $team,
        plan: Plan::pro(),
        period: Subscription::PERIOD_MONTHLY,
        customerEmail: 'owner@nolbase.test',
    );

    expect($result['authorization_url'])->toBe('https://checkout.paystack.com/abc123')
        ->and($result['reference'])->toBe('ref_init_001');

    Http::assertSent(function ($request) {
        // Paystack expects amount in kobo (NGN * 100); Pro is ₦15,000 → 1_500_000
        return str_contains($request->url(), '/transaction/initialize')
            && $request['amount'] === 1500000
            && $request['email'] === 'owner@nolbase.test'
            && data_get($request->data(), 'metadata.period') === Subscription::PERIOD_MONTHLY;
    });
});

test('uses the annual price when period is annual', function () {
    $team = Team::factory()->create();

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'x', 'access_code' => 'y', 'reference' => 'z'],
        ], 200),
    ]);

    InitializeSubscription::run(
        team: $team,
        plan: Plan::pro(),
        period: Subscription::PERIOD_ANNUAL,
        customerEmail: 'owner@nolbase.test',
    );

    Http::assertSent(fn ($r) => $r['amount'] === 15000000); // ₦150,000 in kobo
});
