---
name: writing-tests
description: "How to write and run tests in THIS project: which of the three suites a test belongs in (Pest feature tests in modules, Pest browser tests in tests/Browser, Playwright specs in tests/E2E), where the file goes, which helpers and factories already exist, the exact commands, and the traps that already cost time here (tenant context, guards in tests, the console host, Inertia SSR in the browser suite, the e2e database). Use whenever adding or changing a test, covering a new feature or bug fix with tests, writing an e2e/browser/Playwright test, or when a test fails for a reason that looks environmental — even if the user only says 'tambah tes', 'buat test', 'tes e2e', or 'kenapa tesnya gagal'. For general test design (naming, assertions, what deserves a test) read the testing-best-practices skill as well; this skill is the project-specific how-to."
---

# Writing Tests

Three suites, each for a different question. Pick the lowest one that can answer yours: a rule that a feature test can prove does not need a browser.

| Question | Suite | Location | Run |
| --- | --- | --- | --- |
| Is the server rule right? (validation, permissions, tenant isolation, what gets stored, which mail is queued) | Pest feature test | `modules/<Name>/tests/Feature/` | `php artisan test --compact <file>` |
| Does the page actually work for a user? (forms, dialogs, client-side state, a flow across several pages) | Pest browser test | `tests/Browser/` | `composer test:e2e` |
| Does it work on the real server and real hosts? (host switching, cookies, redirects between `localhost` and `console.localhost`) | Playwright spec | `tests/E2E/` | `composer test:playwright` |

Most tests are feature tests. Browser tests are for what only a rendered page can show. Playwright stays thin: a scenario is written in ONE suite, never both.

Read the reference for the suite you are writing in before the first line:

- **Feature tests** → [feature-tests.md](feature-tests.md)
- **Pest browser tests** → [browser-tests.md](browser-tests.md)
- **Playwright specs** → [playwright-tests.md](playwright-tests.md)

## Workflow

1. Decide the suite from the table above.
2. Read that suite's reference, then a sibling test file in the same folder — match its helpers and style.
3. Write the test for the behaviour and its important failure modes: the happy path, validation, a user without permission, and another school's data (tenant isolation) wherever tenant data is involved.
4. Run the narrowest command that covers it. Rerun after every change to the test.
5. Before calling it done, run the suite it belongs to, and `php artisan test --compact` if application code changed.

## Rules that hold in every suite

- **Assert outcomes, not just text.** A test that only checks a success message passes when nothing was saved. Check the database row, the session, or where the user landed.
- **Prove the refusal too.** For every write: a role without the permission, a guest, and an id belonging to another school.
- **No random data in assertions.** Factories generate random names and numbers; a search for `999` once matched a random NIS and failed one run in ten. Search and assert on values the test set itself.
- **A test that fails only sometimes is a bug in the test.** Find the cause (shared state, random data, timing) instead of rerunning.
- **Never weaken or delete a test to get green** — fix the cause, or ask. Changing an existing test's expectation is fine when the behaviour changed on purpose; say so in the summary.
- **When a test exposes an application bug, report it and fix it as its own change.**
- Use factories and their states; check for an existing state before setting attributes by hand.
- Run `vendor/bin/pint --dirty --format agent` after editing PHP tests.

## Commands

```bash
php artisan test --compact                       # main suite (no browser tests)
php artisan test --compact modules/Core          # one module
php artisan test --compact path/to/FileTest.php  # one file
php artisan test --compact --filter="name"       # one test

composer test:e2e                                # Pest browser suite (builds assets first)
vendor/bin/pest -c phpunit.e2e.xml --filter="name"   # one browser test, assets already built
vendor/bin/pest -c phpunit.e2e.xml --debug       # headed, pauses on failure

composer test:playwright                         # Playwright suite (builds assets first)
npx playwright test onboarding                   # one spec
npx playwright test --ui                         # watch it run

vendor/bin/deptrac analyse --no-progress         # module boundaries
```

Every suite needs the PHP `sockets` extension: the browser plugin hooks into all tests under `tests/`, so without it even `php artisan test` fails with `Call to undefined function socket_create_listen()`.
