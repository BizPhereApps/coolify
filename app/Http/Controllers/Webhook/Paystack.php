<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\PaystackWebhookProcessJob;
use App\Models\PaystackEvent;
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

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $eventType = (string) data_get($payload, 'event', 'unknown');
        $reference = data_get($payload, 'data.reference')
            ?? data_get($payload, 'data.subscription_code')
            ?? data_get($payload, 'data.customer.customer_code');
        $eventId = data_get($payload, 'data.id') ? 'pse_'.data_get($payload, 'data.id').'_'.$eventType : null;

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
