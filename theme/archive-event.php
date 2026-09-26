<?php
/**
 * Archive: Events & Offers.
 *
 * Split into upcoming and past so visitors are not shown expired promotions
 * first (plan §4.3).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

/**
 * Fetch the current page's events, partitioned by whether they have ended.
 *
 * @param string $when 'upcoming' or 'past'.
 * @param int    $page Current page number (1-based).
 * @return WP_Post[]
 */
$safari_partition = static function (string $when, int $page): array {
	$today = current_time('Y-m-d');

	$meta_query = 'past' === $when
		? [[
			'key'     => 'end_date',
			'value'   => $today,
			'compare' => '<',
			'type'    => 'DATE',
		]]
		: [[
			'key'     => 'end_date',
			'value'   => $today,
			'compare' => '>=',
			'type'    => 'DATE',
		]];

	// Events with no end date are always upcoming.
	if ('upcoming' === $when) {
		$meta_query = [
			'relation' => 'OR',
			$meta_query[0],
			[
				'key'     => 'end_date',
				'compare' => 'NOT EXISTS',
			],
		];
	}

	$query = new WP_Query([
		'post_type'      => 'event',
		'posts_per_page' => 6,
		'paged'          => $page,
		'meta_key'       => 'start_date',
		'orderby'        => 'meta_value',
		'order'          => 'past' === $when ? 'DESC' : 'ASC',
		'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	]);

	return $query->posts;
};

$safari_page = max(1, (int) get_query_var('paged'));
$safari_upcoming = $safari_partition('upcoming', $safari_page);
$safari_past     = $safari_partition('past', $safari_page);
?>

<section class="page-hero page-hero--compact">
	<div class="page-hero__bg map-dots" data-parallax="0.08"></div>

	<div class="container page-hero__inner">
		<?php safari_breadcrumbs(); ?>
		<p class="page-hero__eyebrow"><?php esc_html_e('Limited availability', 'safari-travel'); ?></p>
		<h1 class="page-hero__title"><?php post_type_archive_title(); ?></h1>
		<p class="page-hero__lead">
			<?php esc_html_e('Fixed departures with confirmed availability, seasonal offers, and the occasional last-minute space. All prices are per person sharing.', 'safari-travel'); ?>
		</p>
	</div>
</section>

<div class="section section--tight">
	<div class="container">

		<?php if ($safari_upcoming) : ?>
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow' => __('Open now', 'safari-travel'),
                'title'   => __('Upcoming departures & offers', 'safari-travel'),
            ]);
			?>

			<div class="grid grid--2" data-reveal-group>
				<?php foreach ($safari_upcoming as $safari_event) : ?>
					<?php
                    set_query_var('post', $safari_event);
                    get_template_part('template-parts/cards/card-event');
                    ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($safari_past) : ?>
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow' => __('Archive', 'safari-travel'),
                'title'   => __('Recently concluded', 'safari-travel'),
            ]);
			?>

			<div class="grid grid--2">
				<?php foreach ($safari_past as $safari_event) : ?>
					<?php
                    set_query_var('post', $safari_event);
                    get_template_part('template-parts/cards/card-event');
                    ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if (! $safari_upcoming && ! $safari_past) : ?>
			<div class="empty-state">
				<h2 class="empty-state__title"><?php esc_html_e('No offers are running right now', 'safari-travel'); ?></h2>
				<p class="empty-state__text">
					<?php esc_html_e('Seasonal offers are published a few months ahead. Join the conversation and we will tell you when the next one opens.', 'safari-travel'); ?>
				</p>
				<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
					<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary'); ?>
				</div>
			</div>
		<?php endif; ?>

		<?php safari_pagination(); ?>
	</div>
</div>

<?php get_template_part('template-parts/global/cta-banner'); ?>

<?php
get_footer();
