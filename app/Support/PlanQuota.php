<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Team;

class PlanQuota
{
    /**
     * In our plan schema, a quota of 0 means "unlimited" (see PlanSeeder + PRD §11).
     */
    public const UNLIMITED = 0;

    public static function planFor(Team $team): Plan
    {
        return $team->subscription?->plan ?? Plan::firstWhere('code', 'free') ?? new Plan([
            'code' => 'free',
            'max_servers' => 1,
            'max_apps' => 3,
            'max_databases' => 1,
            'max_team_members' => 1,
        ]);
    }

    public static function serverLimit(Team $team): int
    {
        return self::limit($team, 'max_servers');
    }

    public static function appLimit(Team $team): int
    {
        return self::limit($team, 'max_apps');
    }

    public static function databaseLimit(Team $team): int
    {
        return self::limit($team, 'max_databases');
    }

    public static function teamMemberLimit(Team $team): int
    {
        return self::limit($team, 'max_team_members');
    }

    public static function canAddServer(Team $team): bool
    {
        return self::canAddOne($team, self::serverLimit($team), $team->servers()->count());
    }

    public static function canAddApp(Team $team): bool
    {
        return self::canAddOne($team, self::appLimit($team), self::appCount($team));
    }

    public static function canAddDatabase(Team $team): bool
    {
        return self::canAddOne($team, self::databaseLimit($team), self::databaseCount($team));
    }

    public static function canAddTeamMember(Team $team): bool
    {
        return self::canAddOne($team, self::teamMemberLimit($team), $team->members()->count());
    }

    /**
     * @return array{used: int, limit: int, unlimited: bool, percent: ?int}
     */
    public static function serversUsage(Team $team): array
    {
        return self::usage(self::serverLimit($team), $team->servers()->count());
    }

    public static function appsUsage(Team $team): array
    {
        return self::usage(self::appLimit($team), self::appCount($team));
    }

    public static function databasesUsage(Team $team): array
    {
        return self::usage(self::databaseLimit($team), self::databaseCount($team));
    }

    public static function teamMembersUsage(Team $team): array
    {
        return self::usage(self::teamMemberLimit($team), $team->members()->count());
    }

    // ---

    private static function limit(Team $team, string $column): int
    {
        // Root team (id=0) and self-hosted instances are unrestricted.
        if ($team->id === 0 || ! isCloud()) {
            return self::UNLIMITED;
        }

        // Manual override on Team — used by support to grant extra capacity.
        if ($column === 'max_servers' && $team->custom_server_limit !== null && (int) $team->custom_server_limit > 0) {
            return (int) $team->custom_server_limit;
        }

        return (int) self::planFor($team)->{$column};
    }

    private static function canAddOne(Team $team, int $limit, int $current): bool
    {
        if ($limit === self::UNLIMITED) {
            return true;
        }

        return $current < $limit;
    }

    /**
     * @return array{used: int, limit: int, unlimited: bool, percent: ?int}
     */
    private static function usage(int $limit, int $used): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $limit === self::UNLIMITED,
            'percent' => $limit === self::UNLIMITED ? null : (int) round(min(100, ($used / max($limit, 1)) * 100)),
        ];
    }

    private static function appCount(Team $team): int
    {
        return \App\Models\Application::whereRelation('environment.project.team', 'id', $team->id)->count();
    }

    private static function databaseCount(Team $team): int
    {
        $models = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneClickhouse::class,
            \App\Models\StandaloneDragonfly::class,
        ];

        $total = 0;
        foreach ($models as $model) {
            $total += $model::whereRelation('environment.project.team', 'id', $team->id)->count();
        }

        return $total;
    }
}
