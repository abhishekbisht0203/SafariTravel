#!/usr/bin/env bash
# Safari Travel — optional Docker workflow.
#
# Docker is NOT required for local development. This script exists only for
# developers who prefer a containerised stack or for reproducing the deployment
# image. It is the original flow, kept working and idempotent.
#
#   ./scripts/setup-docker.sh          # containers + Aiven + first-run install
#   ./scripts/setup-docker.sh --seed   # force a re-seed
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "error: .env is missing. Copy .env.example to .env and fill in your Aiven credentials." >&2
  exit 1
fi

set -a; source .env; set +a

WP_PORT="${WP_PORT:-8080}"
WP_SITE_URL="${WP_SITE_URL:-http://localhost:$WP_PORT}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:-}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@localhost.test}"
WP_SITE_TITLE="${WP_SITE_TITLE:-Safari Travel}"

command -v docker >/dev/null 2>&1 || { echo "error: docker is not installed." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "error: Docker Compose v2 is required." >&2; exit 1; }

CLI=(docker compose run --rm wpcli)

echo ""
echo "════════════════════════════════════════════"
echo "  Safari Travel — Docker setup (optional)"
echo "════════════════════════════════════════════"
echo ""

echo "▶ Starting containers…"
docker compose up -d --remove-orphans

echo "▶ Waiting for WordPress to answer…"
for _ in $(seq 1 60); do
  if docker compose exec -T wordpress curl -sf http://localhost >/dev/null 2>&1; then
    break
  fi
  sleep 3
done

echo "▶ Checking the WordPress install…"
if "${CLI[@]}" core is-installed >/dev/null 2>&1; then
  echo "  WordPress already installed — leaving content untouched."
else
  if [[ -z "$WP_ADMIN_PASS" ]]; then
    echo "error: WP_ADMIN_PASS is not set. Add it to .env." >&2
    exit 1
  fi
  echo "  Installing WordPress…"
  "${CLI[@]}" core install \
    --url="$WP_SITE_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "▶ Activating theme and plugins…"
"${CLI[@]}" theme activate safari-theme
for plugin in safari-core safari-leads safari-search; do
  if "${CLI[@]}" plugin is-installed "$plugin" >/dev/null 2>&1; then
    "${CLI[@]}" plugin activate "$plugin" || true
  else
    echo "  (plugin $plugin not installed — skipped)"
  fi
done

echo "▶ Flushing permalinks…"
"${CLI[@]}" rewrite structure '/%postname%/'
"${CLI[@]}" rewrite flush

echo "▶ Installing npm dependencies and building assets…"
npm ci
npm run build

if [[ "${1:-}" == "--seed" ]]; then
  echo "▶ Seeding demo content…"
  "${CLI[@]}" safari seed --force
fi

echo ""
echo "  Site  : $WP_SITE_URL"
echo "  Admin : $WP_SITE_URL/wp-admin"
echo ""
