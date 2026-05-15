<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'server_id',
        'name',
        'description',
        'ram_mb',
        'disk_gb',
        'max_apps',
        'max_databases',
        'allow_custom_domain',
        'price_ngn_monthly',
        'price_ngn_annual',
        'is_public',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_custom_domain' => 'boolean',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'ram_mb' => 'integer',
            'disk_gb' => 'integer',
            'max_apps' => 'integer',
            'max_databases' => 'integer',
            'price_ngn_monthly' => 'integer',
            'price_ngn_annual' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ClientInvitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClientSubscription::class);
    }

    public function subTeams(): HasMany
    {
        return $this->hasMany(SubTeam::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
