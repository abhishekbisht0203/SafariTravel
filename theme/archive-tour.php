<?php
/**
 * Archive: Tours.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section class="page-hero page-hero--compact">
	<div class="page-hero__bg map-dots" data-parallax="0.08"></div>

	<div class="container page-hero__inner">
		<?php safari_breadcrumbs(); ?>
		<p class="page-hero__eyebrow"><?php esc_html_e('Signature itineraries', 'safari-travel'); ?></p>
		<h1 class="page-hero__title"><?php post_type_archive_title(); ?></h1>
		<p class="page-hero__lead">
			<?php esc_html_e('Every itinerary here has been walked end-to-end by someone on our team. Extend it, shorten it, or start from scratch — nothing is fixed.', 'safari-travel'); ?>
		</p>
	</div>
</section>

<?php get_template_part('template-parts/global/filter-bar', 'tours'); ?>

<div class="section section--tight">
	<div class="container">
		<?php if (have_posts()) : ?>

			<div class="with-sidebar">
				<?php get_template_part('template-parts/global/filter-sidebar', 'tours'); ?>

				<div>
					<?php $safari_i = 0; ?>
					<div class="grid grid--2" data-reveal-group>
						<?php
						while (have_posts()) :
							the_post();
							get_template_part('template-parts/cards/card-tour', null, [
                                'eager'   => (0 === $safari_i++),
                                'compact' => true,
                            ]);
						endwhile;
						?>
					</div>

					<?php safari_pagination(); ?>
				</div>
			</div>

		<?php else : ?>
			<div class="empty-state">
				<h2 class="empty-state__title"><?php esc_html_e('No itineraries match those filters', 'safari-travel'); ?></h2>
				<p class="empty-state__text">
					<?php esc_html_e('We build most safaris from scratch anyway. Tell us your dates and budget and we will send something written for you.', 'safari-travel'); ?>
				</p>
				<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
					<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary'); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_template_part('template-parts/global/cta-banner'); ?>

<?php
get_footer();
