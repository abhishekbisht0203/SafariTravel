#!/usr/bin/env bash
# Safari Travel — start the local WordPress server (macOS / Linux, no Docker).
#
# PHP's built-in server on http://localhost:8080 with the router that emulates
# WordPress rewrites. Development only — not for production traffic.
#
#   Terminal 1:  ./scripts/start-local.sh
#   Terminal 2:  npm run dev
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP was not found on PATH. See ./scripts/setup-local.sh for details." >&2
  exit 1
fi

PHP_BIN="${PHP_BIN:-php}"

exec "$PHP_BIN" scripts/safari.php start "$@"
