<?php

namespace App\Livewire\Reseller;

use App\Models\ClientInvitation;
use App\Models\HostingOffer;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $team = currentTeam();

        return view('livewire.reseller.index', [
            'offers' => HostingOffer::where('team_id', $team->id)
                ->with('server')
                ->withCount('subTeams')
                ->orderByDesc('created_at')
                ->get(),
            'pendingInvitations' => ClientInvitation::where('developer_team_id', $team->id)
                ->with('offer')
                ->pending()
                ->orderByDesc('created_at')
                ->get(),
            'recentInvitations' => ClientInvitation::where('developer_team_id', $team->id)
                ->with('offer')
                ->whereNotNull('accepted_at')
                ->orderByDesc('accepted_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
