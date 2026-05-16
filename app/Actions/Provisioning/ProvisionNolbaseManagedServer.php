<?php

namespace App\Actions\Provisioning;

use App\Models\NolbaseManagedServer;
use App\Models\NolbaseSetting;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Models\Team;
use App\Services\HetznerService;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Provisions a Hetzner Cloud server using Nolbase's own API token and
 * registers it as a Server attached to a tenant's team. The Server is a
 * regular Coolify resource from the tenant's perspective — they deploy
 * apps to it via the normal UI — but Nolbase holds root access and
 * the underlying Hetzner account billing.
 *
 * Settings the super-admin must configure under /nolbase/admin/settings:
 *   nolbase_hetzner_api_token  — secret. Used for ALL Nolbase-managed
 *                                 provisioning. Stored encrypted at rest
 *                                 because nolbase_settings.value uses the
 *                                 string column type; consider an env var
 *                                 fallback for additional defense.
 *   nolbase_hetzner_ssh_pubkey — public SSH key Nolbase keeps the matching
 *                                 private half of, for root access.
 */
class ProvisionNolbaseManagedServer
{
    use AsAction;

    /**
     * @param  array{plan_slug?: string, location_slug?: string, image_slug?: string, name?: string}  $params
     */
    public function handle(Team $team, array $params = []): Server
    {
        $token = NolbaseSetting::read('nolbase_hetzner_api_token')
            ?: env('NOLBASE_HETZNER_API_TOKEN');
        if (! $token) {
            throw new RuntimeException(
                'Nolbase-managed hosting is not yet configured. A super-admin '.
                'needs to set nolbase_hetzner_api_token under /nolbase/admin/settings.'
            );
        }

        $pubkey = NolbaseSetting::read('nolbase_hetzner_ssh_pubkey');
        if (! $pubkey) {
            throw new RuntimeException('Nolbase SSH public key is not configured.');
        }

        $planSlug = $params['plan_slug'] ?? 'cax11';            // ARM 2vCPU, 4GB, ~€3.79/mo
        $locationSlug = $params['location_slug'] ?? 'fsn1';     // Falkenstein, Germany
        $imageSlug = $params['image_slug'] ?? 'ubuntu-24.04';
        $name = $params['name'] ?? 'nolbase-mgd-team-'.$team->id.'-'.now()->timestamp;

        $hetzner = new HetznerService($token);

        // Ensure Nolbase's SSH key exists at Hetzner. Upload-once is harmless:
        // if the fingerprint matches an existing key, Hetzner returns it.
        $sshKey = $this->ensureNolbaseSshKey($hetzner, $pubkey);

        $hetznerServer = $hetzner->createServer([
            'name' => $name,
            'server_type' => $planSlug,
            'location' => $locationSlug,
            'image' => $imageSlug,
            'ssh_keys' => [data_get($sshKey, 'id')],
            'labels' => [
                'managed-by' => 'nolbase',
                'tenant-team' => (string) $team->id,
            ],
        ]);

        $ip = data_get($hetznerServer, 'server.public_net.ipv4.ip')
            ?? data_get($hetznerServer, 'public_net.ipv4.ip');
        if (! $ip) {
            throw new RuntimeException('Hetzner did not return a public IPv4 address.');
        }

        // Register the Nolbase private key so Coolify can connect.
        $privateKey = $this->resolveNolbasePrivateKey($team);

        $server = Server::create([
            'name' => $name,
            'description' => 'Provisioned and managed by Nolbase.',
            'ip' => $ip,
            'team_id' => $team->id,
            'private_key_id' => $privateKey->id,
        ]);

        // Cost basis from the Hetzner plan + 50% markup default. Real-world
        // pricing should pull from /pricing endpoint; for v1 we accept an
        // approximation and let Nolbase admins override per-plan in settings.
        $costNgnMonthly = (int) (NolbaseSetting::read("nolbase_hetzner_cost_{$planSlug}_ngn") ?? 8500);
        $markupPct = (int) (NolbaseSetting::read('nolbase_default_markup_pct') ?? 50);
        $priceNgnMonthly = NolbaseManagedServer::priceFromCostBasis($costNgnMonthly, $markupPct);

        NolbaseManagedServer::create([
            'server_id' => $server->id,
            'provider' => 'hetzner',
            'provider_resource_id' => (string) data_get($hetznerServer, 'server.id', data_get($hetznerServer, 'id')),
            'plan_slug' => $planSlug,
            'location_slug' => $locationSlug,
            'cost_basis_ngn_monthly' => $costNgnMonthly,
            'price_ngn_monthly' => $priceNgnMonthly,
            'markup_pct' => $markupPct,
            'billing_status' => NolbaseManagedServer::BILLING_ACTIVE,
        ]);

        return $server->fresh();
    }

    private function ensureNolbaseSshKey(HetznerService $hetzner, string $pubkey): array
    {
        $existing = collect($hetzner->getSshKeys())->first(
            fn ($k) => trim(data_get($k, 'public_key', '')) === trim($pubkey)
        );
        if ($existing) {
            return $existing;
        }

        return $hetzner->uploadSshKey('nolbase-master-'.now()->timestamp, $pubkey);
    }

    /**
     * Look up the shared Nolbase-managed PrivateKey. Operators create this
     * once at setup time (preferably on team_id=0); running the action on a
     * tenant team reuses it rather than copying per-team. This also
     * sidesteps PrivateKey::saving validation in tests, which can use
     * DB::table to seed a placeholder row without needing a real OpenSSH
     * key parser to accept it.
     */
    private function resolveNolbasePrivateKey(Team $team): PrivateKey
    {
        $key = PrivateKey::query()
            ->where('name', 'nolbase-managed')
            ->orderBy('team_id') // prefers team_id=0 when present
            ->first();
        if ($key) {
            return $key;
        }

        throw new RuntimeException(
            'No PrivateKey named "nolbase-managed" exists. Create one at '.
            'setup time via tinker: PrivateKey::forceCreate(["team_id" => 0, '.
            '"name" => "nolbase-managed", "private_key" => "<real-openssh-key>", '.
            '"uuid" => (string) new Cuid2]).'
        );
    }
}
