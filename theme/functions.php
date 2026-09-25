<?php
/**
 * Safari Travel theme bootstrap.
 *
 * Loads Composer autoloader, constants, and theme setup.
 * Content structure (CPTs, taxonomies) lives in plugins, not here.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SAFARI_THEME_VERSION', '1.0.0');
define('SAFARI_THEME_DIR', get_template_directory());
define('SAFARI_THEME_URI', get_template_directory_uri());

// Composer autoloader (PSR-4 for theme classes).
$safari_theme_autoload = SAFARI_THEME_DIR . '/vendor/autoload.php';
if (file_exists($safari_theme_autoload)) {
    require_once $safari_theme_autoload;
}

// Core includes.
require_once SAFARI_THEME_DIR . '/inc/setup.php';
require_once SAFARI_THEME_DIR . '/inc/assets.php';
require_once SAFARI_THEME_DIR . '/inc/helpers.php';
require_once SAFARI_THEME_DIR . '/inc/schema.php';
require_once SAFARI_THEME_DIR . '/inc/woocommerce.php';
require_once SAFARI_THEME_DIR . '/inc/block-patterns.php';
