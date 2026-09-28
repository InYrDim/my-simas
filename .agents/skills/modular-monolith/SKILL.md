---
name: modular-monolith
description: Guidance for creating, extracting, or modifying modules in this Laravel + Inertia + React modular-monolith codebase. Use this whenever creating a new module, moving/extracting existing legacy code into a module, adding a controller/model/action that touches a module boundary, wiring cross-module communication, writing or updating a module's CONTRACT.md, or running/interpreting a boundary check (Deptrac, ESLint boundaries). Also use whenever it's unclear which module a piece of domain logic belongs in — do not guess without consulting this skill first.
---

# Modular Monolith — Laravel + Inertia + React

This codebase is being incrementally retrofitted from a legacy Laravel app
into a modular monolith. Most existing code is still in `app/`. New and
touched code moves into `Modules/<Name>/`. Follow this skill for any task
that touches module structure or boundaries.

The non-negotiable MUST/MUST NOT rules live in the root `AGENTS.md` — read
that first if you haven't already this session. This file is the detailed
"how."

## The three things you're always doing

1. **Adding new code** → put it in the correct module, never in `app/`.
2. **Extracting legacy code** → follow `references/migration-playbook.md`. One
   module at a time, incrementally, tests after every file move.
3. **Crossing a module boundary** → only through `Contracts/`, never through
   `Domain/`, `Infrastructure/`, or `Http/` of another module.

## Module folder shape

Full tree with explanations: `assets/MODULE_TEMPLATE.md`. Read it before
creating any new module folder — copy the tree exactly, don't improvise names.

## Writing a module's CONTRACT.md

Template: `assets/CONTRACT_TEMPLATE.md`. Every module must have one. Fill in
every section — don't leave "TBD" placeholders in a contract you're committing.

## Decision table

| Situation | Correct action |
|---|---|
| New feature entirely within one existing module | Add it inside that module, following its existing folder conventions. |
| New feature that doesn't fit any existing module | Stop and ask the user whether this is a new module or belongs in an existing one — don't create a module speculatively. |
| Controller needs data from another module | Constructor-inject that module's `Contracts\*` interface, resolved via the container. Never `use Modules\Other\Domain\...` directly. |
| Need to react to an event in another module | Dispatch/listen via Laravel events (`app/Events`, module-local listeners). Not a direct method call across modules. |
| Shared DTO/value object needed by 2+ modules | `Modules/Shared/Domain/`. Never copy-paste it into each module. |
| Inertia page belongs to a module's feature | `Modules/<Name>/resources/js/Pages/`, rendered via the module's controller. |
| Shared React component (button, layout, etc.) | `Modules/Shared/resources/js/Components/`. Never import one module's component tree from another module. |
| You're touching a file in legacy `app/` for an unrelated bug fix | Fix only what's needed. Don't opportunistically migrate it — that's a separate, deliberate task (see migration playbook). |
| Extracting a legacy feature into a module | `references/migration-playbook.md` — follow it step by step, don't skip the "run tests after each move" step. |
| A Deptrac or ESLint boundary check fails | The check is correct until proven otherwise. Fix the violation. Do not edit the check to make it pass, and do not suppress/ignore the failing rule, without explicit user confirmation. |
| Genuinely unsure which module owns something | Stop. Ask the user. Guessing here is the single most common way this architecture rots. |

## Workflow for this session

1. Identify which module(s) the task touches. If it's legacy code and the
   task is "add a feature," check whether the touched area has already been
   extracted — if not, work in `app/` as-is; don't migrate mid-feature unless
   asked.
2. Read the CONTRACT.md of every module you're touching or calling into.
3. Make the change, respecting the decision table above.
4. Run boundary checks:
   - `vendor/bin/deptrac analyse` (PHP)
   - `npx eslint . --ext .jsx,.tsx` (React)
5. Run tests scoped to the affected module(s).
6. Update CONTRACT.md if the public interface changed.
7. Report failures honestly — a task with a failing boundary check or test is
   not done, regardless of whether the original feature "works."

## When you need more detail

- Extracting/migrating legacy code → read `references/migration-playbook.md` in full before starting.
- Creating a brand-new module from scratch → read `assets/MODULE_TEMPLATE.md`.
- Writing a contract → read `assets/CONTRACT_TEMPLATE.md`.
