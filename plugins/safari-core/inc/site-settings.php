<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('acf/init', static function (): void {
	if (!function_exists('acf_add_options_page')) {
		return;
	}

	acf_add_options_page([
		'page_title'	=>__('Site Settings', 'safari-core'),
		'menu_title'	=>__('Site Settings', 'safari-core'),
		'menu_slug'		=>'safari-site-settings',
		'capability'	=>'manage_options',
		'redirect'		=>false,
		'icon_url'		=>'dashicons-admin-generic',
		'position'		=>59,
	]);
});
