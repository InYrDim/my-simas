# CONTRACT — Modules\Core

Placeholder for SIMAS master data (Fase 1+). Currently an empty skeleton
with a service provider and route file.

## Public surface

None yet. Will expose `Modules\Core\Contracts` / `Events` when real
features land.

## Dependencies

Core may depend on `Shared`, `Laravel`/`Vendor`, and `PlatformPublic`.
Core must never depend on feature modules (Identity is an *upper* layer:
Core may reference `IdentityPublic` only if a future rule change allows
it — today it does not).
