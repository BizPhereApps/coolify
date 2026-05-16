<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\PaystackWebhookProcessJob;
use App\Models\PaystackEvent;
use App\Services\NolbaseAlert;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Paystack extends Controller
{
    public function events(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');

        if (! PaystackService::fromConfig()->verifyWebhookSignature($signature, $rawBody)) {
            Log::warning('Paystack webhook: invalid signature', [
                'ip' => $request->ip(),
                'event' => $request->input('event'),
            ]);

            NolbaseAlert::send(
                title: 'Paystack webhook signature rejected',
                message: 'A POST to /webhooks/payments/paystack/events arrived with an invalid HMAC. Likely cause: probe or PAYSTACK_WEBHOOK_SECRET drift between Paystack dashboard and this deploy.',
                level: NolbaseAlert::LEVEL_WARN,
                context: [
                    'ip' => $request->ip(),
                    'event' => (string) $request->input('event', 'unknown'),
                ],
            );

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $eventType = (string) data_get($payload, 'event', 'unknown');
        $reference = data_get($payload, 'data.reference')
            ?? data_get($payload, 'data.subscription_code')
            ?? data_get($payload, 'data.customer.customer_code');
        // Idempotency key: prefer Paystack's data.id; fall back to a body hash.
        // Postgres treats NULL != NULL in unique constraints, so a null id
        // would allow duplicate rows for events without an explicit id.
        $eventId = data_get($payload, 'data.id')
            ? 'pse_'.data_get($payload, 'data.id').'_'.$eventType
            : 'pse_hash_'.hash('sha256', $rawBody);

        $event = PaystackEvent::firstOrCreate(
            ['paystack_event_id' => $eventId],
            [
                'event_type' => $eventType,
                'paystack_reference' => $reference,
                'payload' => $payload,
            ],
        );

        // Process asynchronously to keep webhook latency low.
        PaystackWebhookProcessJob::dispatch($event->id);

        return response()->json(['message' => 'received']);
    }
}
