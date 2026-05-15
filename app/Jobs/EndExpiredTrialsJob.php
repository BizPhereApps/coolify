<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EndExpiredTrialsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $freePlan = Plan::where('code', 'free')->first();
        if (! $freePlan) {
            Log::warning('EndExpiredTrialsJob: Free plan not found, skipping.');

            return;
        }

        // Trials past their end date, without a Paystack subscription, drop to Free.
        $expired = Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereNull('paystack_subscription_code')
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update([
                'plan_id' => $freePlan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_start' => now(),
                'current_period_end' => null,
            ]);
        }

        // Trials that converted to a Paystack subscription mid-trial are
        // already in STATUS_ACTIVE — nothing to do here. Paystack handles renewals.

        if ($expired->isNotEmpty()) {
            Log::info("EndExpiredTrialsJob: downgraded {$expired->count()} expired trials to Free.");
        }
    }
}
