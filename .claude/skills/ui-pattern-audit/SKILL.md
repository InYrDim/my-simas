---
name: ui-pattern-audit
description: "Audit a page's UI/UX in THIS project against the user's own UX rules (the sections titled 'UX:' in .ai/rules): trace every interaction on a page, compare how it is built today (modal, nested detail page, inline form, how many inputs are visible at once) with what the rules want, and delegate the judgement calls to the modal-or-page agent. Use whenever the user asks to audit, review, trace or check a page's UI/UX, says a page is too full or has too many inputs, asks whether something should be a modal, a step or its own page, or says things like 'audit halaman X', 'trace UI yang tidak sesuai', 'terlalu banyak input', 'ini harusnya modal atau halaman', 'rapikan UX halaman siswa'. Read-only: it reports gaps and proposes changes, it does not edit code."
---

# UI Pattern Audit

Find where a page differs from the UX the user wants, and say what to change. Read-only. The wanted UX lives in the project's rules, not in this skill, so a new preference never needs a change here: it only needs a rule.

## The desired state

1. **The user's own words in the request** win over everything ("saya mau ini modal saja").
2. **The UX rules.** Every section headed `## UX: ...` in `.ai/rules/*.md` (today in `.ai/rules/js.md`). Find them with Grep on `^## UX:`, then read each section to the next heading. Each states what is wanted, why, and a **Deteksi** line that says how to spot a violation. If none are found, say so and stop; there is nothing to audit against.
3. **The base rule in the same file** (components come from `modules/Shared`, never hand-rolled; URLs through Wayfinder).

When two rules pull apart, the more specific one wins and the user's words beat both. Say which you applied.

## Steps

1. **Load the rules** as above and list their titles; they become the columns of your check.
2. **Resolve the page.** From a route, URL, label or name ("halaman siswa", `/ppdb/pengaturan`) to the Inertia component: `modules/*/routes/web.php` → the controller → `Inertia::render('<Module>/<Page>')`. The file is `modules/<Module>/resources/js/Pages/<Module>/<Page>.tsx` (the module name appears twice). Also open its siblings (`Index`, `Show`) and the components it imports. If the name matches several pages, audit the most likely one and say which.
3. **Inventory.** Use Grep and Read. Record each item with `file:line`:
   - Every interaction and its current pattern: `FormDialog`, `FormModal`, `Dialog`, `AlertDialog`, `ConfirmAction`, `Sheet`, `<Link`, `router.visit`, inline `useForm` / `<Form`.
   - The inputs (`Input`, `OptionSelect`, `Textarea`, `Checkbox`, `Switch`) that are visible without any interaction, that is, outside a dialog or sheet, and how many there are per section.
   - The field count behind each form (the FormRequest rules or the form component) and the related data the entity carries (model relations, Resource collections).
4. **Apply each rule's Deteksi line** to the inventory. Mechanical findings (nesting, counts, hand-rolled components, hard-coded `href="/..."`) you can settle yourself.
5. **Delegate the judgement calls, once.** For what a count cannot settle (should this action be a modal or a page, how should a crowded page be split), call the Agent tool with `subagent_type: "modal-or-page"` and ONE prompt for the whole page: the page file, the inventory, the rule titles that apply, and the user's own words. Do not call it per interaction. If the user named several pages, one call each, in parallel (at most three).
6. **Compare and report.** A gap is any difference between today and the rules or the agent's verdict, or any close call the agent flags. Never edit files in this skill; offer the follow-up.

## Report format

Answer in Indonesian, short and scannable.

**Ringkasan:** one or two sentences: what was audited, which rules applied, how many gaps.

| Aksi / bagian | Sekarang | Seharusnya | Aturan | Lokasi | Perubahan | Usaha |
| --- | --- | --- | --- | --- | --- | --- |
| e.g. editor jalur dan kuota | 8 input selalu tampil | tabel + modal | jangan banyak input | `Settings.tsx:325` | pindah ke `FormModal` | S |

List gaps first, the most visible to the user first; what already matches goes in one line after the table. Effort: S (one file), M (a few files), L (new route, controller method and page).

**Keputusan yang tipis:** only close calls, with what would flip them.

End by offering to make the changes. Do not start them unasked.

## Notes

- A finding without a `file:line` is not a finding. Drop it.
- A Show page whose edit already sits in a modal is correct by default; do not flag it.
- Where a rule's number is a stated starting guess (for example how many inputs count as too many), report the count and let the user judge; do not present the guess as fact.
- This audit is about how interactions are built and how much is on screen, not about visual design (color, spacing, copy). For that, point to the `impeccable` skill.
- To add a new preference: record it as a rule whose heading starts with `UX:`, with a Deteksi line. This skill picks it up on the next audit.
