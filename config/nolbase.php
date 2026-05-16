<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AGPL Source Repository
    |--------------------------------------------------------------------------
    |
    | Nolbase is a fork of Coolify (AGPLv3). The AGPL requires that the
    | modified source code is made available to users of the service.
    | This URL points to the public Nolbase fork — shown on /legal/source
    | and in the footer.
    |
    */
    'source_repo_url' => env('NOLBASE_SOURCE_REPO_URL', 'https://github.com/BizPhereApps/coolify'),

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    */
    'brand_name' => env('NOLBASE_BRAND_NAME', 'Nolbase'),
    'support_email' => env('NOLBASE_SUPPORT_EMAIL', 'support@nolbase.io'),
    'marketing_url' => env('NOLBASE_MARKETING_URL', 'https://nolbase.io'),

    /*
    |--------------------------------------------------------------------------
    | Host split — marketing vs app
    |--------------------------------------------------------------------------
    |
    | Production runs two hosts on one Laravel codebase:
    |   - marketing_host (e.g. nolbase.io) serves the landing page, pricing,
    |     and legal pages. No tenant-app routes are reachable here.
    |   - app_host (e.g. app.nolbase.io) serves login, register, dashboard,
    |     and every authenticated tenant route.
    | The EnforceHostRouting middleware redirects cross-host requests to
    | the correct host, preserving path. In dev, set both to the same value
    | (or leave both empty) and the middleware becomes a no-op.
    |
    */
    'marketing_host' => env('NOLBASE_MARKETING_HOST'),
    'app_host' => env('NOLBASE_APP_HOST'),

    /*
    |--------------------------------------------------------------------------
    | Nolbase.app — free default subdomain for deployed apps
    |--------------------------------------------------------------------------
    |
    | Developers can claim a {slug}.nolbase.app subdomain for each deployed
    | application. Nolbase creates a Cloudflare A record pointing directly
    | at the app's server so Traefik issues TLS and routes traffic — same
    | as a custom domain, but zero DNS setup for the developer.
    |
    | Requires a Cloudflare API token with Zone:DNS:Edit on the nolbase.app zone.
    |
    */
    'app_domain' => env('NOLBASE_APP_DOMAIN', 'nolbase.app'),
    'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),
    'cloudflare_zone_id' => env('CLOUDFLARE_ZONE_ID'),
];
