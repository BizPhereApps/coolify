<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a Server as provisioned + owned by Nolbase (on Nolbase's cloud account)
 * but assigned to a tenant's team. The tenant interacts with it via the regular
 * Coolify UI. Nolbase keeps root access for lifecycle (suspend / decommission)
 * and bills the tenant monthly via Paystack invoice (Phase 7.2 — currently
 * tracked but not auto-charged).
 */
class NolbaseManagedServer extends Model
{
    use HasFactory;

    public const BILLING_ACTIVE = 'active';

    public const BILLING_PAST_DUE = 'past_due';

    public const BILLING_SUSPENDED = 'suspended';

    public const BILLING_DECOMMISSIONED = 'decommissioned';

    protected $fillable = [
        'server_id',
        'provider',
        'provider_resource_id',
        'plan_slug',
        'location_slug',
        'cost_basis_ngn_monthly',
        'price_ngn_monthly',
        'markup_pct',
        'billing_status',
        'suspended_at',
        'decommissioned_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_basis_ngn_monthly' => 'integer',
            'price_ngn_monthly' => 'integer',
            'markup_pct' => 'integer',
            'suspended_at' => 'datetime',
            'decommissioned_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isActive(): bool
    {
        return $this->billing_status === self::BILLING_ACTIVE
            && $this->decommissioned_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('billing_status', self::BILLING_ACTIVE)
            ->whereNull('decommissioned_at');
    }

    /**
     * Compute the tenant-facing price from cost basis + markup_pct.
     * Used at provisioning time; thereafter the stored price_ngn_monthly is
     * the source of truth.
     */
    public static function priceFromCostBasis(int $costBasisNgn, int $markupPct = 50): int
    {
        return (int) round($costBasisNgn * (100 + $markupPct) / 100);
    }
}
