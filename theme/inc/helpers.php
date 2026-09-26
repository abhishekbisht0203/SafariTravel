<?php
/**
 * Template helper functions.
 *
 * Two families live here:
 *  1. Content accessors that work whether or not ACF is active, so every
 *     template renders correctly on a fresh install and after a redesign.
 *  2. Presentation helpers that emit small, consistent HTML fragments so the
 *     markup contract lives in exactly one place.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
 * 1. Content accessors (ACF-optional)
 * ========================================================================== */

/**
 * Read an ACF field, falling back to post meta and then to a default.
 *
 * ACF is optional at render time: the field groups ship as JSON in
 * plugins/safari-core/acf-json, but templates must not fatal if the plugin is
 * missing during a staged build.
 *
 * @param string $name    Field name (without the `field_` prefix).
 * @param int    $post_id Post ID, defaults to current.
 * @param mixed  $default Value when the field is empty.
 * @return mixed Field value.
 */
function safari_field(string $name, int $post_id = 0, mixed $default = null): mixed
{
    $post_id = $post_id ?: (int) get_the_ID();

    if (function_exists('get_field')) {
        $value = get_field($name, $post_id ?: null);
        if (null !== $value && '' !== $value && [] !== $value) {
            return $value;
        }
    }

    $meta = get_post_meta($post_id, $name, true);
    if (null !== $meta && '' !== $meta && [] !== $meta) {
        return $meta;
    }

    return $default;
}

/**
 * Read an option stored on the Site Settings screen.
 *
 * @param string $name    Field name.
 * @param mixed  $default Value when unset.
 * @return mixed Field value.
 */
function safari_option(string $name, mixed $default = ''): mixed
{
    if (function_exists('get_field')) {
        $value = get_field($name, 'options');
        if (null !== $value && '' !== $value) {
            return $value;
        }
    }

    $value = get_option('safari_' . $name, $default);

    return ('' === $value || null === $value) ? $default : $value;
}

/**
 * Site-wide contact email.
 */
function safari_contact_email(): string
{
    $option = get_option('safari_leads_settings', []);
    $email  = is_array($option) ? ($option['notification_emails'] ?? '') : '';
    if (is_string($email) && '' !== $email) {
        $first = explode(',', $email)[0];
        $first = sanitize_email(trim($first));
        if ('' !== $first) {
            return $first;
        }
    }
    return (string) get_option('admin_email');
}

/**
 * Primary phone number, digits only.
 */
function safari_phone(): string
{
    return (string) safari_option('phone', '');
}

/**
 * WhatsApp number in international format, digits only (no `+`).
 */
function safari_whatsapp(): string
{
    return ltrim(preg_replace('/\D+/', '', (string) safari_option('whatsapp', '')) ?? '', '0');
}

/**
 * URL for the main "Plan My Safari" call to action.
 */
function safari_plan_url(): string
{
    $configured = (string) safari_option('header_cta_url', '');

    if ('' !== $configured) {
        // A stored relative path needs to be resolved against the site root.
        if (str_starts_with($configured, '/')) {
            return home_url($configured);
        }
        return $configured;
    }

    $page = get_page_by_path('plan-my-safari');

    return $page ? (string) get_permalink($page) : home_url('/plan-my-safari/');
}

/**
 * Label for the main header call to action.
 */
function safari_plan_label(): string
{
    return (string) safari_option('header_cta_text', __('Plan My Safari', 'safari-travel'));
}

/**
 * Format a price for display.
 *
 * @param mixed  $amount   Numeric amount.
 * @param string $currency ISO currency code.
 * @return string Formatted price, or an empty string when not numeric.
 */
function safari_price(mixed $amount, string $currency = 'USD'): string
{
    if (! is_numeric($amount) || (float) $amount <= 0) {
        return '';
    }

    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'KES' => 'KSh ',
        'ZAR' => 'R',
        'INR' => '₹',
    ];

    $value    = (float) $amount;
    $decimals = fmod($value, 1.0) > 0.0 ? 2 : 0;
    $number   = number_format_i18n($value, $decimals);

    if (isset($symbols[$currency])) {
        return $symbols[$currency] . $number;
    }

    return $number . ' ' . $currency;
}

