<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceTransaction extends Model
{
    use HasFactory;

    public const TYPE_CHARGE = 'charge';

    public const TYPE_REFUND = 'refund';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_FEE = 'fee';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'client_subscription_id',
        'developer_team_id',
        'client_user_id',
        'amount_ngn',
        'fee_ngn',
        'net_ngn',
        'paystack_reference',
        'status',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'payload' => 'array',
            'amount_ngn' => 'integer',
            'fee_ngn' => 'integer',
            'net_ngn' => 'integer',
        ];
    }

    public function clientSubscription(): BelongsTo
    {
        return $this->belongsTo(ClientSubscription::class);
    }

    public function developerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'developer_team_id');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
