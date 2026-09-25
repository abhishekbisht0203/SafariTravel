<?php
/**
 * Custom post types.
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

add_action('init', static function (): void {
	$post_types = [
		'destination' => [
			'singular' => __('Destination', 'safari-core'),
			'plural'   => __('Destinations', 'safari-core'),
			'args'     => [
				'has_archive' => 'destinations',
				'menu_icon'   => 'dashicons-location-alt',
				'rewrite'     => ['slug' => 'destinations'],
				'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
				'show_in_rest' => true,
			],
		],
		'tour' => [
			'singular' => __('Tour', 'safari-core'),
			'plural'   => __('Tours', 'safari-core'),
			'args'     => [
				'has_archive' => 'tours',
				'menu_icon'   => 'dashicons-plane',
				'rewrite'     => ['slug' => 'tours'],
				'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
				'show_in_rest' => true,
			],
		],
		'event' => [
			'singular' => __('Event', 'safari-core'),
			'plural'   => __('Events', 'safari-core'),
			'args'     => [
				'has_archive' => 'events',
				'menu_icon'   => 'dashicons-calendar-alt',
				'rewrite'     => ['slug' => 'events'],
				'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields'],
				'show_in_rest' => true,
			],
		],
		'safari_guide' => [
			'singular' => __('Guide', 'safari-core'),
			'plural'   => __('Guides', 'safari-core'),
			'args'     => [
				'has_archive' => 'guides',
				'menu_icon'   => 'dashicons-book-alt',
				'rewrite'     => ['slug' => 'guides'],
				'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
				'show_in_rest' => true,
			],
		],
		'faq' => [
			'singular' => __('FAQ', 'safari-core'),
			'plural'   => __('FAQs', 'safari-core'),
			'args'     => [
				'has_archive' => false,
				'menu_icon'   => 'dashicons-editor-help',
				'rewrite'     => ['slug' => 'faq'],
				'supports'    => ['title', 'editor', 'page-attributes', 'custom-fields'],
				'show_in_rest' => true,
				'menu_position' => 22,
			],
		],
		'testimonial' => [
			'singular' => __('Testimonial', 'safari-core'),
			'plural'   => __('Testimonials', 'safari-core'),
			'args'     => [
				'has_archive' => false,
				'menu_icon'   => 'dashicons-format-quote',
				'rewrite'     => ['slug' => 'testimonials'],
				'supports'    => ['title', 'editor', 'thumbnail', 'custom-fields'],
				'show_in_rest' => true,
				'menu_position' => 23,
			],
		],
	];

	foreach ($post_types as $slug => $config) {
		$defaults = [
			'labels'       => [
				'name'          => $config['plural'],
				'singular_name' => $config['singular'],
				'add_new_item'  => sprintf(
					/* translators: %s: post type singular name */
					__('Add New %s', 'safari-core'),
					$config['singular']
				),
				'edit_item'     => sprintf(
					/* translators: %s: post type singular name */
					__('Edit %s', 'safari-core'),
					$config['singular']
				),
				'search_items'  => sprintf(
					/* translators: %s: post type plural name */
					__('Search %s', 'safari-core'),
					$config['plural']
				),
				'not_found'     => sprintf(
					/* translators: %s: post type plural name */
					__('No %s found', 'safari-core'),
					strtolower($config['plural'])
				),
			],
			'public'       => true,
			'show_in_rest' => true,
			'map_meta_cap' => true,
		];

		register_post_type($slug, array_merge($defaults, $config['args']));
	}
});
