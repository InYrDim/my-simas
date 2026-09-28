# Module: Platform

## Owns

- Database tables: `tenants` (Fase 1)
- Core domain concepts: tenancy (tenant resolution/scoping, tenant-aware
  cache & storage), the module registry, permission registry, and
  Spatie-permission integration for tenant-scoped roles.
  **Reserved module — built in Fase 1. The folder is intentionally empty
  today; Deptrac layers `Platform` / `PlatformPublic` already exist.**

## Public interface (Contracts/)

Fase 1 will place ALL of the following under
`Modules/Platform/app/Contracts/**` (per the surface policy):

- `TenantContext` — current-tenant resolution/scoping service contract.
- `BelongsToTenant` — public trait delegating to the internal TenantScope.
- `TenantNotSetException` — thrown when tenant context is required but absent.
- `TenantCache`, `TenantStorage` — tenant-partitioned cache/storage contracts.
- `ModuleRegistry`, `PermissionRegistry` — registration contracts used by
  feature modules' service providers.
- `HasTenantRoles` — wrapper trait around Spatie roles, consumed by Identity.
- `TenantCreated` event (in `Contracts/Events`) — consumed by modules that
  must seed per-tenant defaults (e.g. default roles, Fase 2).
- Tenant helpers/DTOs as needed.

Internal (private): TenantScope, tenant resolver, queue listeners,
Spatie integration, the `Tenant` model and its persistence. Other modules
access tenancy through contracts/DTOs — never the `Tenant` model.

## Allowed dependencies

- Modules/Shared
- Laravel/Vendor
- (Identity and other modules depend on PlatformPublic — never the reverse.)

## Events published

- `TenantCreated` (Fase 1, in Contracts/Events) — fired when a new tenant
  is provisioned; payload includes the tenant DTO.

## Events consumed

- None.

## Explicitly NOT exposed

- `Tenant` Eloquent model and anything under `App/Domain/**`,
  `App/Infrastructure/**`, `App/Http/**`, `database/**`.
- Direct Spatie classes — consumers use `HasTenantRoles` instead.

## Notes for maintainers

- `ProviderUser` will live here (Fase 1), not in Identity.
- Platform's `tenants` migration must run before all other modules'
  migrations (timestamp ordering; prove with `php artisan migrate:fresh`).
- In Fase 0 this module is empty and reserved; its Deptrac layers and
  ruleset are pre-wired so Fase 1 needs no enforcement rework.
