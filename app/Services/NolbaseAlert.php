<?php

namespace App\Services;

use App\Models\NolbaseSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator-facing alert channel. Distinct from Coolify's per-team
 * notification stack (Slack/Discord/Telegram), which is for tenants;
 * this one is for Nolbase ops — failures, abuse, money-loss events.
 *
 * Reads NOLBASE_ALERTS_WEBHOOK_URL from env or `nolbase_alerts_webhook_url`
 * from NolbaseSetting. Payload is JSON shaped for Slack incoming webhooks,
 * which Discord also accepts via /slack suffix. When the URL is empty,
 * the call is a no-op — making local/test environments silent.
 *
 * Calls are fire-and-forget — network failures are logged at warning level
 * but never re-thrown. An alert system that takes down the thing it's
 * alerting about is worse than no alert system.
 */
class NolbaseAlert
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_WARN = 'warn';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * @param  array<string, scalar|null>  $context  Key/value pairs rendered as a Slack attachment field block. Keep small — webhook payloads have a 3KB practical limit.
     */
    public static function send(string $title, string $message, string $level = self::LEVEL_INFO, array $context = []): void
    {
        $url = self::webhookUrl();
        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)
                ->retry(1, 200, throw: false)
                ->post($url, self::buildPayload($title, $message, $level, $context));
        } catch (Throwable $e) {
            Log::warning('NolbaseAlert dispatch failed', [
                'title' => $title, 'error' => $e->getMessage(),
            ]);
        }
    }

    private static function webhookUrl(): ?string
    {
        // Prefer DB-backed setting so an admin can rotate the URL without a redeploy.
        try {
            $fromDb = NolbaseSetting::read('nolbase_alerts_webhook_url');
            if ($fromDb) {
                return $fromDb;
            }
        } catch (Throwable) {
            // Migration may not have run yet during boot.
        }

        return env('NOLBASE_ALERTS_WEBHOOK_URL');
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private static function buildPayload(string $title, string $message, string $level, array $context): array
    {
        $color = match ($level) {
            self::LEVEL_CRITICAL => '#cc1f1a',
            self::LEVEL_WARN => '#f0b400',
            default => '#2eb886',
        };
        $brand = config('nolbase.brand_name', 'Nolbase');
        $env = config('app.env');

        return [
            'text' => "[{$brand}/{$env}] {$title}",
            'attachments' => [[
                'color' => $color,
                'title' => $title,
                'text' => $message,
                'fields' => collect($context)
                    ->map(fn ($v, $k) => [
                        'title' => (string) $k,
                        'value' => (string) ($v ?? '—'),
                        'short' => mb_strlen((string) ($v ?? '')) < 30,
                    ])
                    ->values()
                    ->all(),
                'ts' => time(),
            ]],
        ];
    }
}
