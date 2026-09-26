# Safari Travel WordPress — Developer README

## Prerequisites

| Tool | Minimum version |
|------|-----------------|
| Docker Desktop | 4.x (with Compose V2) |
| Node.js | 18 LTS |
| npm | 9+ |
| Composer | 2.x (optional locally — CI installs it) |
| Git | 2.x |

---

## Quick start (one command)

```bash
# 1. Clone
git clone https://github.com/your-org/safari-travel.git && cd safari-travel

# 2. Environment config
cp .env.example .env
# Fill in DB_HOST / DB_PASSWORD for the remote MySQL (Aiven), or use the local DB (see below)

# 3. Spin up containers + first-run WordPress install
./scripts/setup.sh
```

The script:
1. Pulls Docker images and starts the stack (`docker compose up -d`)
2. Waits for WordPress to be healthy (connected to the remote MySQL)
3. Installs WordPress via WP-CLI (skips if already installed)
4. Activates the three custom plugins + theme
5. Runs the seed command (`wp safari seed`) to populate demo content
6. Installs npm dependencies and builds assets (`npm ci && npm run build`)

After ~2 minutes open:

| URL | What |
|-----|------|
| http://localhost:8080 | WordPress frontend |
| http://localhost:8080/wp-admin | WordPress admin (admin / admin123) |
| http://localhost:8025 | Mailpit — catches all outgoing email |

---

## Day-to-day development (no Docker)

Once the site has been installed once, running it locally needs only PHP's
built-in server and the Vite dev server. Two terminals:

```bash
# Terminal 1 — WordPress on :8080
npm run wp
#   equivalent to: php -S localhost:8080 -t wordpress scripts/router.php
#   The router is what makes pretty permalinks (/destinations/, /tours/, …)
#   work; without it PHP's built-in server only serves the homepage.

# Terminal 2 — Vite dev server + HMR on :5173
npm run dev
```

Then open **<http://localhost:8080>**. That is the only URL you need: WordPress
stays the application, and Vite only supplies the CSS/JS and the hot-reload
channel. You never have to open :5173 yourself.

| URL | What |
|-----|------|
| http://localhost:8080 | WordPress frontend — the site |
| http://localhost:8080/wp-admin | WordPress admin (admin / admin123) |
| http://localhost:5173/wp-content/themes/safari-theme/assets/dist/ | Vite dev server (assets + HMR only — not a page) |

How the two halves find each other:

- `vite.config.js` owns the dev mount path and writes it to `theme/.vite-dev`
  while the dev server runs, refreshing it every few seconds.
- `theme/inc/assets.php` reads that file. While it is fresh, the theme
  enqueues `@vite/client`, `src/main.css` and `src/main.js` from the dev server
  (as `type="module"`, so HMR works). If the dev server is not running — or
  was killed without cleaning up — the flag goes stale and the theme falls
  back to the built files in `theme/assets/dist/` automatically. You can delete
  `theme/.vite-dev` to force the built assets at any time.

To build the production assets (what a deploy would ship):

```bash
npm run build     # -> theme/assets/dist/ + manifest.json
```

---

## Day-to-day development (Docker)

```bash
# Start stack (if stopped)
docker compose up -d

# Asset development (hot-reload)
npm run dev

# PHP linting
composer phpcs

# JS/CSS linting
npm run lint

# Run PHPUnit tests
composer test

# Run a WP-CLI command
docker compose run --rm wpcli <wp-cli-args>

# Seed demo content (idempotent)
docker compose run --rm wpcli safari seed

# Reset and re-seed
docker compose run --rm wpcli safari seed --reset

# Stop containers
docker compose down

# Stop + wipe volumes (full reset)
docker compose down -v
```

---

## Project structure

```
safari-travel/
├── docker-compose.yml        # Local environment
├── .env.example              # Environment template
├── vite.config.js            # Asset build
├── package.json
├── composer.json             # PHP dev dependencies + phpcs
├── phpunit.xml
├── .phpcs.xml
├── theme/                    # safari-theme (custom WP theme)
│   ├── assets/src/           # THE source of truth for CSS + JS
│   │   ├── main.css          # Vite entry: stylesheet
│   │   ├── main.js           # Vite entry: JS runtime
│   │   ├── css/              # tokens, base, layout, components, forms, motion, pages
│   │   └── js/               # reveal, shell, ui, interactive, parallax, lead-form, home
│   ├── assets/dist/          # Build output + manifest.json (gitignored, never edited)
│   ├── inc/                  # PHP includes (assets.php owns the Vite enqueue)
│   ├── template-parts/       # Partial templates
│   ├── templates/            # Page templates
│   └── woocommerce/          # WooCommerce overrides
├── plugins/
│   ├── safari-core/          # CPTs, taxonomies, roles, site settings
│   ├── safari-leads/         # Lead capture system (priority)
│   └── safari-search/        # Global search + archive filters
├── mu-plugins/
│   └── safari-hardening.php  # Security headers, hardening
├── uploads/                  # Local media (gitignored)
├── scripts/                  # Shell helpers (setup, deploy)
├── tests/                    # PHPUnit tests
│   ├── safari-leads/
│   ├── safari-core/
│   └── _stubs/
└── docs/
    ├── ADMIN-GUIDE.md
    ├── TECHNICAL.md
    └── DEPLOYMENT.md
```

---

## Plugins installed (inside Docker)

The setup script installs these via WP-CLI:

| Plugin | Purpose |
|--------|---------|
| `advanced-custom-fields` | Field groups for CPTs (free version) |
| `woocommerce` | Shop |
| `wp-mail-smtp` | Reliable email via SMTP |
| `redis-cache` | Object cache backend |
| `rank-math-seo` | Meta, schema, sitemaps |

Premium plugins (ACF Pro, Relevanssi Premium) require manual activation; see `docs/DEPLOYMENT.md`.

---

## Database

The site uses a managed MySQL 8 database on Aiven by default. Connection details live in `.env`, which is gitignored. Never commit them.
To use the bundled local MariaDB instead, set `DB_HOST=db`, `DB_SSL=false`, `DB_NAME=safari_wp`, `DB_USER=safari`, `DB_PASSWORD=safari_secret`
and start with `docker compose --profile local-db up -d`.

## Environment variables (`.env`)

| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_HOST` | `db` | MySQL host **with port** (e.g. Aiven `host.aivencloud.com:17620`) |
| `DB_NAME` | `safari_wp` | Database name (`defaultdb` on Aiven) |
| `DB_USER` | `safari` | Database username |
| `DB_PASSWORD` | `safari_secret` | Database password |
| `DB_SSL` | `false` | `true` to connect over TLS (required by Aiven) |
| `DB_PREFIX` | `stv_` | WP table prefix |
| `WP_PORT` | `8080` | WordPress HTTP port |
| `MAILPIT_PORT` | `8025` | Mailpit web UI port |
| `WP_DEBUG` | `true` | Enable `WP_DEBUG` |
| `TURNSTILE_SITE_KEY` | _(blank)_ | Cloudflare Turnstile public key |
| `TURNSTILE_SECRET_KEY` | _(blank)_ | Cloudflare Turnstile secret key |

---

## Production deployment

See `docs/DEPLOYMENT.md` for the full procedure. In summary:

1. CI builds assets, runs lint + tests — must all pass
2. Tagged release triggers SSH rsync to production server
3. WP-CLI cache flush + search-replace for URL changes
4. Manual smoke test

---

## Warranty

3 months from launch. See `plan.md §18` and `docs/DEPLOYMENT.md`.
