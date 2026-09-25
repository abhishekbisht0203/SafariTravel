# PROGRESS — Safari Travel WordPress Build

Single source of execution status. `plan.md` is the spec; this file tracks what is
built, decided, and still open. **Update after every phase (3–5 lines + checkbox).**

## Phase checklist

- [ ] **Phase 0** — Project environment & tooling (Docker/WP stack, Composer, Vite, lint, CI skeleton, README)
- [ ] **Phase 1** — Theme foundation (enqueue, theme.json, templates, components)
- [ ] **Phase 2** — Lead system (DB, REST, forms, spam, email, admin UI, roles)
- [ ] **Phase 3** — Content model & seed data (CPT UI, ACF fields, `wp safari seed`)
- [ ] **Phase 4** — Search & filters (REST search, facet filters, mobile sheet)
- [ ] **Phase 5** — Shop (WooCommerce integration + catalogue fallback)
- [ ] **Phase 6** — Signature design (hero, map, timeline, best-time chart, animations)
- [ ] **Phase 7** — Performance, security, SEO hardening (mu-plugin, caching, schema)
- [ ] **Phase 8** — QA (Lighthouse, axe, forms E2E, cross-device)
- [ ] **Phase 9** — Docs, handover, deployment

## Decisions

- **2026-09-25** — Stack per plan.md §3.1: custom hybrid theme (no page builder), ACF Pro
  field groups already exported to `plugins/safari-core/acf-json/`.
- **2026-09-25** — Lead system is a custom plugin + custom DB table (`plan.md` §5.2), not
  Contact Form 7/Gravity Forms.
- **2026-09-25** — Scaffold inspection complete: `theme/` + `safari-core` + `safari-leads`
  (partial) exist; `safari-search`, `mu-plugins`, `docs`, Docker/Composer/npm/lint configs
  and CI are **absent** and must be created. `safari-leads.php` references 5 missing files →
  must be written before the plugin boots.

## Assumptions (defaults picked, log here)

- Docker Compose v2 plugin missing → will install/locate a working Compose; fallback is
  `@wordpress/env`. Recorded final choice below.
- WP-CLI not installed → install during Phase 0.
- English-first, translation-ready (plan.md §2).
- Inquiry-only commerce, WooCommerce catalogue-mode fallback until Stripe keys arrive.

## Open questions (only truly blocking ones go in the chat)

- (none blocking)

## Known issues / notes

- `theme/` layout uses `src/` + `inc/`; plan.md §3.3 says `assets/src` + `assets/dist`.
  Vite manifest must match `theme/inc/assets.php` keys: `src/main.js`, `src/main.css`,
  output `assets/dist/manifest.json`.
- Contract ceiling in plan.md §1 (20s) is too loose; internal target LCP < 2.5s.
