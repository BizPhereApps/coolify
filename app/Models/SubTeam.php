<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_team_id',
        'client_user_id',
        'project_id',
        'hosting_offer_id',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'terminated_at' => 'datetime',
        ];
    }

    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(HostingOffer::class, 'hosting_offer_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ClientSubscription::class);
    }

    public function isActive(): bool
    {
        return $this->terminated_at === null;
    }
}
