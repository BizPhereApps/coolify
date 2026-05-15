<?php

namespace App\Actions\Paystack;

use App\Models\PayoutAccount;
use App\Models\Team;
use App\Services\PaystackService;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SaveDeveloperPayoutAccount
{
    use AsAction;

    /**
     * Verify a Nigerian bank account via Paystack /bank/resolve, create a
     * Paystack Transfer Recipient, and persist a verified PayoutAccount for
     * the Developer's team.
     *
     * Idempotent: re-running with the same inputs updates the existing row
     * and the recipient_code in place.
     */
    public function handle(Team $team, string $bankCode, string $accountNumber): PayoutAccount
    {
        $paystack = PaystackService::fromConfig();

        $resolved = $paystack->resolveAccount($accountNumber, $bankCode);
        $accountName = data_get($resolved, 'account_name');
        if (! $accountName) {
            throw new RuntimeException('Paystack could not resolve the account name. Double-check the bank + account number.');
        }

        $recipient = $paystack->createTransferRecipient($accountName, $accountNumber, $bankCode);
        $recipientCode = data_get($recipient, 'recipient_code');
        if (! $recipientCode) {
            throw new RuntimeException('Paystack did not return a recipient_code.');
        }

        return PayoutAccount::updateOrCreate(
            ['team_id' => $team->id],
            [
                'bank_code' => $bankCode,
                'account_number_encrypted' => $accountNumber,
                'account_name' => $accountName,
                'paystack_recipient_code' => $recipientCode,
                'verified_at' => now(),
            ],
        );
    }
}
