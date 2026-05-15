<?php

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Support\PlanQuota;
use Livewire\Component;

class Show extends Component
{
    public ?Subscription $subscription = null;

    public ?Plan $plan = null;

    public array $usage = [];

    public function mount(): void
    {
        $team = currentTeam();
        $this->subscription = $team?->subscription;
        $this->plan = $this->subscription?->plan;

        if ($team) {
            $this->usage = [
                'servers' => PlanQuota::serversUsage($team),
                'apps' => PlanQuota::appsUsage($team),
                'databases' => PlanQuota::databasesUsage($team),
                'team_members' => PlanQuota::teamMembersUsage($team),
            ];
        }
    }

    public function render()
    {
        return view('livewire.subscription.show');
    }
}
