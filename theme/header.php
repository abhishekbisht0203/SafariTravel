<?php
/**
 * The site header.
 *
 * Rendered on every request above the page content: <head>, the announcement
 * bar, the sticky header, the mobile drawer, the search overlay and the
 * back-to-top control.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$safari_phone   = safari_phone();
$safari_tel     = safari_tel_url();
$safari_wa      = safari_whatsapp_url();
$safari_announce = (bool) safari_option('announcement_enabled', false);
$safari_announce_text = (string) safari_option('announcement_text', '');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main"><?php esc_html_e('Skip to content', 'safari-travel'); ?></a>

<?php safari_schema_organization(); ?>

<?php if ($safari_announce && '' !== $safari_announce_text) : ?>
	<div class="announcement" data-message="<?php echo esc_attr(wp_strip_all_tags($safari_announce_text)); ?>">
		<div class="container announcement__inner">
			<p class="announcement__text">
				<?php echo wp_kses_post($safari_announce_text); ?>
			</p>
			<button type="button" class="announcement__close" aria-label="<?php esc_attr_e('Dismiss announcement', 'safari-travel'); ?>">
				<?php safari_icon('close'); ?>
			</button>
		</div>
	</div>
<?php endif; ?>

<header class="site-header" role="banner">
	<div class="container site-header__inner">

		<a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
			<?php if (has_custom_logo()) : ?>
				<?php
			 $safari_logo_id = (int) get_theme_mod('custom_logo');
			 echo wp_get_attachment_image(
					$safari_logo_id,
					'full',
					false,
					[
						'class'    => 'site-brand__logo',
						'alt'      => esc_attr(get_bloginfo('name')),
						'loading'  => 'eager',
						'decoding' => 'async',
					]
				);
				?>
			<?php else : ?>
				<svg class="site-brand__mark" viewBox="0 0 40 40" aria-hidden="true" focusable="false">
					<circle cx="20" cy="20" r="19" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.35"/>
					<circle cx="20" cy="14" r="5" fill="currentColor" opacity="0.5"/>
					<path d="M2 30 L13 18 L20 25 L27 15 L38 30 Z" fill="currentColor"/>
				</svg>
				<span class="site-brand__text">
					<span><?php bloginfo('name'); ?></span>
					<span class="site-brand__tagline"><?php bloginfo('description'); ?></span>
				</span>
			<?php endif; ?>
		</a>

		<nav class="site-nav" aria-label="<?php esc_attr_e('Primary', 'safari-travel'); ?>">
			<?php
			if (has_nav_menu('primary')) {
				wp_nav_menu([
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__list',
					'depth'          => 2,
					'fallback_cb'    => false,
				]);
			} else {
				safari_fallback_menu('header');
			}
			?>
		</nav>

		<div class="site-header__actions">
			<?php if ('' !== $safari_tel) : ?>
				<a class="site-header__phone" href="<?php echo esc_url($safari_tel); ?>">
					<?php safari_icon('phone', ['class' => 'icon-phone']); ?>
					<span><?php echo esc_html($safari_phone); ?></span>
				</a>
			<?php endif; ?>

			<button
				type="button"
				class="icon-btn site-header__search"
				data-search-open
				aria-expanded="false"
				aria-controls="safari-search-overlay"
			>
				<?php safari_icon('search'); ?>
				<span class="visually-hidden"><?php esc_html_e('Search', 'safari-travel'); ?></span>
			</button>

			<button
				type="button"
				class="icon-btn nav-toggle"
				data-drawer-open="nav"
				aria-expanded="false"
				aria-controls="safari-nav-drawer"
			>
				<span class="nav-toggle__box" aria-hidden="true">
					<span class="nav-toggle__bar"></span>
					<span class="nav-toggle__bar"></span>
					<span class="nav-toggle__bar"></span>
				</span>
				<span class="visually-hidden"><?php esc_html_e('Open menu', 'safari-travel'); ?></span>
			</button>

			<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary', ['size' => 'sm', 'class' => 'site-header__cta']); ?>
		</div>
	</div>
</header>

<?php
// ── Mobile navigation drawer ────────────────────────────────────────────────
?>
<div class="st-drawer" id="safari-nav-drawer" data-drawer="nav" aria-hidden="true">
	<div class="st-drawer__scrim" data-drawer-close></div>
	<div class="st-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Site menu', 'safari-travel'); ?>">
		<div class="st-drawer__head">
			<p class="st-drawer__title"><?php esc_html_e('Menu', 'safari-travel'); ?></p>
			<button type="button" class="icon-btn st-drawer__close" data-drawer-close>
				<?php safari_icon('close'); ?>
				<span class="visually-hidden"><?php esc_html_e('Close menu', 'safari-travel'); ?></span>
			</button>
		</div>

		<div class="st-drawer__body">
			<?php
			if (has_nav_menu('primary')) {
				wp_nav_menu([
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'drawer-nav',
					'depth'          => 2,
					'fallback_cb'    => false,
				]);
			} else {
				safari_fallback_menu('drawer');
			}
			?>

			<div class="btn-group" style="margin-block-start:var(--space-lg)">
				<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary', ['class' => 'btn--block']); ?>
				<?php if ('' !== $safari_tel) : ?>
					<?php safari_button($safari_tel, $safari_phone, 'outline', ['class' => 'btn--block']); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php
// ── Search overlay ──────────────────────────────────────────────────────────
get_template_part('template-parts/global/search-overlay');
?>

<div id="main" class="site-main">
