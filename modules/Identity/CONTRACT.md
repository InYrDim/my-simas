# Module: Identity

## Owns

- Database tables: `users` (tenant-scoped), `password_reset_tokens`
  (central — TODO Fase 2), `sessions`
- Core domain concepts: tenant-scoped user identity and session
  authentication (login/logout) — the `User` model, its factory, user
  resolution, the user policy, and login rate limiting.

## Public interface (Contracts/)

- `ResolvesUsers` — resolves a user by unique email; returns `UserRecord`.
- `UserRecord` — read-only DTO (id, name, email, emailVerifiedAt, roles).

Binding: `ResolvesUsers` → `DefaultUserResolver` (singleton), registered
in `IdentityServiceProvider`; override in tests via the container.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic; reserved for Fase 1)
- Laravel/Vendor

## Events published

- None yet. Will be added here when Identity starts emitting domain
  events (e.g. `UserRegistered`).

## Events consumed

- None.

## Explicitly NOT exposed

- `App\Domain\Models\User` — other modules must never import it. Store
  `user_id` as a plain column (no FK, no Eloquent relation) and use
  `ResolvesUsers` / `UserRecord` for user data.
- `App\Domain\Actions\DefaultUserResolver` — bind/override the
  contract, not the implementation.
- `App\Domain\Policies\UserPolicy`, `database/migrations`,
  `database/factories` — internal to Identity.

## Notes for maintainers

- `users` is tenant-scoped since Fase 1: `tenant_id` via the
  `Blueprint::tenantId()` macro, `unique(tenant_id, email)` — the same
  email may exist in two tenants. The `User` model uses Platform's
  `BelongsToTenant` + `HasTenantRoles` (PlatformPublic traits); Spatie
  is never imported here.
- Auth surface is login/logout ONLY (no registration, no password
  reset — Fase 2). Login requires tenant context: the central host is
  rejected, credentials are re-checked against the resolved tenant
  after `Auth::validate()`, and rate limiting is enforced IN THE
  CONTROLLER with key `login:{tenant_id}:{email}:{ip}` (a
  `throttle:` middleware closure cannot rely on tenant context —
  ordering is not guaranteed).
- Session isolation: host-only cookies (`SESSION_DOMAIN` unset) are the
  primary defence; Platform's `EnsureSessionTenant` middleware logs out
  sessions whose user belongs to another tenant.
- `password_reset_tokens` is STILL central (keyed by email alone) — it
  crosses tenants today. Highest-priority Fase 2 fix.
- Factory: `UserFactory::forTenant($id)` pins the tenant; without it
  the `creating` hook fills `tenant_id` from ambient context (and
  throws without one — fail closed).
- The login page lives at
  `resources/js/Pages/Identity/Auth/Login.tsx` (module pages resolve as
  `<Module>/<Page>`, so the path doubles the module name) and posts via
  the generated Wayfinder action — not the `route()` helper.
- Module routes are loaded with `loadRoutesFrom()` and do NOT inherit
  the root `web` group: `routes/web.php` declares
  `Route::middleware('web')` itself.
- `ProviderUser` lives in modules/Platform, not here.
- Factory/model binding is explicit via `#[UseFactory]` / `#[UseModel]`
  attributes because Laravel's `App\` naming conventions do not apply
  inside modules.
