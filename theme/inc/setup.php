<?php
/**
 * Theme setup: supports, menus, image sizes.
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
        'primary'   => __('Primary Menu', 'safari-travel'),
        'footer'    => __('Footer Menu', 'safari-travel'),
        'legal'     => __('Legal Menu', 'safari-travel'),
    ]);

    add_image_size('safari-card', 640, 426, true);
    add_image_size('safari-hero', 1920, 1080, true);
    add_image_size('safari-portrait', 480, 640, true);
});
