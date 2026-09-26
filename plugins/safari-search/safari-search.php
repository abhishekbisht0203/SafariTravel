<?php
/**
 * Plugin Name:       Safari Search
 * Plugin URI:        https://example.com/safari-search
 * Description:       Public live-search endpoint for the theme's search overlay, plus archive filter helpers.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Safari Travel
 * Text Domain:       safari-search
 *
 * @package Safari_Search
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('SAFARI_SEARCH_VERSION', '1.0.0');
define('SAFARI_SEARCH_FILE', __FILE__);
define('SAFARI_SEARCH_DIR', plugin_dir_path(__FILE__));
define('SAFARI_SEARCH_URL', plugin_dir_url(__FILE__));

require SAFARI_SEARCH_DIR . 'inc/class-safari-search-rest.php';

add_action('plugins_loaded', static function (): void {
	load_plugin_textdomain('safari-search', false, dirname(plugin_basename(SAFARI_SEARCH_FILE)) . '/languages');
});

Safari_Search_REST::init();
