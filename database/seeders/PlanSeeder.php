<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'Free',
                'description' => 'Try Nolbase with your own server. 1 server, 3 apps, 1 database.',
                'price_ngn_monthly' => 0,
                'price_ngn_annual' => null,
                'max_servers' => 1,
                'max_apps' => 3,
                'max_databases' => 1,
                'max_team_members' => 1,
                'features' => ['byo_server'],
                'is_public' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'For solo devs and small teams. 5 servers, unlimited apps and databases.',
                'price_ngn_monthly' => 15_000,
                'price_ngn_annual' => 150_000,
                'max_servers' => 5,
                'max_apps' => 0,
                'max_databases' => 0,
                'max_team_members' => 5,
                'features' => [
                    'byo_server',
                    'byo_cloud',
                    'preview_environments',
                    'reseller',
                    'email_support_48h',
                ],
                'is_public' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'For agencies and growing teams. Unlimited everything, priority support.',
                'price_ngn_monthly' => 50_000,
                'price_ngn_annual' => 500_000,
                'max_servers' => 0,
                'max_apps' => 0,
                'max_databases' => 0,
                'max_team_members' => 0,
                'features' => [
                    'byo_server',
                    'byo_cloud',
                    'nolbase_managed',
                    'preview_environments',
                    'reseller',
                    'reseller_unlimited',
                    'priority_deploy_queue',
                    'audit_log_export',
                    'email_support_24h',
                ],
                'is_public' => true,
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
