<?php
/**
 * Archive: Destinations.
 *
 * Filter bar + filter drawer + card grid. Filters render as a real form that
 * works without JavaScript and is upgraded to auto-submit by js/ui.js.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$safari_count = (int) ($GLOBALS['wp_query']->found_posts ?? 0);
?>

<section class="page-hero page-hero--compact">
	<?php if (has_post_thumbnail()) : ?>
		<div class="page-hero__bg" data-parallax="0.1">
			<?php safari_thumbnail((int) get_queried_object_id(), 'safari-hero', [], true); ?>
		</div>
	<?php else : ?>
		<div class="page-hero__bg map-dots"></div>
	<?php endif; ?>

	<div class="container page-hero__inner">
		<?php safari_breadcrumbs(); ?>
		<p class="page-hero__eyebrow"><?php esc_html_e('Destinations', 'safari-travel'); ?></p>
		<h1 class="page-hero__title"><?php post_type_archive_title(); ?></h1>
		<p class="page-hero__lead">
			<?php esc_html_e('Nine countries, from the great migration to the gorillas of the Virunga. Every destination below is one our specialists guide personally.', 'safari-travel'); ?>
		</p>
	</div>
</section>

<?php get_template_part('template-parts/global/filter-bar', 'destinations'); ?>

<div class="section section--tight">
	<div class="container">
		<?php if (have_posts()) : ?>

			<div class="with-sidebar">
				<?php
                get_template_part('template-parts/global/filter-sidebar', 'destinations');
                ?>

				<div>
					<div class="grid grid--2" data-reveal-group>
						<?php
						while (have_posts()) :
							the_post();
							get_template_part('template-parts/cards/card-destination');
						endwhile;
						?>
					</div>

					<?php safari_pagination(); ?>
				</div>
			</div>

		<?php else : ?>
			<div class="empty-state">
				<h2 class="empty-state__title"><?php esc_html_e('No destinations match those filters', 'safari-travel'); ?></h2>
				<p class="empty-state__text">
					<?php esc_html_e('Try widening your search, or tell us where you would like to go and we will build the trip around it.', 'safari-travel'); ?>
				</p>
				<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
					<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary'); ?>
					<?php safari_button((string) get_post_type_archive_link('destination'), __('Clear filters', 'safari-travel'), 'outline'); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_template_part('template-parts/global/cta-banner'); ?>

<?php
get_footer();
