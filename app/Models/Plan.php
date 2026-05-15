<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_ngn_monthly',
        'price_ngn_annual',
        'paystack_plan_code_monthly',
        'paystack_plan_code_annual',
        'max_servers',
        'max_apps',
        'max_databases',
        'max_team_members',
        'features',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
            'price_ngn_monthly' => 'integer',
            'price_ngn_annual' => 'integer',
            'max_servers' => 'integer',
            'max_apps' => 'integer',
            'max_databases' => 'integer',
            'max_team_members' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public static function free(): self
    {
        return self::where('code', 'free')->firstOrFail();
    }

    public static function pro(): self
    {
        return self::where('code', 'pro')->firstOrFail();
    }

    public static function business(): self
    {
        return self::where('code', 'business')->firstOrFail();
    }
}