/**
 * Number of days a tour runs, as a human label.
 *
 * @param int $post_id Tour post ID.
 * @return string e.g. "7 days".
 */
function safari_duration_label(int $post_id = 0): string
{
    $days = (int) safari_field('duration_days', $post_id, 0);

    if ($days <= 0) {
        return '';
    }

    return sprintf(
        /* translators: %d: number of days */
        _n('%d day', '%d days', $days, 'safari-travel'),
        $days
    );
}

/**
 * Difficulty label, translated.
 *
 * @param int $post_id Tour post ID.
 * @return string Localised difficulty.
 */
function safari_difficulty_label(int $post_id = 0): string
{
    $value = (string) safari_field('difficulty', $post_id, '');

    if ('' === $value) {
        return '';
    }

    $map = [
        'easy'        => __('Easy', 'safari-travel'),
        'moderate'    => __('Moderate', 'safari-travel'),
        'challenging' => __('Challenging', 'safari-travel'),
        'strenuous'   => __('Strenuous', 'safari-travel'),
    ];

    return $map[$value] ?? ucfirst($value);
}

/**
 * Month keys (jan…dec) a destination is best visited in.
 *
 * @param int $post_id Destination post ID.
 * @return string[] Lower-case month keys.
 */
function safari_best_months(int $post_id = 0): array
{
    $months = safari_field('best_months', $post_id, []);

    if (is_string($months)) {
        $months = array_filter(array_map('trim', explode(',', $months)));
    }

    if (! is_array($months)) {
        return [];
    }

    $valid = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    return array_values(array_filter($months, static fn ($m) => in_array((string) $m, $valid, true)));
}

/**
 * The destination a tour belongs to.
 *
 * @param int $post_id Tour post ID.
 * @return int Destination post ID, or 0.
 */
function safari_tour_destination(int $post_id = 0): int
{
    $id = safari_field('destination', $post_id, 0);

    if (is_array($id)) {
        $id = reset($id);
    }

    return (int) $id;
}

/**
 * Country name for a destination.
 *
 * @param int $post_id Destination post ID.
 * @return string Country name.
 */
function safari_destination_country(int $post_id = 0): string
{
    return (string) safari_field('country', $post_id, '');
}

/**
 * Attach a destination to a Tour for the lead form.
 *
 * @param int $post_id Tour post ID.
 * @return array{0:int,1:int} Destination ID and tour ID.
 */
function safari_lead_context(int $post_id = 0): array
{
    $post_id = $post_id ?: (int) get_the_ID();

    if ('tour' === get_post_type($post_id)) {
        return [safari_tour_destination($post_id), $post_id];
    }

    if ('destination' === get_post_type($post_id)) {
        return [$post_id, 0];
    }

    return [0, 0];
}

/**
 * Convert a three-letter month key to a localised short month name.
 *
 * @param string $key Month key, e.g. 'jan'.
 * @return string Localised short month, or the original key.
 */
function safari_month_name(string $key): string
{
    $keys   = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $key    = strtolower(substr($key, 0, 3));
    $index  = array_search($key, $keys, true);

    if (false === $index) {
        return ucfirst($key);
    }

    $timestamp = mktime(0, 0, 0, $index + 1, 1);

    return date_i18n('M', $timestamp);
}

/**
 * Every month key, in order.
 *
 * @return string[] Month keys.
 */
function safari_all_months(): array
{
    return ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
}

/**
 * Score each month 0–3 for how good a time to visit a destination is.
 *
 * Months explicitly ticked in ACF score 3. Months adjacent to a ticked month
 * score 1, which produces a soft shoulder rather than a hard edge — this
 * matches how seasons actually work on safari ground.
 *
 * @param string[] $best Month keys marked as best.
 * @return array<string,int> Month key to 0–3 score.
 */
