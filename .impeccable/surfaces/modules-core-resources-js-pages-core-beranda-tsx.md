---
version: 1
slug: "modules-core-resources-js-pages-core-beranda-tsx"
primary_target: "modules/Core/resources/js/Pages/Core/Beranda.tsx"
related_targets: ["routes/web.php"]
---

# Beranda Sekolah (tenant landing)

## Scope

New whole surface inside the established world (*Buku Besar Sekolah*, see
DESIGN.md). Operate mode. Phone-first: teachers and staff on low-end
Android lead, school admin on desktop is the second audience. The route is
`/beranda` on the tenant side (named `home`, so the auth redirect lands
there); the app root `/` now dispatches by tenancy state. The page lives in
the Core module, which reads PlatformPublic + IdentityPublic only.

## Audience, job, action

- **Audience** — a school admin who has just signed in, often on a shared
  phone, in daylight or a bright room.
- **Job** — know whether the school is actually set up, and what single
  thing to do next.
- **Action** — one green "Undang staf" (the only task this page can finish
  today) and one quiet top-bar text link to "Pengguna".
- **Proof / content (real only)** — school name, slug, the school's own
  timezone and today's date in it, the account counts from Identity's
  `currentTenantSummary()` (total / active / awaiting activation /
  deactivated / without role), and the role labels the tenant actually has.
- **Constraints** — Bahasa Indonesia; zero icons (words, not icons); one
  green meaning confirmed or go; no color-only state, the word carries it;
  1px rules before any shadow (no shadow here); exactly three type sizes on
  a 14px base; touch targets ≥44px; no gradients, glass, or blur; the app's
  single named breakpoint `sm` (640px) is the only adaptive qualifier.

## Direction contract

**THESIS** — This page is today's page of the school's own ledger, dated in
the school's own timezone: the one fact no two schools share. It refuses the
SaaS dashboard default (greeting, KPI tiles, feature grid) by leading with a
date instead of a number, and by putting the record's one unfinished line
first.

**OWN-WORLD** — White paper surface on the `form-grey` ground; 1px `rule`
hairlines do all the separating; `ledger-ink` for the day's line;
`pencil-grey`/`faint-pencil` for meta; `stamp-ink` emerald 700 for the single
confirmed action and for the one settled word; 8px corners; no shadow
anywhere; Instrument Sans at 20/600, 20/500, 14, 12 only; mono for the two
identifiers (slug, IANA zone) and nothing else.

**STORY** — The visitor understands this school is its own record with its own
clock, believes the record is kept honestly (deactivated accounts counted as
kept, never deleted; invited accounts counted as waiting, never as active),
and does exactly one thing: invite the first colleague or open the user list.

**FIRST VIEWPORT** — At 390px: white 64px top bar, hairline under, "Beranda"
left, a `pencil-grey` text link "Pengguna" right (shown only with
`identity.users.view`); then the school name at 20/600, the day's date at
20/500 beneath it, the zone in 12px mono under that; a hairline; the day's
single sentence at 14px with exactly one semibold phrase in it; then the
full-width 44px green "Undang staf" (only with `identity.users.create`).
Below the fold: ruled 12px rows — Kode sekolah (slug in mono), Peran (the
tenant's role labels), Akun ("3 total · 2 aktif", plus "· 1 menunggu" when
someone is waiting), and Dinonaktifkan ("1 · tetap tersimpan") only when
someone is deactivated. The primary action sits third in the reading order —
after the school's identity and its one unfinished line, before any
inventory.

**FORM** — "Waktu Sekolah", position 7 of seven grounded structures, dealt as
the lead of hand `176d80a6` (re-roll 1) and locked by the user. Code-led: no
comp owed, no comp exists.

**SIGNATURE INTERACTION — "catatan hari ini"** — the dated line is the
subject, not chrome. It resolves in the school's timezone on every visit
(`now($tenant->timezone)`, frozen and proven in tests against a New York
tenant), and it states, in the order work actually waits: an account with no
role → an invitation not yet answered → the truth of a school with nobody
else in it → the settled day, where the phrase `sudah punya peran` is the
page's only green word. The green action never demotes: when nothing is
unfinished the sentence changes state, the action stays. The ledger closes
for the day without navigation and without a toast. The page's one authored
moment is the day's rule being drawn once, left to right, 320ms, scale-only,
gated by `prefers-reduced-motion`; everything else uses the established
grammar — 150ms `transition-colors` and `active:translate-y-px`.

**FINISH** — unreviewed and undocumented is unfinished; this build ends with
the finish review, the verdict, DESIGN.md, and the detector pass.

## Unresolved

- Per-role counts would need a second contract crossing
  (`ResolvesUsers::rolesByName()`); until then the page states roles as
  defined, never as staffed.
- No module shelf: the only registered keys are internal (`core`,
  `identity`) and are not school-facing.
- No history and no `created_at` on the public tenant DTO — the page dates
  today, it does not narrate a timeline.
- **Provisional amber is night-only in this system** (`#fcd34d` is 1.5:1 on
  paper, far under the 4.5:1 body floor, and it is used as text only on
  `night-board`). The awaiting state therefore carries weight and words, not
  color — which matches DESIGN.md's "on paper a pending state has no chip,
  the word alone". A paper-safe amber step is a candidate system addition
  and needs explicit approval; it was not invented here.
- This surface introduces the app's first deliberate breakpoint (`sm`), now
  reconciled in DESIGN.md.
