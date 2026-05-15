<?php

namespace App\Actions\Paystack;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\PaystackService;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class InitializeSubscription
{
    use AsAction;

    /**
     * Kicks off a Paystack-hosted checkout for a team to subscribe to a plan.
     * Returns the authorization URL to redirect the user to.
     */
    public function handle(Team $team, Plan $plan, string $period = Subscription::PERIOD_MONTHLY, ?string $customerEmail = null): array
    {
        if ($plan->code === 'free') {
            throw new RuntimeException('Cannot initialize a Paystack transaction for the Free plan.');
        }

        $price = $period === Subscription::PERIOD_ANNUAL
            ? $plan->price_ngn_annual
            : $plan->price_ngn_monthly;

        if (! $price) {
            throw new RuntimeException("Plan {$plan->code} has no price configured for period {$period}.");
        }

        $planCode = $period === Subscription::PERIOD_ANNUAL
            ? $plan->paystack_plan_code_annual
            : $plan->paystack_plan_code_monthly;

        $email = $customerEmail ?? $team->members()->orderBy('team_user.role', 'desc')->value('email');
        if (! $email) {
            throw new RuntimeException('Team has no member with an email to bill.');
        }

        $paystack = PaystackService::fromConfig();

        $transaction = $paystack->initializeTransaction(
            email: $email,
            amountNgn: $price,
            metadata: [
                'team_id' => $team->id,
                'plan_id' => $plan->id,
                'period' => $period,
            ],
            planCode: $planCode,
        );

        return [
            'authorization_url' => $transaction['authorization_url'],
            'access_code' => $transaction['access_code'],
            'reference' => $transaction['reference'],
        ];
    }
}
