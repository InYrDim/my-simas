# Module: Shared

## Owns
- Database tables: none (must never own any)
- Core domain concepts: none by design — pure technical utilities only
  (base classes, generic traits/helpers, generic value objects, base
  exceptions, generic UI). Knows nothing about school business domains
  (student, class, teacher, attendance, PPDB) or other modules.

## Public interface (Contracts/)
- All of `Modules\Shared` is public by definition. There is no
  internal/external split — the Deptrac `Shared` layer covers the whole
  module and every module may use it.

## Allowed dependencies
- Laravel/Vendor only. Never `Modules\*` (enforced by Deptrac and an
  arch test).

## Events published
- None.

## Events consumed
- None.

## Explicitly NOT exposed
- Nothing — but the *inbound* rule is the point: code used by only one
  module belongs in that module, and business-meaningful code used by
  two modules must be reported (candidate for the owning module's
  contract or a future Core module), never parked here.

## Notes for maintainers
- The arch test "modules/Shared never imports another module namespace"
  fails the suite on any violation — do not weaken it.
