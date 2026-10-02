# Module: Identity

## Owns

- Database tables: `users` (tenant-scoped), `password_reset_tokens`
  (TENANT-SCOPED since Fase 2: `tenant_id` + composite PK
  `(tenant_id, email)`), `sessions`
- Core domain concepts: tenant-scoped user identity and the full auth
  lifecycle — `User` model, factory, user resolution, `UserPolicy`,
  login/logout + forgot/reset/set-password (all rate limited in the
  controller), default role seeding, first-admin provisioning,
  school-admin user management (create/invite/roles/
  deactivate/reactivate/reset-link), transactional mailables, and the
  provider-console "Pengguna" page (`Console/SchoolAdminController`, rutes on
  the console host behind `auth:provider`): school admins across tenants,
  invite/re-invite, reset link, deactivate/reactivate. Every action runs in
  the target tenant's context; the last-active-admin invariant still holds.

## Public interface (Contracts/)

- `ResolvesUsers` — resolves a user by unique email; returns `UserRecord`.
  Also `currentTenantSummary()`: counts (total/active/awaitingActivation/
  deactivated/withoutRole) of the CURRENT tenant, so a module outside
  Identity can report on a school without importing the `User` model.
  Fails closed without tenant context.
  `findMany(ids)` returns the accounts of the current tenant keyed by id
  (an id of another school is simply absent).
- `UserRecord` — read-only DTO (id, name, email — nullable —,
  emailVerifiedAt, roles, username, active, mustChangePassword).
- `AccountProvisioner` — lets the module that owns a person's record
  (Core: students, teachers) give that person an account and keep it in
  step: `create(NewAccount)` (returns the id; the account signs in by
  username and must change its password), `resetPassword`,
  `updateIdentity` (name + username), `deactivate` (the last active
  admin is protected), `reactivate`. Works on the current tenant only.
- `DTOs/NewAccount` (name, username, password, role, optional email).
- `Exceptions/UsernameTakenException`,
  `Exceptions/AccountActionRefusedException`.
- `UserSummary` — read-only DTO (total, active, awaitingActivation, deactivated, withoutRole).

Binding: `ResolvesUsers` → `DefaultUserResolver`, `AccountProvisioner` →
`Infrastructure/Accounts/DefaultAccountProvisioner` (singletons),
registered in `IdentityServiceProvider`; override in tests via the
container.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Laravel/Vendor

## Events published

- None yet. Will be added here when Identity starts emitting domain
  events (e.g. `UserRegistered`).

## Implements for Platform

