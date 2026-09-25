<?php
/**
 * Activation routines.
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

register_activation_hook(SAFARI_CORE_FILE, static function (): void {
	// Ensure CPT/taxonomy registration runs before flushing rewrites.
	do_action('init');

	flush_rewrite_rules();

	update_option('safari_core_version', SAFARI_CORE_VERSION);
});

register_deactivation_hook(SAFARI_CORE_FILE, static function (): void {
	flush_rewrite_rules();
});
