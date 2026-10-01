<?php

/*
 * Host switching for browser tests.
 *
 * The browser always talks to the test server on 127.0.0.1; which HOST the
 * application sees is decided server-side, per request, from the browser
 * plugin's configured host. `visit(...)->withHost()` only covers the first
 * page load, so a flow that keeps clicking on the provider console has to
 * switch the configured host itself — and switch back for the central
 * (school and applicant) pages.
 */

/**
 * Serve the following requests as the provider console host.
 */
function onConsoleHost(): void
{
    pest()->browser()->withHost((string) config('tenancy.console_domain'));

    // Console routes are domain-bound, so their redirects point at the
    // console origin while the browser stays on 127.0.0.1. Inertia reads
    // its response headers through XHR, and a cross-origin XHR only sees
    // headers the server exposes.
    config(['cors.exposed_headers' => ['*']]);
}

/**
 * Serve the following requests as the central host (the default).
 */
function onCentralHost(): void
{
    pest()->browser()->withHost(null);
}
