---
version: 1
slug: "m-resources-js-pages-platform-landing-tsx-189a448e"
primary_target: "modules/Platform/resources/js/Pages/Platform/Landing.tsx"
related_targets: ["app/Http/Controllers/EntryController.php"]
---

# Landing page publik (Lembar)

## Scope

Whole new surface with its own visual world, deliberately distinct from the
dashboard (school portal + console: sky primary, Geist, square corners).
Persuade mode. Route `/` for a visitor with no session; a visitor with a
remembered school code, a school user, an applicant or the console host keep
their old redirects (`App\Http\Controllers\EntryController`). Page lives in
Platform (`Pages/Platform/Landing.tsx`), content in
`Components/Landing/content.ts`, world in `Components/Landing/landing.css`.

## Audience, job, action

- **Audience** — whoever decides for a school (kepala sekolah, operator/TU,
  admin), phone or office desktop; secondarily staff looking for login and
  PPDB applicants.
- **Job** — understand what SIMAS is, trust that a person reviews every
  school, and apply.
- **Action** — graphite "Daftarkan sekolah" (applicant.register); quiet
  "Masuk ke sekolah Anda" (login); PPDB and applicant login as tertiary links.
- **Proof (real only)** — the six modules, the real five-step onboarding,
  default-role grants from Identity's roles.php, trial days from
  `billing.trial_days`. No customers, figures or prices. Demo school code
  `123456` is labelled "Contoh pengisian".

## Direction contract

**THESIS** — The page is a Lembar Jawaban Komputer: a form every Indonesian
school has handled, filled once and correctly. It refuses the SaaS hero +
screenshot + feature-card grid by making the hero a working answer sheet.

**OWN-WORLD** — White paper, cobalt drop-out ink `#2350c8` (deep `#173a99`,
line `#b3c4ee`, tint `#eef3fd`) for every rule, label and bubble ring;
graphite `#1d1d1f` for text, fills and the primary button; black timing marks;
2px corners; ink bands as section heads; one ink-drenched closing band.
Archivo variable (condensed 72–85% for heads, 118% wordmark), Azeret Mono 500
only for written digits and step numbers. Both self-hosted, latin subset.

**STORY** — The visitor sees a school's application being filled in, learns
modules switch on per school and that a person reviews it, reads who does
what, the standing rules, and applies.

**FIRST VIEWPORT** — Timing rail left edge; masthead with SIMAS wordmark,
ink caps tagline, Calon siswa / Masuk / graphite "Daftarkan sekolah"; left
6/12: condensed headline "Administrasi sekolah, diisi sekali dan benar.",
lead, CTA pair, trial line under an ink rule; right 6/12: the application
sheet (corner marks, kode sekolah bubble columns, jenjang, toggleable modul
bubbles, status Diajukan → Ditinjau tim kami → Disetujui).

**FORM** — LJK answer sheet, position 6 of 7 on the grounded list, seed key
c4f27f53 (assigned). User changed the ink from pink-red to blue.

**SIGNATURE INTERACTION** — the sheet fills in 2B pencil mark by mark on load
(280ms scale/opacity, staggered, off under reduced motion); module bubbles
toggle by hand; the timing rail fills with reading progress.

**FINISH** — unreviewed and undocumented is unfinished; this build ends with
the finish review, the verdict, DESIGN.md, and every shipping raster carrying
its provenance.

## Unresolved

- Global fonts (Geist, Instrument Sans) still download on the landing.
- `VITE_APP_NAME` unset: tab title ends in "- Laravel" (app-wide).
