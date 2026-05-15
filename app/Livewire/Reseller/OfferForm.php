<?php

namespace App\Livewire\Reseller;

use App\Models\HostingOffer;
use App\Models\Server;
use Livewire\Component;

class OfferForm extends Component
{
    public ?HostingOffer $offer = null;

    public ?int $server_id = null;

    public string $name = '';

    public string $description = '';

    public int $ram_mb = 512;

    public int $disk_gb = 5;

    public int $max_apps = 1;

    public int $max_databases = 0;

    public bool $allow_custom_domain = true;

    public int $price_ngn_monthly = 10000;

    public ?int $price_ngn_annual = null;

    public bool $is_active = true;

    public function mount(?HostingOffer $offer = null): void
    {
        if ($offer && $offer->exists) {
            $this->assertOwns($offer->team_id);
            $this->offer = $offer;
            $this->server_id = $offer->server_id;
            $this->name = $offer->name;
            $this->description = $offer->description ?? '';
            $this->ram_mb = $offer->ram_mb;
            $this->disk_gb = $offer->disk_gb;
            $this->max_apps = $offer->max_apps;
            $this->max_databases = $offer->max_databases;
            $this->allow_custom_domain = $offer->allow_custom_domain;
            $this->price_ngn_monthly = $offer->price_ngn_monthly;
            $this->price_ngn_annual = $offer->price_ngn_annual;
            $this->is_active = $offer->is_active;
        }
    }

    public function getAvailableServersProperty()
    {
        return Server::query()
            ->where('team_id', currentTeam()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'ip']);
    }

    private function assertOwns(int $teamId): void
    {
        if ($teamId !== currentTeam()->id) {
            abort(403);
        }
    }

    public function save(): mixed
    {
        $this->validate([
            'server_id' => 'required|integer|exists:servers,id,team_id,'.currentTeam()->id,
            'name' => 'required|string|min:2|max:64',
            'description' => 'nullable|string|max:500',
            'ram_mb' => 'required|integer|min:64|max:1048576',
            'disk_gb' => 'required|integer|min:1|max:10240',
            'max_apps' => 'required|integer|min:1|max:1000',
            'max_databases' => 'required|integer|min:0|max:1000',
            'price_ngn_monthly' => 'required|integer|min:0',
            'price_ngn_annual' => 'nullable|integer|min:0',
        ]);

        $data = [
            'team_id' => currentTeam()->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'ram_mb' => $this->ram_mb,
            'disk_gb' => $this->disk_gb,
            'max_apps' => $this->max_apps,
            'max_databases' => $this->max_databases,
            'allow_custom_domain' => $this->allow_custom_domain,
            'price_ngn_monthly' => $this->price_ngn_monthly,
            'price_ngn_annual' => $this->price_ngn_annual,
            'is_active' => $this->is_active,
        ];

        if ($this->offer) {
            $this->offer->update($data);
        } else {
            HostingOffer::create($data);
        }

        return $this->redirect(route('reseller.index'));
    }

    public function render()
    {
        return view('livewire.reseller.offer-form');
    }
}
