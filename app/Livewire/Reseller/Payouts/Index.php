<?php

namespace App\Livewire\Reseller\Payouts;

use App\Actions\Marketplace\RunPayoutBatch;
use App\Models\MarketplaceTransaction;
use App\Models\PayoutAccount;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $team = currentTeam();

        $account = PayoutAccount::where('team_id', $team->id)->first();
        $pendingCharges = RunPayoutBatch::make()->pendingChargesFor($team->id);
        $pendingBalance = (int) $pendingCharges->sum('net_ngn');

        $payouts = MarketplaceTransaction::query()
            ->where('developer_team_id', $team->id)
            ->where('type', MarketplaceTransaction::TYPE_PAYOUT)
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        $recentCharges = MarketplaceTransaction::query()
            ->where('developer_team_id', $team->id)
            ->where('type', MarketplaceTransaction::TYPE_CHARGE)
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get();

        return view('livewire.reseller.payouts.index', [
            'account' => $account,
            'pendingBalance' => $pendingBalance,
            'pendingChargesCount' => $pendingCharges->count(),
            'minPayout' => RunPayoutBatch::MIN_PAYOUT_NGN,
            'payouts' => $payouts,
            'recentCharges' => $recentCharges,
        ]);
    }
}
