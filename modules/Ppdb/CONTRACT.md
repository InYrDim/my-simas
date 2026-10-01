# Module: Ppdb

## Owns

- Database tables: none yet (admissions tables arrive with the first
  persisted feature).
- Core domain concepts: PPDB (Penerimaan Peserta Didik Baru) — the
  admissions period and its waves, applicants, selection by score, and
  the announcement of results.

## Public interface (Contracts/)

- None yet. `modules/Ppdb/app/Contracts` does not exist until a real
  contract does; the Deptrac `PpdbPublic` layer is already wired for it.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Modules/Identity **App/Contracts only** (IdentityPublic)
- Modules/Core **App/Contracts only** (CorePublic)
- Laravel/Vendor

Never another feature module (Attendance), not even via its Public
surface.

## Events published

- None.

## Events consumed

- None.

## Explicitly NOT exposed

- Everything — nothing in Ppdb may be imported by another module until a
  real contract exists. Turning an accepted applicant into a student is
  Core's job and will arrive as a Core-owned contract, never a direct call.

## Notes for maintainers

- Module key `ppdb` is registered in `ModuleRegistry` by
  `PpdbServiceProvider` and enabled per tenant; routes sit behind `auth`
  + `module:ppdb`. No permissions are registered yet (mockup phase), so
  the sidebar entry shows to every signed-in user of an enabled tenant.
- Mockup phase: `PpdbController` serves static sample data from
  `Infrastructure/Mock/PpdbMockData`; nothing is persisted. Pages:
  `/ppdb` (Ringkasan), `/ppdb/pendaftar`, `/ppdb/seleksi`. The public
  applicant-facing form is out of scope here.
