<?php

namespace App\Livewire\Server\New;

use App\Models\CloudProviderToken;
use App\Models\NolbaseSetting;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Services\VultrService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ByVultr extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public Collection $available_tokens;

    #[Locked]
    public $private_keys;

    #[Locked]
    public bool $limit_reached;

    #[Locked]
    public ?string $referral_url = null;

    public ?int $selected_token_id = null;

    public ?int $private_key_id = null;

    public string $name = '';

    public string $region_id = 'ewr';

    public string $plan_id = 'vc2-1c-1gb';

    public ?int $os_id = null;

    public array $regions = [];

    public array $plans = [];

    public array $os = [];

    public bool $loading_options = false;

    public function mount(): void
    {
        $this->limit_reached = Team::serverLimitReached();
        $this->referral_url = NolbaseSetting::read('vultr_referral_code');
        $this->private_keys = PrivateKey::ownedAndOnlySShKeys()->where('id', '!=', 0)->get();
        if ($this->private_keys->count() > 0) {
            $this->private_key_id = $this->private_keys->first()->id;
        }
        $this->loadTokens();
    }

    public function loadTokens(): void
    {
        $this->available_tokens = CloudProviderToken::ownedByCurrentTeam()
            ->forProvider('vultr')
            ->get();
    }

    public function selectToken(int $tokenId): void
    {
        $token = $this->available_tokens->firstWhere('id', $tokenId);
        if (! $token) {
            $this->addError('selected_token_id', 'Invalid token.');

            return;
        }
        $this->selected_token_id = $tokenId;
        $this->loadProviderOptions();
    }

    private function loadProviderOptions(): void
    {
        $this->loading_options = true;
        try {
            $token = CloudProviderToken::ownedByCurrentTeam()->findOrFail($this->selected_token_id);
            $service = new VultrService($token->token);
            $this->regions = $service->getRegions();
            $this->plans = $service->getPlans();
            // Filter OS list to recent Ubuntu/Debian — Vultr returns hundreds otherwise.
            $allOs = $service->getOperatingSystems();
            $this->os = array_values(array_filter($allOs, fn ($o) => in_array(data_get($o, 'family'), ['ubuntu', 'debian'], true)));
            if (! $this->os_id && ! empty($this->os)) {
                $this->os_id = data_get($this->os[0], 'id');
            }
        } catch (\Throwable $e) {
            $this->addError('selected_token_id', 'Could not load Vultr options: '.$e->getMessage());
            $this->regions = $this->plans = $this->os = [];
        }
        $this->loading_options = false;
    }

    public function provision(): void
    {
        if ($this->limit_reached) {
            $this->addError('provision', 'You have reached your plan server limit.');

            return;
        }

        $this->validate([
            'selected_token_id' => 'required|integer',
            'private_key_id' => 'required|integer|exists:private_keys,id,team_id,'.currentTeam()->id,
            'name' => 'required|string|min:2|max:64',
            'region_id' => 'required|string',
            'plan_id' => 'required|string',
            'os_id' => 'required|integer',
        ]);

        $token = CloudProviderToken::ownedByCurrentTeam()->findOrFail($this->selected_token_id);
        $privateKey = PrivateKey::findOrFail($this->private_key_id);
        $service = new VultrService($token->token);

        try {
            $publicKey = trim($privateKey->publicKey());
            $sshKey = $service->uploadSshKey('nolbase-'.now()->timestamp, $publicKey);
            $instance = $service->createInstance([
                'region' => $this->region_id,
                'plan' => $this->plan_id,
                'os_id' => $this->os_id,
                'label' => $this->name,
                'hostname' => $this->name,
                'sshkey_id' => [data_get($sshKey, 'id')],
                'tags' => ['nolbase', 'team-'.currentTeam()->id],
            ]);

            $ip = $service->findPublicIpv4($instance);
            if (! $ip) {
                for ($i = 0; $i < 12 && ! $ip; $i++) {
                    sleep(5);
                    $latest = $service->getInstance((string) data_get($instance, 'id'));
                    $ip = $service->findPublicIpv4($latest);
                }
            }

            if (! $ip) {
                $this->addError('provision', 'Instance created but public IP did not become available in time.');

                return;
            }

            $server = Server::create([
                'name' => $this->name,
                'description' => 'Provisioned via Vultr (Nolbase BYO cloud).',
                'ip' => $ip,
                'team_id' => currentTeam()->id,
                'private_key_id' => $privateKey->id,
                'cloud_provider_token_id' => $token->id,
            ]);

            $this->redirect(route('server.show', $server->uuid));
        } catch (\Throwable $e) {
            $this->addError('provision', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.server.new.by-vultr');
    }
}