function safari_month_levels(array $best): array
{
    $all   = safari_all_months();
    $best  = array_values(array_unique(array_map('strval', $best)));
    $levels = array_fill_keys($all, 0);

    foreach ($best as $key) {
        $index = array_search(strtolower($key), $all, true);

        if (false === $index) {
            continue;
        }

        $levels[$all[$index]] = 3;

        // Shoulder months.
        foreach ([$index - 1, $index + 1] as $neighbour) {
            if ($neighbour >= 0 && $neighbour < 12) {
                $levels[$all[$neighbour]] = max($levels[$all[$neighbour]], 1);
            }
        }
    }

    return $levels;
}

/* ==========================================================================
 * 2. Images
 * ========================================================================== */

/**
 * Resolve the post a card template part should render.
 *
 * WordPress gives a template part no `$post` of its own: `set_query_var( 'post',
 * $id )` only writes into `$wp_query->query_vars`, so a card rendered outside
 * the loop had `$post` undefined and fell through to `get_the_title( 0 )`.
 * That call is documented to use the *current* post when given 0, which on the
 * front page is the "Home" page — so every card on the homepage rendered as a
 * card titled "Home" pointing at the homepage. The data was never wrong; the
 * lookup was.
 *
 * Resolution order, most explicit first:
 *
 *   1. `$args['post_id']` / `$args['post']` — what a caller outside the loop
 *      passes, e.g. a related-rail or a hand-picked showcase entry.
 *   2. `$post` — correct when the caller is inside a real WP_Loop.
 *   3. 0, meaning "unknown": the caller is expected to render a deliberate
 *      empty state rather than silently print some other post's data.
 *
 * @param array $args Template arguments passed to get_template_part().
 * @return int Post ID, or 0 when no post could be identified.
 */
function safari_card_post_id(array $args = []): int
{
    foreach (['post_id', 'post'] as $key) {
        $candidate = $args[$key] ?? 0;

        if (is_object($candidate) && isset($candidate->ID)) {
            $candidate = (int) $candidate->ID;
        }

        $candidate = (int) $candidate;

        if ($candidate > 0) {
            return $candidate;
        }
    }

    // Inside a WP_Loop, $post is a real object and is the right answer.
    if (isset($post) && is_object($post) && isset($post->ID)) {
        return (int) $post->ID;
    }

    return 0;
}

/**
 * Print a responsive featured image, or a branded placeholder.
 *
 * The hero variant is never lazy and carries fetchpriority=high so it can
 * become the LCP element (plan §10).
 *
 * @param int    $post_id     Post ID.
 * @param string $size        Registered size.
 * @param array  $attr        Extra attributes.
 * @param bool   $eager       Load eagerly with high priority.
 */
