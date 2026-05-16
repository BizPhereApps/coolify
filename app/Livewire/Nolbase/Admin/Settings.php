<?php

namespace App\Livewire\Nolbase\Admin;

use App\Models\NolbaseAdminAudit;
use App\Models\NolbaseSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Settings extends Component
{
    /**
     * The set of editable keys + metadata.
     */
    public const DEFINITIONS = [
        'digitalocean_referral_url' => [
            'label' => 'DigitalOcean referral URL',
            'description' => 'Partner link shown to tenants choosing DO for BYO cloud hosting. Format: https://m.do.co/c/XXXX',
            'placeholder' => 'https://m.do.co/c/your-referral-code',
        ],
        'hetzner_referral_code' => [
            'label' => 'Hetzner referral code',
            'description' => 'Coupon/referral code shown alongside the Hetzner signup CTA.',
            'placeholder' => 'XXXXXXXXX',
        ],
        'vultr_referral_code' => [
            'label' => 'Vultr referral code',
            'description' => 'Referral code shown alongside the Vultr signup CTA.',
            'placeholder' => '',
        ],
        'source_repo_url' => [
            'label' => 'AGPL source repository URL',
            'description' => 'Public Nolbase fork URL shown on /legal/source and in the footer.',
            'placeholder' => 'https://github.com/<org>/nolbase',
        ],
        'support_email' => [
            'label' => 'Support email',
            'description' => 'Surfaced on suspension messages, transactional emails, /legal/source.',
            'placeholder' => 'support@nolbase.io',
        ],
        'announcement_banner' => [
            'label' => 'Tenant announcement banner',
            'description' => 'Optional system-wide message shown to all tenants. Leave blank to hide.',
            'placeholder' => 'Scheduled maintenance Sunday 02:00 UTC',
        ],
        'nolbase_hetzner_api_token' => [
            'label' => 'Nolbase-managed: Hetzner API token',
            'description' => "Nolbase's own Hetzner Cloud API token. Used to provision managed servers on Nolbase's account. Visible only to staff+. Leave blank to disable Nolbase-managed hosting.",
            'placeholder' => 'eyJfMA...',
        ],
        'nolbase_hetzner_ssh_pubkey' => [
            'label' => 'Nolbase-managed: SSH public key',
            'description' => "Public half of Nolbase's master SSH key. Injected into every managed server at provisioning time. Paste the full 'ssh-ed25519 AAAA... comment' line.",
            'placeholder' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5...',
        ],
        'nolbase_hetzner_ssh_private_key' => [
            'label' => 'Nolbase-managed: SSH private key',
            'description' => 'Matching PRIVATE key (OpenSSH format, full -----BEGIN... block). Stored encrypted at rest. Required for Coolify to SSH into managed servers.',
            'placeholder' => '-----BEGIN OPENSSH PRIVATE KEY-----',
        ],
        'nolbase_default_markup_pct' => [
            'label' => 'Nolbase-managed: markup percentage',
            'description' => 'Default markup over the Hetzner cost basis. 50 means a €5 server is billed at ₦12,750 NGN (cost ≈ ₦8,500 × 1.5). Configurable per plan via nolbase_hetzner_cost_<slug>_ngn.',
            'placeholder' => '50',
        ],
    ];

    public array $values = [];

    public function mount(): void
    {
        foreach (array_keys(self::DEFINITIONS) as $key) {
            $this->values[$key] = NolbaseSetting::read($key, '') ?? '';
        }
    }

    public function save(): void
    {
        $admin = Auth::guard('nolbase')->user();
        if (! $admin?->canManageAdmins() && ! $admin?->isStaff()) {
            $this->addError('save', 'Only staff and superadmins can edit settings.');

            return;
        }

        $changed = [];
        foreach (self::DEFINITIONS as $key => $meta) {
            $existing = NolbaseSetting::read($key, null);
            $newValue = $this->values[$key] ?? '';
            $newValue = $newValue === '' ? null : $newValue;
            if ($existing !== $newValue) {
                NolbaseSetting::write($key, $newValue, $admin, $meta['label'], $meta['description']);
                $changed[] = $key;
            }
        }

        if ($changed) {
            NolbaseAdminAudit::log($admin, 'settings.updated', null, ['keys' => $changed]);
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => count($changed).' setting(s) updated.']);
    }

    public function render()
    {
        return view('livewire.nolbase.admin.settings', [
            'definitions' => self::DEFINITIONS,
        ]);
    }
}
