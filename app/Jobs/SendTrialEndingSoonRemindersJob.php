<?php

namespace App\Jobs;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/**
 * Sends "trial ends soon" reminders to tenants whose Pro trial is between
 * 2 and 3 days from expiring AND who have not yet added a Paystack payment
 * method. Designed to run daily — the date window means each subscription
 * is notified once.
 */
class SendTrialEndingSoonRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $upcoming = Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now()->addDays(2), now()->addDays(3)])
            ->whereNull('paystack_subscription_code')
            ->with('team.members')
            ->get();

        foreach ($upcoming as $subscription) {
            $ownerEmail = $subscription->team?->members()
                ->wherePivot('role', 'owner')
                ->value('email');
            if (! $ownerEmail) {
                continue;
            }

            try {
                Mail::send(
                    'emails.trial-ends-soon',
                    ['subscriptionUrl' => url('/subscription')],
                    fn (Email $m) => $m->to($ownerEmail)->subject('Your Nolbase trial ends in a few days'),
                );
            } catch (\Throwable $e) {
                Log::warning('trial-ends-soon email failed', ['team_id' => $subscription->team_id, 'error' => $e->getMessage()]);
            }
        }

        if ($upcoming->isNotEmpty()) {
            Log::info("SendTrialEndingSoonRemindersJob: notified {$upcoming->count()} trial(s) ending soon.");
        }
    }
}
