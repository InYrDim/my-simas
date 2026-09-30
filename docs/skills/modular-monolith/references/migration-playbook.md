# Migration Playbook — Extracting a Legacy Feature into a Module

Read this in full before extracting any legacy code. Do not skip steps or
batch them to "save time" — the incremental approach is the point.

## Ground rule

**One module. One session (or a few small sessions). Never a big-bang rewrite.**
If a proposed extraction touches more than one bounded concept, stop and ask
whether it should be split into separate module extractions.

## Steps

1. **Pick the target.** Choose one bounded concept (e.g. "Invoicing",
   "Notifications") — ideally the one with the fewest existing cross-references
   in the legacy code. Confirm scope with the user if it's ambiguous.

2. **Create the empty module skeleton first.** Use `assets/MODULE_TEMPLATE.md`.
   Do not move any files yet. Commit/checkpoint this step alone.

3. **Inventory what needs to move.** List every model, controller, migration,
   Inertia page, and test file that belongs to this concept. Share this list
   before moving anything if the task is large.

4. **Move one file at a time.**
    - Move the file into its new module location.
    - Update its namespace.
    - Update every place that referenced the old namespace (search the whole
      repo, not just the obvious callers).
    - Run the relevant tests immediately.
    - Only move on to the next file once this one passes.

5. **Add `Contracts/` last, after the internals are moved.** Define the
   minimal public interface other code actually needs — don't expose more
   than necessary. Update any legacy callers to depend on the contract
   instead of the class you just moved, if they can't be migrated into the
   module themselves.

6. **Write CONTRACT.md** using `assets/CONTRACT_TEMPLATE.md` once the module
   is stable.

7. **Add the module to `deptrac.php`** (Deptrac 4.x PHP config — there is
   no `deptrac.yaml`) as its own pair of layers (`<Name>` internal +
   `<Name>Public` for `App/Contracts/**`), wire the ruleset per the layer
   order in AGENTS.md, and make sure glue layers (root `App`/`Database`)
   may reach the new module's Public surface — not its internals.

8. **Run Deptrac** (`composer deptrac`). Expect it to report pre-existing
   violations elsewhere in the codebase that are unrelated to this
   extraction — that's expected in a retrofit. Only the module you just
   extracted needs to be clean. Do not attempt to fix unrelated violations
   in this task. Also run the arch tests (`vendor/bin/pest
tests/Architecture`): they check CONTRACT.md presence, the cross-module
   FK ban (except tenant_id), Shared isolation, and the Spatie ban outside
   Platform.

9. **Run the full test suite once**, not just the module's tests, before
   calling the extraction done — legacy code may have been implicitly relying
   on the old location.

10. **Stop.** Do not chain straight into extracting a second module in the
    same session unless the user explicitly asks for it.

## Signs you're doing it wrong

- You've moved more than ~5–10 files without running tests in between.
- You're touching files that belong to a different bounded concept than the
  one you started extracting.
- You're tempted to "just inline" a dependency on another module's internals
  to make the extraction easier — this is the boundary check's job to catch;
  don't route around it.
- You're editing `deptrac.php` to silence a failure instead of fixing the
  actual dependency.
