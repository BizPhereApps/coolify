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

function sig(string $body): string
{
    return hash_hmac('sha512', $body, config('paystack.webhook_secret'));
}

function postWebhook($test, string $body): void
{
    $test->call(
        'POST', '/webhooks/payments/paystack/events', [], [], [],
        ['HTTP_X-PAYSTACK-SIGNATURE' => sig($body), 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertOk();
}

test('events WITHOUT data.id are deduplicated by raw-body hash', function () {
    // Real Paystack quirk: some event types (e.g. legacy or test events) omit data.id.
    // Before the fix, two such events stored as separate rows because Postgres treats
    // NULL != NULL in unique constraints.
    $body = json_encode([
        'event' => 'paymentrequest.success',
        'data' => ['reference' => 'ref_xyz', 'customer' => ['customer_code' => 'CUS_a']],
    ]);

    postWebhook($this, $body);
    postWebhook($this, $body);
    postWebhook($this, $body);

    expect(PaystackEvent::count())->toBe(1);
});

test('two distinct events without data.id are stored separately', function () {
    $body1 = json_encode(['event' => 'x', 'data' => ['reference' => 'ref1']]);
    $body2 = json_encode(['event' => 'x', 'data' => ['reference' => 'ref2']]);

    postWebhook($this, $body1);
    postWebhook($this, $body2);

    expect(PaystackEvent::count())->toBe(2);
});

test('events WITH data.id continue to use the id-based key (regression check)', function () {
    $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 42, 'reference' => 'ref_42']]);

    postWebhook($this, $body);
    postWebhook($this, $body);

    expect(PaystackEvent::count())->toBe(1);
    expect(PaystackEvent::first()->paystack_event_id)->toBe('pse_42_charge.success');
});
