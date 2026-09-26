<?php
/**
 * Best-time-to-visit month chart.
 *
 * Twelve cells, one per month, shaded by how good that month is. The chart is
 * static HTML + CSS — no charting library, no canvas, and every month is
 * exposed to assistive tech through its title attribute.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var string[] $best    Month keys marked as best.
 * @var string   $heading Optional heading.
 */

$safari_best   = $best ?? [];
$safari_levels = safari_month_levels($safari_best);
$safari_primary = $safari_best ? reset($safari_best) : '';

// A readable summary for screen readers and for the caption.
$safari_summary = $safari_best
	? sprintf(
        /* translators: 1: first month, 2: last month */
        __('Peak season runs from %1$s to %2$s.', 'safari-travel'),
        safari_month_name((string) reset($safari_best)),
        safari_month_name((string) end($safari_best))
    )
	: __('Seasonality varies — ask us for the best window for your trip.', 'safari-travel');
?>

<div class="month-chart" data-reveal="up">
	<?php if (! empty($args['heading'])) : ?>
		<h3 class="month-chart__heading" style="margin:0 0 var(--space-2xs);font-size:var(--text-lg)">
			<?php echo esc_html((string) $args['heading']); ?>
		</h3>
	<?php endif; ?>

	<p class="text-sm text-muted" style="margin:0 0 var(--space-md)"><?php echo esc_html($safari_summary); ?></p>

	<div class="month-chart__grid" role="img" aria-label="<?php echo esc_attr($safari_summary); ?>">
		<?php foreach (safari_all_months() as $safari_month) : ?>
			<?php $safari_level = $safari_levels[$safari_month]; ?>
			<span
				class="month-chart__cell"
				data-level="<?php echo esc_attr((string) $safari_level); ?>"
				title="<?php
                    printf(
                        /* translators: 1: month name, 2: suitability */
                        esc_attr(__('%1$s — %2$s', 'safari-travel')),
                        esc_attr(safari_month_name($safari_month)),
                        esc_attr(
                            3 === $safari_level
                                ? __('prime time', 'safari-travel')
                                : (1 === $safari_level
                                    ? __('shoulder season', 'safari-travel')
                                    : __('less suitable', 'safari-travel'))
                        )
                    );
				?>"
			></span>
		<?php endforeach; ?>
	</div>

	<div class="month-chart__labels" aria-hidden="true">
		<?php foreach (safari_all_months() as $safari_month) : ?>
			<span class="month-chart__label"><?php echo esc_html(safari_month_name($safari_month)); ?></span>
		<?php endforeach; ?>
	</div>

	<div class="month-chart__legend">
		<span class="month-chart__legend-item">
			<span class="month-chart__swatch" style="background:var(--st-forest-600)"></span>
			<?php esc_html_e('Prime time', 'safari-travel'); ?>
		</span>
		<span class="month-chart__legend-item">
			<span class="month-chart__swatch" style="background:var(--st-forest-200)"></span>
			<?php esc_html_e('Shoulder season', 'safari-travel'); ?>
		</span>
		<span class="month-chart__legend-item">
			<span class="month-chart__swatch" style="background:var(--st-paper-200)"></span>
			<?php esc_html_e('Less suitable', 'safari-travel'); ?>
		</span>
		<?php if (! empty($safari_primary)) : ?>
			<span class="month-chart__legend-item" style="margin-inline-start:auto">
				<?php safari_icon('sun'); ?>
				<span><?php echo esc_html(safari_month_name($safari_primary)); ?></span>
			</span>
		<?php endif; ?>
	</div>
</div>
