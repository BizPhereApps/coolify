<?php

namespace App\Services;

use App\Exceptions\RateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Vultr API v2 wrapper for Nolbase BYO-cloud provisioning.
 * Mirrors the shape of DigitalOceanService.
 *
 * Docs: https://www.vultr.com/api/
 */
class VultrService
{
    private string $baseUrl = 'https://api.vultr.com/v2';

    public function __construct(private readonly string $token) {}

    public function getRegions(): array
    {
        return $this->requestPaginated('get', '/regions', 'regions');
    }

    public function getPlans(): array
    {
        return $this->requestPaginated('get', '/plans', 'plans');
    }

    /**
     * Operating systems available for new instances.
     */
    public function getOperatingSystems(): array
    {
        return $this->requestPaginated('get', '/os', 'os');
    }

    public function getSshKeys(): array
    {
        return $this->requestPaginated('get', '/ssh-keys', 'ssh_keys');
    }

    public function uploadSshKey(string $name, string $publicKey): array
    {
        return $this->request('post', '/ssh-keys', [
            'name' => $name,
            'ssh_key' => $publicKey,
        ])['ssh_key'];
    }

    public function createInstance(array $params): array
    {
        return $this->request('post', '/instances', $params)['instance'];
    }

    public function getInstance(string $id): array
    {
        return $this->request('get', "/instances/{$id}")['instance'];
    }

    public function deleteInstance(string $id): void
    {
        $this->request('delete', "/instances/{$id}");
    }

    public function findPublicIpv4(array $instance): ?string
    {
        $ip = data_get($instance, 'main_ip');

        return $ip && $ip !== '0.0.0.0' ? $ip : null;
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $response = $this->client()->{$method}($this->baseUrl.$endpoint, $data);

        return $this->parse($response);
    }

    private function requestPaginated(string $method, string $endpoint, string $key, array $data = []): array
    {
        $items = [];
        $cursor = null;
        for ($i = 0; $i < 20; $i++) {
            $url = $this->baseUrl.$endpoint.'?per_page=200';
            if ($cursor) {
                $url .= '&cursor='.rawurlencode($cursor);
            }
            $response = $this->client()->{$method}($url, $data);
            $body = $this->parse($response);
            $items = array_merge($items, data_get($body, $key, []));
            $cursor = data_get($body, 'meta.links.next');
            if (! $cursor) {
                break;
            }
        }

        return $items;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, function (int $attempt, \Throwable $exception) {
                if ($exception instanceof RequestException) {
                    $r = $exception->response;
                    if ($r && $r->status() === 429) {
                        $retryAfter = (int) ($r->header('Retry-After') ?? 5);

                        return min($retryAfter, 60) * 1000;
                    }
                }

                return $attempt * 200;
            }, throw: false);
    }

    private function parse(Response $response): array
    {
        if ($response->status() === 429) {
            throw new RateLimitException(
                'Vultr rate limit exceeded.',
                (int) ($response->header('Retry-After') ?? 0),
            );
        }
        if (! $response->successful()) {
            // Vultr puts errors under various keys; try the common ones.
            $body = $response->json();
            $message = data_get($body, 'error') ?? data_get($body, 'message') ?? ($response->body() ?: 'Vultr request failed.');
            throw new RuntimeException("Vultr API error ({$response->status()}): {$message}");
        }

        return $response->json() ?? [];
    }
}
