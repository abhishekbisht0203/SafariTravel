<?php
/**
 * Sticky filter bar for archives.
 *
 * Shows the live result count, a mobile "Filter" trigger that opens the bottom
 * sheet, and the sort control.
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
$safari_count   = (int) ($GLOBALS['wp_query']->found_posts ?? 0);

$safari_sorts = [
    'destinations' => [
        'menu_order' => __('Featured first', 'safari-travel'),
        'title'      => __('Name (A–Z)', 'safari-travel'),
    ],
    'tours'        => [
        'date'         => __('Newest first', 'safari-travel'),
        'price_from'   => __('Price: low to high', 'safari-travel'),
        'duration_days'=> __('Duration: shortest', 'safari-travel'),
    ],
    'events'       => [
        'meta_value_num' => __('Soonest first', 'safari-travel'),
    ],
    'guides'       => [
        'date' => __('Newest first', 'safari-travel'),
    ],
];

$safari_active = [];
foreach ($_GET as $safari_key => $safari_value) {
    if (in_array($safari_key, ['region', 'safari_type', 'travel_style', 'season', 'guide_topic', 'sort'], true) && '' !== $safari_value) {
        $safari_active[$safari_key] = (array) $safari_value;
    }
}
?>

<div class="filter-bar">
	<div class="container filter-bar__inner">
		<p class="filter-bar__count" aria-live="polite">
			<?php
            printf(
                /* translators: %s: number of results */
                esc_html(_n('<strong>%s</strong> destination', '<strong>%s</strong> destinations', $safari_count, 'safari-travel')),
                esc_html(number_format_i18n($safari_count))
            );
			?>
		</p>

		<div class="filter-bar__actions">
			<?php if (! empty($safari_active)) : ?>
				<a class="btn btn--quiet btn--sm" href="<?php echo esc_url((string) get_post_type_archive_link($safari_context === 'guides' ? 'safari_guide' : $safari_context)); ?>">
					<?php esc_html_e('Clear all', 'safari-travel'); ?>
				</a>
			<?php endif; ?>

			<?php if (! empty($safari_sorts[$safari_context])) : ?>
				<form method="get" class="filter-bar__sort" style="display:contents">
					<?php
                    // Preserve existing filters when sorting.
                    foreach ($safari_active as $safari_k => $safari_v) {
                        foreach ($safari_v as $safari_single) {
                            printf(
                                '<input type="hidden" name="%s" value="%s">',
                                esc_attr((string) $safari_k),
                                esc_attr((string) $safari_single)
                            );
                        }
                    }
                    ?>
					<label class="visually-hidden" for="safari-sort-<?php echo esc_attr($safari_context); ?>">
						<?php esc_html_e('Sort results', 'safari-travel'); ?>
					</label>
					<select
						id="safari-sort-<?php echo esc_attr($safari_context); ?>"
						name="sort"
						class="sort-select"
						onchange="this.form.submit()"
					>
						<?php foreach ($safari_sorts[$safari_context] as $safari_val => $safari_label) : ?>
							<option value="<?php echo esc_attr((string) $safari_val); ?>" <?php selected(($_GET['sort'] ?? '') === $safari_val); ?>>
								<?php echo esc_html($safari_label); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			<?php endif; ?>

			<button
				type="button"
				class="btn btn--outline btn--sm filter-open"
				data-drawer-open="filters"
				aria-expanded="false"
				aria-controls="safari-filters-drawer"
			>
				<?php safari_icon('filter'); ?>
				<?php esc_html_e('Filter', 'safari-travel'); ?>
				<?php if (! empty($safari_active)) : ?>
					<span class="badge badge--brand"><?php echo esc_html((string) count($safari_active)); ?></span>
				<?php endif; ?>
			</button>
		</div>
	</div>
</div>
