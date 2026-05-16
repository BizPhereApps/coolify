<?php

namespace App\Actions\Nolbase;

use App\Models\Application;
use App\Rules\ValidNolbaseSubdomain;
use App\Services\NolbaseDnsService;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterNolbaseSubdomain
{
    use AsAction;

    /**
     * Register a nolbase.app subdomain for an application.
     *
     * - Validates slug format and reserved words
     * - Checks DB uniqueness
     * - Creates Cloudflare A record pointing to the app's server IP
     * - Saves slug + record ID on the application
     * - Appends the nolbase.app FQDN to the application's fqdn field so Traefik picks it up on next deploy
     *
     * @return array{fqdn: string, record_id: string}
     */
    public function handle(Application $application, string $slug): array
    {
        $slug = strtolower(trim($slug));

        Validator::make(['subdomain' => $slug], [
            'subdomain' => ['required', 'string', new ValidNolbaseSubdomain],
        ])->validate();

        // DB uniqueness check (case-insensitive)
        $taken = Application::query()
            ->where('nolbase_subdomain', $slug)
            ->where('id', '!=', $application->id)
            ->exists();

        if ($taken) {
            throw new \RuntimeException("The subdomain \"{$slug}\" is already taken.");
        }

        $dns = app(NolbaseDnsService::class);

        $serverIp = $application->destination->server->ip;
        if (! $serverIp) {
            throw new \RuntimeException('The application\'s server does not have an IP address configured.');
        }

        // Cloudflare double-check (catches slugs taken by deleted apps whose DB rows were purged)
        if ($dns->isConfigured() && $dns->recordExists($slug)) {
            throw new \RuntimeException("The subdomain \"{$slug}\" is already in use.");
        }

        $recordId = null;
        if ($dns->isConfigured()) {
            $recordId = $dns->createARecord($slug, $serverIp);
        }

        $nolbaseFqdn = $dns->fqdn($slug);

        // Deregister any previous subdomain before registering the new one
        if ($application->nolbase_subdomain && $application->nolbase_subdomain !== $slug) {
            DeregisterNolbaseSubdomain::run($application);
        }

        // Append to the application's fqdn so Traefik picks it up on next deploy
        $existingFqdn = $application->fqdn ? rtrim($application->fqdn, ',') : '';
        $fqdns = collect(explode(',', $existingFqdn))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->reject(fn ($f) => str_contains($f, config('nolbase.app_domain', 'nolbase.app')))
            ->push($nolbaseFqdn)
            ->implode(',');

        $application->forceFill([
            'nolbase_subdomain' => $slug,
            'nolbase_dns_record_id' => $recordId,
            'fqdn' => $fqdns,
        ])->save();

        return ['fqdn' => $nolbaseFqdn, 'record_id' => $recordId ?? ''];
    }
}
