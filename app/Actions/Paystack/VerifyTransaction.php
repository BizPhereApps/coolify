<?php

namespace App\Actions\Paystack;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\PaystackService;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class VerifyTransaction
{
    use AsAction;

    /**
     * Verify a Paystack transaction reference (typically from the callback URL)
     * and activate the subscription on the corresponding team.
     */
    public function handle(string $reference): Subscription
    {
        $paystack = PaystackService::fromConfig();
        $data = $paystack->verifyTransaction($reference);

        if (data_get($data, 'status') !== 'success') {
            throw new RuntimeException('Paystack transaction did not succeed.');
        }

        $teamId = (int) data_get($data, 'metadata.team_id');
        $planId = (int) data_get($data, 'metadata.plan_id');
        $period = data_get($data, 'metadata.period', Subscription::PERIOD_MONTHLY);

        $team = Team::findOrFail($teamId);
        $plan = Plan::findOrFail($planId);

        $customerCode = data_get($data, 'customer.customer_code');
        $subscriptionCode = data_get($data, 'plan_object.subscription_code')
            ?? data_get($data, 'authorization.subscription_code');
        // I7: capture the email_token whenever Paystack returns it so an
        // immediate subscription disable later doesn't fail. Paystack sometimes
        // returns this on verify (when the plan has a subscription_code) and
        // always returns it on the subscription.create webhook.
        $emailToken = data_get($data, 'plan_object.subscription_email_token')
            ?? data_get($data, 'authorization.subscription_email_token');

        $subscription = $team->subscription ?? new Subscription(['team_id' => $team->id]);

        $subscription->fill([
            'team_id' => $team->id,
            'plan_id' => $plan->id,
            'paystack_customer_code' => $customerCode ?? $subscription->paystack_customer_code,
            'paystack_subscription_code' => $subscriptionCode ?? $subscription->paystack_subscription_code,
            'paystack_email_token' => $emailToken ?? $subscription->paystack_email_token,
            'status' => Subscription::STATUS_ACTIVE,
            'period' => $period,
            'current_period_start' => now(),
            'current_period_end' => $period === Subscription::PERIOD_ANNUAL
                ? now()->addYear()
                : now()->addMonth(),
            'cancel_at_period_end' => false,
            'cancelled_at' => null,
            'last_payment_failed_at' => null,
        ])->save();

        return $subscription->refresh();
    }
}
