<?php
/**
 * Block pattern registration for the Safari Travel theme.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

add_action(
	'init',
	static function (): void {
		if (! function_exists('register_block_pattern_category')) {
			return;
		}

		register_block_pattern_category(
			'safari-travel',
			array(
				'label' => __('Safari Travel', 'safari-travel'),
			)
		);

		$patterns = array(
			'hero'            => array(
				'title'  => __('Hero: Safari call to action', 'safari-travel'),
				'content' => '<!-- wp:cover {"overlayColor":"primary-dark","minHeight":560,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:560px"><span aria-hidden="true" class="wp-block-cover__background has-primary-dark-background-color has-background-dim-60 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"level":1,"textAlign":"center","textColor":"white"} -->
<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color">' . esc_html__('Discover Africa, one safari at a time', 'safari-travel') . '</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"white"} -->
<p class="has-text-align-center has-white-color has-text-color">' . esc_html__('Tailor-made journeys, small-group tours and unforgettable wildlife encounters.', 'safari-travel') . '</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="#lead-form">' . esc_html__('Plan my safari', 'safari-travel') . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->',
			),
			'cta-banner'      => array(
				'title'  => __('CTA banner', 'safari-travel'),
				'content' => '<!-- wp:group {"align":"full","backgroundColor":"primary","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-primary-background-color has-background"><!-- wp:paragraph {"align":"center","textColor":"white"} -->
<p class="has-text-align-center has-white-color has-text-color">' . esc_html__('Ready when you are — talk to a safari specialist today.', 'safari-travel') . '</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"accent","textColor":"ink"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-ink-color has-accent-background-color has-text-color has-background wp-element-button" href="/contact/">' . esc_html__('Get in touch', 'safari-travel') . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
			),
		);

		foreach ($patterns as $name => $pattern) {
			register_block_pattern(
				'safari-travel/' . $name,
				array(
					'title'      => $pattern['title'],
					'categories' => array('safari-travel'),
					'content'    => $pattern['content'],
				)
			);
		}
	}
);
