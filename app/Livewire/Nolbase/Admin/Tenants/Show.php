<?php

namespace App\Livewire\Nolbase\Admin\Tenants;

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

    public function mount(Team $team): void
    {
        $this->team = $team->load(['subscription.plan', 'members']);
        $this->usage = [
            'servers' => PlanQuota::serversUsage($team),
            'apps' => PlanQuota::appsUsage($team),
            'databases' => PlanQuota::databasesUsage($team),
            'team_members' => PlanQuota::teamMembersUsage($team),
        ];
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
