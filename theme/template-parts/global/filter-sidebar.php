<?php
/**
 * Facet filters for an archive.
 *
 * Rendered once as a sidebar (desktop) and once inside a bottom sheet (mobile).
 * The two instances share the same field names, so a selection made in one
 * carries to the other after the reload.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var string $context Archive context key: destinations|tours|events|guides.
 */

$safari_context = $args['context'] ?? 'destinations';
$safari_post_type = 'guides' === $safari_context ? 'safari_guide' : $safari_context;

/**
 * Facet definitions per context.
 *
 * @return array<string,array{label:string,taxonomy:string,limit:int}> Facet list.
 */
$safari_facets = [
    'destinations' => [
        ['label' => __('Region', 'safari-travel'), 'taxonomy' => 'region', 'limit' => 20],
        ['label' => __('Safari type', 'safari-travel'), 'taxonomy' => 'safari_type', 'limit' => 20],
        ['label' => __('Best month to visit', 'safari-travel'), 'taxonomy' => 'season', 'limit' => 12],
    ],
    'tours'        => [
        ['label' => __('Destination', 'safari-travel'), 'taxonomy' => 'region', 'limit' => 20],
        ['label' => __('Safari type', 'safari-travel'), 'taxonomy' => 'safari_type', 'limit' => 20],
        ['label' => __('Travel style', 'safari-travel'), 'taxonomy' => 'travel_style', 'limit' => 10],
        ['label' => __('Season', 'safari-travel'), 'taxonomy' => 'season', 'limit' => 12],
    ],
    'events'       => [
        ['label' => __('Type', 'safari-travel'), 'taxonomy' => 'event_type', 'limit' => 10],
        ['label' => __('Region', 'safari-travel'), 'taxonomy' => 'region', 'limit' => 20],
    ],
    'guides'       => [
        ['label' => __('Topic', 'safari-travel'), 'taxonomy' => 'guide_topic', 'limit' => 20],
    ],
];

/**
 * Render the filter form body. Shared by the sidebar and the sheet.
 *
 * @param string $context   Context key.
 * @param string $post_type Post type being filtered.
 */
$safari_render_form = static function (string $context, string $post_type): void {
	$facets = $safari_facets[$context] ?? [];
	$id     = 'safari-facet-' . $post_type;

	printf('<form class="filter-form" method="get" action="%s" id="%s">', esc_url((string) get_post_type_archive_link($post_type)), esc_attr($id));

	// A hidden s/post_type marker keeps WP from dropping the archive context.
	printf('<input type="hidden" name="post_type" value="%s">', esc_attr($post_type));

	foreach ($facets as $facet) {
		$terms = get_terms([
			'taxonomy'   => $facet['taxonomy'],
			'hide_empty' => true,
			'number'     => (int) $facet['limit'],
			'orderby'    => 'count',
			'order'      => 'DESC',
		]);

		if (is_wp_error($terms) || empty($terms)) {
			continue;
		}

		$selected = isset($_GET[$facet['taxonomy']]) ? (array) $_GET[$facet['taxonomy']] : [];

		printf(
			'<div class="filter-group"><h3 class="filter-group__title" id="%s-%s">%s</h3>',
			esc_attr($id),
			esc_attr($facet['taxonomy']),
			esc_html($facet['label'])
		);

		echo '<div class="filter-group__options" role="group" aria-labelledby="' . esc_attr($id . '-' . $facet['taxonomy']) . '">';

		foreach ($terms as $term) {
			$is_on = in_array($term->slug, array_map('strval', $selected), true);

			printf(
				'<label class="filter-option">
					<input type="checkbox" name="%s" value="%s"%s>
					<span>%s</span>
					<span class="filter-option__count">%s</span>
				</label>',
				esc_attr($facet['taxonomy']),
				esc_attr($term->slug),
				checked($is_on, true, false),
				esc_html($term->name),
				esc_html(number_format_i18n((int) $term->count))
			);
		}

		echo '</div></div>';
	}

	echo '<noscript><button type="submit" class="btn btn--primary btn--block btn--sm">' . esc_html__('Apply filters', 'safari-travel') . '</button></noscript>';
	echo '</form>';
};
?>

<div class="with-sidebar__aside">
	<?php $safari_render_form($safari_context, $safari_post_type); ?>
</div>

<div class="st-drawer st-drawer--bottom" id="safari-filters-drawer" data-drawer="filters" aria-hidden="true">
	<div class="st-drawer__scrim" data-drawer-close></div>
	<div class="st-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Filter results', 'safari-travel'); ?>">
		<div class="st-drawer__head">
			<p class="st-drawer__title"><?php esc_html_e('Filter', 'safari-travel'); ?></p>
			<button type="button" class="icon-btn st-drawer__close" data-drawer-close>
				<?php safari_icon('close'); ?>
				<span class="visually-hidden"><?php esc_html_e('Close filters', 'safari-travel'); ?></span>
			</button>
		</div>

		<div class="st-drawer__body">
			<?php $safari_render_form($safari_context, $safari_post_type); ?>
		</div>

		<div class="st-drawer__foot">
			<button type="button" class="btn btn--primary btn--block" data-drawer-close data-filter-done>
				<?php safari_icon('check'); ?>
				<?php esc_html_e('Show results', 'safari-travel'); ?>
			</button>
		</div>
	</div>
</div>
