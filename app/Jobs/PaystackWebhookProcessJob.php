<?php

namespace App\Jobs;

use App\Actions\Paystack\HandleWebhookEvent;
use App\Models\PaystackEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaystackWebhookProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public int $paystackEventId) {}

    public function handle(): void
    {
        $event = PaystackEvent::find($this->paystackEventId);
        if (! $event || $event->isProcessed()) {
            return;
        }

        HandleWebhookEvent::run($event);
    }
}
