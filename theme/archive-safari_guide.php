<?php
/**
 * Archive: Travel Guides.
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
	<?php if (has_post_thumbnail()) : ?>
		<div class="page-hero__bg" data-parallax="0.1">
			<?php safari_thumbnail((int) get_queried_object_id(), 'safari-hero', [], true); ?>
		</div>
	<?php else : ?>
		<div class="page-hero__bg map-dots"></div>
	<?php endif; ?>

	<div class="container page-hero__inner">
		<?php safari_breadcrumbs(); ?>
		<p class="page-hero__eyebrow"><?php esc_html_e('Before you go', 'safari-travel'); ?></p>
		<h1 class="page-hero__title"><?php post_type_archive_title(); ?></h1>
		<p class="page-hero__lead">
			<?php esc_html_e('Visas, vaccines, packing, photography, and the things we wish someone had told us on our first trip.', 'safari-travel'); ?>
		</p>
	</div>
</section>

<?php get_template_part('template-parts/global/filter-bar', 'guides'); ?>

<div class="section section--tight">
	<div class="container">
		<?php if (have_posts()) : ?>

			<div class="with-sidebar">
				<?php get_template_part('template-parts/global/filter-sidebar', 'guides'); ?>

				<div>
					<div class="grid grid--3" data-reveal-group>
						<?php
						while (have_posts()) :
							the_post();
							get_template_part('template-parts/cards/card-guide');
						endwhile;
						?>
					</div>

					<?php safari_pagination(); ?>
				</div>
			</div>

		<?php else : ?>
			<div class="empty-state">
				<h2 class="empty-state__title"><?php esc_html_e('No guides published yet', 'safari-travel'); ?></h2>
				<p class="empty-state__text">
					<?php esc_html_e('Our specialists are writing. In the meantime, ask them anything directly.', 'safari-travel'); ?>
				</p>
				<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
					<?php safari_button(safari_page_url('contact'), __('Contact us', 'safari-travel'), 'primary'); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php get_template_part('template-parts/global/cta-banner'); ?>

<?php
get_footer();
