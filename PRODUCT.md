# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Priority order, confirmed:

1. **Teacher / school staff (Guru, Staf/TU) — the primary user.** Does the school's
   daily record-keeping (attendance, and PPDB/admissions) on low-end Android
   phones, often on a shared device, on a slow network, in short bursts between
   other duties. Their job: capture the right record quickly and correctly the
   first time, without training and without a desktop nearby.
2. **School admin (Admin Sekolah) — the tenant's owner.** The tenant's day-to-day
   back office, usually on an office desktop or laptop. Jobs: accept the account
   (set password from the approval email), manage the school's user accounts and
   roles, and eventually run the school's operations.
3. **Provider operator (Admin Provider).** Works in the central provider console
   (SaaS-company side). Jobs: review pending school applications and approve or
   reject them, correct the school data at approval, manage tenants (activate,
   suspend, modules), and run the platform.

4. **PPDB applicant (calon siswa, usually with a parent or guardian) — the lowest
   priority, and not a school user.** Someone who wants a place at one school. They
   make their own account on the central host (separate from every school login),
   join one school with its school code or link, fill in the registration form the
   school built, and then follow the status and the result on their own page, mostly
   on a phone and on a slow network. Their job: apply once, correctly, and learn the
   outcome without calling the school.

A *school* applicant is **not** a user: before approval, a prospective school only
submits a public application form. No account, no password, no session exists for
them.

## Product Purpose

SIMAS (*Sistem Informasi Manajemen Sekolah*) is a multi-tenant SaaS built and sold by
a provider company to schools in Indonesia. One tenant is one school, reached on its
own subdomain or custom domain, with all of its data separated by `tenant_id` in a
single database.

The product makes four things possible: a school can apply to join and be onboarded
without a developer; it then gets an isolated, always-available workspace for its own
administration and daily school operations (master data, academics, attendance,
admissions, and its own WhatsApp number for notices); families can apply to the school
and follow their application; and the provider company can operate that platform
centrally without touching the database.

Success, as confirmed so far: a school can apply, be approved, sign in on its own
domain, and manage its own people and modules on its own — and the provider can run
and audit the platform without developer intervention. **How success is measured is
undecided.**

Provider-side subscription management exists (plans, trial vs subscribed tenants,
invoices) with a placeholder payment step that always succeeds; real payment is
deliberately deferred. Nothing about cost is shown to schools.

Deliberately out of scope so far: real payment processing, notification-center
machinery, and school users registering themselves (school users never self-register).

## Positioning

A commercial multi-tenant SaaS from a provider company to Indonesian schools,
delivered as a per-school web app on its own domain — staff-first on mobile, with the
school's back office on desktop.

The mechanism a neighboring product could not truthfully copy: **provider-approved
onboarding.** A school is not a customer until a human provider operator approves its
application, and that approval is where the school becomes a real tenant — the
operator may correct the school's data at that moment, the tenant is created, its
default roles are seeded, its onboarding modules are enabled, and the first admin is
provisioned and emailed. Features then roll out **per school** through tenant module
flags rather than to every school at once.

No pricing, packaging, or commercial terms have been decided. Nothing about cost may
be shown to schools.

## Operating Context

- **Environments.** Dev uses `*.localhost` subdomains with a seeded provider user, two
  dev schools (one in `Asia/Jakarta`, one in `Asia/Makassar` with different modules),
  and one pending application. Production is shared hosting / VPS with wildcard DNS and
  a single wildcard certificate behind Cloudflare; `APP_URL` is the central host.
- **One school = one tenant = one database row.** Tenant cache, tenant storage, and
  queued jobs all carry the tenant explicitly; there is no per-school database.
- **Time is per tenant** (`Asia/Jakarta` default), so dates and day names follow the
  school's own timezone, not the server's.
- **Devices and networks.** Teachers and staff: low-end Android phones, shared
  devices, slow/mobile networks. School admin: office desktop or laptop. No native
  app and no app store — mobile web is the delivery, and it must work on a bad
  connection.
- **School accounts are always created by someone else.** Either the school admin
  creates them (directly with a password, or by email invitation), the system
  provisions the first admin on approval, or the school makes student and teacher
  accounts from the people records (NIS or NIP plus a first password that must be
  changed). School users have no public registration and no self-signup. There are
  exactly two public registrations, each a central account outside any school: a
  prospective **school** (the application form) and a **PPDB applicant**.
- **Deactivation is soft and irreversible-looking by design.** Rows and roles are
  kept for audit; a deactivated user is refused at login and their active session is
  closed on the next request. Two anti-lockout invariants hold: nobody can deactivate
  themselves, and the last active school admin of a tenant cannot be deactivated.
