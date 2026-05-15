<?php

namespace App\Actions\Paystack;

use App\Models\Subscription;
use App\Services\PaystackService;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundTransaction
{
    use AsAction;

    public function handle(string $paystackReference, ?int $amountNgn = null, ?Subscription $subscription = null): array
    {
        $data = PaystackService::fromConfig()->refundTransaction($paystackReference, $amountNgn);

        $subscription?->update(['refunded_at' => now()]);

        return $data;
    }
}
