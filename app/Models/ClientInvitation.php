<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClientInvitation extends Model
{
    use HasFactory;

    public const INVITATION_TTL_DAYS = 7;

    protected $fillable = [
        'hosting_offer_id',
        'developer_team_id',
        'email',
        'project_name',
        'invitation_token',
        'sent_at',
        'expires_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(HostingOffer::class, 'hosting_offer_id');
    }

    public function developerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'developer_team_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->cancelled_at === null
            && $this->expires_at?->isFuture() === true;
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->cancelled_at === null
            && $this->expires_at?->isPast() === true;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
