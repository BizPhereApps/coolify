<?php

namespace App\Actions\Paystack;

use App\Models\Subscription;
use App\Services\PaystackService;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelSubscription
{
    use AsAction;

    /**
     * Cancel a subscription. By default it cancels at period end (tenant retains
     * access until the end of the paid period). Pass $immediate=true to disable
     * at Paystack right away.
     */
    public function handle(Subscription $subscription, bool $immediate = false): Subscription
    {
        if ($immediate && $subscription->paystack_subscription_code && $subscription->paystack_email_token) {
            PaystackService::fromConfig()->disableSubscription(
                $subscription->paystack_subscription_code,
                $subscription->paystack_email_token,
            );

            $subscription->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_at_period_end' => false,
            ]);

            return $subscription->refresh();
        }

        $subscription->update([
            'cancel_at_period_end' => true,
        ]);

        return $subscription->refresh();
    }
}