- **Roles are machine names in the database** (`admin-sekolah`, `guru`, `staf-tu`,
  `siswa`); human labels ("Admin Sekolah", "Guru", "Staf/TU", "Siswa") live in config
  only. Each business module attaches its own permissions to them (for example
  attendance recording for teachers, PPDB review for Staf/TU, everything for the
  school admin), and the school admin can see them in the roles page.
- **Email is transactional only**, sent as queued mailables directly; there is no
  notification center or preference store. Password and set-password links expire
  after 60 minutes. A dead invite is handled by issuing a new invitation, not by a
  resend button.
- **WhatsApp is per school, and the school decides.** A school asks for a WhatsApp
  number, the provider approves, and the school links it by QR; messages then leave
  from the school's own number through the queue. Modules register the kinds of
  notice they can send (absences, gate in/out, PPDB results); every kind starts off,
  and the school switches it on and words it. The product never messages anyone for a
  kind the school has not switched on.
- **Onboarding ritual, end to end.** Applicant submits the public form on the central
  host → pending application → provider reviews and approves (correcting
  name/slug/timezone if needed) or rejects with a note → tenant, roles, module flags,
  and first admin are created → set-password email → the new admin sets a password and
  signs in on the school domain.
- **Anti-enumeration is policy, not accident.** Login, forgot-password, and
  set-password answer identically regardless of account state; unknown emails,
  deactivated users, and password-less users get the same response and no email.
  Joining a school with a code answers one message for every refusal (unknown,
  suspended, no PPDB, no open period), so nobody can list which schools use the
  product. The public forms are IP-throttled and carry a honeypot.
- **Admissions ritual, end to end.** The school admin sets a period, waves, paths with
  a quota, and builds the registration form (fields, order, required or optional,
  file uploads) → shares the school link or code → the applicant makes an account,
  verifies the email, joins the school and sends the form (locked there once sent; only
  the committee can cancel) → the committee verifies, or asks for a correction → one
  score per applicant, decisions within each path's quota → the results are announced
  (visible to the applicant only then, and optionally by WhatsApp) → the committee
  records re-registration, which makes the student in master data without a class →
  the school places the student in a class and makes the account.
- **Hosting constraints that are felt as speed.** No Octane, no Supervisor; queued
  work runs from a cron-driven database queue. Perceived latency on a slow connection
  is a product constraint, not just an implementation detail.
