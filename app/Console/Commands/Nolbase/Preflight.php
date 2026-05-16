<?php

namespace App\Console\Commands\Nolbase;

use App\Models\NolbaseAdmin;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Runs every automated check from nolbase/LAUNCH-CHECKLIST.md sections 4-7.
 * Each check returns PASS, WARN, or FAIL. Exit code reflects the worst
 * outcome:
 *   0 — all green (warnings allowed unless --strict)
 *   1 — at least one FAIL (or any WARN under --strict)
 *
 * Use --json for machine-readable output (CI, monitoring).
 *
 * This does NOT replace the operational steps in §1, §2, §3, §8 —
 * those involve external systems (Paystack dashboard, ZeptoMail
 * verification, lawyer conversation) that can't be automated.
 */
class Preflight extends Command
{
    protected $signature = 'nolbase:preflight
        {--strict : Treat WARN as failure}
        {--json : Output machine-readable JSON instead of human-formatted}';

    protected $description = 'Run automated pre-launch checks against the current environment.';

    private const STATUS_PASS = 'PASS';

    private const STATUS_WARN = 'WARN';

    private const STATUS_FAIL = 'FAIL';

    /** @var array<int, array{name:string, status:string, message:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkAppEnvironment();
        $this->checkAppKey();
        $this->checkDatabaseConnection();
        $this->checkCloudMode();
        $this->checkDebugDisabled();
        $this->checkTelescopeDisabled();
        $this->checkAgplSourceRepo();
        $this->checkLegalSourceRoute();
        $this->checkPaystackSecrets();
        $this->checkPaystackWebhookRoute();
        $this->checkMailConfig();
        $this->checkPlansSeeded();
        $this->checkPlansHavePaystackCodes();
        $this->checkSuperAdminExists();
        $this->checkMarketplaceFeeConfigured();

        return $this->reportAndExit();
    }

    private function checkAppEnvironment(): void
    {
        $env = config('app.env');
        if ($env === 'production') {
            $this->passCheck('app.env', 'production');
        } elseif ($env === 'staging') {
            $this->warnCheck('app.env', "is '{$env}' — flip to 'production' for live launch");
        } else {
            $this->failCheck('app.env', "is '{$env}' — must be 'production' or 'staging' for a real deploy");
        }
    }

    private function checkAppKey(): void
    {
        $key = config('app.key');
        if (! $key) {
            $this->failCheck('APP_KEY', 'is empty — run `php artisan key:generate`');
        } else {
            $this->passCheck('APP_KEY', 'set');
        }
    }

    private function checkDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
            $this->passCheck('database', 'connection healthy ('.config('database.default').')');
        } catch (Throwable $e) {
            $this->failCheck('database', 'cannot connect: '.$e->getMessage());
        }
    }

    private function checkCloudMode(): void
    {
        $selfHosted = config('constants.coolify.self_hosted', true);
        if ($selfHosted === false) {
            $this->passCheck('cloud mode', 'isCloud() returns true (SELF_HOSTED=false)');
        } else {
            $this->failCheck('cloud mode', 'SELF_HOSTED is not false — Nolbase requires multi-tenant mode');
        }
    }

    private function checkDebugDisabled(): void
    {
        $debug = (bool) config('app.debug');
        $env = config('app.env');
        if ($env === 'production' && $debug) {
            $this->failCheck('APP_DEBUG', 'is true in production — leaks stack traces');
        } else {
            $this->passCheck('APP_DEBUG', $debug ? 'enabled (OK in non-prod)' : 'disabled');
        }
    }

    private function checkTelescopeDisabled(): void
    {
        $enabled = env('TELESCOPE_ENABLED', false);
        $env = config('app.env');
        if ($env === 'production' && $enabled) {
            $this->failCheck('Telescope', 'TELESCOPE_ENABLED=true in production — leaks query payloads');
        } else {
            $this->passCheck('Telescope', $enabled ? 'enabled (OK in non-prod)' : 'disabled');
        }
    }

    private function checkAgplSourceRepo(): void
    {
        $url = config('nolbase.source_repo_url');
        if (! $url || str_contains((string) $url, 'REPLACE-ME')) {
            $this->failCheck('AGPL source URL', "NOLBASE_SOURCE_REPO_URL still placeholder: {$url}");

            return;
        }
        if (! preg_match('#^https?://#i', (string) $url)) {
            $this->failCheck('AGPL source URL', "is not a URL: {$url}");

            return;
        }
        $this->passCheck('AGPL source URL', $url);
    }

    private function checkLegalSourceRoute(): void
    {
        try {
            $route = Route::getRoutes()->getByName('legal.source');
            if (! $route) {
                $this->failCheck('/legal/source route', 'route name `legal.source` not registered');

                return;
            }
            $this->passCheck('/legal/source route', $route->uri());
        } catch (Throwable $e) {
            $this->failCheck('/legal/source route', $e->getMessage());
        }
    }

    private function checkPaystackSecrets(): void
    {
        $secret = (string) config('paystack.secret_key');
        $webhookSecret = (string) config('paystack.webhook_secret');
        $public = (string) config('paystack.public_key');
        $env = config('app.env');

        if (! $secret) {
            $this->failCheck('PAYSTACK_SECRET_KEY', 'is empty');
        } elseif ($env === 'production' && str_starts_with($secret, 'sk_test_')) {
            $this->failCheck('PAYSTACK_SECRET_KEY', 'is a TEST key in production');
        } else {
            $this->passCheck('PAYSTACK_SECRET_KEY', str_starts_with($secret, 'sk_live_') ? 'live key' : 'set');
        }

        if (! $webhookSecret) {
            $this->failCheck('PAYSTACK_WEBHOOK_SECRET', 'is empty — webhook signature verification will compare against the API secret, which weakens defense-in-depth');
        } else {
            $this->passCheck('PAYSTACK_WEBHOOK_SECRET', 'set');
        }

        if (! $public) {
            $this->warnCheck('PAYSTACK_PUBLIC_KEY', 'is empty — checkout pages may break');
        } else {
            $this->passCheck('PAYSTACK_PUBLIC_KEY', 'set');
        }
    }

    private function checkPaystackWebhookRoute(): void
    {
        $route = Route::getRoutes()->getByName('paystack.webhook');
        if (! $route) {
            $this->failCheck('paystack webhook route', 'route name `paystack.webhook` not registered');

            return;
        }
        $this->passCheck('paystack webhook route', '/'.$route->uri());
    }

    private function checkMailConfig(): void
    {
        $from = config('mail.from.address');
        if (! $from || str_contains((string) $from, 'example.com')) {
            $this->failCheck('MAIL_FROM_ADDRESS', "is placeholder or empty: {$from}");
        } else {
            $this->passCheck('MAIL_FROM_ADDRESS', $from);
        }

        $mailer = config('mail.default');
        if ($mailer === 'log' || $mailer === 'array') {
            if (config('app.env') === 'production') {
                $this->failCheck('MAIL_MAILER', "is '{$mailer}' in production — outbound mail won't actually send");
            } else {
                $this->passCheck('MAIL_MAILER', "'{$mailer}' (OK in non-prod)");
            }
        } else {
            $this->passCheck('MAIL_MAILER', $mailer);
        }
    }

    private function checkPlansSeeded(): void
    {
        try {
            $codes = Plan::query()->pluck('code')->all();
            $missing = array_diff(['free', 'pro', 'business'], $codes);
            if ($missing) {
                $this->failCheck('plans seeded', 'missing: '.implode(', ', $missing).' — run `php artisan db:seed --class=PlanSeeder`');
            } else {
                $this->passCheck('plans seeded', count($codes).' plans present');
            }
        } catch (Throwable $e) {
            $this->failCheck('plans seeded', $e->getMessage());
        }
    }

    private function checkPlansHavePaystackCodes(): void
    {
        try {
            foreach (['pro', 'business'] as $code) {
                $plan = Plan::query()->where('code', $code)->first();
                if (! $plan) {
                    continue; // already reported by checkPlansSeeded
                }
                if (! $plan->paystack_plan_code_monthly || ! $plan->paystack_plan_code_annual) {
                    $this->failCheck(
                        "plan {$code} paystack codes",
                        'missing paystack_plan_code_monthly/_annual — set them via Paystack dashboard then update the plans row'
                    );
                } else {
                    $this->passCheck("plan {$code} paystack codes", 'monthly+annual set');
                }
            }
        } catch (Throwable $e) {
            $this->failCheck('plan paystack codes', $e->getMessage());
        }
    }

    private function checkSuperAdminExists(): void
    {
        try {
            $count = NolbaseAdmin::query()
                ->where('role', NolbaseAdmin::ROLE_SUPERADMIN)
                ->count();
            if ($count === 0) {
                $this->failCheck('superadmin', 'no NolbaseAdmin with role=superadmin — run `php artisan nolbase:admin:create`');
            } else {
                $this->passCheck('superadmin', "{$count} present");
            }
        } catch (Throwable $e) {
            $this->failCheck('superadmin', $e->getMessage());
        }
    }

    private function checkMarketplaceFeeConfigured(): void
    {
        $fee = (float) config('paystack.marketplace_fee_pct');
        if ($fee <= 0 || $fee > 50) {
            $this->warnCheck('marketplace fee', "is {$fee}% — verify NOLBASE_MARKETPLACE_FEE_PCT is intentional");
        } else {
            $this->passCheck('marketplace fee', "{$fee}%");
        }
    }

    private function passCheck(string $name, string $msg): void
    {
        $this->results[] = ['name' => $name, 'status' => self::STATUS_PASS, 'message' => $msg];
    }

    private function warnCheck(string $name, string $msg): void
    {
        $this->results[] = ['name' => $name, 'status' => self::STATUS_WARN, 'message' => $msg];
    }

    private function failCheck(string $name, string $msg): void
    {
        $this->results[] = ['name' => $name, 'status' => self::STATUS_FAIL, 'message' => $msg];
    }

    private function reportAndExit(): int
    {
        $passes = $warns = $fails = 0;
        foreach ($this->results as $r) {
            match ($r['status']) {
                self::STATUS_PASS => $passes++,
                self::STATUS_WARN => $warns++,
                self::STATUS_FAIL => $fails++,
            };
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => ['pass' => $passes, 'warn' => $warns, 'fail' => $fails],
                'checks' => $this->results,
            ], JSON_PRETTY_PRINT));
        } else {
            foreach ($this->results as $r) {
                $tag = match ($r['status']) {
                    self::STATUS_PASS => '<fg=green>PASS</>',
                    self::STATUS_WARN => '<fg=yellow>WARN</>',
                    self::STATUS_FAIL => '<fg=red>FAIL</>',
                };
                $this->line("  {$tag}  {$r['name']} — {$r['message']}");
            }
            $this->newLine();
            $this->line("  <fg=green>{$passes} pass</> · <fg=yellow>{$warns} warn</> · <fg=red>{$fails} fail</>");
        }

        if ($fails > 0) {
            return self::FAILURE;
        }
        if ($warns > 0 && $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
