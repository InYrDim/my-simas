# Pest browser tests

## Contents
- What this suite is
- Writing a test
- Hosts: central and console
- Links from mails
- Traps
- Debugging

## What this suite is

`tests/Browser/`, run through `phpunit.e2e.xml` (`composer test:e2e`). The folder is deliberately absent from `phpunit.xml`, so `php artisan test` never starts a browser — keep it that way.

The browser plugin serves the application **inside the test process**. That is the point: factories, `Mail::fake()`, and database assertions work exactly as in a feature test, in the same in-memory database. It is also the source of every trap below: one PHP process answers all requests, which production never does.

`tests/Pest.php` already sets up every browser test: Inertia SSR off, built assets forced, host reset after each test. Do not repeat that in a test file.

## Writing a test

```php
<?php

use Modules\Platform\Database\Factories\ProviderUserFactory;

require_once __DIR__.'/Support/hosts.php';

it('lets a provider open the applicant list', function () {
    ProviderUserFactory::new()->create(['email' => 'admin@simas.test', 'password' => 'rahasia-sekali']);

    onConsoleHost();

    $page = visit('/login');

    $page->assertSee('Console Provider')
        ->fill('email', 'admin@simas.test')
        ->fill('password', 'rahasia-sekali')
        ->press('Masuk')
        ->assertPathIs('/dashboard');

    $page->navigate('/applicants')
        ->assertSee('Undang pemohon')
        ->assertNoJavaScriptErrors();
});
```

- `visit('/path')` opens a page; keep the returned `$page` and keep working on it.
- `fill('name', 'value')` takes the input's `name`; `press('Teks tombol')` and `click('Teks tautan')` take visible text.
- Move on with `->navigate('/path')` or by clicking a link. End a page's checks with `assertNoJavaScriptErrors()`.
- Assert the database as well as the screen — it is the reason to use this suite instead of Playwright.

`tests/Browser/SmokeTest.php` is the smallest working reference; `tests/Browser/OnboardingJourneyTest.php` shows a full flow across both hosts.

Shared helpers live in `tests/Browser/Support/onboarding.php`: `seedOnboardingPlans()`, `providerSignsIn()`, `applicantSignsIn($email, $password)`, `mailedPath(...)`, `refreshSignedInUsers()`. Sign people in through these (the real forms), not `actingAs()` — `actingAs()` also changes the default guard.

## Hosts: central and console

The browser always talks to `127.0.0.1`. Which host the application sees is decided server-side from the plugin's configured host, per request.

```php
onConsoleHost();   // following requests are served as console.localhost
onCentralHost();   // back to the school / applicant host (the default)
```

Both live in `tests/Browser/Support/hosts.php`. A flow that crosses hosts (provider approves, applicant logs in) switches between them inside one test. The host is reset after every test.

`visit(...)->withHost('console.localhost')` is NOT enough: it covers only the first page load, and the form post that follows lands on the central host.

## Links from mails

Fake the mail, take the URL from the mailable, and visit only its path and query — the host inside the link comes from `TenantUrl::root()` (`localhost`), not the test server:

```php
Mail::fake();
// ... the step that queues the mail ...
$url = null;
Mail::assertQueued(ApplicantVerifyMail::class, function (ApplicantVerifyMail $mail) use (&$url) {
    $url = $mail->verifyUrl;

    return true;
});

$page->navigate((string) preg_replace('#^https?://[^/]+#', '', $url));
```

`mailedPath($mailable, $property)` in `tests/Browser/Support/onboarding.php` does exactly this. The invitation and reset links are signed relative, so the path alone stays valid. The verification link is signed absolute against the request host, which here is the test server itself, so its path works too.

## Traps

- **Inertia SSR must stay off here.** Inertia memoises its server-side render per request scope, and in this single process the scope never ends: with SSR on, the second full page load returns the FIRST page's HTML (the symptom: a page shows the previous page's content, or the login form after a successful login). `tests/Pest.php` disables it; the SSR path is therefore not covered by this suite.
- **A running `npm run dev` leaves `public/hot`**, which points pages at the dev server. `tests/Pest.php` overrides the hot file so tests always use built assets — which is why `composer test:e2e` builds first. After changing frontend code, rebuild before running a single test with `vendor/bin/pest -c phpunit.e2e.xml`.
- **Two `visit()` calls open two separate pages**, each with its own cookies. Use `navigate()` to stay signed in.
- **A failed `assertSee` reports the page's INITIAL URL** ("initially with the url ..."), not where the browser is now. Look at the screenshot instead.
- **Console redirects are cross-origin** in this suite (console routes are domain-bound, the browser sits on `127.0.0.1`). `onConsoleHost()` exposes the response headers Inertia needs; a raw JSON page with "must receive a valid Inertia response" means the host was switched some other way.
- **A guard keeps the user it loaded, across requests.** After a request changes the signed-in user from the outside (opening the email-verification link), the next request still sees the old model — the applicant is sent back to the verification notice. Call `refreshSignedInUsers()` after such a step. A real server reloads the user on every request, so this is not an application bug.
- **`press('Teks')` can miss a visible button** (it matches exact text). When it times out on a button you can see in the screenshot, use a role locator: `click('internal:role=button[name="Ya, tolak"i]')`.
- **Never run `npm run build` while browser tests are running.** The build rewrites `public/build`; a running test then fails with a 404 on an asset file, reported as a timeout plus a `NotFoundHttpException` from the server. One suite at a time.
- **If the Pest process hangs after printing its result**, kill it; this happened once and did not repeat.
- **Screenshots** of failures land in `tests/Browser/Screenshots/` (git-ignored). Delete them when done.

## Debugging

- Read the failure screenshot first (the Read tool shows images).
- `vendor/bin/pest -c phpunit.e2e.xml --debug --filter="name"` runs headed and pauses on failure.
- To see what the server answered, listen to `Illuminate\Foundation\Http\Events\RequestHandled` inside the test and log method, path, host, status and `Location` to a file — that is how the SSR and host problems above were found. Remove the probe afterwards.
