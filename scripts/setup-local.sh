#!/usr/bin/env bash
# Safari Travel — one-command local setup (macOS / Linux, no Docker).
#
# Thin wrapper around `php scripts/safari.php setup`, which does the actual work
# in PHP so every platform behaves identically. Idempotent: safe to re-run.
#
#   ./scripts/setup-local.sh
#   ./scripts/setup-local.sh --no-seed
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if ! command -v php >/dev/null 2>&1; then
  echo ""
  echo "  PHP 8.2 or newer was not found on PATH."
  echo "  Install it (macOS: brew install php@8.2 / Debian: sudo apt install php8.2-cli) and re-run."
  echo ""
  exit 1
fi

PHP_BIN="${PHP_BIN:-php}"

exec "$PHP_BIN" scripts/safari.php setup "$@"
