#!/usr/bin/env bash
# scripts/setup.sh — One-command local setup for Safari Travel WordPress.
# Usage: ./scripts/setup.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ── Load .env ──────────────────────────────────────────────────────────────
if [[ -f "$ROOT/.env" ]]; then
  set -a; source "$ROOT/.env"; set +a
fi

WP_PORT="${WP_PORT:-8080}"
WP_SITE_URL="${WP_SITE_URL:-http://localhost:$WP_PORT}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:-admin123}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@localhost.test}"
WP_SITE_TITLE="${WP_SITE_TITLE:-Safari Travel}"

CLI="docker compose run --rm wpcli"

echo ""
echo "════════════════════════════════════════════"
echo "  Safari Travel — Local Setup"
echo "════════════════════════════════════════════"
echo ""

# ── 1. Start containers ────────────────────────────────────────────────────
echo "▶ Starting Docker containers…"
docker compose up -d --remove-orphans

echo "▶ Waiting for WordPress to be healthy…"
until docker compose exec -T wordpress curl -sf http://localhost > /dev/null 2>&1; do
  sleep 3
done
echo "  WordPress is up."

# ── 2. Install WordPress (idempotent) ──────────────────────────────────────
echo "▶ Checking WordPress install…"
if ! $CLI core is-installed 2>/dev/null; then
  echo "  Installing WordPress…"
  $CLI core install \
    --url="$WP_SITE_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
else
  echo "  WordPress already installed — skipping."
fi

# ── 3. Activate theme and plugins ──────────────────────────────────────────
echo "▶ Activating theme…"
$CLI theme activate safari-theme

echo "▶ Activating plugins…"
for plugin in safari-core safari-leads safari-search; do
  $CLI plugin activate "$plugin" 2>/dev/null || echo "  (plugin $plugin not yet installed — will be available after composer install)"
done

# ── 4. Flush rewrite rules ────────────────────────────────────────────────
echo "▶ Flushing permalinks…"
$CLI rewrite structure '/%postname%/'
$CLI rewrite flush

# ── 5. Install npm deps and build assets ──────────────────────────────────
echo "▶ Installing npm dependencies…"
cd "$ROOT"
npm ci

echo "▶ Building theme assets…"
npm run build

# ── 6. Seed demo content ──────────────────────────────────────────────────
echo "▶ Seeding demo content (idempotent)…"
$CLI safari seed || echo "  (seed command not yet available — run after full setup)"

echo ""
echo "════════════════════════════════════════════"
echo "  ✓ Done!"
echo ""
echo "  Frontend:  $WP_SITE_URL"
echo "  Admin:     $WP_SITE_URL/wp-admin"
echo "             user: $WP_ADMIN_USER / pass: $WP_ADMIN_PASS"
echo "  Mailpit:   http://localhost:${MAILPIT_PORT:-8025}"
echo "════════════════════════════════════════════"
echo ""
