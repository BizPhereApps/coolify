<?php

namespace App\Livewire\Client;

use App\Models\Application;
use App\Models\SubTeam;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Dashboard extends Component
{
    public SubTeam $subTeam;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $subTeam = SubTeam::with(['parentTeam', 'project', 'offer.server', 'subscription'])
            ->where('client_user_id', $user->id)
            ->whereNull('terminated_at')
            ->first();

        abort_unless($subTeam, 404, 'No active hosting found for this account.');

        $this->subTeam = $subTeam;
    }

    public function getApplicationsProperty()
    {
        if (! $this->subTeam->project_id) {
            return collect();
        }

        return Application::whereRelation('environment', 'project_id', $this->subTeam->project_id)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'fqdn', 'status', 'git_repository', 'environment_id']);
    }

    public function render()
    {
        return view('livewire.client.dashboard');
    }
}
