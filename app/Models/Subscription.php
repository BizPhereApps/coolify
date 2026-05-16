<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_ANNUAL = 'annual';

    protected $fillable = [
        'team_id',
        'plan_id',
        'paystack_subscription_code',
        'paystack_customer_code',
        'paystack_email_token',
        'paystack_authorization_code',
        'status',
        'period',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'cancelled_at',
        'last_payment_failed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_payment_failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    public function isOnTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function billingInterval(): string
    {
        return $this->period === self::PERIOD_ANNUAL ? 'yearly' : 'monthly';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE]);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_TRIALING, self::STATUS_ACTIVE]);
    }
}
