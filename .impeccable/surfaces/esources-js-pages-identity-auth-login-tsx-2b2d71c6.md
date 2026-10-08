---
version: 1
slug: "esources-js-pages-identity-auth-login-tsx-2b2d71c6"
primary_target: "modules/Identity/resources/js/Pages/Identity/Auth/Login.tsx"
related_targets: ["modules/Ppdb/resources/js/Pages/Ppdb/Account/Login.tsx","modules/Shared/resources/js/components/lembar/LembarShell.tsx"]
---

# Halaman masuk & akun (Lembar) — sekolah dan PPDB

## Scope

Operate mode. Every unauthenticated (and the forced change-password) page of
the school portal — Identity Auth: Login, ForgotPassword, ResetPassword,
SetPassword, ChangePassword — and of the PPDB applicant — Ppdb Account:
Login, Register, ForgotPassword, SetPassword, VerifyNotice. Extends the
landing's Lembar world (user-approved, 2026-10-08). Shared primitives live in
`modules/Shared/resources/js/components/lembar/`. Applicant (school
pemohon) pages and the provider console login are out of scope.

## Audience, job, action

- Teacher/staff on a low-end phone, admin on a desktop, applicant parent on
  a phone. Job: get in (or back in) quickly and correctly.
- Action: one graphite submit per page. Copy, labels, routes, anti-
  enumeration answers unchanged; a short "Petunjuk pengisian" adds only
  product truth.

## Direction contract

**THESIS** — Signing in is filling in one small answer sheet correctly. It
refuses the centered white card with a green button.

**OWN-WORLD** — Lembar: white paper, cobalt drop-out ink for frames, labels
and field rules, graphite for what a person writes and the submit, 2px
corners, corner marks, timing rail; Archivo condensed heads, Azeret Mono
only for the school code. One addition: correction red (teacher's red pen)
for field errors only.

**STORY** — The visitor sees which sheet this is (school or PPDB), fills a
few boxes, reads the instructions if unsure, and submits.

**FIRST VIEWPORT** — Timing rail left; 64px masthead (SIMAS wordmark,
caps sheet label); at lg a 12-col split: left 5 condensed title, lead,
petunjuk; right 7 the 2px-framed sheet with tint header strip, fields and a
full-width graphite submit. Phone: title, sheet, petunjuk.

**FORM** — LJK answer sheet extended from the landing (seed c4f27f53); user
pinned the concept, no new roll. Signature: the kode sekolah comb (one
character per box) and the timing rail filling as fields are filled.

**FINISH** — unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved

- Applicant (pemohon sekolah) pages still on the old card.
