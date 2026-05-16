<?php

namespace App\Livewire\Nolbase\Admin;

use App\Models\NolbaseManagedInvoice;
use App\Models\NolbaseManagedServer;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Operator-facing fleet view: every Nolbase-managed server across all
 * tenants, with per-row cost vs price (margin) and the status of its
 * latest monthly invoice. Used to answer "are we profitable?" and "who
 * is past due right now?" at a glance.
 */
#[Layout('layouts.simple')]
class ManagedFleet extends Component
{
    public int $totalServers = 0;

    public int $activeServers = 0;

    public int $pastDueServers = 0;

    public int $suspendedServers = 0;

    public int $totalMonthlyRevenueNgn = 0;

    public int $totalMonthlyCostNgn = 0;

    public int $totalMonthlyMarginNgn = 0;

    public function render()
    {
        $servers = NolbaseManagedServer::query()
            ->with(['server.team'])
            ->whereNull('decommissioned_at')
            ->orderBy('billing_status')
            ->orderByDesc('id')
            ->get();

        $monthStart = now()->startOfMonth()->toDateString();
        $invoiceMap = NolbaseManagedInvoice::query()
            ->whereDate('billing_month', $monthStart)
            ->get()
            ->keyBy('team_id');

        $this->totalServers = $servers->count();
        $this->activeServers = $servers->where('billing_status', NolbaseManagedServer::BILLING_ACTIVE)->count();
        $this->pastDueServers = $servers->where('billing_status', NolbaseManagedServer::BILLING_PAST_DUE)->count();
        $this->suspendedServers = $servers->where('billing_status', NolbaseManagedServer::BILLING_SUSPENDED)->count();
        $this->totalMonthlyRevenueNgn = (int) $servers->where('billing_status', NolbaseManagedServer::BILLING_ACTIVE)->sum('price_ngn_monthly');
        $this->totalMonthlyCostNgn = (int) $servers->where('billing_status', NolbaseManagedServer::BILLING_ACTIVE)->sum('cost_basis_ngn_monthly');
        $this->totalMonthlyMarginNgn = $this->totalMonthlyRevenueNgn - $this->totalMonthlyCostNgn;

        $rows = $servers->map(fn (NolbaseManagedServer $m) => [
            'managed' => $m,
            'invoice' => $invoiceMap->get(optional($m->server)->team_id),
            'margin_ngn' => (int) ($m->price_ngn_monthly ?? 0) - (int) ($m->cost_basis_ngn_monthly ?? 0),
        ]);

        return view('livewire.nolbase.admin.managed-fleet', ['rows' => $rows]);
    }
}
