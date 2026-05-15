<?php

namespace App\Livewire\Subscription;

use App\Actions\Paystack\CancelSubscription as CancelSubscriptionAction;
use App\Models\Subscription;
use Livewire\Component;

class Actions extends Component
{
    public Subscription $subscription;

    public function mount(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function cancelAtPeriodEnd(): void
    {
        $this->authorize('update', $this->subscription->team);
        CancelSubscriptionAction::run($this->subscription, immediate: false);
        $this->subscription->refresh();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Subscription will end at the close of the current billing period.']);
    }

    public function resume(): void
    {
        $this->authorize('update', $this->subscription->team);
        $this->subscription->update(['cancel_at_period_end' => false]);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Subscription resumed.']);
    }

    public function render()
    {
        return view('livewire.subscription.actions');
    }
}
