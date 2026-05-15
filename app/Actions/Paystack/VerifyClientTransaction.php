<?php

namespace App\Actions\Paystack;

use App\Models\ClientInvitation;
use App\Models\ClientSubscription;
use App\Models\HostingOffer;
use App\Models\MarketplaceTransaction;
use App\Models\Project;
use App\Models\SubTeam;
use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class VerifyClientTransaction
{
    use AsAction;

    /**
     * Verify a Client (marketplace) Paystack transaction.
     *
     * On success creates:
     *   - User (if doesn't exist for this email)
     *   - SubTeam (parent=Developer's team, client=this user)
     *   - Project + production Environment under the Developer's team
     *   - ClientSubscription with Paystack codes + period dates
     *   - MarketplaceTransaction: charge entry + fee entry
     *
     * Wraps everything in a DB transaction so a partial failure rolls back.
     */
    public function handle(string $reference): ClientSubscription
    {
        $paystack = PaystackService::fromConfig();
        $data = $paystack->verifyTransaction($reference);

        if (data_get($data, 'status') !== 'success') {
            throw new RuntimeException('Paystack transaction did not succeed.');
        }

        $invitationId = (int) data_get($data, 'metadata.invitation_id');
        $offerId = (int) data_get($data, 'metadata.hosting_offer_id');
        $period = data_get($data, 'metadata.period', ClientSubscription::PERIOD_MONTHLY);
        $email = data_get($data, 'customer.email');
        $customerCode = data_get($data, 'customer.customer_code');
        $subscriptionCode = data_get($data, 'plan_object.subscription_code')
            ?? data_get($data, 'authorization.subscription_code');
        $emailToken = data_get($data, 'plan_object.subscription_email_token')
            ?? data_get($data, 'authorization.subscription_email_token');
        $amountKobo = (int) data_get($data, 'amount', 0);

        $invitation = ClientInvitation::findOrFail($invitationId);
        $offer = HostingOffer::findOrFail($offerId);

        if (! $invitation->isPending()) {
            throw new RuntimeException('Invitation is no longer pending.');
        }

        return DB::transaction(function () use (
            $invitation, $offer, $period, $email, $customerCode, $subscriptionCode, $emailToken, $amountKobo, $reference, $data
        ) {
            $user = $this->resolveOrCreateClient($email, $invitation->project_name);

            $subTeam = SubTeam::create([
                'parent_team_id' => $invitation->developer_team_id,
                'client_user_id' => $user->id,
                'hosting_offer_id' => $offer->id,
            ]);

            // Project::created auto-creates a 'production' Environment + ProjectSetting
            // (see Project::booted()), so we don't create those ourselves.
            $project = Project::create([
                'team_id' => $invitation->developer_team_id,
                'name' => $invitation->project_name,
                'description' => "Client project for {$user->email} (via {$offer->name}).",
            ]);
            $subTeam->update(['project_id' => $project->id]);

            $now = now();
            $periodEnd = $period === ClientSubscription::PERIOD_ANNUAL
                ? $now->copy()->addYear()
                : $now->copy()->addMonth();

            $clientSub = ClientSubscription::create([
                'sub_team_id' => $subTeam->id,
                'hosting_offer_id' => $offer->id,
                'paystack_subscription_code' => $subscriptionCode,
                'paystack_customer_code' => $customerCode,
                'paystack_email_token' => $emailToken,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'period' => $period,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
            ]);

            // Money ledger: gross charge to client + the platform fee row.
            $amountNgn = (int) round($amountKobo / 100);
            $feePct = (float) config('paystack.marketplace_fee_pct', 10);
            $feeNgn = (int) round($amountNgn * $feePct / 100);
            $netNgn = $amountNgn - $feeNgn;

            MarketplaceTransaction::create([
                'type' => MarketplaceTransaction::TYPE_CHARGE,
                'client_subscription_id' => $clientSub->id,
                'developer_team_id' => $invitation->developer_team_id,
                'client_user_id' => $user->id,
                'amount_ngn' => $amountNgn,
                'fee_ngn' => $feeNgn,
                'net_ngn' => $netNgn,
                'paystack_reference' => $reference,
                'status' => MarketplaceTransaction::STATUS_SUCCESS,
                'occurred_at' => $now,
                'payload' => $data,
            ]);
            MarketplaceTransaction::create([
                'type' => MarketplaceTransaction::TYPE_FEE,
                'client_subscription_id' => $clientSub->id,
                'developer_team_id' => $invitation->developer_team_id,
                'client_user_id' => $user->id,
                'amount_ngn' => $feeNgn,
                'fee_ngn' => 0,
                'net_ngn' => $feeNgn,
                'paystack_reference' => $reference,
                'status' => MarketplaceTransaction::STATUS_SUCCESS,
                'occurred_at' => $now,
            ]);

            $invitation->update(['accepted_at' => $now]);

            return $clientSub->load(['subTeam', 'offer']);
        });
    }

    private function resolveOrCreateClient(string $email, string $projectName): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        // New Client signup. Generate a placeholder password they'll reset
        // via the standard /forgot-password flow once they want to access
        // their dashboard. They got here via the invitation token, which
        // itself is proof of access.
        return User::create([
            'name' => $projectName.' (client)',
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
        ]);
    }
}
