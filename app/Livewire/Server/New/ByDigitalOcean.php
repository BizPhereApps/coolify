<?php

namespace App\Livewire\Server\New;

use App\Models\CloudProviderToken;
use App\Models\NolbaseSetting;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Services\DigitalOceanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ByDigitalOcean extends Component
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

    public string $region_slug = 'nyc3';

    public string $size_slug = 's-1vcpu-1gb';

    public string $image_slug = 'ubuntu-22-04-x64';

    public array $regions = [];

    public array $sizes = [];

    public array $images = [];

    public bool $loading_options = false;

    public function mount(): void
    {
        $this->limit_reached = Team::serverLimitReached();
        $this->referral_url = NolbaseSetting::read('digitalocean_referral_url');
        $this->private_keys = PrivateKey::ownedAndOnlySShKeys()->where('id', '!=', 0)->get();
        if ($this->private_keys->count() > 0) {
            $this->private_key_id = $this->private_keys->first()->id;
        }
        $this->loadTokens();
    }

    public function loadTokens(): void
    {
        $this->available_tokens = CloudProviderToken::ownedByCurrentTeam()
            ->forProvider('digitalocean')
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
            $service = new DigitalOceanService($token->token);
            $this->regions = $service->getRegions();
            $this->sizes = $service->getSizes();
            $this->images = $service->getImages();
        } catch (\Throwable $e) {
            $this->addError('selected_token_id', 'Could not load DigitalOcean options: '.$e->getMessage());
            $this->regions = $this->sizes = $this->images = [];
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
            'region_slug' => 'required|string',
            'size_slug' => 'required|string',
            'image_slug' => 'required|string',
        ]);

        $token = CloudProviderToken::ownedByCurrentTeam()->findOrFail($this->selected_token_id);
        $privateKey = PrivateKey::findOrFail($this->private_key_id);
        $service = new DigitalOceanService($token->token);

        try {
            $publicKey = trim($privateKey->publicKey());
            $sshKey = $service->uploadSshKey('nolbase-'.now()->timestamp, $publicKey);
            $droplet = $service->createDroplet([
                'name' => $this->name,
                'region' => $this->region_slug,
                'size' => $this->size_slug,
                'image' => $this->image_slug,
                'ssh_keys' => [data_get($sshKey, 'id')],
                'tags' => ['nolbase', 'team-'.currentTeam()->id],
            ]);

            $ip = $service->findPublicIpv4($droplet);
            if (! $ip) {
                // poll a few times — DO may not have assigned the IP yet
                for ($i = 0; $i < 12 && ! $ip; $i++) {
                    sleep(5);
                    $latest = $service->getDroplet((int) data_get($droplet, 'id'));
                    $ip = $service->findPublicIpv4($latest);
                }
            }

            if (! $ip) {
                $this->addError('provision', 'Droplet created but public IP did not become available in time.');

                return;
            }

            $server = Server::create([
                'name' => $this->name,
                'description' => 'Provisioned via DigitalOcean (Nolbase BYO cloud).',
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
        return view('livewire.server.new.by-digital-ocean');
    }
}
