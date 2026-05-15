<?php

namespace App\Livewire\Marketplace;

use App\Actions\Paystack\InitializeClientSubscription;
use App\Models\ClientInvitation;
use App\Models\ClientSubscription;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class AcceptInvitation extends Component
{
    public ClientInvitation $invitation;

    public string $period = ClientSubscription::PERIOD_MONTHLY;

    public function mount(string $token): void
    {
        $this->invitation = ClientInvitation::with(['offer.server', 'developerTeam'])
            ->where('invitation_token', $token)
            ->firstOrFail();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period === ClientSubscription::PERIOD_ANNUAL
            ? ClientSubscription::PERIOD_ANNUAL
            : ClientSubscription::PERIOD_MONTHLY;
    }

    public function pay(): mixed
    {
        if (! $this->invitation->isPending()) {
            $this->addError('pay', $this->invitation->isExpired()
                ? 'This invitation has expired. Ask your developer to send a new one.'
                : 'This invitation is no longer available.');

            return null;
        }

        try {
            $result = InitializeClientSubscription::run($this->invitation, $this->period);

            return $this->redirect($result['authorization_url']);
        } catch (\Throwable $e) {
            $this->addError('pay', $e->getMessage());

            return null;
        }
    }

    public function render()
    {
        return view('livewire.marketplace.accept-invitation');
    }
}
