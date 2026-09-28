# Module: Identity

## Owns
- Database tables: `users`, `password_reset_tokens`, `sessions`
- Core domain concepts: user identity and the authentication scaffold —
  the `User` model, its factory, user resolution, and the user policy.

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
- Fase 1 TODO: `users` gains `tenant_id` + `unique(tenant_id, email)`;
  `password_reset_tokens` becomes tenant-aware. Login behaviour must
  not change until then.
- Fase 1 TODO: roles come via a `HasTenantRoles` wrapper trait in
  PlatformPublic — never import Spatie packages here; `User` currently
  has no roles trait and `UserRecord::$roles` is always `[]`.
- Fase 1 TODO: `ProviderUser` will live in modules/Platform, not here.
- Factory/model binding is explicit via `#[UseFactory]` / `#[UseModel]`
  attributes because Laravel's `App\` naming conventions do not apply
  inside modules.
