<?php

namespace App\Livewire\Reseller;

use App\Models\ClientInvitation;
use App\Models\HostingOffer;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Symfony\Component\Mime\Email;

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

        // I5: prevent duplicate pending invitations for the same email+offer.
        // The Developer almost certainly meant to re-send, so we cancel the
        // existing pending invite and issue a fresh token. (vs. silent reject,
        // which leaves the Developer wondering why nothing arrived.)
        $existing = ClientInvitation::query()
            ->where('hosting_offer_id', $offer->id)
            ->where('email', $this->email)
            ->pending()
            ->get();
        foreach ($existing as $old) {
            $old->update(['cancelled_at' => now()]);
        }

        $invitation = ClientInvitation::create([
            'hosting_offer_id' => $offer->id,
            'developer_team_id' => currentTeam()->id,
            'email' => $this->email,
            'project_name' => $this->project_name,
            'invitation_token' => ClientInvitation::generateToken(),
            'sent_at' => now(),
            'expires_at' => now()->addDays(ClientInvitation::INVITATION_TTL_DAYS),
        ]);

        $this->dispatchInvitationEmail($invitation, $offer);

        return $this->redirect(route('reseller.index'));
    }

    private function dispatchInvitationEmail(ClientInvitation $invitation, HostingOffer $offer): void
    {
        try {
            Mail::send(
                'emails.marketplace-invitation',
                [
                    'developerName' => currentTeam()->name,
                    'projectName' => $invitation->project_name,
                    'offerName' => $offer->name,
                    'priceMonthly' => $offer->price_ngn_monthly,
                    'acceptUrl' => route('marketplace.invitation.accept', $invitation->invitation_token),
                    'expiresInDays' => ClientInvitation::INVITATION_TTL_DAYS,
                ],
                fn (Email $message) => $message
                    ->to($invitation->email)
                    ->subject(currentTeam()->name.' invited you to host on Nolbase')
            );
        } catch (\Throwable $e) {
            // Mail failure must not block invitation creation. The Developer
            // can resend later from the index page.
            \Illuminate\Support\Facades\Log::warning('Marketplace invitation email failed', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.reseller.invitation-form');
    }
}
