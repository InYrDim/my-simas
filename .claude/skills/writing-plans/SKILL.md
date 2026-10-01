---
name: writing-plans
description: "Writes and maintains implementation plan documents under docs/ai/plan/fase-N/ with a fixed shape: context, goal, problem scope (in/out), constraints and decisions, design, staged status table, per-stage task checklists with acceptance criteria, testing, verification, and an execution log. Use whenever the user asks for a plan, rencana, 'buat plan', 'sarankan rencana', tahapan, roadmap of a feature or phase, wants the flow of a feature decided before building, or asks to write a plan down to docs — even if they do not say 'document'. Also use to update the status of an existing plan when a stage starts or finishes ('perbarui status plan', 'tahap 2 selesai') and to revise a plan whose scope changed. Not for one-file fixes or changes small enough to describe in one sentence."
---

# Writing Plans

A plan here is a document a **fresh agent session can execute without this conversation**. That is the test for every line: if a reader who never saw the chat cannot act on it, it is missing a path, a decision, or a definition. The plan is also a **living document** — it is edited while the work runs, so its status is always true.

Plans live in `docs/ai/plan/fase-N/<slug>-plan.md` and are written in Indonesian (code identifiers, paths and commands stay as they are). `docs/ai/plan/fase-4/tenant-onboarding-plan.md` is a worked example.

Pick the mode from the request:

- **New plan** — follow "Creating a plan".
- **Status update** — follow "Keeping status true".
- **Revision** (scope changed) — follow "Revising a plan".

## Creating a plan

1. **Read before designing.** A plan that contradicts the code is worse than none. Read, as far as they are touched:
   - the `CONTRACT.md` of every module involved (what it owns, its public surface)
   - `docs/architecture/modular-monolith.md` and `.ai/rules/index.md` (plus the rule files it maps to)
   - earlier plans in `docs/ai/plan/` that the work builds on
   - the existing code: look for actions, contracts, components and test helpers to reuse before proposing new ones
2. **Ask only what is the user's to decide** — scope, order, product behaviour, anything that changes a locked decision. Offer one recommendation per question. Do not ask what the code can answer; go and look instead. A conflict between the request and an existing rule is always worth raising, because silently picking a side is how plans go wrong.
3. **Choose the location.** `docs/ai/plan/fase-N/<slug>-plan.md`, slug in kebab-case naming the outcome. If it is unclear whether this belongs to an existing fase or a new one, ask.
4. **Write from the template.** Read [template.md](template.md) and fill every section. Keep the headings; a section that truly does not apply says so in one line rather than disappearing, so readers know it was considered.
5. **Check it** against "Before handing over" below and fix what fails.
6. **Summarise and stop.** Give the user the path, the goal, the stages, and anything that surprised you. Writing a plan is not permission to execute it — wait for the go-ahead.

In plan mode only the plan file can be edited: write the plan there and make "tulis ke `docs/ai/plan/...`" the first execution step, so the document exists before any code does.

## What makes a plan executable

- **Goal as observable behaviour.** "Setelah ini, admin sekolah bisa …" — something a person can do and see, not "refactor X". It tells the executor when to stop.
- **Scope with explicit non-goals.** State what is left out and why. Unstated non-goals are where scope creep and wrong assumptions come from.
- **Constraints quoted, not copied.** Name the specific architecture rules that bind this work (layer order, no foreign keys except `tenant_id`, public surface only in `Contracts/`, …) with a pointer to their source. Copying the whole rulebook buries the two rules that matter.
- **Vertical stages.** Each stage delivers something that works and can be verified on its own, touches one module where possible, and ends at a point where stopping is safe. This project stops between stages for the user's approval, so a stage that cannot stand alone blocks everything.
- **Tasks with paths and proof.** Every task names the file or directory it touches; mark paths that do not exist yet with `(baru)` so the executor does not go looking for them, and check that every other path really exists. Every stage ends with "Selesai bila" — the observable result plus the exact command that shows it.
- **Reuse named.** When existing code is to be reused, give its path, so the executor does not rebuild it.
- **Exact commands.** `php artisan test --compact modules/Core`, not "run the tests".
- **Boundaries for the executor.** Three short lists — Selalu / Tanya dulu / Jangan — covering the actions specific to this work (migrations on shared data, changing a contract, deleting tests, pushing).

Prefer relevance over length. A long plan is not a better plan; the reader's attention is the budget.

## Keeping status true

The status table is what the user reads first, so it must never claim more than what is verified.

- Set a stage to 🟡 when work on it starts.
- Set it to ✅ only after that stage's tests and verification actually passed, and write a short note in Catatan: what was built and anything that differed from the plan.
- Tick each task as it is finished, not in a batch at the end.
- Record every deviation in "Log keputusan" with the date and the reason. A deviation that is only in the code is invisible to the next reader.
- Unexpected findings (a rule that bit, a wrong assumption) go under "Temuan".
- Update the "Diperbarui" date and the document status (Draf → Disetujui → Berjalan → Selesai).
- After a stage is ✅, stop and wait for approval before starting the next.

If tests fail or a step was skipped, say so in the note and leave the stage 🟡.

## Revising a plan

When scope changes, rewrite the affected sections so the document still reads as one coherent plan — do not append a contradiction at the bottom. Add a line to "Log keputusan" saying what changed and why. Completed stages keep their ✅ and notes; they are history.

## Before handing over

- [ ] A reader without this conversation could start the first task.
- [ ] Goal is observable; every stage has "Selesai bila" with a command.
- [ ] Di luar cakupan is filled in.
- [ ] Every task names a path; existing paths were checked, new ones are marked `(baru)`; reused code is named with its path.
- [ ] Constraints cite their source and do not conflict with a module's `CONTRACT.md`.
- [ ] Stages are vertical and each can be verified alone.
- [ ] Decisions made by the user are recorded with the date.
- [ ] No statement depends on "tadi", "di atas dalam obrolan", or other chat context.
