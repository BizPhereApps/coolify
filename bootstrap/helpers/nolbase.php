<?php

/**
 * Build an absolute URL on the app subdomain (`app.nolbase.io`) from any
 * context. Used by marketing pages (which live on the apex `nolbase.io`)
 * to link into the SaaS app for login, register, dashboard, etc.
 *
 * Falls back to a relative path if NOLBASE_APP_HOST is not configured —
 * useful in dev where both marketing and app live on a single host.
 */
function nolbase_app_url(string $path = '/'): string
{
    $path = '/'.ltrim($path, '/');
    $host = (string) config('nolbase.app_host');
    if (! $host) {
        return $path;
    }
    $scheme = request()->isSecure() || app()->environment('production') ? 'https' : 'http';

    return $scheme.'://'.$host.$path;
}

/**
 * Build an absolute URL on the marketing host (`nolbase.io`). Used by app
 * pages (e.g. agpl-footer) to link to legal/pricing on the marketing site.
 */
function nolbase_marketing_url(string $path = '/'): string
{
    $path = '/'.ltrim($path, '/');
    $host = (string) config('nolbase.marketing_host');
    if (! $host) {
        return $path;
    }
    $scheme = request()->isSecure() || app()->environment('production') ? 'https' : 'http';

    return $scheme.'://'.$host.$path;
}
