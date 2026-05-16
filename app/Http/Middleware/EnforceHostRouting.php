<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Splits Nolbase across two hosts on one Laravel codebase:
 *   - config('nolbase.marketing_host')  → only marketing/legal/pricing
 *   - config('nolbase.app_host')        → only the tenant SaaS app
 *
 * When a request lands on the wrong host, redirect (301 permanent on GET,
 * 307 on others to preserve method/body) to the correct host preserving
 * the path + query string.
 *
 * No-op when:
 *   - either config is empty (dev mode, single host)
 *   - both configs resolve to the same host (also dev)
 *
 * Marketing-host-allowed paths are matched by an allowlist of prefixes.
 * Everything else on the marketing host gets bounced to the app host.
 */
class EnforceHostRouting
{
    /**
     * Path prefixes that belong to the MARKETING host. Everything not in
     * this list is treated as an app-host path. Order doesn't matter.
     */
    private const MARKETING_PATHS = [
        '/',
        '/pricing',
        '/legal',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $marketingHost = strtolower((string) config('nolbase.marketing_host'));
        $appHost = strtolower((string) config('nolbase.app_host'));

        if (! $marketingHost || ! $appHost || $marketingHost === $appHost) {
            return $next($request);
        }

        $currentHost = strtolower($request->getHost());
        $path = '/'.ltrim($request->path(), '/');
        $isMarketingPath = $this->isMarketingPath($path);

        if ($currentHost === $marketingHost && ! $isMarketingPath) {
            return $this->redirectToHost($request, $appHost);
        }

        if ($currentHost === $appHost && $isMarketingPath && $path !== '/') {
            // App host at "/" stays on app host — it's the auth gateway.
            // But pricing/legal explicitly belong on the marketing site.
            return $this->redirectToHost($request, $marketingHost);
        }

        return $next($request);
    }

    private function isMarketingPath(string $path): bool
    {
        foreach (self::MARKETING_PATHS as $prefix) {
            if ($prefix === '/') {
                if ($path === '/') {
                    return true;
                }

                continue;
            }
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function redirectToHost(Request $request, string $targetHost): Response
    {
        $scheme = $request->isSecure() ? 'https' : 'http';
        $target = $scheme.'://'.$targetHost.'/'.ltrim($request->getRequestUri(), '/');

        // 301 is cacheable + SEO-friendly for GET; 307 preserves method/body
        // for POST/PUT/etc. so we don't silently drop a form submission.
        $status = $request->isMethod('GET') ? 301 : 307;

        return redirect()->away($target, $status);
    }
}
