<?php
/**
 * Theme setup: supports, menus, image sizes, and runtime configuration.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    load_theme_textdomain('safari-travel', SAFARI_THEME_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    add_theme_support('wp-block-styles');
    add_theme_support('custom-logo', [
        'height'      => 48,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets',
    ]);
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 400,
        'gallery_thumbnail_image_width' => 200,
        'single_image_width' => 800,
        'product_grid' => [
            'default_columns' => 3,
            'min_columns'     => 2,
            'max_columns'     => 4,
        ],
    ]);

    register_nav_menus([
        'primary' => __('Primary Menu', 'safari-travel'),
        'footer'  => __('Footer Menu', 'safari-travel'),
        'legal'   => __('Legal Menu', 'safari-travel'),
    ]);

    /*
     * Image sizes are registered for the layouts that actually use them, so
     * WordPress never generates unused derivatives (plan §10).
     */
    add_image_size('safari-card', 640, 400, true);      // Archive cards
    add_image_size('safari-card-lg', 960, 600, true);   // Featured cards
    add_image_size('safari-hero', 1920, 1080, true);    // Full-bleed heroes
    add_image_size('safari-portrait', 640, 800, true);  // Tall cards
    add_image_size('safari-pano', 2000, 750, true);     // Ultra-wide bands
}, 5);

/**
 * Add `no-js` to <html>, then flip it to `js` in an inline <head> script.
 *
 * The reveal system hides `[data-reveal]` content behind `.js`, so this
 * guarantees content is never hidden when scripts fail. Inlined to avoid a
 * flash of hidden content.
 *
 * @param string $output Existing language attributes.
 * @return string Filtered attributes.
 */
add_filter('language_attributes', static function (string $output): string {
    return $output . ' class="no-js"';
});

add_action('wp_head', static function (): void {
    echo "<script>document.documentElement.classList.replace('no-js','js');</script>\n";
}, 1);

/**
 * Body classes used by the stylesheet and the JS runtime.
 *
 * @param string[] $classes Existing classes.
 * @return string[] Filtered classes.
 */
add_filter('body_class', static function (array $classes): array {
    if (safari_is_shop()) {
        $classes[] = 'is-shop';
    }

    if (is_singular() && has_post_thumbnail()) {
        $classes[] = 'has-hero-image';
    }

    if (is_front_page()) {
        $classes[] = 'is-home';
    }

    if (get_theme_mod('safari_reduced_motion', false)) {
        $classes[] = 'reduce-motion';
    }

    return $classes;
});

/**
 * Trim WordPress head output we do not use (plan §10).
 */
add_action('wp_head', static function (): void {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
}, 20);

/**
 * Body classes on the <body> element, plus a data attribute the JS reads for
 * the REST base URL used by live search.
 */
add_action('wp_footer', static function (): void {
    if (is_admin() || is_feed()) {
        return;
    }

    printf(
        '<script>window.safariData=%s;</script>' . "\n",
        wp_json_encode([
            'restUrl'      => esc_url_raw(rest_url('safari/v1/')),
            'searchPerPage' => 8,
            'homeUrl'      => esc_url_raw(home_url('/')),
        ])
    );
}, 5);

/**
 * Register the custom image size names in the media picker so editors pick the
 * right one (plan §8).
 */
add_filter('image_size_names_choose', static function (array $sizes): array {
    return array_merge($sizes, [
        'safari-card'     => __('Safari Card (640×400)', 'safari-travel'),
        'safari-card-lg'  => __('Safari Card Large (960×600)', 'safari-travel'),
        'safari-hero'     => __('Safari Hero (1920×1080)', 'safari-travel'),
        'safari-portrait' => __('Safari Portrait (640×800)', 'safari-travel'),
        'safari-pano'     => __('Safari Panoramic (2000×750)', 'safari-travel'),
    ]);
});
