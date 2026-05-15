<?php

namespace App\Livewire\Subscription;

use App\Actions\Paystack\InitializeSubscription;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PricingPlans extends Component
{
    public string $period = 'monthly';

    #[Computed]
    public function plans(): Collection
    {
        return Plan::where('is_public', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function currentPlanCode(): ?string
    {
        return currentTeam()?->subscription?->plan?->code;
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === Subscription::PERIOD_ANNUAL ? 'annual' : 'monthly';
    }

    public function choose(int $planId): mixed
    {
        $team = currentTeam();
        if (! $team) {
            return $this->redirect(route('login'));
        }

        $plan = Plan::findOrFail($planId);

        if ($plan->code === 'free') {
            return $this->dispatch('toast', ['type' => 'info', 'message' => 'Free plan is the default — no payment required.']);
        }

        $result = InitializeSubscription::run(
            team: $team,
            plan: $plan,
            period: $this->period === 'annual' ? Subscription::PERIOD_ANNUAL : Subscription::PERIOD_MONTHLY,
            customerEmail: auth()->user()?->email,
        );

        return $this->redirect($result['authorization_url']);
    }

    public function render()
    {
        return view('livewire.subscription.pricing-plans');
    }
}
