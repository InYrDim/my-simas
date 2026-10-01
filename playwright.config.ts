import { defineConfig, devices } from '@playwright/test';

import { appEnv, centralUrl, port } from './tests/E2E/support/env';

/**
 * Playwright: the thin "real environment" suite (tests/E2E). It starts a
 * real PHP server with its own database and talks to the real hosts —
 * what the in-process Pest browser suite (tests/Browser) cannot cover.
 * Detailed scenarios stay in the Pest suite; nothing is tested twice.
 *
 * Run with `composer test:playwright`.
 */
export default defineConfig({
    testDir: './tests/E2E',
    globalSetup: './tests/E2E/global-setup.ts',
    outputDir: './test-results',

    // One seeded database and one single-threaded PHP server: the specs
    // share state (an application is approved once), so they run in order.
    fullyParallel: false,
    workers: 1,

    forbidOnly: !!process.env.CI,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL: centralUrl,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

    webServer: {
        // PHP's built-in server with Laravel's router script — what
        // `artisan serve` runs underneath. Started directly so the
        // process environment below reaches the application as is, and
        // with OPcache off so this server never shares a cache with a
        // dev server that may be running from an older PHP configuration.
        command: `php -d opcache.enable=0 -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: './public',
        url: `${centralUrl}/up`,
        env: appEnv,
        reuseExistingServer: false,
        stdout: 'ignore',
        stderr: 'ignore',
        timeout: 60_000,
    },
});
