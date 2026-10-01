# Feature tests (Pest)

## Contents
- Where the file goes
- Setting up a school and a user
- Reaching school, console and applicant routes
- What to cover
- Traps

## Where the file goes

`modules/<Name>/tests/Feature/<Thing>Test.php`, created with `php artisan make:test --pest <Thing>Test` and moved there if needed. Every folder is already bound to `TestCase` + `RefreshDatabase` by `tests/Pest.php`.

- Core and Identity tests declare a namespace (`Modules\Core\Tests\Feature`, `Modules\Identity\Tests\Feature`).
- Platform tests have none: their helper functions are GLOBAL, so each helper needs a name no other test file uses.
- Root `tests/` is the glue layer (architecture tests, tests that cross modules). It may use any module.

Helpers shared by several files live in a `Support/helpers.php` next to the tests and are pulled in with `require_once __DIR__.'/Support/helpers.php';` — a helper defined inside one test file is missing when another file runs alone.

## Setting up a school and a user

Schools are tenants. A tenant-scoped model can only be queried or created inside a tenant context.

```php
$tenant = TenantFactory::new()->create(['slug' => 'sekolah-uji']);
$user = UserFactory::new()->forTenant($tenant->id)->create();

// Roles and any tenant-scoped query need the context:
app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('admin-sekolah'));

actingAs($user);
```

Existing helpers — use them before writing new ones:

| Helper | Where | Does |
| --- | --- | --- |
| `school($slug, $path)` | `tests/Pest.php` | Puts the school in the session and returns the path: `get(school($slug, '/master/kelas'))` |
| `schoolId($slug)` | `tests/Pest.php` | The tenant id (= school code) for a slug |
| `schoolAs($slug, $role)` | `modules/Core/tests/Feature/Support/helpers.php` | Creates a tenant, a user with that role, signs in, returns the tenant |
| `inSchool($tenant, fn)` | same | Runs a closure inside the tenant's context |
| `yearWithSemesters($tenant, $status, $start)` | same | An academic year with Ganjil and Genap |
| `classIn($tenant, $attributes)` | same | A class in the school's first academic year and grade |

Default roles: `admin-sekolah` (may manage), `guru` and `staf-tu` (may view master data). They are seeded when a tenant is created.

Applicants and provider staff are central, not tenant-scoped:

```php
actingAs(ApplicantFactory::new()->create(), 'applicant');       // verified by default; ->unverified() for the other case
actingAs(ProviderUserFactory::new()->create(), 'provider');
TenantApplicationFactory::new()->forApplicant($applicant)->create(['plan_key' => 'starter']);   // ->rejected('catatan')
```

## Reaching school, console and applicant routes

- **School routes**: `get(school($tenant->slug, '/master/siswa'))`. Without the school in the session there is no tenant context.
- **Console routes** are bound to the console host: `get('http://console.localhost/applications')`.
- **Applicant and other central routes**: `get('http://localhost/pemohon')`.
- Inertia pages: `->assertInertia(fn (Assert $page) => $page->component('Core/Master/Classes/Index')->where('classes.0.name', 'X 1')->has('rooms', 1))`.

## What to cover

For a page with writes, in this order:

1. **Happy path** — the request succeeds AND the row is what was sent (`inSchool($tenant, fn () => Model::query()->sole())`).
2. **Validation** — `assertSessionHasErrors([...])`, and nothing was stored.
3. **Domain rules** — the invariant itself (one active year, a room in use cannot be deleted): the refusal and that nothing changed.
4. **Permission** — a `guru` gets `assertForbidden()` on writes and `assertOk()` on reads; a guest is redirected.
5. **Tenant isolation** — another school's record: `assertNotFound()` on show/update/delete, absent from lists, and unique rules allow the same value in a second school.
6. **Side effects** — `Mail::fake()` then `Mail::assertQueued(...)` with a closure on the mailable's public properties; `Event::fake([X::class])` only for events whose listeners you do not need.

## Traps

- **`actingAs($x, 'applicant')` also makes that guard the DEFAULT.** School routes (`auth`) then look open to an applicant — a test artefact. To prove guard separation, log in for real (`post('/pemohon/masuk', [...])`) or call `Auth::shouldUse('web')` afterwards.
- **The guard keeps the model instance it was given.** After a request changes that user (verifying an email), `actingAs($user->fresh(), ...)` before the next request.
- **`Rule::unique` and `Rule::exists` ignore the tenant scope.** A test that only uses one school cannot see a missing `where('tenant_id', ...)`; add the second-school case.
- **`DB::table()` bypasses the tenant scope.** Fine for asserting raw rows in a test, wrong for reading "this school's" data.
- **Faking an event inside a transaction** skips its listeners: `Event::fake([TenantApproved::class])` means no first admin is provisioned. To prove a rollback, add a throwing listener instead (`Event::listen(X::class, fn () => throw new RuntimeException('...'))`).
- **`fake()->unique()` values can still collide with literals** the test uses; pick literals outside the factory's range or set the attribute explicitly.
- **Time**: the school's clock is the tenant timezone. Use `$this->travelTo('2026-03-30 10:00:00')` rather than depending on today.
- **Architecture tests** (`tests/Architecture/ModularMonolithTest.php`) fail the suite when a migration uses `constrained(` for anything but `tenant_id`, or a module imports another module's internals — including from a seeder. Run the whole suite after touching migrations or imports.
- **Deptrac** complains about a new model using `BelongsToTenant`; add the model to `deptrac.baseline.yaml` (that file grows only for this).
