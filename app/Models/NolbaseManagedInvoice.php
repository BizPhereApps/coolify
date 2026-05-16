<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NolbaseManagedInvoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'team_id',
        'billing_month',
        'total_ngn',
        'line_items',
        'paystack_reference',
        'status',
        'failure_reason',
        'billed_at',
        'refunded_ngn',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
            'line_items' => 'array',
            'total_ngn' => 'integer',
            'billed_at' => 'datetime',
            'refunded_ngn' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