function safari_thumbnail(int $post_id, string $size = 'safari-card', array $attr = [], bool $eager = false): void
{
    $classes = trim('card__img ' . (string) ($attr['class'] ?? ''));

    if (! has_post_thumbnail($post_id)) {
        echo safari_placeholder_svg($size, $classes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    $attributes = array_merge(
        [
            'class'   => $classes,
            'loading' => $eager ? 'eager' : 'lazy',
            'alt'     => esc_attr(get_the_title($post_id)),
        ],
        $attr
    );

    if ($eager) {
        $attributes['fetchpriority'] = 'high';
        $attributes['decoding']      = 'async';
    }

    echo get_the_post_thumbnail($post_id, $size, $attributes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Inline SVG placeholder in the brand palette, used when no image exists.
 *
 * The artwork is a stylised acacia-and-sun landscape so an empty site still
 * looks designed rather than broken.
 *
 * @param string $size   Registered image size.
 * @param string $class  Extra classes.
 */
function safari_placeholder_svg(string $size = 'safari-card', string $class = ''): string
{
    $ratio = [
        'safari-card'      => '16 / 10',
        'safari-hero'      => '16 / 9',
        'safari-portrait'  => '4 / 5',
        'thumbnail'        => '4 / 3',
        'medium'           => '4 / 3',
        'large'            => '16 / 9',
    ][$size] ?? '16 / 10';

    $classes = esc_attr(trim('placeholder ' . $class));
    $label   = esc_attr__('Image coming soon', 'safari-travel');

    return <<<SVG
<svg class="{$classes}" viewBox="0 0 640 400" role="img" aria-label="{$label}" focusable="false" preserveAspectRatio="xMidYMid slice" style="aspect-ratio:{$ratio}">
  <rect width="640" height="400" fill="#0F2B1F"/>
  <circle cx="486" cy="118" r="52" fill="#C9A227" opacity="0.32"/>
  <path d="M0 300 L150 214 L268 300 Z" fill="#14432F"/>
  <path d="M232 300 L400 196 L560 300 Z" fill="#1B5E43" opacity="0.75"/>
  <path d="M420 300 L540 236 L640 300 Z" fill="#24764F" opacity="0.55"/>
  <g stroke="#6BA98A" stroke-width="3" stroke-linecap="round" opacity="0.5">
    <path d="M96 268v-42M96 232l-20 16M96 226l20 16"/>
  </g>
  <rect x="0" y="300" width="640" height="100" fill="#0B2018"/>
</svg>
SVG;
}

/**
 * Reading time for a post.
 *
 * @param int $post_id Post ID.
 */
function safari_reading_time(int $post_id = 0): int
{
    $post_id = $post_id ?: (int) get_the_ID();

    if (! $post_id) {
        return 0;
    }

    $content = (string) get_post_field('post_content', $post_id);
    $words   = str_word_count(wp_strip_all_tags($content));

    return max(1, (int) ceil($words / 200));
}

/**
 * Trimmed, escaped excerpt.
 *
 * @param int      $length  Word count.
 * @param int|null $post_id Post ID.
 * @return string Plain text.
 */
function safari_excerpt(int $length = 24, ?int $post_id = null): string
{
    $post_id = $post_id ?: (int) get_the_ID();

    if (! $post_id) {
        return '';
    }

    $text = has_excerpt($post_id)
        ? get_the_excerpt($post_id)
        : wp_strip_all_tags(get_post_field('post_content', $post_id));

    return wp_trim_words($text, $length, '…');
}

/* ==========================================================================
 * 3. Markup fragments
 * ========================================================================== */

/**
 * Render a button/link.
 *
 * @param string $url     Destination URL.
 * @param string $label   Visible label.
 * @param string $variant primary|accent|outline|inverse|clay|subtle|quiet.
 * @param array  $args    size, class, attrs, magnetic.
 */
function safari_button(string $url, string $label, string $variant = 'primary', array $args = []): void
{
    $variants = ['primary', 'accent', 'outline', 'inverse', 'clay', 'subtle', 'quiet'];
    $variant  = in_array($variant, $variants, true) ? $variant : 'primary';

    $classes = ['btn', 'btn--' . $variant];

    if (! empty($args['size'])) {
        $classes[] = 'btn--' . sanitize_html_class((string) $args['size']);
    }
    if (! empty($args['class'])) {
        $classes[] = (string) $args['class'];
    }

    // The magnetic pull is applied client-side and only for fine pointers,
    // so the attribute is always safe to emit.
    if (! empty($args['magnetic'])) {
        $classes[] = 'btn--magnetic';
    }

    $magnetic = ! empty($args['magnetic']) ? ' data-magnetic="0.3"' : '';

    printf(
        '<a class="%s" href="%s"%s%s>%s</a>',
        esc_attr(implode(' ', $classes)),
        esc_url($url),
        $magnetic,
        ! empty($args['attrs']) ? ' ' . $args['attrs'] : '',
        esc_html($label)
    );
}

/**
 * Print an inline SVG icon from the theme's sprite set.
 *
 * Icons are inlined (not an icon font) so they inherit `currentColor` and cost
 * no extra request.
 *
 * @param string $name  Icon name.
 * @param array  $args  class, size.
 */
function safari_icon(string $name, array $args = []): void
{
    $paths = safari_icon_paths();

    if (! isset($paths[$name])) {
        return;
    }

    $class = trim('icon ' . (string) ($args['class'] ?? ''));
    $attrs = '';

    if (! empty($args['attrs'])) {
        $attrs = ' ' . $args['attrs'];
    }

    printf(
        '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"%s>%s</svg>',
        esc_attr($class),
        $attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $paths[$name] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    );
}

/**
 * The theme's inline SVG icon paths.
 *
 * @return array<string,string> Icon name to inner SVG markup.
 */
function safari_icon_paths(): array
{
    static $icons = null;

    if (null !== $icons) {
        return $icons;
    }

    $icons = [
        'arrow-right'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left'    => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'arrow-up'      => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down'    => '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'chevron-down'  => '<path d="M6 9l6 6 6-6"/>',
        'close'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu'          => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'search'        => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'phone'         => '<path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2Z"/>',
        'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'whatsapp'      => '<path d="M3 21l1.6-4.6A8 8 0 1 1 8 20.4L3 21Z"/><path d="M8.6 9.2c.2-.5.4-.5.7-.5h.6c.2 0 .4 0 .6.5l.7 1.6c.1.3 0 .5-.1.6l-.4.5c-.2.2-.2.4 0 .6.5.7 1.2 1.3 2 1.6.3.1.5.1.6-.1l.5-.6c.2-.2.4-.2.6-.1l1.5.8c.3.2.4.3.4.5 0 .2 0 .8-.3 1.1-.3.4-.9.7-1.4.7-1.1 0-2.3-.5-3.5-1.5-1.2-1-2-2.1-2.3-3-.2-.5-.2-1 .1-1.4Z"/>',
        'pin'           => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'calendar'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'users'         => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M18 20a5.5 5.5 0 0 0-3-4.9"/>',
        'user'          => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'shield'        => '<path d="M12 3l7 3v5.5c0 4.3-2.9 8.2-7 9.5-4.1-1.3-7-5.2-7-9.5V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',
        'award'         => '<circle cx="12" cy="9" r="5"/><path d="m8.5 13.5-1 7 4.5-2.5 4.5 2.5-1-7"/>',
        'leaf'          => '<path d="M4 20C3 12 8 5 20 4c1 12-6 16-12 15"/><path d="M4 20c3-6 7-9 12-11"/>',
        'camera'        => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7 10 4h4l1 3"/><circle cx="12" cy="13" r="3.5"/>',
        'compass'       => '<circle cx="12" cy="12" r="9"/><path d="m15 9-2 5-4 2 2-5 4-2Z"/>',
        'check'         => '<path d="m4 12 5 5L20 6"/>',
        'check-circle'  => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 8h.01"/>',
        'star'          => '<path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.5-5-2.7-5 2.7 1-5.5-4-3.9 5.6-.8L12 4Z"/>',
        'quote'         => '<path d="M9 7H5.5A2.5 2.5 0 0 0 3 9.5V13a2 2 0 0 0 2 2h2v2H4M20 7h-3.5A2.5 2.5 0 0 0 14 9.5V13a2 2 0 0 0 2 2h2v2h-3"/>',
        'bed'           => '<path d="M3 18V7M3 12h18v6M21 18v-4M7 10h3"/>',
        'binocular'     => '<path d="M6 8h3l1.5 8H4.5L6 8ZM18 8h-3l-1.5 8h4.5L18 8Z"/><path d="M9 8h6"/>',
        'route'         => '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18h5a3.5 3.5 0 0 0 0-7h-3a3.5 3.5 0 0 1 0-7h5"/>',
        'sparkle'       => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"/>',
        'truck'         => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'lock'          => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'headset'       => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="2"/><rect x="17" y="13" width="4" height="6" rx="2"/><path d="M20 19a3 3 0 0 1-3 3h-3"/>',
        'filter'        => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>',
        'home'          => '<path d="m4 11 8-7 8 7v9H4z"/><path d="M10 20v-6h4v6"/>',
        'globe'         => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>',
        'mountain'      => '<path d="m3 19 6-11 4 7 2-3 6 7H3Z"/>',
        'tent'          => '<path d="M12 4 3 20h18L12 4Z"/><path d="M12 9v11"/>',
        'coffee'        => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"/><path d="M17 9h1.5a2.5 2.5 0 0 1 0 5H17"/>',
        'wifi'          => '<path d="M5 12.5a10 10 0 0 1 14 0M8 16a6 6 0 0 1 8 0"/><path d="M12 20h.01"/>',
        'refresh'       => '<path d="M20 11a8 8 0 1 0-2 6"/><path d="M20 5v6h-6"/>',
        'download'      => '<path d="M12 3v12M7 11l5 5 5-5M4 20h16"/>',
    ];

    return $icons;
}

/**
 * Star rating markup.
 *
 * @param int    $rating Rating 0–5.
 * @param string $label  Accessible label, e.g. "4.9 out of 5".
 */
function safari_rating(int $rating, string $label = ''): void
{
    $rating = max(0, min(5, $rating));

    if ($rating < 1) {
        return;
    }

    $label = $label ?: sprintf(
        /* translators: %s: rating value */
        __('%s out of 5', 'safari-travel'),
        number_format_i18n($rating, 1)
    );

    printf(
        '<span class="rating" role="img" aria-label="%s">',
        esc_attr($label)
    );

    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= $rating ? 'currentcolor' : 'none';
        printf(
            '<svg class="rating__star" viewBox="0 0 24 24" aria-hidden="true" fill="%s" stroke="currentColor" stroke-width="1.5">%s</svg>',
            esc_attr($fill),
            '<path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.5-5-2.7-5 2.7 1-5.5-4-3.9 5.6-.8L12 4Z"/>'
        );
    }

    echo '</span>';
}

/**
 * Pagination for any archive.
 */
function safari_pagination(): void
{
    $links = paginate_links([
        'type'      => 'list',
        'mid_size'  => 1,
        'end_size'  => 1,
        'prev_text' => '',
        'next_text' => '',
    ]);

    if (! $links) {
        return;
    }

    echo '<nav class="pagination" aria-label="' . esc_attr__('Pagination', 'safari-travel') . '">';
    // The list items are structural only; the anchors carry the semantics.
    echo wp_kses_post(str_replace('<li>', '', $links));
    echo '</nav>';
}

/**
 * Breadcrumb trail. Also feeds the BreadcrumbList JSON-LD (plan §12).
 */
function safari_breadcrumbs(): void
{
    if (is_front_page()) {
        return;
    }

    $items = [];

    $items[] = [
        'name' => __('Home', 'safari-travel'),
        'url'  => home_url('/'),
    ];

    if (is_singular('destination') || is_singular('tour') || is_singular('event') || is_singular('safari_guide')) {
        $post_type = get_post_type();
        $object    = get_post_type_object($post_type);

        if ($object && $object->has_archive) {
            $items[] = [
                'name' => $object->labels->name,
                'url'  => (string) get_post_type_archive_link($post_type),
            ];
        }

        $items[] = ['name' => get_the_title()];
    } elseif (is_category() || is_tag() || is_tax()) {
        $items[] = [
            'name' => single_term_title('', false),
            'url'  => get_term_link(),
        ];
    } elseif (is_post_type_archive()) {
        $items[] = ['name' => post_type_archive_title('', false)];
    } elseif (is_page()) {
        $ancestors = array_reverse(get_post_ancestors(get_the_ID()));

        foreach ($ancestors as $ancestor) {
            $items[] = [
                'name' => get_the_title($ancestor),
                'url'  => (string) get_permalink($ancestor),
            ];
        }

        $items[] = ['name' => get_the_title()];
    } elseif (is_search()) {
        $items[] = [
            'name' => sprintf(
                /* translators: %s: search term */
                __('Search: %s', 'safari-travel'),
                get_search_query()
            ),
        ];
    } elseif (is_404()) {
        $items[] = ['name' => __('Page not found', 'safari-travel')];
    } elseif (is_home()) {
        $items[] = ['name' => __('Travel Guides', 'safari-travel')];
    }

    if (count($items) < 1) {
        return;
    }

    printf(
        '<nav class="breadcrumbs breadcrumbs--inverse" aria-label="%s"><div class="container"><ol class="breadcrumbs__list">',
        esc_attr__('Breadcrumb', 'safari-travel')
    );

    $last = count($items) - 1;

    foreach ($items as $index => $item) {
        $is_last = ($index === $last);

        echo '<li class="breadcrumbs__item' . ($is_last ? ' breadcrumbs__item--current' : '') . '">';

        if (! $is_last && ! empty($item['url'])) {
            printf('<a href="%s">%s</a>', esc_url($item['url']), esc_html($item['name']));
        } else {
            printf(
                '<span%s>%s</span>',
                $is_last ? ' aria-current="page"' : '',
                esc_html($item['name'])
            );
        }

        echo '</li>';
    }

    echo '</ol></div></nav>';

    safari_schema_breadcrumbs($items);
}

/**
 * Trust bar shown in the footer and on key conversion pages.
 *
 * @param bool $dark Render for dark surfaces.
 */
function safari_trust_bar(bool $dark = false): void
{
    $items = [
        ['shield',  __('Licensed & bonded operator', 'safari-travel')],
        ['headset', __('24/7 in-destination support', 'safari-travel')],
        ['award',   __('Tailor-made, never cookie-cutter', 'safari-travel')],
        ['lock',    __('Secure, encrypted payments', 'safari-travel')],
    ];

    printf(
        '<ul class="trust-bar%s">',
        $dark ? ' trust-bar--dark' : ''
    );

    foreach ($items as [$icon, $label]) {
        echo '<li class="trust-bar__item">';
        safari_icon($icon);
        echo '<span>' . esc_html($label) . '</span></li>';
    }

    echo '</ul>';
}

/**
 * A WhatsApp deep link with a pre-filled message.
 *
 * @param string $message Prefilled text.
 */
function safari_whatsapp_url(string $message = ''): string
{
    $number = safari_whatsapp();

    if ('' === $number) {
        return '';
    }

    $default = __("Hi! I'd like to plan a safari.", 'safari-travel');

    return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($message ?: $default);
}

/**
 * A tel: link built from the configured phone number.
 */
function safari_tel_url(): string
{
    $phone = safari_phone();

    if ('' === $phone) {
        return '';
    }

    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

/**
 * Whether the current view is a shop view that WooCommerce owns.
 */
function safari_is_shop(): bool
{
    return function_exists('is_woocommerce') && (
        is_woocommerce()
        || is_cart()
        || is_checkout()
        || is_account_page()
    );
}

/* ==========================================================================
 * 4. Navigation fallbacks
 * ========================================================================== */

/**
 * The primary navigation destinations the site needs to be usable.
 *
 * Used only until an editor assigns Appearance → Menus. Each entry mirrors
 * the plan.md §4.3 sitemap so a fresh install is already navigable.
 *
 * @return array<int,array{label:string,url:string,children:array}>
 */
function safari_primary_nav_items(): array
{
    $items = [
        [
            'label'    => __('Destinations', 'safari-travel'),
            'url'      => (string) get_post_type_archive_link('destination'),
            'children' => [],
        ],
        [
            'label'    => __('Tours', 'safari-travel'),
            'url'      => (string) get_post_type_archive_link('tour'),
            'children' => [],
        ],
        [
            'label'    => __('Offers & Events', 'safari-travel'),
            'url'      => (string) get_post_type_archive_link('event'),
            'children' => [],
        ],
        [
            'label'    => __('Travel Guides', 'safari-travel'),
            'url'      => (string) get_post_type_archive_link('safari_guide'),
            'children' => [],
        ],
        [
            'label'    => __('Support', 'safari-travel'),
            'url'      => '',
            'children' => [
                ['label' => __('Plan My Safari', 'safari-travel'), 'url' => safari_plan_url()],
                ['label' => __('Contact Us', 'safari-travel'), 'url' => safari_page_url('contact')],
                ['label' => __('FAQ', 'safari-travel'), 'url' => safari_page_url('faq')],
            ],
        ],
    ];

    if (function_exists('wc_get_page_permalink')) {
        $items[] = [
            'label'    => __('Shop', 'safari-travel'),
            'url'      => (string) wc_get_page_permalink('shop'),
            'children' => [],
        ];
    }

    return array_values(array_filter($items, static fn (array $i): bool => '' !== (string) $i['url'] || ! empty($i['children'])));
}

/**
 * Permalink for a page looked up by slug, falling back to a slug URL.
 *
 * @param string $slug Page slug.
 * @return string URL.
 */
function safari_page_url(string $slug): string
{
    $page = get_page_by_path($slug);

    return $page ? (string) get_permalink($page) : home_url('/' . $slug . '/');
}

/**
 * Configured social profile URLs, keyed by icon name.
 *
 * Profiles are stored as `safari_social_1..5` theme mods (see inc/schema.php)
 * so the editor has one place to manage them.
 *
 * @return array<string,string> Icon name to URL.
 */
function safari_social_links(): array
{
    $known = ['facebook', 'instagram', 'whatsapp', 'youtube', 'linkedin'];
    $links = [];

    foreach ($known as $index => $network) {
        $url = get_theme_mod('safari_social_' . ($index + 1), '');

        if (is_string($url) && '' !== $url) {
            $links[$network] = $url;
        }
    }

    return $links;
}

/**
 * Render a hard-coded navigation tree when no menu is assigned.
 *
 * Kept in the theme so the site is never un-navigable, per plan §8. Renders
 * either the desktop dropdown pattern or the flat drawer accordion pattern.
 *
 * @param string $variant 'header' for the dropdown bar, 'drawer' for the sheet.
 */
function safari_fallback_menu(string $variant = 'header'): void
{
    $is_drawer = ('drawer' === $variant);
    $classes   = $is_drawer ? 'drawer-nav' : 'site-nav__list';
    $item_type = 'drawer' === $variant ? 'drawer-nav' : 'site-nav';

    printf('<ul class="%s">', esc_attr($classes));

    foreach (safari_primary_nav_items() as $item) {
        $has_children = ! empty($item['children']);
        $url          = (string) $item['url'];

        // Mark the branch that contains the current page.
        $is_current = $url && untrailingslashit($url) === untrailingslashit((string) get_permalink());
        $is_type    = $url && $url === (string) get_post_type_archive_link(get_post_type());

        $item_classes = array_filter([
            $is_current ? $item_type . '__item--current' : '',
            $has_children ? $item_type . '__item--has-children' : '',
        ]);

        printf('<li class="%s">', esc_attr(implode(' ', $item_classes)));

        printf(
            '<a class="%s" href="%s"%s>%s</a>',
            esc_attr($item_type . '__link'),
            esc_url('' !== $url ? $url : '#'),
            $is_current ? ' aria-current="page"' : '',
            esc_html($item['label'])
        );

        if ($has_children) {
            printf(
                '<ul class="%s">',
                esc_attr($is_drawer ? 'drawer-nav__sub' : 'site-nav__submenu')
            );

            foreach ($item['children'] as $child) {
                printf(
                    '<li><a class="%s" href="%s">%s</a></li>',
                    esc_attr($item_type . '__link'),
                    esc_url($child['url']),
                    esc_html($child['label'])
                );
            }

            echo '</ul>';
        }

        echo '</li>';
    }

    echo '</ul>';
}
