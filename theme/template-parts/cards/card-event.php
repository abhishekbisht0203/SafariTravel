<?php
/**
 * Card: Event / Offer.
 *
 * Shows a date block, the offer title, and an optional promo code. Offers
 * whose end date has passed render in a muted "past" state rather than
 * disappearing, so the archive never looks broken (plan §4.3).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int|WP_Post $post  Event post.
 * @var bool       $dark  Render for a dark surface.
 */

$safari_id   = is_object($post) ? (int) $post->ID : (int) $post;
$safari_start = (string) safari_field('start_date', $safari_id, '');
$safari_end   = (string) safari_field('end_date', $safari_id, '');
$safari_code  = (string) safari_field('promo_code', $safari_id, '');
$safari_types = get_the_terms($safari_id, 'event_type');
$safari_type  = (is_array($safari_types) && $safari_types) ? $safari_types[0]->name : '';

$safari_is_past = '' !== $safari_end && strtotime($safari_end) < strtotime('today');
$safari_dark    = ! empty($args['dark']);
$safari_ts      = '' !== $safari_start ? strtotime($safari_start) : strtotime('today');
?>

<article <?php post_class('offer' . ($safari_dark ? ' offer--dark' : '') . ' offer--accent' . ($safari_is_past ? ' offer--past' : '')); ?> data-reveal="up">

	<?php if ($safari_ts) : ?>
		<div class="offer__date" aria-hidden="true">
			<span class="offer__date-day"><?php echo esc_html(date_i18n('d', (int) $safari_ts)); ?></span>
			<span class="offer__date-month"><?php echo esc_html(date_i18n('M', (int) $safari_ts)); ?></span>
		</div>
	<?php endif; ?>

	<div class="offer__body">
		<?php if ('' !== $safari_type) : ?>
			<p class="card__eyebrow"><?php echo esc_html($safari_type); ?></p>
		<?php endif; ?>

		<h3 class="offer__title">
			<a href="<?php echo esc_url((string) get_permalink($safari_id)); ?>"><?php echo esc_html(get_the_title($safari_id)); ?></a>
		</h3>

		<p class="offer__text"><?php echo esc_html(safari_excerpt(20, $safari_id)); ?></p>

		<?php if ($safari_start) : ?>
			<p class="card__meta" style="margin-block-start:var(--space-2xs)">
				<span class="card__meta-item">
					<?php safari_icon('calendar'); ?>
					<?php
                    if ('' !== $safari_end && $safari_end !== $safari_start) {
                        printf(
                            /* translators: 1: start date, 2: end date */
                            esc_html(__('%1$s – %2$s', 'safari-travel')),
                            esc_html(date_i18n(get_option('date_format'), (int) strtotime($safari_start))),
                            esc_html(date_i18n(get_option('date_format'), (int) strtotime($safari_end)))
                        );
                    } else {
                        echo esc_html(date_i18n(get_option('date_format'), (int) $safari_ts));
                    }
					?>
				</span>

				<?php if ('' !== $safari_code) : ?>
					<span class="card__meta-item">
						<?php safari_icon('sparkle'); ?>
						<?php
                        printf(
                            /* translators: %s: promo code */
                            esc_html(__('Code: %s', 'safari-travel')),
                            '<strong>' . esc_html($safari_code) . '</strong>'
                        );
						?>
					</span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="offer__actions">
		<?php
		safari_button(
			(string) get_permalink($safari_id),
			$safari_is_past ? __('View offer', 'safari-travel') : __('Enquire', 'safari-travel'),
			$safari_dark ? 'inverse-outline' : 'outline',
			['size' => 'sm']
		);
		?>
	</div>
</article>
