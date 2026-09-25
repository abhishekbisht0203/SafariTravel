<?php
/**
 * Custom taxonomies.
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

add_action('init', static function (): void {
	$taxonomies = [
		'region' => [
			'singular'   => __('Region', 'safari-core'),
			'plural'     => __('Regions', 'safari-core'),
			'hierarchical' => true,
			'objects'    => ['destination', 'tour', 'event'],
		],
		'safari_type' => [
			'singular'   => __('Safari Type', 'safari-core'),
			'plural'     => __('Safari Types', 'safari-core'),
			'hierarchical' => false,
			'objects'    => ['destination', 'tour'],
		],
		'travel_style' => [
			'singular'   => __('Travel Style', 'safari-core'),
			'plural'     => __('Travel Styles', 'safari-core'),
			'hierarchical' => false,
			'objects'    => ['tour', 'destination'],
		],
		'season' => [
			'singular'   => __('Season', 'safari-core'),
			'plural'     => __('Seasons', 'safari-core'),
			'hierarchical' => false,
			'objects'    => ['tour', 'destination', 'event'],
		],
		'event_type' => [
			'singular'   => __('Event Type', 'safari-core'),
			'plural'     => __('Event Types', 'safari-core'),
			'hierarchical' => true,
			'objects'    => ['event'],
		],
		'guide_topic' => [
			'singular'   => __('Guide Topic', 'safari-core'),
			'plural'     => __('Guide Topics', 'safari-core'),
			'hierarchical' => false,
			'objects'    => ['safari_guide'],
		],
		'faq_category' => [
			'singular'   => __('FAQ Category', 'safari-core'),
			'plural'     => __('FAQ Categories', 'safari-core'),
			'hierarchical' => true,
			'objects'    => ['faq'],
		],
	];

	foreach ($taxonomies as $slug => $config) {
		$defaults = [
			'labels'            => [
				'name'          => $config['plural'],
				'singular_name' => $config['singular'],
				'search_items'  => sprintf(
					/* translators: %s: taxonomy plural name */
					__('Search %s', 'safari-core'),
					$config['plural']
				),
				'all_items'     => sprintf(
					/* translators: %s: taxonomy plural name */
					__('All %s', 'safari-core'),
					$config['plural']
				),
				'edit_item'     => sprintf(
					/* translators: %s: taxonomy singular name */
					__('Edit %s', 'safari-core'),
					$config['singular']
				),
				'not_found'     => sprintf(
					/* translators: %s: taxonomy plural name */
					__('No %s found', 'safari-core'),
					strtolower($config['plural'])
				),
			],
			'hierarchical'      => $config['hierarchical'],
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => ['slug' => $slug],
		];

		register_taxonomy($slug, $config['objects'], $defaults);
	}
});
