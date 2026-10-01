# Playwright specs

## Contents
- What this suite is
- How a run works
- Writing a spec
- Seeded data
- Traps
- Debugging

## What this suite is

`tests/E2E/*.spec.ts`, configured by `playwright.config.ts`, run with `composer test:playwright`. It is the thin "real environment" suite: a real PHP server, real hosts, a real database file. It exists for what the in-process Pest browser suite cannot prove — host switching, cookies, and redirects between `localhost` and `console.localhost`.

Keep it thin. A scenario that needs a factory, a mail link, or a database assertion belongs in `tests/Browser/`. Add a spec here only when the real server or real hosts are the thing under test.

## How a run works

1. `tests/E2E/global-setup.ts` empties `database/e2e.sqlite` and runs `php artisan migrate:fresh --seed` against it.
2. Playwright starts PHP's built-in server on port **8989** (`webServer` in `playwright.config.ts`), with OPcache off.
3. Specs run in file order, one worker, sharing that database and server.
4. Playwright stops the server.

Port, URLs, the environment overrides and the seeded accounts are all in `tests/E2E/support/env.ts`. Import from there; never hard-code a URL or a password in a spec.

The dev database (`database/database.sqlite`) is never touched: the suite's variables are process environment variables, which Laravel does not overwrite from `.env`.

## Writing a spec

```ts
import { expect, test } from '@playwright/test';

import { accounts, centralUrl, consoleUrl } from './support/env';

test('the provider console is closed to a school session', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${consoleUrl}/login`);
    await page.getByLabel('Email').fill(accounts.provider.email);
    await page.getByLabel('Kata sandi').fill(accounts.provider.password);
    await page.getByRole('button', { name: 'Masuk' }).click();

    await expect(page).toHaveURL(`${consoleUrl}/dashboard`);

    await context.close();
});
```

- **One browser context per person.** A provider and an applicant in the same test each get `browser.newContext()`, so their cookies never mix.
- **Locate by role and label**, the way a user finds things: `getByRole('button', { name: ... })`, `getByLabel(...)`. Add `exact: true` when a name is a substring of another (`'Pengajuan'` also matches "1 pengajuan sekolah menunggu").
- **Assert the full URL including the host** — the host is what this suite is for.
- Central pages may use relative paths (`page.goto('/login')`, the base URL is the central host); console pages need `${consoleUrl}/...`.

`tests/E2E/onboarding.spec.ts` and `tests/E2E/smoke.spec.ts` are the working references.

## Seeded data

From `PlatformDevSeeder` and the root `DatabaseSeeder` (they run because the suite keeps `APP_ENV=local`):

| What | Value |
| --- | --- |
| Provider | `admin@simas.com` / `admin123` |
| Applicant with a pending application | `kepsek@sekolah-c.test` / `password`, school `SMA Sekolah C` |
| Schools | `sekolah-a`, `sekolah-b` |
| School admin | `admin@sekolah-a.test` / `password` (school code = tenant id, generated per seed) |
| Plans | `starter`, `standard`, `pro` |

Need other data? Prefer extending the dev seeder (it also helps manual testing) over creating records through the UI inside a spec.

## Traps

- **Specs share one database and run in order.** `onboarding.spec.ts` approves the only pending application; a second spec cannot approve it again. A spec that consumes seeded state must say so in its doc comment, and the next one must not depend on it.
- **Port 8123 belongs to the developer's own `php artisan serve`.** The suite uses 8989 and `reuseExistingServer: false`; do not point it at a running dev server, which uses the dev database.
- **`migrate:fresh` is only safe because the config is not cached.** A cached config ignores the environment and would aim at the dev database, so `global-setup.ts` refuses to run when `bootstrap/cache/config.php` exists. Keep both guards if you edit it.
- **OPcache stays off for the suite's server** (`-d opcache.enable=0`). A fresh PHP server with OPcache on failed to boot on the dev machine ("A facade root has not been set", underneath: `Cannot instantiate interface RecursiveIterator`).
- **The server is single-threaded on Windows.** Keep `workers: 1`; parallel specs would queue on it and time out.
- **Mails go to the log** (`MAIL_MAILER=log`). A flow that needs a link from a mail belongs in the Pest browser suite.
- **Assets**: `composer test:playwright` builds first. With `npm run dev` running, pages load from the dev server instead; both work.
- `npm run check` reports formatting issues across the frontend that predate this suite; new spec files appear in that list too.

## Debugging

- `npx playwright test --ui` to watch and step through.
- A failure keeps a screenshot, an `error-context.md` (the page as text — fastest way to see a PHP error page) and a trace: `npx playwright show-trace test-results/<folder>/trace.zip`.
- A page that shows a PHP fatal instead of the app means the server did not boot — check the environment in `tests/E2E/support/env.ts` and the OPcache note above.
- `test-results/` and `playwright-report/` are git-ignored; delete them when done.
