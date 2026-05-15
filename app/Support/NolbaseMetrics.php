<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Support\Carbon;

class NolbaseMetrics
{
    /**
     * Monthly Recurring Revenue in NGN. Annual subscriptions are amortized to
     * a monthly figure (price_ngn_annual / 12).
     */
    public static function mrr(): int
    {
        $monthly = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('period', Subscription::PERIOD_MONTHLY)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->sum('plans.price_ngn_monthly');

        $annualToMonthly = (int) Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('period', Subscription::PERIOD_ANNUAL)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('coalesce(sum(plans.price_ngn_annual), 0) / 12 as amount')
            ->value('amount');

        return (int) $monthly + $annualToMonthly;
    }

    /**
     * Annual Run Rate (MRR × 12), useful for headline figures.
     */
    public static function arr(): int
    {
        return self::mrr() * 12;
    }

    public static function activeSubscriptions(): int
    {
        return Subscription::where('status', Subscription::STATUS_ACTIVE)->count();
    }

    public static function trialingSubscriptions(): int
    {
        return Subscription::where('status', Subscription::STATUS_TRIALING)->count();
    }

    public static function pastDueSubscriptions(): int
    {
        return Subscription::where('status', Subscription::STATUS_PAST_DUE)->count();
    }

    public static function totalTeams(): int
    {
        return Team::query()->whereNotIn('id', [0])->count();
    }

    public static function newTeamsLast30Days(): int
    {
        return Team::query()->whereNotIn('id', [0])->where('created_at', '>=', now()->subDays(30))->count();
    }

    /**
     * @return array<string, int> plan_code => count
     */
    public static function planDistribution(): array
    {
        return Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', Subscription::STATUS_ACTIVE)
            ->selectRaw('plans.code, count(*) as total')
            ->groupBy('plans.code')
            ->pluck('total', 'code')
            ->toArray();
    }

    public static function asOf(): Carbon
    {
        return now();
    }
}
