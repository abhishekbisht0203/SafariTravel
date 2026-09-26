#!/usr/bin/env bash
# Deprecated alias kept for backwards compatibility.
#
# Historically this was the only setup entry point and it required Docker.
# Local setup no longer needs Docker — use the new name so the intent is clear:
#
#   ./scripts/setup-local.sh          # PHP + Aiven + Vite, no Docker
#   ./scripts/setup-docker.sh         # containerised stack (optional)
#
# This wrapper simply forwards to setup-local.sh, which is safe to re-run.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "note: scripts/setup.sh is now an alias for scripts/setup-local.sh (no Docker required)." >&2

exec "$SCRIPT_DIR/setup-local.sh" "$@"
