<?php
/**
 * Plugin Name:       Safari Leads
 * Plugin URI:        https://example.com/safari-leads
 * Description:       Lead capture forms, lead management, notifications and retention for Safari Travel.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Safari Travel
 * Text Domain:       safari-leads
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('SAFARI_LEADS_VERSION', '1.0.0');
define('SAFARI_LEADS_FILE', __FILE__);
define('SAFARI_LEADS_DIR', plugin_dir_path(__FILE__));
define('SAFARI_LEADS_URL', plugin_dir_url(__FILE__));

require SAFARI_LEADS_DIR . 'inc/class-safari-lead-settings.php';
require SAFARI_LEADS_DIR . 'inc/class-safari-lead-db.php';
require SAFARI_LEADS_DIR . 'inc/class-safari-lead-save.php';
require SAFARI_LEADS_DIR . 'inc/class-safari-lead-rest.php';
require SAFARI_LEADS_DIR . 'inc/class-safari-lead-form.php';
require SAFARI_LEADS_DIR . 'inc/class-safari-lead-email.php';

if (is_admin()) {
	require SAFARI_LEADS_DIR . 'inc/admin/class-safari-lead-admin.php';
}

add_action('plugins_loaded', static function (): void {
	load_plugin_textdomain('safari-leads', false, dirname(plugin_basename(SAFARI_LEADS_FILE)) . '/languages');
});

register_activation_hook(SAFARI_LEADS_FILE, static function (): void {
	Safari_Lead_DB::create_tables();
	Safari_Lead_Settings::merge_defaults();

	if (! wp_next_scheduled('safari_leads_retention_cron')) {
		wp_schedule_event(time() + 3600, 'daily', 'safari_leads_retention_cron');
	}
});

register_deactivation_hook(SAFARI_LEADS_FILE, static function (): void {
	wp_clear_scheduled_hook('safari_leads_retention_cron');
});

add_action('init', array(Safari_Lead_DB::class, 'maybe_create_tables'));

Safari_Lead_Save::init();
Safari_Lead_REST::init();
Safari_Lead_Form::init();
Safari_Lead_Email::init();

add_action('safari_leads_retention_cron', array(Safari_Lead_Save::class, 'purge_expired'));
add_filter('wp_privacy_personal_data_exporters', array(Safari_Lead_Save::class, 'register_exporter'));
add_filter('wp_privacy_personal_data_erasers', array(Safari_Lead_Save::class, 'register_eraser'));
