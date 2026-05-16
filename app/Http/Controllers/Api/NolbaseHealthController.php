<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Component-level health endpoint at /api/nolbase/health.
 *
 * Unlike the plain /api/health (which just returns "OK" for trivial
 * load-balancer liveness checks), this endpoint runs real probes against
 * the systems Nolbase depends on. Use it from:
 *   - kubernetes readinessProbe / blackbox exporter
 *   - uptime monitors (UptimeRobot, BetterStack, Healthchecks.io)
 *   - LAUNCH-CHECKLIST.md §5.1 post-deploy smoke checks
 *
 * Critical checks (any failure → 503): db, redis.
 * Optional checks (only run with ?deep=1, included for visibility but
 * do not affect HTTP status): paystack.
 *
 * Output shape:
 *   {
 *     "status": "healthy" | "degraded",
 *     "checked_at": "2026-05-16T12:34:56Z",
 *     "version": { "commit": "abc123" },
 *     "checks": {
 *       "db":       { "ok": true, "latency_ms": 3 },
 *       "redis":    { "ok": true, "latency_ms": 1 },
 *       "paystack": { "ok": true, "latency_ms": 240 }   // only if deep=1
 *     }
 *   }
 */
class NolbaseHealthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $checks = [
            'db' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $criticalDown = collect($checks)->contains(fn ($c) => $c['ok'] === false);

        if ($request->boolean('deep')) {
            $checks['paystack'] = $this->checkPaystack();
            // Paystack reachability does NOT mark Nolbase degraded — a
            // Paystack outage means new sign-ups stall, but the running
            // service is otherwise fine. Webhooks queue.
        }

        return response()->json([
            'status' => $criticalDown ? 'degraded' : 'healthy',
            'checked_at' => now()->toIso8601String(),
            'version' => ['commit' => $this->commitSha()],
            'checks' => $checks,
        ], $criticalDown ? 503 : 200);
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->select('select 1');

            return ['ok' => true, 'latency_ms' => $this->elapsedMs($start)];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => $this->elapsedMs($start), 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            $reply = Redis::ping();
            $ok = $reply === true || $reply === 'PONG' || $reply === '+PONG';

            return ['ok' => $ok, 'latency_ms' => $this->elapsedMs($start)];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => $this->elapsedMs($start), 'error' => $e->getMessage()];
        }
    }

    /**
     * Lightweight Paystack reachability probe. Hits /bank (publicly cacheable,
     * no rate-limit pressure), with a short timeout so a Paystack outage can't
     * make our health endpoint slow.
     */
    private function checkPaystack(): array
    {
        $start = microtime(true);
        try {
            $secret = config('paystack.secret_key');
            if (! $secret) {
                return ['ok' => false, 'latency_ms' => 0, 'error' => 'PAYSTACK_SECRET_KEY not set'];
            }
            $response = Http::baseUrl(config('paystack.base_url', 'https://api.paystack.co'))
                ->withToken($secret)
                ->timeout(5)
                ->get('/bank', ['country' => 'nigeria', 'perPage' => 1]);

            return [
                'ok' => $response->successful(),
                'latency_ms' => $this->elapsedMs($start),
                'http_status' => $response->status(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'latency_ms' => $this->elapsedMs($start), 'error' => $e->getMessage()];
        }
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * Best-effort commit SHA. Read from .git/HEAD chain when available so
     * deploys that copy the .git tree expose the running version. Falls back
     * to env('GIT_COMMIT_SHA') for image-based builds that bake it in.
     */
    private function commitSha(): ?string
    {
        $envSha = env('GIT_COMMIT_SHA');
        if ($envSha) {
            return substr($envSha, 0, 12);
        }
        try {
            $head = @file_get_contents(base_path('.git/HEAD'));
            if (! is_string($head)) {
                return null;
            }
            $head = trim($head);
            if (str_starts_with($head, 'ref: ')) {
                $ref = substr($head, 5);
                $sha = @file_get_contents(base_path('.git/'.$ref));

                return is_string($sha) ? substr(trim($sha), 0, 12) : null;
            }

            return substr($head, 0, 12);
        } catch (Throwable) {
            return null;
        }
    }
}
