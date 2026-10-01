<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Modules\Platform\App\Domain\Models\Tenant;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Module test suites
|--------------------------------------------------------------------------
|
| Each module carries its own Feature/Unit tests under
| modules/<Name>/tests (per the module template). They are bound here
| with absolute paths because Pest resolves relative targets against
| the tests/ directory.
|
*/

foreach (glob(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature') ?: [] as $featureDir) {
    pest()->extend(TestCase::class)
        ->use(RefreshDatabase::class)
        ->in($featureDir);
}

/*
|--------------------------------------------------------------------------
| Browser (end-to-end) tests
|--------------------------------------------------------------------------
|
| tests/Browser drives a real browser through Pest's browser plugin. The
| folder is NOT listed in phpunit.xml: it runs only via phpunit.e2e.xml
| (`composer test:e2e`), so the main suite needs neither Playwright nor
| built assets.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // The plugin serves every request from this one PHP process.
        // Inertia memoises its server-side render per "request scope",
        // which never ends here: with SSR on, every full page load after
        // the first would be answered with the first page's HTML.
        config(['inertia.ssr.enabled' => false]);

        // Always the built assets, even while `npm run dev` is running
        // (public/hot would point the pages at the dev server).
        Vite::useHotFile(storage_path('framework/testing/e2e-no-hot-file'));
    })
    ->afterEach(function (): void {
        // A test may have switched to the console host (Support/hosts.php).
        pest()->browser()->withHost(null);
    })
    ->in('Browser');

foreach (glob(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit') ?: [] as $unitDir) {
    pest()->extend(TestCase::class)
        ->in($unitDir);
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, it is likely you will want to check that values meet
| certain conditions. Here you can add custom expectations to use in your tests.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Point the next request(s) at a school: remembers the tenant in the
 * session (what a real login/emailed link does) and returns the path,
 * so it drops straight into get()/post()/... in place of a tenant host.
 */
function school(string $slug, string $path = '/'): string
{
    test()->withSession(['tenant_id' => schoolId($slug)]);

    return $path === '' ? '/' : $path;
}

/**
 * The tenant id (= school code) for a tenant slug.
 */
function schoolId(string $slug): string
{
    return (string) Tenant::query()
        ->withoutGlobalScopes()
        ->where('slug', $slug)
        ->value('id');
}
