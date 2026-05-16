<?php

namespace App\Livewire\Server\New;

use App\Actions\Provisioning\ProvisionNolbaseManagedServer;
use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class NolbaseManaged extends Component
{
    use AuthorizesRequests;

    public string $plan_slug = 'cax11';

    public string $location_slug = 'fsn1';

    public string $image_slug = 'ubuntu-24.04';

    public string $name = '';

    public bool $limit_reached = false;

    public bool $available = false;

    public ?int $estimated_price_ngn = null;

    public function mount(): void
    {
        $this->limit_reached = Team::serverLimitReached();
        $this->available = (bool) NolbaseSetting::read('nolbase_hetzner_api_token')
            || (bool) env('NOLBASE_HETZNER_API_TOKEN');
        $this->recomputeEstimate();
    }

    public function updatedPlanSlug(): void
    {
        $this->recomputeEstimate();
    }

    private function recomputeEstimate(): void
    {
        $cost = (int) (NolbaseSetting::read("nolbase_hetzner_cost_{$this->plan_slug}_ngn") ?? 8500);
        $markup = (int) (NolbaseSetting::read('nolbase_default_markup_pct') ?? 50);
        $this->estimated_price_ngn = NolbaseManagedServer::priceFromCostBasis($cost, $markup);
    }

    public function provision(): mixed
    {
        if (! $this->available) {
            $this->addError('provision', 'Nolbase-managed hosting is not enabled on this instance.');

            return null;
        }
        if ($this->limit_reached) {
            $this->addError('provision', 'You have reached your plan server limit.');

            return null;
        }

        $this->validate([
            'plan_slug' => ['required', 'string', 'in:cax11,cax21,cax31,cax41,ccx13,ccx23'],
            'location_slug' => ['required', 'string', 'in:fsn1,nbg1,hel1,ash,hil'],
            'image_slug' => ['required', 'string'],
            'name' => ['nullable', 'string', 'min:2', 'max:64', 'regex:/^[a-zA-Z0-9-_]+$/'],
        ]);

        try {
            $server = ProvisionNolbaseManagedServer::run(currentTeam(), [
                'plan_slug' => $this->plan_slug,
                'location_slug' => $this->location_slug,
                'image_slug' => $this->image_slug,
                'name' => $this->name ?: null,
            ]);

            return $this->redirect(route('server.show', $server->uuid));
        } catch (\Throwable $e) {
            $this->addError('provision', $e->getMessage());

            return null;
        }
    }

    public function render()
    {
        return view('livewire.server.new.nolbase-managed');
    }
}
