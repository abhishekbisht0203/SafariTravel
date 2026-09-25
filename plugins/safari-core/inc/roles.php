<?php
/**
 * Roles and capabilities.
 *
 * Capabilities:
 * - view_safari_leads
 * - edit_safari_leads
 * - delete_safari_leads
 * - export_safari_leads
 * - manage_safari_leads_settings
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

add_action('init', static function (): void {
	$lead_caps = [
		'view_safari_leads',
		'edit_safari_leads',
		'delete_safari_leads',
		'export_safari_leads',
		'manage_safari_leads_settings',
	];

	// Administrators already get all capabilities; register lead caps on role level.
	$admin = get_role('administrator');
	if ($admin) {
		foreach ($lead_caps as $cap) {
			$admin->add_cap($cap);
		}
	}

	if (! get_role('safari_sales_manager')) {
		add_role(
			'safari_sales_manager',
			__('Sales Manager', 'safari-core'),
			[
				'read'                 => true,
				'upload_files'         => true,
				'view_safari_leads'    => true,
				'edit_safari_leads'    => true,
				'export_safari_leads'  => true,
			]
		);
	} else {
		$role = get_role('safari_sales_manager');
		if ($role) {
			$sales_caps = [
				'view_safari_leads',
				'edit_safari_leads',
				'export_safari_leads',
			];
			foreach ($sales_caps as $cap) {
				$role->add_cap($cap);
			}
			$role->remove_cap('delete_safari_leads');
			$role->remove_cap('manage_safari_leads_settings');
		}
	}
}, 5);
