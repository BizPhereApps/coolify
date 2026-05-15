<?php

namespace App\Livewire\Nolbase\Admin\Tenants;

use App\Actions\Paystack\RefundTransaction;
use App\Models\Application;
use App\Models\NolbaseAdminAudit;
use App\Models\Team;
use App\Support\PlanQuota;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Show extends Component
{
    public Team $team;

    public array $usage = [];

    public string $refundReference = '';

    public function mount(Team $team): void
    {
        $this->team = $team->load(['subscription.plan', 'members', 'servers']);
        $this->usage = [
            'servers' => PlanQuota::serversUsage($team),
            'apps' => PlanQuota::appsUsage($team),
            'databases' => PlanQuota::databasesUsage($team),
            'team_members' => PlanQuota::teamMembersUsage($team),
        ];
    }

    public function getRecentAppsProperty()
    {
        return Application::whereRelation('environment.project.team', 'id', $this->team->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'name', 'fqdn', 'created_at']);
    }

    public function refund(): void
    {
        $admin = Auth::guard('nolbase')->user();
        if (! $admin?->canSuspendTenants()) {
            $this->addError('refund', 'Insufficient privileges.');

            return;
        }
        if (! $this->refundReference) {
            $this->addError('refund', 'Paystack reference is required.');

            return;
        }

        try {
            RefundTransaction::run($this->refundReference, null, $this->team->subscription);
            NolbaseAdminAudit::log($admin, 'tenant.refund', $this->team, ['paystack_reference' => $this->refundReference]);
            $this->refundReference = '';
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Refund initiated at Paystack.']);
        } catch (\Throwable $e) {
            $this->addError('refund', $e->getMessage());
        }
    }

    public function suspend(): void
    {
        $admin = Auth::guard('nolbase')->user();
        if (! $admin?->canSuspendTenants()) {
            $this->addError('action', 'Insufficient privileges.');

            return;
        }

        $this->team->update(['nolbase_status' => 'suspended']);
        NolbaseAdminAudit::log($admin, 'tenant.suspend', $this->team);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Tenant suspended.']);
    }

    public function unsuspend(): void
    {
        $admin = Auth::guard('nolbase')->user();
        if (! $admin?->canSuspendTenants()) {
            $this->addError('action', 'Insufficient privileges.');

            return;
        }

        $this->team->update(['nolbase_status' => 'active']);
        NolbaseAdminAudit::log($admin, 'tenant.unsuspend', $this->team);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Tenant reactivated.']);
    }

    public function render()
    {
        return view('livewire.nolbase.admin.tenants.show');
    }
}
