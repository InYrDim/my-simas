<?php

/*
|--------------------------------------------------------------------------
| Tenancy (Fase 1 — Platform module)
|--------------------------------------------------------------------------
|
| Central domains are hosts that never resolve to a tenant: requests to
| them run without tenant context (no fallback, no override). Tenant
| hosts are either "{slug}.{central_domain}" or a custom domain stored
| on the tenant record. Reserved slugs can never be provisioned because
| they would shadow central/infrastructure hosts.
|
*/

return [

    'central_domains' => collect(explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')))
        ->map(fn (string $domain): string => strtolower(trim($domain)))
        ->filter()
        ->values()
        ->all(),

    'reserved_slugs' => [
        'www', 'admin', 'api', 'app', 'central',
        'mail', 'webmail', 'smtp', 'ftp', 'ns1', 'ns2',
        'cdn', 'static', 'assets', 'status',
        'docs', 'help', 'support', 'blog',
        'pay', 'billing', 'secure', 'vpn', 'cpanel',
        'test', 'demo', 'staging', 'dashboard',
    ],

    'default_timezone' => env('TENANCY_DEFAULT_TIMEZONE', 'Asia/Jakarta'),

    // Tenant URL generation (Fase 2, TenantUrl contract): scheme/port
    // for links built inside the queue (reset/set-password emails).
    // Production: https + no port. Dev: http + :8000 (or the port the
    // app is served on). Custom domains terminate TLS per-tenant.
    'url_scheme' => env('TENANCY_URL_SCHEME', 'http'),

    'url_port' => env('TENANCY_URL_PORT', ''),

    // Modules enabled when a school application is approved (Fase 2
    // Stage 5). Flagged modules only — core is always active and never
    // carries a per-tenant flag row.
    'onboarding_modules' => ['identity'],

];
