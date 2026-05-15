<?php

namespace App\Actions\Marketplace;

use App\Models\MarketplaceTransaction;
use App\Models\PayoutAccount;
use App\Services\PaystackService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RunPayoutBatch
{
    use AsAction;

    /**
     * Minimum payout balance in NGN. Anything below this is rolled forward
     * to the next run (avoids per-transfer Paystack fees on tiny payouts).
     */
    public const MIN_PAYOUT_NGN = 5_000;

    /**
     * For every Developer with a verified PayoutAccount and pending balance
     * >= MIN_PAYOUT_NGN, initiate a single Paystack Transfer for the sum
     * and record a TYPE_PAYOUT MarketplaceTransaction that "covers" the
     * underlying charges by referencing their ids in the payload.
     *
     * Safe to run repeatedly: only charges without a matching payout
     * (by inclusion in any prior TYPE_PAYOUT.payload.charge_ids) are
     * included.
     *
     * @return array{paid: int, skipped: int, total_ngn: int}
     */
    public function handle(): array
    {
        $accounts = PayoutAccount::query()
            ->whereNotNull('verified_at')
            ->whereNotNull('paystack_recipient_code')
            ->get();

        $paid = 0;
        $skipped = 0;
        $totalNgn = 0;
        $paystack = null;

        foreach ($accounts as $account) {
            $pending = $this->pendingChargesFor($account->team_id);
            $balance = (int) $pending->sum('net_ngn');

            if ($balance < self::MIN_PAYOUT_NGN) {
                $skipped++;
                continue;
            }

            $paystack ??= PaystackService::fromConfig();

            try {
                DB::transaction(function () use ($paystack, $account, $pending, $balance) {
                    $reference = 'nb_payout_'.$account->team_id.'_'.now()->format('YmdHis');
                    $reason = 'Nolbase marketplace payout — '.now()->format('Y-m-d');

                    $transfer = $paystack->initiateTransfer(
                        recipientCode: $account->paystack_recipient_code,
                        amountNgn: $balance,
                        reason: $reason,
                        reference: $reference,
                    );

                    MarketplaceTransaction::create([
                        'type' => MarketplaceTransaction::TYPE_PAYOUT,
                        'developer_team_id' => $account->team_id,
                        'amount_ngn' => $balance,
                        'fee_ngn' => 0,
                        'net_ngn' => $balance,
                        'paystack_reference' => $reference,
                        'status' => MarketplaceTransaction::STATUS_PENDING,
                        'occurred_at' => now(),
                        'payload' => [
                            'paystack_transfer' => $transfer,
                            'charge_ids' => $pending->pluck('id')->all(),
                        ],
                    ]);
                });
                $paid++;
                $totalNgn += $balance;
            } catch (\Throwable $e) {
                Log::error('RunPayoutBatch: transfer failed', [
                    'team_id' => $account->team_id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        return ['paid' => $paid, 'skipped' => $skipped, 'total_ngn' => $totalNgn];
    }

    /**
     * Charges for this developer that aren't already covered by a previous
     * payout (i.e. their id isn't in any TYPE_PAYOUT payload.charge_ids).
     */
    public function pendingChargesFor(int $developerTeamId, ?Carbon $until = null): \Illuminate\Database\Eloquent\Collection
    {
        $coveredCharges = $this->coveredChargeIds($developerTeamId);

        return MarketplaceTransaction::query()
            ->where('developer_team_id', $developerTeamId)
            ->where('type', MarketplaceTransaction::TYPE_CHARGE)
            ->where('status', MarketplaceTransaction::STATUS_SUCCESS)
            ->when($until, fn ($q) => $q->where('occurred_at', '<=', $until))
            ->whereNotIn('id', $coveredCharges)
            ->orderBy('occurred_at')
            ->get();
    }

    /**
     * @return array<int> ids of charges that are already in a payout's payload.
     */
    public function coveredChargeIds(int $developerTeamId): array
    {
        $ids = [];
        $payouts = MarketplaceTransaction::query()
            ->where('developer_team_id', $developerTeamId)
            ->where('type', MarketplaceTransaction::TYPE_PAYOUT)
            ->get(['payload']);
        foreach ($payouts as $p) {
            foreach (data_get($p->payload, 'charge_ids', []) as $id) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
