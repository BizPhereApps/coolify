<?php

namespace App\Livewire\Reseller;

use App\Models\ClientInvitation;
use App\Models\HostingOffer;
use Livewire\Component;

class InvitationForm extends Component
{
    public ?int $hosting_offer_id = null;

    public string $email = '';

    public string $project_name = '';

    public function mount(?int $offer_id = null): void
    {
        $this->hosting_offer_id = $offer_id;
    }

    public function getAvailableOffersProperty()
    {
        return HostingOffer::where('team_id', currentTeam()->id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function send(): mixed
    {
        $this->validate([
            'hosting_offer_id' => 'required|integer',
            'email' => 'required|email|max:255',
            'project_name' => 'required|string|min:2|max:64',
        ]);

        $offer = HostingOffer::where('team_id', currentTeam()->id)
            ->findOrFail($this->hosting_offer_id);

        $invitation = ClientInvitation::create([
            'hosting_offer_id' => $offer->id,
            'developer_team_id' => currentTeam()->id,
            'email' => $this->email,
            'project_name' => $this->project_name,
            'invitation_token' => ClientInvitation::generateToken(),
            'sent_at' => now(),
            'expires_at' => now()->addDays(ClientInvitation::INVITATION_TTL_DAYS),
        ]);

        // Phase 6.2 will dispatch a ZeptoMail notification with the accept link.
        // For now, the Developer can copy the invitation_token from the list view.

        return $this->redirect(route('reseller.index'));
    }

    public function render()
    {
        return view('livewire.reseller.invitation-form');
    }
}
