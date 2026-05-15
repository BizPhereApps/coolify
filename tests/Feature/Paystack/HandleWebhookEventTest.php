<?php

use App\Actions\Paystack\HandleWebhookEvent;
use App\Models\PaystackEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

function makeSubscription(array $overrides = []): Subscription
{
    $team = Team::factory()->create();

    return Subscription::create(array_merge([
        'team_id' => $team->id,
        'plan_id' => Plan::pro()->id,
        'paystack_customer_code' => 'CUS_test_'.$team->id,
        'status' => Subscription::STATUS_TRIALING,
        'period' => Subscription::PERIOD_MONTHLY,
        'trial_ends_at' => now()->addDays(10),
    ], $overrides));
}

function fireEvent(string $type, array $data): PaystackEvent
{
    return PaystackEvent::create([
        'event_type' => $type,
        'paystack_event_id' => $type.'_'.uniqid(),
        'payload' => ['event' => $type, 'data' => $data],
    ]);
}

test('subscription.create activates a trialing subscription matched by customer_code', function () {
    $sub = makeSubscription();
    $event = fireEvent('subscription.create', [
        'subscription_code' => 'SUB_new',
        'email_token' => 'tok_xyz',
        'next_payment_date' => now()->addMonth()->toIso8601String(),
        'customer' => ['customer_code' => $sub->paystack_customer_code],
    ]);

    HandleWebhookEvent::run($event);

    $sub->refresh();
    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($sub->paystack_subscription_code)->toBe('SUB_new')
        ->and($sub->paystack_email_token)->toBe('tok_xyz');
    expect($event->fresh()->isProcessed())->toBeTrue();
});

test('subscription.disable marks the subscription as cancelled', function () {
    $sub = makeSubscription([
        'status' => Subscription::STATUS_ACTIVE,
        'paystack_subscription_code' => 'SUB_disable_me',
    ]);
    $event = fireEvent('subscription.disable', [
        'subscription_code' => 'SUB_disable_me',
    ]);

    HandleWebhookEvent::run($event);

    $sub->refresh();
    expect($sub->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and($sub->cancelled_at)->not->toBeNull();
});

test('subscription.not_renew also marks the subscription as cancelled', function () {
    $sub = makeSubscription([
        'status' => Subscription::STATUS_ACTIVE,
        'paystack_subscription_code' => 'SUB_no_renew',
    ]);
    $event = fireEvent('subscription.not_renew', ['subscription_code' => 'SUB_no_renew']);

    HandleWebhookEvent::run($event);

    expect($sub->fresh()->status)->toBe(Subscription::STATUS_CANCELLED);
});

test('invoice.payment_failed marks subscription past_due', function () {
    $sub = makeSubscription(['status' => Subscription::STATUS_ACTIVE]);
    $event = fireEvent('invoice.payment_failed', [
        'customer' => ['customer_code' => $sub->paystack_customer_code],
    ]);

    HandleWebhookEvent::run($event);

    $sub->refresh();
    expect($sub->status)->toBe(Subscription::STATUS_PAST_DUE)
        ->and($sub->last_payment_failed_at)->not->toBeNull();
});

test('charge.success on a past_due subscription brings it back to active', function () {
    $sub = makeSubscription([
        'status' => Subscription::STATUS_PAST_DUE,
        'last_payment_failed_at' => now()->subDays(2),
    ]);
    $event = fireEvent('charge.success', [
        'customer' => ['customer_code' => $sub->paystack_customer_code],
    ]);

    HandleWebhookEvent::run($event);

    $sub->refresh();
    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($sub->last_payment_failed_at)->toBeNull();
});

test('already-processed events are skipped', function () {
    $sub = makeSubscription(['status' => Subscription::STATUS_ACTIVE]);
    $event = fireEvent('subscription.disable', ['subscription_code' => 'SUB_x']);
    $event->update(['processed_at' => now()]);

    HandleWebhookEvent::run($event);

    expect($sub->fresh()->status)->toBe(Subscription::STATUS_ACTIVE);
});
