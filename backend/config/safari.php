<?php

declare(strict_types=1);

use App\Models\Lead;

return [

    /*
    |--------------------------------------------------------------------------
    | Safari Travel
    |--------------------------------------------------------------------------
    |
    | Every value that couples the Laravel backend to the WordPress front end
    | lives here. Nothing in the application reads env() directly for these
    | settings, so the coupling can be reasoned about (and tested) in one place.
    |
    | WordPress stays the CMS and the public website. Laravel never renders a
    | page for a visitor; it only serves an API and talks to WordPress.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    |
    | Laravel and WordPress share one Aiven MySQL database, so the backend
    | MUST use its own table prefix. WordPress owns `stv_*`; this default
    | keeps the API on `safari_api_*`. The two can never collide, and a
    | `php artisan migrate:rollback` in one app cannot touch the other.
    |
    | Set DB_TABLE_PREFIX= to share an empty (prefix-less) database, e.g. when
    | you point the backend at SQLite for local work or at CI.
    |
    */
    'table_prefix' => env('DB_TABLE_PREFIX', 'safari_api_'),

    /*
    |--------------------------------------------------------------------------
    | Public lead intake
    |--------------------------------------------------------------------------
    | Mirrors the defaults of the WordPress plugin (safari-leads) so both
    | intake paths accept and reject exactly the same submissions.
    */
    'leads' => [
        'statuses' => Lead::STATUSES,

        'source_forms' => [
            'plan_my_safari',
            'tour_page',
            'contact',
            'popup',
            'event',
            'product',
        ],

        'budget_ranges' => [
            'under_2000',
            '2000_5000',
            '5000_10000',
            '10000_20000',
            'over_20000',
        ],

        'travel_styles' => [
            'luxury',
            'mid-range',
            'budget',
        ],

        // Seconds a real visitor needs before the form may be submitted.
        'min_submit_seconds' => (int) env('LEAD_MIN_SUBMIT_SECONDS', 3),

        // Honeypot + time-to-submit + rate limit + optional Turnstile.
        'spam' => [
            'honeypot_field' => 'website',
            'rate_limit_max' => (int) env('LEAD_RATE_LIMIT_MAX', 5),
            'rate_limit_window' => (int) env('LEAD_RATE_LIMIT_WINDOW', 600),
        ],

        // Privacy: months before closed leads are anonymised. 0 disables.
        'retention_months' => (int) env('LEAD_RETENTION_MONTHS', 24),

        // Salt used to hash the visitor IP. The raw address is never stored.
        // Falls back to APP_KEY, so rotating APP_KEY rotates every hash.
        'ip_hash_salt' => env('LEAD_IP_HASH_SALT', env('APP_KEY')),

        'notify_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LEAD_NOTIFY_EMAILS', ''))
        ))),

        'auto_reply' => [
            'enabled' => (bool) env('LEAD_AUTO_REPLY', false),
            'subject' => env('LEAD_AUTO_REPLY_SUBJECT', 'Thanks for contacting Safari Travel'),
            'template' => 'lead-auto-reply',
        ],

        'admin_template' => 'lead-admin-notification',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    | When the secret is blank, verification is skipped entirely — the same
    | fail-open behaviour the WordPress plugin uses, so turning Turnstile off
    | locally is a one-line change.
    */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | WordPress integration
    |--------------------------------------------------------------------------
    | The backend reads published content from WordPress over the WP REST API
    | and (optionally) mirrors lead status changes back into the plugin's
    | tables. Everything is off unless explicitly enabled, so a fresh checkout
    | has no coupling at all.
    */
    'wordpress' => [
        // Base URL of the WordPress site, e.g. http://localhost:8080
        'url' => rtrim((string) env('SAFARI_WP_URL', ''), '/'),

        // User + application password used for authenticated reads/writes.
        // Blank means anonymous (public endpoints only).
        'username' => env('SAFARI_WP_USERNAME', ''),
        'application_password' => env('SAFARI_WP_APP_PASSWORD', ''),

        'timeout' => (int) env('SAFARI_WP_TIMEOUT', 8),
        'verify_tls' => (bool) env('SAFARI_WP_VERIFY_TLS', true),

        // Prefix of the WordPress tables, used only to name the mirrored rows.
        'table_prefix' => env('DB_PREFIX', 'stv_'),

        // Cache published content for this many seconds.
        'cache_ttl' => (int) env('SAFARI_WP_CACHE_TTL', 300),

        // Mirror API leads into the WordPress store so the wp-admin lead list
        // agrees with the API. Off by default; requires SAFARI_API_KEY and the
        // safari-api-bridge mu-plugin. Even when on, the backend only ever talks
        // to WordPress over its REST API — it never writes a `stv_*` table.
        'mirror_leads' => (bool) env('SAFARI_API_MIRROR', false),

        // Post types exposed through the read-only content proxy.
        'content_types' => [
            'destination',
            'tour',
            'event',
            'safari_guide',
            'testimonial',
            'faq',
            'post',
            'page',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WordPress -> Laravel lead delegation (opt-in)
    |--------------------------------------------------------------------------
    | When enabled, the safari-leads REST endpoint may forward submissions to
    | this API instead of writing its own tables. The WordPress handler keeps
    | its current behaviour unless SAFARI_API_DELEGATE=true is set in the root
    | .env, and falls back to local persistence if this API is unreachable.
    |
    | The shared key is sent as X-Safari-Api-Key and required by
    | App\Http\Middleware\VerifyApiKey whenever it is non-empty.
    */
    'api' => [
        'key' => env('SAFARI_API_KEY', ''),
        'header' => 'X-Safari-Api-Key',
    ],

];
