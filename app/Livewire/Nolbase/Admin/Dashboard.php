<?php

namespace App\Livewire\Nolbase\Admin;

use App\Support\NolbaseMetrics;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Dashboard extends Component
{
    public int $mrr = 0;

    public int $arr = 0;

    public int $activeSubscriptions = 0;

    public int $trialingSubscriptions = 0;

    public int $pastDueSubscriptions = 0;

    public int $totalTeams = 0;

    public int $newTeams30d = 0;

    public array $planDistribution = [];

    public function mount(): void
    {
        $this->mrr = NolbaseMetrics::mrr();
        $this->arr = NolbaseMetrics::arr();
        $this->activeSubscriptions = NolbaseMetrics::activeSubscriptions();
        $this->trialingSubscriptions = NolbaseMetrics::trialingSubscriptions();
        $this->pastDueSubscriptions = NolbaseMetrics::pastDueSubscriptions();
        $this->totalTeams = NolbaseMetrics::totalTeams();
        $this->newTeams30d = NolbaseMetrics::newTeamsLast30Days();
        $this->planDistribution = NolbaseMetrics::planDistribution();
    }

    public function render()
    {
        return view('livewire.nolbase.admin.dashboard');
    }
}
