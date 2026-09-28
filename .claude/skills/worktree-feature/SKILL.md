---
name: worktree-feature
description: "Lease a treehouse worktree and develop a feature inside it, isolated from the main checkout. Trigger when the user asks to work in a worktree baru / new worktree, develop or explore a feature in isolation, or run parallel agents (e.g. 'dua agent paralel', '2 agent'), or says things like 'di worktree baru saya ingin kembangkan fitur X'. Handles: aligning the pool base branch, leasing slots, seeding gitignored files (treehouse v2.3.0 has no .worktreeinclude support), creating a branch, then doing the work directly or fanning out one subagent per slot. Do not use for ordinary work in the main checkout."
---

# Worktree Feature Development

You are leasing an isolated treehouse worktree for feature work. The pool lives at `~/.treehouse/<repo>` and is configured by `treehouse.toml` at the repo root. Resolve the binary first:

```bash
TH="$LOCALAPPDATA/treehouse/treehouse.exe"; [ -x "$TH" ] || TH=treehouse
```

## Step 1 — Align the pool base branch (self-healing)

treehouse v2.3.0 ignores `base_branch` in `treehouse.toml` and cuts worktrees from `origin/HEAD`. This repo's active branch is recorded in `treehouse.toml` (`base_branch = "test"` — PR fitur menuju `test`; lihat `.ai/rules/git-workflow.md`). Make `origin/HEAD` agree:

1. Read `base_branch` from `treehouse.toml`.
2. `git symbolic-ref refs/remotes/origin/HEAD` — if it does not resolve to `refs/remotes/origin/<base_branch>`, run `git remote set-head origin <base_branch>`. This is a local-only setting, safe to repeat.

If a worktree was leased before this fix, return it (Step 6) and lease a fresh one — its code is stale.

## Step 2 — Lease

Acquire one slot per feature / per parallel agent:

```bash
"$TH" get --lease 2>&1 | grep '\.treehouse' | tail -1
```

The last matching line is the worktree path. The `post_create` hooks (composer install, npm install, wayfinder:generate, migrate) run automatically during this call — wait for it to finish before moving on.

## Step 3 — Seed (required on treehouse v2.3.0)

v2.3.0 does not support `.worktreeinclude`, so copy the gitignored-but-needed files from the main checkout into the worktree yourself (run from the main checkout root):

```bash
for item in .env auth.json database/database.sqlite AGENTS.md CLAUDE.md .mcp.json boost.json skills-lock.json .agents .claude .freebuff; do
  [ -e "$item" ] && cp -r "$item" "<worktree-path>/"
done
rm -f "<worktree-path>/.claude/scheduled_tasks.lock"
```

(Once treehouse ships `.worktreeinclude` support and the manifest is committed, drop this step.)

## Step 4 — Branch before committing

Worktrees sit on detached HEAD. Before any commit:

```bash
git -C "<worktree-path>" switch -c feat/<short-slug>
```

## Step 5 — Do the work

- **Single feature**: work directly inside the worktree (cd into it, or absolute paths on every command). Never mix main-checkout paths into worktree commands.
- **N parallel agents**: spawn one subagent per slot (background, in one message so they run concurrently). Every subagent prompt must state:
  - its worktree path — work ONLY there;
  - the feature task;
  - `git switch -c feat/<slug>` before committing;
  - run the narrowest tests: `vendor/bin/pest <path> --compact` from inside the worktree;
  - report back: commits made + test results.
  Never give two agents the same slot or knowingly overlapping files.
- **Dev servers**: the seeded `.env` has `APP_URL=localhost:8000`. If two slots run `artisan serve` / `vite` at once, adjust the port and `APP_URL` per slot first.

## Step 6 — Returning slots (only when the user asks, or the slot is stale)

```bash
"$TH" return <worktree-path>
```

- On v2.3.0 a slot with seeded/installed files counts as "uncommitted changes" — `return` then prompts `Clean and return? [Y/n]`. In a non-interactive shell it aborts; that is fine. Do NOT reach for `--force` on your own: it **cleans and resets** the worktree, discarding uncommitted work. Surface the situation to the user and let them decide.
- If the user confirms nothing valuable is in the slot, `"$TH" return --force <worktree-path>` is the non-interactive path.
- NEVER run `destroy` or `prune`, and never pass `--include-unlanded` / `--include-in-use` / `--include-leased`, unless the user explicitly asks.
- Stale-pool cleanup: `"$TH" prune` (dry-run) first; `--yes` only on user approval.
