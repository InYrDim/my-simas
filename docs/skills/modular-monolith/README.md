# Modular Monolith — skill copy (tracked)

This is the **tracked copy** of the `modular-monolith` agent skill,
mirrored from `.claude/skills/modular-monolith/` (which is gitignored in
this starter kit so each dev/agent machine carries its own).

- `SKILL.md` — behavior guide for agents working on module structure.
- `assets/MODULE_TEMPLATE.md` — the authoritative module folder shape.
- `assets/CONTRACT_TEMPLATE.md` — the CONTRACT.md shape every module must
  fill (arch test enforces presence).
- `references/migration-playbook.md` — step-by-step legacy extraction.

## Sync rule

The living source is `.claude/skills/modular-monolith/`. When it changes,
re-copy the four files here and commit, so the team and CI see the same
rules. The canonical boundary policy lives in
`docs/architecture/modular-monolith.md`; these files must never contradict
it or the standing rules in `AGENTS.md`.
