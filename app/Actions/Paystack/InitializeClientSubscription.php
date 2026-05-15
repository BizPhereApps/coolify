<?php

namespace App\Actions\Paystack;

use App\Models\ClientInvitation;
use App\Models\ClientSubscription;
use App\Services\PaystackService;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class InitializeClientSubscription
{
    use AsAction;

    /**
     * Kicks off a Paystack-hosted checkout for a Client paying their Developer
     * for a hosting offer. Returns the authorization URL to redirect to.
     *
     * The transaction metadata carries the invitation_id and offer_id so the
     * callback handler (VerifyClientTransaction) can wire up the SubTeam,
     * Project, ClientSubscription, and MarketplaceTransaction on success.
     */
    public function handle(ClientInvitation $invitation, string $period = ClientSubscription::PERIOD_MONTHLY): array
    {
        if (! $invitation->isPending()) {
            throw new RuntimeException('This invitation is no longer pending (expired, cancelled, or already accepted).');
        }

        $offer = $invitation->offer;
        $price = $period === ClientSubscription::PERIOD_ANNUAL
            ? $offer->price_ngn_annual
            : $offer->price_ngn_monthly;

        if (! $price) {
            throw new RuntimeException("Offer {$offer->id} has no price configured for period {$period}.");
        }

        $paystack = PaystackService::fromConfig();

        $transaction = $paystack->initializeTransaction(
            email: $invitation->email,
            amountNgn: $price,
            metadata: [
                'marketplace' => true,
                'invitation_id' => $invitation->id,
                'invitation_token' => $invitation->invitation_token,
                'hosting_offer_id' => $offer->id,
                'developer_team_id' => $invitation->developer_team_id,
                'period' => $period,
            ],
            callbackUrl: url('/payments/paystack/marketplace-callback'),
        );

        return [
            'authorization_url' => $transaction['authorization_url'],
            'access_code' => $transaction['access_code'],
            'reference' => $transaction['reference'],
        ];
    }
}