- `Modules\Platform\App\Contracts\SchoolSessionOpener` →
  `Infrastructure/Auth/DefaultSchoolSessionOpener` (bound in
  `IdentityServiceProvider`, replacing Platform's refusing default).
  Same rules as the school login minus the school code: credentials are
  checked inside the school's context, deactivated accounts and
  suspended schools are refused, every failure answers `false`. Used by
  Platform's applicant login once an application is approved.

## Events consumed

- `TenantCreated` (PlatformPublic) → `SeedDefaultRoles`: seeds the
  tenant's default school roles via `TenantRoles::ensure` —
  idempotent, safe on re-fire.
- `TenantApproved` (PlatformPublic) → `ProvisionFirstAdmin`: creates
  the first admin-sekolah user. When the event carries the applicant
  account's `passwordHash` (Fase 4), the admin takes that password over
  with a verified email and NO mail is sent. Without a hash (application
  with no applicant account): password null, a tenant-scoped
  set-password token is minted and `SetPasswordMail` queued with a
  `TenantUrl` school-coded link. Idempotent; runs inside the approval transaction
  (failure rolls the approval back). Both listeners register in
  `IdentityServiceProvider`.

## Explicitly NOT exposed

- `App\Domain\Models\User` — other modules must never import it. Store
  `user_id` as a plain column (no FK, no Eloquent relation) and use
  `ResolvesUsers` / `UserRecord` for user data.
- `App\Domain\Actions\DefaultUserResolver` and all other Domain
  actions (`CreateUser`, `InviteUser`, `SendResetLink`,
  `DeactivateUser`, `ReactivateUser`) — internal; HTTP is the surface.
- `App\Domain\Policies\UserPolicy`, `App\Infrastructure\Auth\*`
  (tenant-scoped token repository/broker/minter),
  `App\Infrastructure\Onboarding\*`, `database/migrations`,
  `database/factories`, `mail/*` templates — internal to Identity.

## Notes for maintainers

- `users` is tenant-scoped since Fase 1: `tenant_id` via the
  `Blueprint::tenantId()` macro, `unique(tenant_id, email)` — the same
  email may exist in two tenants. Since Fase 8 the email is OPTIONAL and
  an account may sign in by `username` instead (`unique(tenant_id,
  username)`; a student's NIS, a teacher's NIP). `must_change_password`
  marks a password someone else chose. The `User` model uses Platform's
  `BelongsToTenant` + `HasTenantRoles` (PlatformPublic traits); Spatie
  is never imported here.
- Login takes ONE field, `login`: a value with `@` is looked up as the
  email, anything else as the username. Only that column and the
  password reach `Auth::validate()`. The throttle bucket is
  `login:{tenant}:{login}:{ip}` and every failure answers the same
  generic error on `login`.
- `GET/PUT /ganti-kata-sandi` (`password.change`): any signed-in user
  changes their own password. `Http/Middleware/RequirePasswordChange`
  (web group, registered in `bootstrap/app.php` after
  `EnsureSessionTenant`) sends an account flagged
  `must_change_password` there from every route except that page and
  `logout` (JSON requests get 403).
- An account without an email gets no reset or invitation mail
  (`User::sendPasswordResetNotification`, `SendResetLink`, the console
  admin actions): its password is reset from the student or teacher
  record in Core.
- `php artisan roles:sync {--tenant=slug}` brings the default roles of
  schools that already exist in line with `config/roles.php` (same call
  as `SeedDefaultRoles`; idempotent).
- Auth surface (Fase 2): login, logout, forgot-password, reset-
  password, set-password (activation for password-null accounts),
  user management `/users` + invite. `/users` is paged (25), searched
  (`?q=` on name, email, username) and filtered by role (`?role=`). NO public registration — school
  accounts are created by their admin or via provisioning. Login
  requires tenant context (central rejected) and re-checks the
  resolved tenant after `Auth::validate()`; rate limiting lives IN
  THE CONTROLLER with tenant-keyed buckets
  (`login:{tenant}:{email}:{ip}`, same pattern for reset/set-
  password/invite) — a `throttle:` middleware closure cannot rely on
  tenant context.
- Password tokens: `TenantDatabaseTokenRepository` scopes every row by
  the AMBIENT tenant context (fail closed without one) — a token
  minted in tenant A is never valid on tenant B. TTL 60 minutes for
  all flows. Token PUBLISHING (provisioning, invitations) goes through
  `TenantTokenMinter`; the three consuming flows (reset, provisioning,
  invitation) share ONE table — the page decides the effect, and a
  token consumed on the "other" page is an accepted trade-off
  (recorded in docs/architecture).
- Mails are queued Mailables with URLs built from PlatformPublic's
  `TenantUrl` (queue-safe, no request root). Views resolve via the
  `Identity::` namespace (`loadViewsFrom(…/mail, 'Identity')`) — a
  dot-path like `modules/Identity/mail/…` does NOT work (finder maps
  dots to separators). No Notification machinery (locked decision).
- Anti-enumeration: login (deactivated = generic auth.failed),
  forgot-password (unknown/deactivated/password-less = generic "sent"),
  set-password (deactivated = invalid link). The eligibility skip for
  deactivated/password-less users happens in
  `User::sendPasswordResetNotification` — silently.
- Deactivation (`users.deactivated_at`): rows + roles kept; login
  refuses generically; live sessions ended by Platform's
  `EnsureSessionTenant` (attribute-based, no Identity import).
  `DeactivateUser` enforces the two anti-lockout invariants (no
  self-deactivation; last ACTIVE admin-sekolah protected, checked
  under the target's own tenant context).
- Roles: machine names from `config/roles.php` (`admin-sekolah`,
  `guru`, `staf-tu`, and `siswa` — which holds only `attendance.qr.show`,
  its own attendance QR); labels live in
  config only. Permissions
  `identity.users.{view,create,update,deactivate,sendReset}` are
  registered via `PermissionRegistry` and attached to admin-sekolah on
  seed. `UserPolicy` = permission gate + same-tenant target re-assert
  + deactivated-actor before-deny; invitations deliberately reuse
  `identity.users.create` (no separate invite permission).
- Factory: `UserFactory::forTenant($id)` pins the tenant (states:
  `unverified`, `deactivated`, `invited`, `withUsername($name)`,
  `mustChangePassword`); without it the `creating`
  hook fills `tenant_id` from ambient context (and throws without one
  — fail closed).
- Pages live at `resources/js/Pages/Identity/…` (module pages resolve
  as `<Module>/<Page>`, so the path doubles the module name) and post
  via generated Wayfinder actions — not the `route()` helper.
- Module routes are loaded with `loadRoutesFrom()` and do NOT inherit
  the root `web` group: `routes/web.php` declares
  `Route::middleware('web')` itself. User management routes sit behind
  `auth` + `module:identity` (403 when the tenant's identity flag is
  off).
- `ProviderUser` lives in modules/Platform, not here.
- Factory/model binding is explicit via `#[UseFactory]` / `#[UseModel]`
  attributes because Laravel's `App\` naming conventions do not apply
  inside modules.
