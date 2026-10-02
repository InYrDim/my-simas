# Module: Ppdb

## Owns

- Database tables. **Central** (no `tenant_id` scope, never `BelongsToTenant`):
  `ppdb_accounts` — an applicant's own account (name, email, password, and
  the one school it joined: `tenant_id`, the only foreign key). **Tenant-scoped**
  (no foreign keys but `tenant_id`; every other id is a plain indexed column):
  `ppdb_periods` (an admissions period; one is active), `ppdb_form_fields`
  (the fields of a period's registration form) and `ppdb_applicant_answers`
  (answers to the custom ones), `ppdb_waves`
  (registration waves with open and close days), `ppdb_paths` (admission
  paths with their quota) and `ppdb_applicants` (the applicants, with
  score, decision and the student made at re-registration).
- Core domain concepts: PPDB (Penerimaan Peserta Didik Baru) — the
  applicant's account and joining a school, the registration form, the
  committee's verification, selection by score within a path's quota, the
  announcement of results, re-registration (daftar ulang), and the PPDB
  reports and figures on Statistik & Laporan.
- Permissions (names attached to the default roles through Identity's
  `config/roles.php`): `ppdb.view` (list, detail, selection read-only,
  reports — admin, staf-tu), `ppdb.applicants.manage` (enter, change,
  verify, cancel, re-register — admin, staf-tu), `ppdb.selection.manage`
  (scores, decisions, announcing — admin) and `ppdb.settings.manage`
  (periods, waves, paths and quota, the registration form builder, the
  school's code and link — admin).
  An applicant's account has no permissions: it is not a school user.

## Public interface (Contracts/)

- None. `modules/Ppdb/app/Contracts` does not exist: nothing in Ppdb is
  used by another module. It registers with Platform and Core through
  their contracts instead.

## Allowed dependencies

- Modules/Shared
- Modules/Platform **App/Contracts only** (PlatformPublic)
- Modules/Identity **App/Contracts only** (IdentityPublic)
- Modules/Core **App/Contracts only** (CorePublic)
- Laravel/Vendor

Never another feature module (Attendance), not even via its Public surface.

## Events published

- None.

## Events consumed

- None.

## Explicitly NOT exposed

- Everything: models, actions, the account, the ranking, the reports.

## Notes for maintainers

- **What it registers** (`PpdbServiceProvider`): the module key `ppdb`
  (enabled per tenant), its permissions, the sidebar entries (the whole
  group needs `ppdb.view`, "Pengaturan" needs `ppdb.settings.manage`), two
  reports (`ppdb-applicants` with a column per custom field, `ppdb-result`) and one statistics provider with
  Core's `ReportRegistry` / `StatisticsRegistry`, the `Ppdb::` mail views
  (`modules/Ppdb/mail`) and the two stand-ins below. It reads Core only
  through `StudentAdmission`; it never imports Core's models.
- **The applicant's account is central.** Guard `ppdb`
  (`config/auth.php`, model `PpdbAccount`), pages under `/calon-siswa/*`
  with no tenant: register (IP throttle + honeypot), sign in (limiter
  `ppdb-account:{email}:{ip}`, one generic error), forgot password (one
  generic answer), verify email, reset — all by signed RELATIVE links with no
  token table (the verification link is tied to the email, the reset link
  also to the current password, so it works once). Mails are queued
  Mailables (`Infrastructure/Mail`); URLs come from `TenantUrl::root()`.
  Signing out ends only the `ppdb` guard, never the whole session: a school
  login in the same browser stays. It is the second kind of public
  registration after Platform's school applicants; school users still have
  none.
- **Pages that touch a school enter it explicitly.** `ResolveTenant` adopts
  whatever school the session remembers, even for a guest, so the account
  pages never use it: `TenantContext::run($account->tenant_id, …)` and only
  the registration with this `account_id`. `account_id` and the school
  never come from a request.
- **Joining** (`JoinSchool`): the school code is the tenant id the school's
  own people type at sign-in; the school's link is
  `/calon-siswa/gabung?school=<code>` (a guest is sent to sign in first and
  comes back). `TenantDirectory` + `TenantModules` + a running period decide;
  EVERY refusal (unknown, suspended, no PPDB, no running period) gives the
  same message so the form cannot be used to find schools; ten refusals in
  ten minutes per account and address block further tries. One school at a
  time: leave first. Leaving (`LeaveSchool`) only until the form is sent;
  after that the school's committee cancels the registration
  (`CancelApplication`), which frees the account.
- **The form** (`/calon-siswa/formulir`): the applicant has no wave to
  choose — `RegisterApplicant` puts them in the wave open today (waves of a
  period never overlap) and refuses when none is open or the account
  already has a registration (unique `(tenant_id, account_id)`). Fields the
  form must not decide (status, decision, score, number, source, student,
  account, school) are ignored by construction (`Applicant::DATA_FIELDS`).
  Only while the committee asks for a correction ("Perlu perbaikan") the
  applicant may change the data (`UpdateOwnApplication`); saving sends it
  back to "Menunggu verifikasi".
- **The form is the school's to build, per period** (Formulir page,
  `ppdb.settings.manage`). A period's form is rows in `ppdb_form_fields`,
  in order: the ten built-in fields of the applicant record (`key` = its
  column; the path, name and gender are locked: always asked, required,
  never archived) and the custom ones a school adds (`key` empty; types text,
  paragraph, number, date, select, checkboxes, file, and a section heading).
  Every period is seeded with the ten built-in rows when it is created and a
  new period copies the form of the school's latest one (`CopyFormFields`; an
  archived custom field is left behind). `SaveForm` saves the whole list in
  order, all or nothing, and refuses a list that leaves a field out (a stale
  page); a custom field's type is locked once it has answers; archiving keeps
  every answer and the detail page still shows it; a field is deleted
  (`DeleteFormField`) only while nobody answered it; a closed period's form is
  read-only. The basic validation per type lives in `rules` (`CustomFieldRules`:
  max length and format, number range, future dates, file size and kinds).
  `FormRules` turns the form into validation rules: built-in values at the
  column name, custom answers at `answers.<field id>`; an archived field has no
  rule so what is sent for it is dropped. The committee's request uses the
  applicant's period (the running one for a new applicant); the applicant's
  own request looks the period up inside the school the account joined, and a
  correction follows the period the registration is in. Answers are rows in
  `ppdb_applicant_answers` (text; JSON for checkboxes and files),
  `SaveAnswers` takes only asked custom fields and leaves a field not sent as
  it was. The built-in columns of the applicant are nullable for the fields a
  school may archive.
