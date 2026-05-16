<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NolbaseDnsService
{
    private string $apiToken;

    private string $zoneId;

    private string $appDomain;

    public function __construct()
    {
        $this->apiToken = (string) config('nolbase.cloudflare_api_token');
        $this->zoneId = (string) config('nolbase.cloudflare_zone_id');
        $this->appDomain = (string) config('nolbase.app_domain', 'nolbase.app');
    }

    public function isConfigured(): bool
    {
        return $this->apiToken !== '' && $this->zoneId !== '';
    }

    /**
     * Create an A record for {slug}.nolbase.app pointing to $ip.
     * Returns the Cloudflare DNS record ID.
     */
    public function createARecord(string $slug, string $ip): string
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(10)
            ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records", [
                'type' => 'A',
                'name' => "{$slug}.{$this->appDomain}",
                'content' => $ip,
                'ttl' => 60,
                'proxied' => false,
            ]);

        if (! $response->successful() || ! $response->json('success')) {
            $errors = implode(', ', array_column($response->json('errors', []), 'message'));
            throw new \RuntimeException("Cloudflare DNS record creation failed: {$errors}");
        }

        return $response->json('result.id');
    }

    /**
     * Delete a DNS record by its Cloudflare record ID.
     * Silently ignores 404 (already gone).
     */
    public function deleteRecord(string $recordId): void
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(10)
            ->delete("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records/{$recordId}");

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful() || ! $response->json('success')) {
            $errors = implode(', ', array_column($response->json('errors', []), 'message'));
            Log::warning("Cloudflare DNS record deletion failed for {$recordId}: {$errors}");
        }
    }

    /**
     * Check whether a slug is already taken in Cloudflare's zone.
     */
    public function recordExists(string $slug): bool
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(10)
            ->get("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records", [
                'name' => "{$slug}.{$this->appDomain}",
                'type' => 'A',
            ]);

        if (! $response->successful()) {
            return false;
        }

        return count($response->json('result', [])) > 0;
    }

    public function fqdn(string $slug): string
    {
        return "https://{$slug}.{$this->appDomain}";
    }
}
