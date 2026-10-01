<?php

/*
|--------------------------------------------------------------------------
| Tenancy (Fase 1 — Platform module)
|--------------------------------------------------------------------------
|
| Tenants are NOT resolved from the host: a school is identified by its
| school code (the tenant id today, NPSN later) typed on the login form
| and remembered in the session. Central domains serve the school login
| and the public application form; the console domain serves the
| provider console and never carries a tenant.
|
*/

return [

    'central_domains' => collect(explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')))
        ->map(fn (string $domain): string => strtolower(trim($domain)))
        ->filter()
        ->values()
        ->all(),

    // Provider console host (login, applications review). Never a tenant.
    'console_domain' => strtolower(trim((string) env('TENANCY_CONSOLE_DOMAIN', 'console.localhost'))),

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
