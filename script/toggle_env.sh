#!/usr/bin/env bash
# Alternates .env between .env.local and .env.production on every run.
# First run (or when .env matches neither) -> local; then production, local, ...
# Pass "local" or "production" to pick one explicitly (used by composer dev/prod).

set -euo pipefail

cd "$(dirname "$0")/.."

for source in .env.local .env.production; do
    if [[ ! -f "$source" ]]; then
        echo "Error: $source tidak ditemukan." >&2
        exit 1
    fi
done

if [[ $# -gt 0 ]]; then
    target="$1"
    if [[ "$target" != "local" && "$target" != "production" ]]; then
        echo "Error: argumen harus 'local' atau 'production'." >&2
        exit 1
    fi
elif [[ -f .env ]] && cmp -s .env .env.local; then
    target="production"
else
    target="local"
fi

cp ".env.$target" .env

echo "Sekarang berada di env: $target (.env <- .env.$target)"
