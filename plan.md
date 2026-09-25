# Safari Travel Website — Production Project Plan

**Stack:** WordPress 6.x · PHP 8.2+ · MySQL 8 / MariaDB 10.6+ · Custom theme + custom plugins
**Priority feature:** Lead Generation System
**Warranty:** 3 months after launch

---

## Table of Contents

1. [Goals & Success Criteria](#1-goals--success-criteria)
2. [Clarifications Needed From the Client](#2-clarifications-needed-from-the-client)
3. [Technical Architecture](#3-technical-architecture)
4. [Content Model (CPTs, Taxonomies, Fields)](#4-content-model)
5. [Lead Generation System (Priority)](#5-lead-generation-system-priority)
6. [Search & Filtering](#6-search--filtering)
7. [Shop](#7-shop)
8. [CMS & Admin Experience](#8-cms--admin-experience)
9. [Design & UX](#9-design--ux)
10. [Performance](#10-performance)
11. [Security & Data Protection](#11-security--data-protection)
12. [SEO](#12-seo)
13. [Development Workflow & Environments](#13-development-workflow--environments)
14. [Testing Strategy](#14-testing-strategy)
15. [Deployment & Launch](#15-deployment--launch)
16. [Timeline & Milestones](#16-timeline--milestones)
17. [Deliverables & Handover](#17-deliverables--handover)
18. [Warranty & Post-Launch](#18-warranty--post-launch)
19. [Risks & Mitigations](#19-risks--mitigations)
20. [Acceptance Checklist](#20-acceptance-checklist)

---

## 1. Goals & Success Criteria

| Goal | Measurable target |
|---|---|
| Generate travel inquiries | Every form submission stored in DB + admin email delivered within 1 min; 0 lost leads |
| Mobile-first experience | All key flows usable at 360px width; Lighthouse Mobile Accessibility ≥ 90 |
| Fast loading | **LCP < 2.5s, INP < 200ms, CLS < 0.1** on 4G (see note below) |
| Admin independence | Client staff can add/edit all content types and manage leads without a developer |
| Findability | Global search + filters return relevant results in < 1s |
| Security | No critical findings in WPScan / basic pentest checklist before launch |

> ⚠️ **Note on the "up to 20 seconds" load-time requirement:** This is far too loose for a production site. Google Core Web Vitals treat anything above ~4s LCP as "poor", and it would hurt both SEO ranking and lead conversion. We propose committing internally to **LCP < 2.5s** and treating 20s as the contractual worst-case ceiling only. Recommend confirming this with the client in writing.

---

## 2. Clarifications Needed From the Client

Resolve these during the research stage (Days 1–5). Record answers in the final specification.

**Business**
- [ ] Target markets and languages? (Multilingual → WPML or Polylang, adds ~5–10 days)
- [ ] Currency/currencies displayed? Are tour prices shown or "price on request"?
- [ ] Shop: real online payments, or catalogue with "request/order" only? Which payment gateways (Stripe, PayPal, local Indian/African gateways)?
- [ ] Physical products (shipping, tax) or digital/vouchers only?
- [ ] Can tours be booked online, or only via lead inquiry? (Booking engine = separate scope)
- [ ] Who receives lead emails? Single inbox or routing by destination?
- [ ] CRM integration needed (HubSpot, Zoho, Pipedrive, Google Sheets)?
- [ ] WhatsApp / click-to-call required? (Very common for safari operators)

**Content**
- [ ] Who supplies content, photos, and translations? Deadline?
- [ ] Brand assets: logo, colours, fonts, brand guidelines?
- [ ] Approximate content volume (destinations, tours, products, articles)?
- [ ] Is there an existing site to migrate (URLs → 301 redirects)?

**Legal & hosting**
- [ ] Privacy policy, terms, cookie policy — supplied by client's legal?
- [ ] Applicable data laws (GDPR for EU visitors, India DPDP Act 2023, etc.)
- [ ] Hosting: provided by client or by us? Domain and DNS access?
- [ ] Transactional email service (SMTP provider) account?

---

## 3. Technical Architecture

### 3.1 Stack

| Layer | Choice | Reason |
|---|---|---|
| CMS | WordPress 6.x (latest stable) | Required by client |
| PHP | 8.2 or 8.3 | Performance, security support |
| Database | MySQL 8.0 / MariaDB 10.6+ | Standard |
| Theme | **Custom theme** (hybrid: classic PHP templates + Gutenberg blocks for content areas) | Full control over performance & design; no page-builder bloat |
| Custom fields | ACF Pro | Fast, editor-friendly field UIs; Flexible Content for landing pages |
| Shop | WooCommerce | De facto WordPress e-commerce standard |
| Leads | **Custom plugin `safari-leads`** | Priority feature; full control over data, statuses, security |
| Search | Custom REST endpoint + WP_Query (+ optionally Relevanssi / SearchWP for relevance) | Unified cross-content search |
| Filtering | Custom AJAX filter (REST) or FacetWP | Faceted filtering on destinations/tours/events/products |
| SEO | Rank Math or Yoast SEO | Meta, schema, sitemaps |
| Cache | Server page cache (LiteSpeed / Nginx FastCGI) + Redis object cache | Speed |
| CDN | Cloudflare | Static assets, image delivery, WAF, DDoS protection |
| Email | SMTP via WP Mail SMTP + provider (Postmark / Brevo / Amazon SES / Mailgun) | Reliable delivery; `wp_mail` via PHP mail is unreliable |
| Security | Wordfence or Solid Security + Cloudflare WAF | Hardening, 2FA, brute-force protection |
| Backups | UpdraftPlus / host snapshots → offsite (S3 / Backblaze) | Recovery |
| Build tools | Vite (or @wordpress/scripts), SCSS/Tailwind, Composer, npm | Modern asset pipeline |

**Plugin policy:** keep the plugin count minimal (target ≤ 15 active). Every plugin must be justified, actively maintained, and license-owned by the client.

### 3.2 Hosting Requirements

- Managed WordPress or VPS (e.g. Cloudways, Kinsta, SiteGround, WP Engine, or DigitalOcean + RunCloud)
- 2+ vCPU, 4 GB RAM minimum; SSD/NVMe storage
- PHP 8.2+, OPcache, Redis, HTTP/2 or HTTP/3, free SSL (Let's Encrypt)
- SSH + Git access, WP-CLI, staging environment, daily automated backups
- Server region close to primary audience (CDN covers the rest)

### 3.3 Code Structure

```
wp-content/
├── themes/
│   └── safari-theme/
│       ├── assets/
│       │   ├── src/           # SCSS, JS (ES modules), images, icons (SVG sprite)
│       │   └── dist/          # Built, minified, hashed assets
│       ├── inc/
│       │   ├── setup.php      # theme supports, menus, image sizes
│       │   ├── enqueue.php    # conditional asset loading
│       │   ├── acf-blocks/    # custom Gutenberg blocks via ACF
│       │   ├── helpers.php
│       │   └── schema.php     # JSON-LD output
│       ├── template-parts/    # cards, hero, filters, CTA, lead-form
│       ├── templates/         # page templates
│       ├── woocommerce/       # WooCommerce template overrides
│       ├── archive-*.php / single-*.php
│       ├── functions.php
│       ├── theme.json         # design tokens for block editor
│       └── style.css
├── plugins/
│   ├── safari-core/           # CPTs, taxonomies, ACF field groups (JSON), roles
│   ├── safari-leads/          # Lead generation system (see §5)
│   └── safari-search/         # Global search + filter REST endpoints
└── mu-plugins/
    └── safari-hardening.php   # security headers, disable XML-RPC, etc.
```

**Rule:** content structure (CPTs, taxonomies, leads) lives in **plugins**, not the theme — so data survives a future redesign.

### 3.4 Coding Standards

- WordPress Coding Standards (PHPCS + WPCS), PHP 8.2 type hints, namespaced classes, PSR-4 autoloading via Composer
- Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize on input, nonces on every form/action, capability checks on every admin action
- `$wpdb->prepare()` for all custom SQL
- ESLint + Prettier for JS; Stylelint for CSS
- All strings translatable (`__()`, `_e()`, text domains) — ready for multilingual even if launched in one language

---

## 4. Content Model

### 4.1 Custom Post Types

| CPT | Slug | Purpose | Key ACF fields |
|---|---|---|---|
| Destination | `/destinations/` | Countries / parks / regions | Hero gallery, overview, best time to visit (months), wildlife highlights, climate, map (lat/lng), related tours, FAQs, "from" price |
| Tour / Program | `/tours/` | Travel programs & itineraries | Duration (days), price from, currency, group size, difficulty, day-by-day itinerary (repeater), inclusions/exclusions, accommodation level, departure dates, gallery, destination(s) (relationship), CTA form preset |
| Event / Listing | `/events/` | Events, promotions, announcements | Start/end date, location, type, promo code, expiry date (auto-hide), external link, CTA |
| Recommendation / Guide | Posts (`/guides/`) | Articles, tips, travel info | Standard WP posts + reading time, related destinations |
| FAQ | `faq` (no single page, or `/faq/`) | Reusable Q&A | Question, answer, FAQ category, display order |
| Product | WooCommerce `product` | Shop items | WooCommerce native + custom attributes |
| Testimonial *(recommended)* | `testimonial` | Social proof | Name, country, rating, tour, photo |
| Lead | Custom DB table (see §5) | Inquiries | — |

### 4.2 Taxonomies

| Taxonomy | Applies to | Examples |
|---|---|---|
| `region` (hierarchical) | Destination, Tour, Event, Post | Africa → Kenya → Maasai Mara |
| `safari_type` | Tour, Destination | Game drive, Walking, Gorilla trekking, Migration, Photography, Honeymoon, Family |
| `travel_style` | Tour | Luxury, Mid-range, Budget |
| `season` | Tour, Destination | Jan–Dec months or Dry/Wet season |
| `event_type` | Event | Promotion, Festival, Group departure, Announcement |
| `guide_topic` (categories) | Post | Health & visas, Packing, Wildlife, Photography |
| `faq_category` | FAQ | Booking, Payments, Safety, Visas |

### 4.3 Page Structure (Sitemap)

```
Home
├── Destinations (archive + filters)
│   └── Single Destination
├── Tours (archive + filters)
│   └── Single Tour
├── Shop (WooCommerce)
│   ├── Product category
│   ├── Single product
│   ├── Cart / Checkout / My Account (if payments enabled)
├── Recommendations / Guides (blog archive + topics)
│   └── Single article
├── Events & Offers (archive: upcoming / past, filters)
│   └── Single event
├── Support
│   ├── Contact (form, phone, WhatsApp, map, office hours)
│   └── FAQ (accordion, searchable, grouped by category)
├── Plan My Safari (main lead form landing page)
├── Search results
├── About Us
├── Legal: Privacy Policy, Terms, Cookie Policy
├── Thank-you page (after inquiry — for conversion tracking)
└── 404
```

---

## 5. Lead Generation System (Priority)

Implemented as a standalone plugin **`safari-leads`** so it is independent of the theme and easy to maintain.

### 5.1 Why a custom DB table (not a CPT or a form plugin)?

| Option | Verdict |
|---|---|
| Contact Form 7 + Flamingo | Weak admin UI, no statuses, limited filtering |
| Gravity Forms / WPForms Pro | Viable fallback, but paid license, less control over status workflow and data model |
| CPT `lead` | Works, but mixes sensitive personal data into `wp_posts`/`wp_postmeta`, slow filtering via meta queries, leaks into exports/search |
| **Custom table** ✅ | Fast indexed search/filter, clear data ownership, easy retention/deletion, isolated from public queries |

### 5.2 Database Schema

```sql
CREATE TABLE {prefix}safari_leads (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  status           VARCHAR(20) NOT NULL DEFAULT 'new',   -- new|in_progress|contacted|closed_won|closed_lost|spam
  name             VARCHAR(190) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  phone            VARCHAR(50)  NULL,
  destination_id   BIGINT UNSIGNED NULL,                 -- linked Destination post
  tour_id          BIGINT UNSIGNED NULL,                 -- linked Tour post (if submitted from tour page)
  destination_text VARCHAR(190) NULL,                    -- free text fallback ("Not sure yet")
  date_from        DATE NULL,
  date_to          DATE NULL,
  dates_flexible   TINYINT(1) NOT NULL DEFAULT 0,
  adults           SMALLINT UNSIGNED NULL,
  children         SMALLINT UNSIGNED NULL,
  budget_range     VARCHAR(50) NULL,
  message          TEXT NULL,
  source_form      VARCHAR(50) NOT NULL,                 -- plan_my_safari|tour_page|contact|popup|event
  source_url       VARCHAR(500) NULL,
  utm_source       VARCHAR(100) NULL,
  utm_medium       VARCHAR(100) NULL,
  utm_campaign     VARCHAR(100) NULL,
  referrer         VARCHAR(500) NULL,
  consent_privacy  TINYINT(1) NOT NULL DEFAULT 0,
  consent_marketing TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash          CHAR(64) NULL,                        -- SHA-256 + salt, not raw IP
  assigned_to      BIGINT UNSIGNED NULL,                 -- WP user ID
  PRIMARY KEY (id),
  KEY status (status),
  KEY created_at (created_at),
  KEY email (email),
  KEY destination_id (destination_id),
  KEY assigned_to (assigned_to)
);

CREATE TABLE {prefix}safari_lead_notes (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  type       VARCHAR(20) NOT NULL DEFAULT 'note',        -- note|status_change|email_sent|system
  content    TEXT NOT NULL,
  PRIMARY KEY (id),
  KEY lead_id (lead_id)
);
```

Tables created via `dbDelta()` on activation, with a stored schema version for future migrations.

### 5.3 Forms

| Form | Location | Fields |
|---|---|---|
| **Plan My Safari** (multi-step) | Dedicated page + homepage CTA | Step 1: destination, dates, flexible toggle · Step 2: adults, children, budget, travel style · Step 3: name, email, phone (with country code), message, consent |
| **Tour inquiry** | Every single Tour page (sticky CTA on mobile) | Pre-filled tour + destination; dates, travellers, name, email, phone, message |
| **Quick inquiry** | Destination pages, Event pages | Name, email/phone, dates, travellers, message |
| **Contact** | Support page | Name, email, phone, subject, message |

**Requirements**
- Built as a reusable template part / Gutenberg block, rendered by the plugin (`[safari_lead_form preset="tour"]` shortcode + block)
- Mobile-first: large tap targets, correct input types (`tel`, `email`, `date`), native date pickers or a lightweight picker, autocomplete attributes
- International phone input with country code (e.g. intl-tel-input, lazy-loaded)
- Client-side validation (instant feedback) **and** server-side validation (authoritative)
- Submission via REST API (`POST /wp-json/safari/v1/leads`) with AJAX — no page reload; graceful fallback to standard POST if JS fails
- Success state → redirect to Thank-you page (enables Google Ads / GA4 / Meta conversion tracking)
- Hidden fields auto-captured: source form, page URL, UTM parameters (stored in first-party cookie on landing), referrer
- Accessible: labels, `aria-live` error messages, keyboard navigable

### 5.4 Spam & Abuse Protection

- Cloudflare **Turnstile** (privacy-friendly, invisible) or Google reCAPTCHA v3
- Honeypot field + minimum time-to-submit check
- WP REST nonce
- Rate limiting per IP hash (e.g. max 5 submissions / 10 min) via transients
- Server-side sanitization: `sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, date validation, integer bounds on traveller counts
- Suspicious submissions saved with status `spam` (not silently dropped) so real leads are never lost

### 5.5 Notifications

- **Admin email** on every new lead: HTML template with all fields, direct "Open in admin" link, reply-to set to the visitor's email
- **Routing rules** (configurable in settings): e.g. Kenya/Tanzania leads → Team A, others → default inbox; multiple recipients supported
- **Visitor auto-reply** (configurable text): confirms receipt, expected response time, contact phone/WhatsApp
- Sent via SMTP provider (SPF, DKIM, DMARC configured on the domain)
- **Fail-safe:** the lead is saved to the DB *before* sending email; email failures are logged and shown in admin, with a "Resend notification" button
- *Optional:* Telegram / Slack / WhatsApp Business notification webhook

### 5.6 Admin Panel ("Leads" menu)

**List screen** (built on `WP_List_Table`)
- Columns: ID, date, name, email, phone, destination, travel dates, travellers, source, status (coloured badge), assigned to
- Status tabs with counts: All · New · In Progress · Contacted · Closed (Won/Lost) · Spam
- Search: name, email, phone, message
- Filters: status, destination, date received (range), travel date (range), source form, assigned user
- Sorting on date, status, travel date
- Bulk actions: change status, assign, mark spam, export, delete
- Pagination, screen options (per-page count, visible columns)

**Single lead screen**
- All submitted data, clickable `mailto:` / `tel:` / WhatsApp link
- Change status, assign to a team member
- Internal notes timeline (who did what and when — status changes logged automatically)
- Link to the tour/destination the lead came from

**Dashboard widget:** new leads today / this week, count by status, last 5 leads.
**Admin menu badge:** count of `new` leads.

**Export:** CSV export of current filtered view (capability-restricted; export action is logged).

**Settings page:** notification recipients & routing, auto-reply text, spam protection keys, data retention period, status labels.

### 5.7 Roles & Capabilities

| Role | Leads access |
|---|---|
| Administrator | Full: view, edit, delete, export, settings |
| **Sales Manager** (custom role) | View, edit status, add notes, assign; no delete, no settings |
| Editor / Content Manager | No lead access (content only) |
| Shop Manager | WooCommerce only |

Custom capabilities: `view_safari_leads`, `edit_safari_leads`, `delete_safari_leads`, `export_safari_leads`, `manage_safari_leads_settings`.

### 5.8 Privacy & Retention

- Consent checkbox (required) linked to Privacy Policy; marketing consent optional and separate
- Integrate with WordPress **Personal Data Exporter / Eraser** tools (by email) for GDPR/DPDP requests
- Configurable auto-deletion or anonymisation of closed leads after N months (WP-Cron job)
- Leads never exposed via public REST endpoints (the only public endpoint is create/POST)

### 5.9 Optional Integrations (quote separately if requested)

- CRM push (HubSpot / Zoho / Pipedrive) via webhook or API on new lead
- Google Sheets sync
- GA4 + Google Ads + Meta Pixel conversion events (included at basic level via the Thank-you page)

---

## 6. Search & Filtering

### 6.1 Global Search

- Search icon in header → full-screen overlay on mobile, dropdown on desktop
- **Live suggestions** (debounced, 300ms) via `GET /wp-json/safari/v1/search?q=`
- Results grouped by type: Destinations · Tours · Events · Guides · Products · FAQs
- Searches titles, excerpts, content and key ACF fields (wildlife, highlights)
- Full results page with type tabs and filters
- Relevance improved with Relevanssi (free) or SearchWP if weighting/ACF indexing needs it
- "No results" state suggests popular destinations + lead form CTA
- Search terms logged (anonymously) so the client sees what visitors look for

### 6.2 Filters

| Archive | Filters |
|---|---|
| Destinations | Region/country, safari type, best month to visit |
| Tours | Destination, safari type, duration range, price range, travel style, month of departure |
| Events | Type, month, location, upcoming/past |
| Guides | Topic, destination |
| Shop | Category, price, attributes (WooCommerce) |

**Implementation**
- AJAX filtering via REST (no page reload), with **URL query parameters updated** (shareable, back-button safe, crawlable base pages)
- Mobile: filters in a bottom-sheet drawer with "Show X results" button; desktop: sidebar or top bar
- Result counts, "clear all", active filter chips
- Sorting: popularity, price, duration, date
- Price/duration stored as indexed numeric meta; heavy queries cached (transients / object cache)
- If filter complexity grows → FacetWP (paid) is the fallback option

---

## 7. Shop

- **WooCommerce** with a custom-styled theme integration (templates overridden only where needed)
- Product types: physical (gear, apparel, souvenirs), digital (guides/e-books), gift vouchers (via plugin if required)
- Cart, checkout (optimised single-page, guest checkout allowed), My Account
- Payment gateways per client decision (Stripe / PayPal / Razorpay / local gateway)
- Shipping zones and tax configuration as supplied by the client
- Order emails styled to brand
- Disable WooCommerce scripts/styles on non-shop pages (performance)
- **Fallback if no online payments:** catalogue mode with "Request this item" → creates a lead

---

## 8. CMS & Admin Experience

The client must manage everything without a developer.

- Clean admin menu: custom ordering, grouped CPTs, remove unused items (Comments if disabled, etc.)
- ACF field groups with instructions, validation, conditional logic, sensible defaults
- **Flexible page builder** via ACF Flexible Content / custom Gutenberg blocks: Hero, Destination grid, Tour carousel, CTA banner, Testimonials, FAQ block, Lead form, Image+Text, Stats, Gallery — editors compose landing pages safely within the design system
- Global options page ("Site Settings"): phone, WhatsApp, email, address, social links, header CTA text, footer content, announcement bar, default lead form settings
- Menus editable via Appearance → Menus
- Image field guidance (recommended sizes) shown in admin
- Admin preview of drafts; scheduled publishing for events/promotions
- Automatic unpublishing of expired promotions (cron)
- Custom admin columns: e.g. Tours list shows destination, duration, price; Events list shows dates
- Custom roles: Administrator, Content Manager (Editor), Sales Manager, Shop Manager
- **Admin user guide** (PDF + short screen-recorded videos) delivered at handover

---

## 9. Design & UX

### 9.1 Process

1. Moodboard & style direction (2 options) → client approval
2. Design system: colours, typography, spacing, grid, buttons, cards, form elements, icons
3. Homepage design (mobile first, then tablet & desktop) → max 2 revision rounds
4. Internal page designs: destination archive/single, tour archive/single, shop pages, guide archive/single, events, support/FAQ, contact, Plan My Safari form, search, 404, thank-you
5. Clickable prototype (Figma) for key flows, especially mobile lead submission

### 9.2 Principles

- **Mobile first:** designed at 360–390px, then scaled to 768px and 1280/1440px
- Visual direction: warm earth tones (savannah, ochre, deep green), large immersive photography, bold typography
- **Clear CTAs everywhere:** header "Plan My Safari" button; sticky bottom CTA bar on mobile (Inquire · Call · WhatsApp)
- Trust signals: testimonials, ratings, certifications/partners, secure-payment badges
- Breadcrumbs on all internal pages
- **Subtle animations:** fade/slide-in on scroll (IntersectionObserver, no heavy libraries), hover states, smooth accordion/drawer transitions, lightweight image sliders (e.g. Swiper, loaded only where used)
- Respect `prefers-reduced-motion`
- Accessibility target: **WCAG 2.1 AA** (contrast, focus states, alt text, semantic HTML, keyboard navigation)

### 9.3 Breakpoints

| Name | Width |
|---|---|
| Mobile | 0–767px (primary) |
| Tablet | 768–1023px |
| Desktop | 1024–1439px |
| Wide | ≥ 1440px |

---

## 10. Performance

**Targets (mobile, 4G):** LCP < 2.5s · INP < 200ms · CLS < 0.1 · Lighthouse Performance ≥ 85 on key templates · Page weight < 1.5 MB on first load (homepage)

**Images**
- Automatic conversion to **WebP/AVIF** on upload (e.g. ShortPixel, Imagify, or host/Cloudflare Polish)
- Registered image sizes per usage; `srcset` + `sizes` everywhere
- Native `loading="lazy"` for below-the-fold images; hero image **not** lazy-loaded, with `fetchpriority="high"` and preload
- Explicit width/height to prevent layout shift
- Max upload size guidance; server-side resize of oversized originals

**Assets**
- Custom theme = no page-builder overhead
- Critical CSS inlined, rest deferred; JS `defer`/module, per-template conditional loading
- Self-hosted, subset WOFF2 fonts with `font-display: swap` (max 2 families)
- SVG icon sprite instead of icon fonts
- Remove unused WP/plugin assets (emoji, embeds, WooCommerce on non-shop pages, block library CSS if unused)
- Third-party scripts (analytics, pixels, chat) loaded after interaction/idle; consent-aware

**Server & caching**
- Full-page cache (excluded: cart, checkout, account, logged-in users)
- Redis object cache
- OPcache enabled
- Cloudflare CDN with Brotli compression, HTTP/3
- Database: index custom tables, limit post revisions, scheduled cleanup of transients
- Video: YouTube/Vimeo embeds as click-to-load facades

---

## 11. Security & Data Protection

**WordPress hardening**
- Latest core/plugins/themes; only licensed, reputable, maintained plugins
- `DISALLOW_FILE_EDIT` true; strong unique salts; non-default DB table prefix
- Disable XML-RPC; restrict REST user enumeration; hide WP version
- Change or protect login URL; limit login attempts; **2FA mandatory for Administrator and Sales Manager roles**
- Principle of least privilege for all user accounts
- Correct file permissions (644 files / 755 dirs, `wp-config.php` 600/640)
- Block PHP execution in `/uploads`

**Transport & headers**
- HTTPS everywhere with HSTS
- Security headers: `Content-Security-Policy` (report-only first, then enforced), `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy`, `Permissions-Policy`

**Lead data protection**
- Leads in a dedicated table, accessible only via capability-checked admin screens
- No lead data in public REST responses, sitemaps, search, or front-end
- IPs stored hashed, not raw
- Export actions logged; bulk delete restricted to Administrators
- Retention policy + WordPress privacy tools integration (see §5.8)
- DB backups encrypted and stored off-site; access limited

**Perimeter & monitoring**
- Cloudflare WAF + bot protection + rate limiting on `/wp-login.php` and `/wp-json/safari/v1/leads`
- Wordfence / Solid Security: malware scan, file integrity monitoring
- Uptime monitoring (UptimeRobot / Better Stack) with alerts
- Activity log (e.g. WP Activity Log / Simple History) for admin actions

**Backups**
- Daily DB + weekly full backups, 30-day retention, off-site
- **Restore tested at least once before launch**

---

## 12. SEO

- Clean permalinks (`/destinations/kenya/`, `/tours/7-day-maasai-mara-safari/`)
- Rank Math / Yoast: editable titles & meta descriptions per page, Open Graph & Twitter cards
- XML sitemaps (excluding thank-you, search, cart, account pages); robots.txt
- **Schema.org JSON-LD:** Organization / TravelAgency, BreadcrumbList, TouristDestination, TouristTrip (tours), Event, Product, FAQPage, Article
- Canonical URLs; `noindex` on filter permutations with many params, search results, and staging
- Semantic headings (one H1 per page), descriptive alt text
- 301 redirects from old site URLs (if migrating); redirect manager for the client
- Custom 404 with search + popular destinations
- Google Search Console + GA4 setup; conversion event on lead submission
- Internal linking: destinations ↔ tours ↔ guides ↔ events
- Staging blocked from indexing (password-protected + `noindex`)

---

## 13. Development Workflow & Environments

| Environment | Purpose | Notes |
|---|---|---|
| Local | Development | LocalWP / DDEV / Docker; Xdebug |
| Staging | Client review, QA | Password-protected, `noindex`, mirrors production config |
| Production | Live | Deploy only from `main` after QA approval |

- **Git** (GitHub / GitLab / Bitbucket): only custom code in repo (theme, custom plugins, mu-plugins, config); WP core and third-party plugins via Composer (WPackagist) where possible
- Branching: `main` (production) · `develop` (staging) · `feature/*` · `hotfix/*`; pull requests with code review
- **CI/CD** (GitHub Actions or similar): PHPCS/WPCS lint, ESLint, build assets, run tests, deploy to staging on `develop` merge, deploy to production on tagged release (via SSH/rsync or host integration)
- Environment-specific config via `.env` / `wp-config` constants (never commit secrets)
- Database: content flows production → staging (never the reverse after launch); code flows staging → production
- Task tracking: Jira / Trello / ClickUp with sprint board; weekly client demo

---

## 14. Testing Strategy

### 14.1 Functional

- [ ] All forms: required fields, validation messages, success/error states, JS-disabled fallback
- [ ] **Lead delivery:** saved to DB → admin email received → auto-reply received → correct routing → UTM/source captured
- [ ] Lead admin: search, each filter, sorting, status changes, notes, assignment, bulk actions, CSV export, permissions per role
- [ ] Spam protection: honeypot, Turnstile, rate limit
- [ ] Search: live suggestions, grouped results, no-results state
- [ ] Filters: every combination on each archive, URL params, back button, clear all, counts
- [ ] Shop: add to cart, coupon, checkout with test payments, order emails, stock, refunds
- [ ] CMS: create/edit/delete each content type as each role; expired promotions hidden automatically
- [ ] Navigation, menus, breadcrumbs, 404, redirects

### 14.2 Responsive & Browser Compatibility

| Devices | Browsers |
|---|---|
| iPhone (Safari, iOS latest & latest-1) | Chrome (latest 2) |
| Android (Chrome, Samsung Internet) | Safari (latest 2) |
| iPad / Android tablet | Firefox (latest 2) |
| Desktop 1280 / 1440 / 1920 | Edge (latest 2) |

Real devices for key flows + BrowserStack for coverage.

### 14.3 Performance
- Lighthouse / PageSpeed Insights on every key template (mobile & desktop)
- WebPageTest on 4G profile
- Load test lead endpoint and search (e.g. k6) — target 50 concurrent users without errors

### 14.4 Security
- WPScan vulnerability scan
- Check security headers (securityheaders.com), SSL (SSL Labs A+)
- Verify lead data not accessible without authentication (REST, direct URLs, exports)
- Role/permission tests; brute-force protection test

### 14.5 Accessibility
- axe DevTools / WAVE automated checks; keyboard-only walkthrough; screen reader spot check (VoiceOver/TalkBack) on lead form

### 14.6 Automated tests (custom plugins)
- PHPUnit (WP test suite) for lead validation, storage, status transitions, capabilities, REST endpoint
- Playwright E2E: submit lead on mobile viewport → verify in admin

**Bug tracking:** severity levels (Blocker / Critical / Major / Minor); launch requires **zero Blockers and Criticals**.

---

## 15. Deployment & Launch

### 15.1 Pre-launch Checklist
- [ ] All content populated and proofread; placeholder text/images removed
- [ ] Final client sign-off on staging
- [ ] Production server provisioned and hardened; PHP/DB versions verified
- [ ] SSL active; domain DNS TTL lowered 24h before switch
- [ ] SMTP configured; SPF/DKIM/DMARC records verified; test emails pass spam checks (mail-tester ≥ 9/10)
- [ ] Payment gateways switched to **live** keys
- [ ] Turnstile/reCAPTCHA production keys
- [ ] Caching, CDN, Redis enabled and tested
- [ ] Backups scheduled and one restore tested
- [ ] Analytics, Search Console, conversion tracking, cookie consent banner
- [ ] "Discourage search engines" **unchecked**; staging remains blocked
- [ ] 301 redirects in place (if migrating)
- [ ] Admin accounts created for client with correct roles and 2FA; developer accounts documented

### 15.2 Launch Day
1. Final full backup of staging
2. Deploy code + migrate DB (WP-CLI search-replace for URLs)
3. Point DNS / switch domain
4. Flush caches, purge CDN
5. **Smoke test:** homepage, each archive, singles, search, filters, submit a real test lead (all forms), shop test order, admin login, lead admin
6. Submit sitemap to Google Search Console
7. Monitor error logs, uptime and lead submissions for 48 hours

### 15.3 Rollback Plan
- Keep previous version/DNS config ready; backup restore procedure documented; rollback decision within 2 hours if a Blocker appears

---

## 16. Timeline & Milestones

Based on the client's estimates; stages overlap where possible.

| # | Stage | Duration | Overlaps with | Deliverable / Milestone |
|---|---|---|---|---|
| 1 | Research & specification | 1–5 days | — | Signed final specification, sitemap, content model, clarifications answered |
| 2 | Homepage design | 5–10 days | — | Approved homepage design (mobile/tablet/desktop) + design system |
| 3 | Internal page design | 15–25 days | Dev setup (4a) | All page templates approved; clickable prototype |
| 4 | Development | 45–60 days | Design (late), Content | Working staging site |
| 5 | Content population | 20–30 days | Development (second half) | All content entered, optimised images |
| 6 | Testing & bug fixing | 2–6 days* | — | QA report, zero Blockers/Criticals |
| 7 | Launch | 1–2 days | — | Live production site |

\* **Recommendation:** 2–6 days is tight for a site of this scope. Plan for continuous QA during development plus a **dedicated 7–10 day** final QA window to protect the launch date.

### 16.1 Development Breakdown (Stage 4)

| Sprint | Weeks | Scope |
|---|---|---|
| 4a — Foundation | 1–2 | Environments, repo, CI/CD, theme scaffold, build pipeline, `safari-core` plugin (CPTs, taxonomies, ACF), roles, header/footer, design tokens |
| 4b — **Lead system** | 2–4 | `safari-leads`: DB schema, REST endpoint, forms, validation, spam protection, email notifications/routing, admin list + single view, statuses, notes, export, settings, dashboard widget, privacy tools, tests |
| 4c — Templates | 3–6 | Homepage, Destination/Tour/Event/Guide archives & singles, Support, FAQ, Contact, Plan My Safari, 404, thank-you; ACF blocks |
| 4d — Search & filters | 5–7 | `safari-search` plugin, live search, filter UI (mobile drawer), URL state, caching |
| 4e — Shop | 6–8 | WooCommerce setup, templates, payments, emails |
| 4f — Polish | 8–9 | Animations, performance optimisation, SEO/schema, security hardening, accessibility fixes, admin UX cleanup |

**Lead system is built first among features** so it can be tested the longest.

### 16.2 Overall Estimate
- **Sequential sum:** ~89–138 days
- **With planned overlaps:** ~**14–18 weeks** (≈ 3.5–4.5 months) from kickoff to launch
- Main schedule risk: late content delivery by the client (see §19)

### 16.3 Client Approval Gates
1. Specification sign-off → 2. Homepage design → 3. Internal designs → 4. Staging review (feature-complete) → 5. Content complete → 6. UAT sign-off → Launch

---

## 17. Deliverables & Handover

- Production WordPress website (live, on client's hosting & domain)
- Custom theme `safari-theme` and plugins `safari-core`, `safari-leads`, `safari-search` (source code + Git repository access)
- Configured CMS: content types, taxonomies, fields, roles, menus, options
- Lead generation system with admin management, statuses, notifications
- Responsive design (Figma source files)
- Populated content structure
- Licences for premium plugins registered **in the client's name**
- Credentials document (hosting, WP admin, SMTP, CDN, analytics) handed over securely (password manager share, not email)
- **Documentation:**
  - Admin user guide (content editing, leads management, shop orders)
  - Short video tutorials (5–8 videos, 3–5 min each)
  - Technical documentation (architecture, deployment, backups, plugin list, cron jobs, custom code)
- Handover/training session (1–2 hours, recorded)

---

## 18. Warranty & Post-Launch

**Warranty: 3 months from launch date.**

**Covered (free):** defects in implemented functionality — forms not submitting, leads not saving or not notifying, broken layouts on supported browsers/devices, filter/search errors, errors in custom theme/plugin code.

**Not covered (billable):** new features or design changes, content edits, issues caused by client-installed plugins/themes or code edits, hosting outages, third-party service changes (payment gateway APIs, etc.), updates that break due to unsupported plugins added later.

**Warranty process**
- Issues reported via a single channel (email / ticket system) with URL, device, browser, screenshots
- Response times: Blocker (site down / leads lost) — within 4 business hours · Critical — 1 business day · Minor — 3–5 business days
- Monthly warranty summary report

**Recommended after warranty:** maintenance plan (monthly updates, backups check, security monitoring, uptime, performance review, small content/dev hours).

---

## 19. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Late or incomplete content/photos from client | Launch delay | Content deadline in contract; content template sheets; placeholder policy; start population early |
| Scope creep (booking engine, multilingual, CRM) | Budget/timeline overrun | Change-request process; these are explicitly out of base scope |
| Heavy, unoptimised images from client | Slow site | Automatic compression/resizing on upload; image guidelines in admin |
| Lead emails landing in spam | Lost business | SMTP provider + SPF/DKIM/DMARC; leads always stored in DB regardless of email |
| Spam flood | Admin noise | Turnstile + honeypot + rate limit + spam status |
| Plugin vulnerabilities | Security breach | Minimal plugin set, auto-updates for minor/security, WAF, monitoring |
| Unclear payment/shop requirements | Rework | Resolve in Stage 1; catalogue-mode fallback |
| Too-short QA window | Bugs in production | Continuous QA + extended final QA window |
| Hosting underpowered | Slow/unstable site | Minimum hosting spec agreed in Stage 1 |

---

## 20. Acceptance Checklist

The project is accepted when all items are confirmed on production:

- [ ] All sections live: Destinations, Tours, Shop, Recommendations, Support, Events, Search
- [ ] Global search and all filters work on mobile, tablet, desktop
- [ ] All lead forms submit; leads stored in admin; admin email + auto-reply delivered
- [ ] Leads can be searched, filtered, status-changed (New / In Progress / Contacted / Closed), annotated and exported
- [ ] Client staff can independently create/edit destinations, tours, products, articles, events, FAQs
- [ ] Responsive on all devices/browsers in §14.2
- [ ] Core Web Vitals targets met on key templates (mobile)
- [ ] Images optimised and lazy-loaded
- [ ] SEO basics in place: meta, sitemap, schema, Search Console
- [ ] Security checks passed; SSL A/A+; backups running and restore tested
- [ ] Documentation and training delivered; credentials handed over
- [ ] Warranty period start date recorded