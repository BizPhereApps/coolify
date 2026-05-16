<?php

namespace App\Actions\Nolbase;

use App\Models\Application;
use App\Services\NolbaseDnsService;
use Lorisleiva\Actions\Concerns\AsAction;

class DeregisterNolbaseSubdomain
{
    use AsAction;

    /**
     * Remove the nolbase.app subdomain from an application.
     *
     * - Deletes the Cloudflare DNS A record
     * - Strips the nolbase.app FQDN from the application's fqdn field
     * - Nulls out nolbase_subdomain + nolbase_dns_record_id
     *
     * Safe to call on an app that has no subdomain (no-op).
     */
    public function handle(Application $application): void
    {
        if (! $application->nolbase_subdomain) {
            return;
        }

        $dns = app(NolbaseDnsService::class);
        $appDomain = config('nolbase.app_domain', 'nolbase.app');

        // Delete Cloudflare record
        if ($dns->isConfigured() && $application->nolbase_dns_record_id) {
            $dns->deleteRecord($application->nolbase_dns_record_id);
        }

        // Strip the nolbase.app FQDN from the fqdn field
        $fqdns = collect(explode(',', (string) $application->fqdn))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->reject(fn ($f) => str_contains($f, $appDomain))
            ->implode(',');

        $application->forceFill([
            'nolbase_subdomain' => null,
            'nolbase_dns_record_id' => null,
            'fqdn' => $fqdns ?: null,
        ])->save();
    }
}
