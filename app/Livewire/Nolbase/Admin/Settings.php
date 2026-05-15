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
            'placeholder' => 'support@nolbase.com',
        ],
        'announcement_banner' => [
            'label' => 'Tenant announcement banner',
            'description' => 'Optional system-wide message shown to all tenants. Leave blank to hide.',
            'placeholder' => 'Scheduled maintenance Sunday 02:00 UTC',
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

        $this->dispatch('toast', ['type' => 'success', 'message' => count($changed)." setting(s) updated."]);
    }

    public function render()
    {
        return view('livewire.nolbase.admin.settings', [
            'definitions' => self::DEFINITIONS,
        ]);
    }
}