- **Terminology.** *Sekolah* (school), *tenant* (one school), *Admin Sekolah*,
  *Guru*, *Staf/TU*, *pengajuan* (application), *provider* (the SaaS-company side),
  *slug* (the school's subdomain label).

Not built yet, per the repo's recorded plans (`docs/ai/plan`): a module-expiry job,
real document verification for PPDB (today the committee's decision stands in for it),
`ppdb` inside the subscription plans (a provider switches it on per school), and
deciding which plans keep Absensi (today every plan includes it).

## Capabilities and Constraints

- **Two surfaces, two audiences:** the school portal (tenant staff) and the central
  provider console (SaaS operator). The incumbent UI already separates them visually.
- **Tenancy:** single database + `tenant_id`; `tenants.id` and every `tenant_id` are
  ULIDs. The tenant is resolved from the **school code**
  (tenant id today, NPSN later) typed on the login form or carried by an emailed link,
  then remembered in the session; hosts play no part (the provider console lives on its
  own `console.*` host). A logged-in session cannot be switched to another school, and an
  unknown code fails closed with a generic error.
- **Architecture:** a Laravel modular monolith with enforced boundaries
  (Shared → Platform → Identity → Core → feature modules). Modules talk only through
  public contracts; feature modules never depend on each other, and no Eloquent
  relations or cross-module foreign keys cross a boundary.
- **Auth:** tenant login/logout, forgot password, reset password, set-password
  activation, and a separate provider guard. Login is rate-limited per
  tenant + email + IP. Sessions are host-scoped cookies.
- **User management:** the school admin can create users (with or without an email
  invitation), edit name and roles, deactivate/reactivate, and send a reset link.
  Actions are gated by `identity.users.*` permissions plus a policy that re-asserts
  same-tenant scope.
- **Per-tenant modules:** feature modules are flagged per tenant with enable/disable
  and optional expiry; `core` is always active. A disabled module returns 403, not 404.
  Built so far: Core (master data, academics, import, statistics and reports, student
  and teacher accounts, the school's WhatsApp page, the Beranda landing, which shows
  each role its own blocks: the office the day's attendance and applicants waiting,
  a teacher the lessons of the day, a student their own day and month), Absensi
  (gate and per-lesson attendance by the student's one-time QR or by hand) and PPDB
  (admissions, above). Roles and permissions have a page for the school admin.
- **Public surface:** two groups of unauthenticated pages — the school application form
  on the central host (plus a generic "awaiting review" response), and the PPDB
  applicant's account pages under `/calon-siswa` (register, sign in, verify, reset, join
  a school, the registration form and the applicant's own status). Both are
  rate-limited and answer generically.
- **Stack constraints already fixed by the codebase:** Laravel + Inertia SPA (SSR-capable)
  with React 19, Tailwind CSS 4, and Wayfinder-generated routes; each module owns its
  own pages and components; TypeScript is type-checked and formatted in CI.
- **Language:** all end-user-facing text — UI, validation errors, and transactional
  email — is Bahasa Indonesia. Internal docs and code comments are written in
  Indonesian or English and are not user-facing.
- **Undecided product facts:** no accessibility target has been set; no success metric
  is defined; the provider company has no name, logo, or color identity to display.

## Brand Commitments

- The product name is **SIMAS** — *Sistem Informasi Manajemen Sekolah*. It is
  committed: do not rename it or re-expand the acronym.
- **No brand identity exists yet.** There is no logo, mark, wordmark treatment, or
  color system in the repo (only the framework starter's favicon). Branding is open to
  be defined later; nothing may assume a specific mark, color, or visual identity.
- Voice: Bahasa Indonesia, plain, calm, and institutional — no marketing enthusiasm.
  Existing approved copy reads like "Daftarkan sekolah Anda" and "Tim kami akan
  meninjau pengajuan dan mengirim email aktivasi ke admin sekolah setelah disetujui."
  That register is the baseline; the provider is spoken of as "tim kami", never as a
  vendor or a salesperson.

## Evidence on Hand

- **No customer evidence exists.** No pilot school, no real user session, no
  testimonial, no case study, no usage data, no screenshot of real use. Future work
  **must not fabricate** any of these. Every school, user, and application visible in
  the running app is dev seed data (sekolah-a, sekolah-b, sekolah-c's pending
  application, `admin@sekolah-a.test`, a dev provider user).
- **No brand assets** beyond the framework starter icons in `public/`.
- **Product and process truth that does exist and is safe to reuse:** the phase plans
  under `docs/ai/plan/fase-1` to `fase-11` (explicitly locked decisions — the de facto
  spec, including the PPDB plans in `fase-11`), `docs/architecture/modular-monolith.md`
  (canonical tenancy and boundary policy), and each module's `CONTRACT.md`
  (Platform, Identity, Core, Attendance, Ppdb, Shared).
- **Behavior is proven only by tests** under `modules/*/tests/Feature/` (tenancy
  isolation, auth, roles, deactivation, password reset, application approval,
  invitations, provider review, master data, attendance, admissions), plus a Pest
  browser suite (`tests/Browser`) and a Playwright suite (`tests/E2E`) for whole
  journeys. Nothing has been tried by real users on real devices.

## Product Principles

1. **Staff-mobile-first.** Design for a teacher on a low-end Android phone, on a slow
   connection, sharing a device. The school's office desktop is the second surface,
   never the assumed one.
2. **One school, one world.** A tenant's data, session, cache, storage, roles, and
   module flags are separate from every other school's. Any bleed between schools is a
   defect, not an edge case.
3. **A human approves; the system provisions.** No school exists and no account is
   usable until a provider operator approves the application. Approval is a deliberate
   human step that also corrects the school's own data. The same holds inside a school:
   an applicant becomes a student only when the committee records it.
4. **Calm administration over cleverness.** Authentication, approval, and user
   management are high-stakes and low-frequency. They must be legible and hard to
   get wrong, and they must never be the moment a school locks itself out.
5. **Fail closed and answer plainly.** Every boundary — tenancy, permission, token
   scope, deactivated account, unknown email — refuses safely and answers generically
   instead of revealing what exists. Everything the user reads is in Bahasa Indonesia.

## Accessibility & Inclusion

No accessibility target or standard has been set (e.g. WCAG level) — **undecided**,
and it should be asked about before any interface work claims conformance. The
inclusion-relevant fact that *is* confirmed: the primary user works on a low-end
Android phone on a slow, shared-device, mobile network, so performance, legibility at
small sizes, and clear non-color status signals are requirements of the product, not
polish.
