<?php

namespace App\Livewire\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use Livewire\Component;

class Show extends Component
{
    public ?Subscription $subscription = null;

    public ?Plan $plan = null;

    public function mount(): void
    {
        $team = currentTeam();
        $this->subscription = $team?->subscription;
        $this->plan = $this->subscription?->plan;
    }

    public function render()
    {
        return view('livewire.subscription.show');
    }
}
