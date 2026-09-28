# CONTRACT — Modules\Identity

Owner of authentication and user identity: `User` model, its factory,
the `users` / `password_reset_tokens` / `sessions` migrations, and
user-resolution logic.

## Public surface (the only things other modules may use)

| Namespace | Contents |
|---|---|
| `Modules\Identity\Contracts` | `ResolvesUsers` — resolve a user by email; `UserRecord` — read-only user DTO |

Nothing else (models, services, policies, HTTP) is public. Binding is
registered in `IdentityServiceProvider` and can be overridden in tests
via `$this->app->bind(ResolvesUsers::class, ...)`. Prefer the contract
over importing `User` directly.

## Cross-module data rules

- Other modules **must not** import `Modules\Identity\Models\User`.
  Store `user_id` as a plain column (no FK, no Eloquent relation) and
  use `ResolvesUsers` / `UserRecord` when user data is needed.
- No cross-module foreign keys in migrations.

## Deferred to Fase 1 (Platform)

- `tenant_id` column + `unique(tenant_id, email)` on `users`.
- Tenant-aware `password_reset_tokens`.
- `HasTenantRoles` wrapper trait provided by PlatformPublic (do **not**
  import Spatie packages here); `User` currently has no roles trait.
- `ProviderUser` lives in `modules/Platform`, not here.

## Behaviour lock (Fase 0)

Login/auth behaviour, table names, and URLs are unchanged.