- **Uploaded files** stay in the school's private partition
  (`TenantStorage`, module `ppdb`: `applicants/{applicant}/{field}-{random}.{ext}`),
  named by the server; the answer keeps the sender's name (stripped of any
  path), size and type. Only a field's own size (100–5120 KB) and kinds (PDF,
  JPG/PNG) are accepted, checked by content. A replaced file is removed once
  the save commits, a file written for a failed save is removed again, and
  cancelling a registration removes its files. Downloads are attachments with
  `nosniff`: the committee at `/ppdb/pendaftar/{id}/berkas/{field}`
  (`ppdb.view`), the account at `/calon-siswa/formulir/berkas/{field}` (its own
  registration only); the path always comes from the answer row. `DocumentCheck`
  stays a stand-in: verifying is still the committee's decision.
- **The committee** enters walk-ins without an account (`source = staff`,
  any wave, `account_id` empty), verifies (`VerifyApplicant`; "Perlu
  perbaikan" needs a note the applicant reads), changes data (the path is
  locked after a decision; a re-registered applicant is frozen) and
  cancels (only before a decision). Numbers are `PPDB-{yy}-{0001}` per
  school and entry year, retried on a clash.
- **Selection** (`SaveSelection`): the verified applicants of one path,
  ranked by score; a decision needs a score; accepted never exceeds the
  quota (`SavePaths` also refuses lowering the quota under the accepted);
  a save is all or nothing. After the announcement the only change left is
  waiting list → accepted within the quota, and that applicant is told.
- **Announcing** (`PublishResults`): refused while a verified applicant is
  undecided, when there is no one verified, and a second time. Until
  `ppdb_periods.results_published_at` is set the applicant's page shows no
  decision (nor the score at any time).
- **Re-registration** (`EnrollApplicant`): after the announcement, for an
  accepted applicant, once; the committee types the NIS and Core's
  `StudentAdmission::admit()` makes the student (active, no class, no
  account) in the same transaction. Placing the student in a class and
  making the account are the school's usual steps (Warga Sekolah, fase 8).
- **Stand-ins** (decision of 2026-10-02: later, after the core): `DocumentCheck`
  (verifying says the documents are complete) and `ResultAnnouncer`
  (announcing tells nobody) are interfaces in `Domain/Support` with
  always-true implementations in `Infrastructure/Stubs`, bound in the
  provider. WhatsApp results (`ppdb.result` stays "Segera hadir" in Core)
  and a public result page are not built.
- **Days** are the school's own `Y-m-d` strings (`Domain/Support/SchoolDay`
  over `TenantContext::timezone()`); a wave is upcoming, open or closed
  from the school's today and is never stored. Reports count an applicant
  for the academic year their `registered_on` falls in.
- Sample data: `php artisan db:seed --class="Modules\Ppdb\Database\Seeders\PpdbDemoSeeder"`
  (schools with the module and no period only; the results stay
  unannounced). The module is in no billing plan yet: a provider adds
  `ppdb` to a plan's modules, or switches it on per school.
