<?php
/**
 * Plugin Name: Safari Core
 * Plugin URI:  https://example.com/safari-core
 * Description: Content model foundation for Safari Travel — CPTs, taxonomies, roles and shared setup.
 * Version:     1.0.0
 * Author:      Safari Travel
 * Text Domain: safari-core
 * Requires PHP: 8.2
 * Requires at least: 6.4
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('SAFARI_CORE_VERSION', '1.0.0');
define('SAFARI_CORE_FILE', __FILE__);
define('SAFARI_CORE_DIR', plugin_dir_path(__FILE__));
define('SAFARI_CORE_URL', plugin_dir_url(__FILE__));

require SAFARI_CORE_DIR . 'inc/post-types.php';
require SAFARI_CORE_DIR . 'inc/taxonomies.php';
require SAFARI_CORE_DIR . 'inc/roles.php';
require SAFARI_CORE_DIR . 'inc/install.php';
require SAFARI_CORE_DIR . 'inc/site-settings.php';
require SAFARI_CORE_DIR . 'inc/class-safari-images.php';

add_filter('acf/settings/load_json', function (array $paths): array {
	$paths[] = SAFARI_CORE_DIR . 'acf-json';

	return $paths;
});

add_filter('acf/settings/save_json', function (): string {
	return SAFARI_CORE_DIR . 'acf-json';
});

add_action('plugins_loaded', static function (): void {
	load_plugin_textdomain('safari-core', false, dirname(plugin_basename(SAFARI_CORE_FILE)) . '/languages');
});

// WP-CLI: `wp safari seed`
if (defined('WP_CLI') && WP_CLI) {
	require SAFARI_CORE_DIR . 'inc/class-safari-seed-command.php';
}
