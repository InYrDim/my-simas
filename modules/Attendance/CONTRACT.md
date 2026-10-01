# Module: Attendance

## Owns

- Database tables: none yet (attendance tables arrive with the first
  persisted feature).
- Core domain concepts: daily student attendance (Absensi) — roll call per
  class and day, the school-wide overview, and the monthly recap.

## Public interface (Contracts/)

- None yet. `modules/Attendance/app/Contracts` does not exist until a
  real contract does; the Deptrac `AttendancePublic` layer is already
  wired for it.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Modules/Identity **App/Contracts only** (IdentityPublic)
- Modules/Core **App/Contracts only** (CorePublic)
- Laravel/Vendor

Never another feature module (Ppdb), not even via its Public surface.

## Events published

- None.

## Events consumed

- None.

## Explicitly NOT exposed

- Everything — nothing in Attendance may be imported by another module
  until a real contract exists.

## Notes for maintainers

- Module key `attendance` is registered in `ModuleRegistry` by
  `AttendanceServiceProvider` and enabled per tenant; routes sit behind
  `auth` + `module:attendance`. No permissions are registered yet
  (mockup phase), so the sidebar entry shows to every signed-in user of
  an enabled tenant.
- Mockup phase: `AttendanceController` serves static sample data from
  `Infrastructure/Mock/AttendanceMockData`; nothing is persisted. Pages:
  `/absensi` (Rekap Hari Ini), `/absensi/input`, `/absensi/rekap`.
  The DB phase stores `student_id` / `class_id` as plain columns (no FK
  to Core) plus `tenant_id`.
