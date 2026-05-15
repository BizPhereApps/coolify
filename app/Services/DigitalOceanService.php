<?php

namespace App\Services;

use App\Exceptions\RateLimitException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal DigitalOcean API v2 wrapper for Nolbase BYO-cloud provisioning.
 * Mirrors the shape of HetznerService so the Livewire layer can target either.
 *
 * Docs: https://docs.digitalocean.com/reference/api/api-reference/
 */
class DigitalOceanService
{
    private string $baseUrl = 'https://api.digitalocean.com/v2';

    public function __construct(private readonly string $token) {}

    public function getRegions(): array
    {
        return $this->requestPaginated('get', '/regions', 'regions');
    }

    public function getSizes(): array
    {
        return $this->requestPaginated('get', '/sizes', 'sizes');
    }

    public function getImages(): array
    {
        // distribution-only public images (no apps/snapshots) — keeps the list small
        return $this->requestPaginated('get', '/images?type=distribution&per_page=200', 'images');
    }

    public function getSshKeys(): array
    {
        return $this->requestPaginated('get', '/account/keys', 'ssh_keys');
    }

    public function uploadSshKey(string $name, string $publicKey): array
    {
        return $this->request('post', '/account/keys', [
            'name' => $name,
            'public_key' => $publicKey,
        ])['ssh_key'];
    }

    public function createDroplet(array $params): array
    {
        return $this->request('post', '/droplets', $params)['droplet'];
    }

    public function getDroplet(int $id): array
    {
        return $this->request('get', "/droplets/{$id}")['droplet'];
    }

    public function deleteDroplet(int $id): void
    {
        $this->request('delete', "/droplets/{$id}");
    }

    public function findPublicIpv4(array $droplet): ?string
    {
        foreach (data_get($droplet, 'networks.v4', []) as $net) {
            if (data_get($net, 'type') === 'public') {
                return data_get($net, 'ip_address');
            }
        }

        return null;
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $response = $this->client()->{$method}($this->baseUrl.$endpoint, $data);

        return $this->parse($response);
    }

    private function requestPaginated(string $method, string $endpoint, string $key, array $data = []): array
    {
        $items = [];
        $url = $this->baseUrl.$endpoint;
        for ($page = 1; $page <= 20; $page++) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $response = $this->client()->{$method}($url.$sep."page={$page}&per_page=200", $data);
            $body = $this->parse($response);
            $batch = data_get($body, $key, []);
            $items = array_merge($items, $batch);
            if (! data_get($body, 'links.pages.next')) {
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
                'DigitalOcean rate limit exceeded.',
                (int) ($response->header('Retry-After') ?? 0),
            );
        }
        if (! $response->successful()) {
            $message = data_get($response->json(), 'message', $response->body() ?: 'DigitalOcean request failed.');
            throw new RuntimeException("DigitalOcean API error ({$response->status()}): {$message}");
        }

        return $response->json() ?? [];
    }
}
