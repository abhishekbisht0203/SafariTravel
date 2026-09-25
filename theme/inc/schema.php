<?php
/**
 * Structured data (Schema.org JSON-LD) — plan §12.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Organization / TravelAgency schema sitewide.
 */
function safari_schema_organization(): void
{
    if (! is_front_page()) {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'TravelAgency',
        'name'     => get_bloginfo('name'),
        'url'      => home_url('/'),
        'logo'     => safari_site_logo_url(),
        'address'  => safari_schema_address(),
    ];

    $same = [];
    foreach (safari_social_profiles() as $profile) {
        $same[] = esc_url_raw($profile);
    }
    if ($same) {
        $data['sameAs'] = $same;
    }

    safari_print_json_ld($data);
}

/**
 * BreadcrumbList for singular views.
 */
function safari_schema_breadcrumbs(array $items): void
{
    if (empty($items)) {
        return;
    }

    $breadcrumbs = [];
    $position    = 1;
    foreach ($items as $item) {
        $breadcrumbs[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $item['name'],
            'item'     => $item['url'] ?? null,
        ];
    }

    safari_print_json_ld([
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => array_filter($breadcrumbs),
    ]);
}

/**
 * TouristDestination schema for a destination CPT.
 */
function safari_schema_destination(int $post_id): void
{
    if (get_post_type($post_id) !== 'destination') {
        return;
    }

    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'TouristDestination',
        'name'     => get_the_title($post_id),
        'url'      => get_permalink($post_id),
        'description' => safari_excerpt(40, $post_id),
    ];

    if (has_post_thumbnail($post_id)) {
        $img = wp_get_attachment_image_url((int) get_post_thumbnail_id($post_id), 'safari-hero');
        if ($img) {
            $data['image'] = $img;
        }
    }

    safari_print_json_ld($data);
}

/**
 * TouristTrip schema for a tour CPT.
 */
function safari_schema_tour(int $post_id): void
{
    if (get_post_type($post_id) !== 'tour') {
        return;
    }

    $meta = get_post_meta($post_id, '_safari_tour', true);
    $days = is_array($meta) ? (int) ($meta['duration_days'] ?? 0) : 0;
    $from = is_array($meta) ? (string) ($meta['price_from'] ?? '') : '';

    $data = [
        '@context'    => 'https://schema.org',
        '@type'       => 'TouristTrip',
        'name'        => get_the_title($post_id),
        'url'         => get_permalink($post_id),
        'description' => safari_excerpt(40, $post_id),
    ];

    if ($days > 0) {
        $data['touristType'] = sprintf(
            /* translators: %d: number of days */
            _n('%d-day safari', '%d-day safari', $days, 'safari-travel'),
            $days
        );
    }

    if ($from !== '' && is_numeric($from)) {
        $data['offers'] = [
            '@type'         => 'Offer',
            'price'         => $from,
            'priceCurrency' => get_option('safari_leads_settings', [])['currency'] ?? 'USD',
            'availability'  => 'https://schema.org/InStock',
            'url'           => get_permalink($post_id),
        ];
    }

    safari_print_json_ld($data);
}

/**
 * FAQPage schema from faq posts.
 */
function safari_schema_faq(array $faqs): void
{
    if (empty($faqs)) {
        return;
    }

    $main = [];
    foreach ($faqs as $faq) {
        $mainEntity[] = [
            '@type'          => 'Question',
            'name'           => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => wp_strip_all_tags($faq['answer']),
            ],
        ];
    }

    safari_print_json_ld([
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $mainEntity ?? [],
    ]);
}

/**
 * Print JSON-LD script tag with proper escaping.
 */
function safari_print_json_ld(array $data): void
{
    $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (! $json) {
        return;
    }
    echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function safari_site_logo_url(): string
{
    if (has_custom_logo()) {
        $id = (int) get_theme_mod('custom_logo');
        $url = wp_get_attachment_image_url($id, 'full');
        if ($url) {
            return $url;
        }
    }
    return '';
}

function safari_schema_address(): array
{
    $settings = get_option('safari_leads_settings', []);
    return [
        '@type'           => 'PostalAddress',
        'addressLocality' => $settings['city'] ?? get_bloginfo('name'),
        'addressCountry'  => $settings['country'] ?? '',
    ];
}

function safari_social_profiles(): array
{
    $profiles = [];
    for ($i = 1; $i <= 5; $i++) {
        $url = get_theme_mod('safari_social_' . $i, '');
        if (is_string($url) && $url !== '') {
            $profiles[] = $url;
        }
    }
    return $profiles;
}
