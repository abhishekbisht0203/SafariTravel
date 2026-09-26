<?php
/**
 * Itinerary timeline for a tour.
 *
 * Reads the ACF repeater `itinerary` and renders it as an accessible ordered
 * list. Falls back to the tour's own content when no repeater is filled in, so
 * a tour is never presented with an empty timeline.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int  $post_id Tour post ID.
 * @var bool $compact Render a tighter variant.
 */

$safari_id  = (int) ($args['post_id'] ?? get_the_ID());
$safari_days = (int) safari_field('duration_days', $safari_id, 0);
$safari_itin = safari_field('itinerary', $safari_id, []);

if (! is_array($safari_itin) || empty($safari_itin)) {
    return;
}
?>

<ol class="timeline">
	<?php foreach (array_values($safari_itin) as $safari_index => $safari_day) : ?>
		<?php
		$safari_title = (string) ($safari_day['day_title'] ?? '');
		$safari_text  = (string) ($safari_day['day_description'] ?? '');
		$safari_bullets = $safari_day['highlights'] ?? [];
		$safari_number = $safari_index + 1;
		?>

		<li class="timeline__item" data-reveal="up" data-reveal-delay="<?php echo esc_attr((string) min($safari_index * 60, 240)); ?>">
			<span class="timeline__badge" aria-hidden="true"><?php echo esc_html((string) $safari_number); ?></span>

			<div class="timeline__day-col">
				<span class="timeline__day">
					<?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: day number */
                            __('Day %d', 'safari-travel'),
                            $safari_number
                        )
                    );
					?>
				</span>
			</div>

			<div>
				<?php if ('' !== $safari_title) : ?>
					<h3 class="timeline__title"><?php echo esc_html($safari_title); ?></h3>
				<?php endif; ?>

				<?php if ('' !== $safari_text) : ?>
					<div class="timeline__text"><?php echo wp_kses_post(wpautop($safari_text)); ?></div>
				<?php endif; ?>

				<?php if (is_array($safari_bullets) && $safari_bullets) : ?>
					<ul class="timeline__list">
						<?php foreach ($safari_bullets as $safari_bullet) : ?>
							<?php
                            $safari_label = is_array($safari_bullet)
                                ? (string) ($safari_bullet['item'] ?? reset($safari_bullet))
                                : (string) $safari_bullet;

                            if ('' === trim($safari_label)) {
                                continue;
                            }
							?>
							<li><?php echo esc_html(trim($safari_label)); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
</ol>
