<?php

use App\Models\PaystackEvent;
use Database\Seeders\InstanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_dummy');
    config()->set('paystack.webhook_secret', 'whsec_test');
    Queue::fake();
});

function paystackSignature(string $body): string
{
    return hash_hmac('sha512', $body, config('paystack.webhook_secret'));
}

test('webhook rejects requests with an invalid signature', function () {
    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => 'ref_1']]);

    $response = $this->call(
        'POST',
        '/webhooks/payments/paystack/events',
        [],
        [],
        [],
        ['HTTP_X-PAYSTACK-SIGNATURE' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
        $body,
    );

    $response->assertStatus(401);
    expect(PaystackEvent::count())->toBe(0);
});

test('webhook accepts a valid signature and stores the event', function () {
    $body = json_encode(['event' => 'subscription.create', 'data' => ['id' => 42, 'subscription_code' => 'SUB_abc', 'customer' => ['customer_code' => 'CUS_abc']]]);

    $response = $this->call(
        'POST',
        '/webhooks/payments/paystack/events',
        [],
        [],
        [],
        ['HTTP_X-PAYSTACK-SIGNATURE' => paystackSignature($body), 'CONTENT_TYPE' => 'application/json'],
        $body,
    );

    $response->assertOk();
    expect(PaystackEvent::count())->toBe(1);
    $event = PaystackEvent::first();
    expect($event->event_type)->toBe('subscription.create')
        ->and($event->paystack_reference)->toBe('SUB_abc');
    Queue::assertPushed(\App\Jobs\PaystackWebhookProcessJob::class);
});

test('webhook is idempotent — same Paystack event id only persists once', function () {
    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 99, 'reference' => 'ref_99', 'customer' => ['customer_code' => 'CUS_99']]]);
    $sig = paystackSignature($body);

    for ($i = 0; $i < 3; $i++) {
        $this->call(
            'POST',
            '/webhooks/payments/paystack/events',
            [],
            [],
            [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
            $body,
        )->assertOk();
    }

    expect(PaystackEvent::count())->toBe(1);
});
