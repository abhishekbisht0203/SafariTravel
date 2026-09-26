<?php
/**
 * Card: Tour — the "signature" overlay variant.
 *
 * A full-bleed photo with the title, duration and price laid over it. Used on
 * the homepage rail, the tours archive and related rails.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int|WP_Post $post    Tour post.
 * @var bool       $eager   Load the image eagerly.
 * @var bool       $compact Omit the description block.
 */

$safari_id = safari_card_post_id($args);

if ($safari_id <= 0) {
    return;
}
$safari_days      = (int) safari_field('duration_days', $safari_id, 0);
$safari_price     = safari_field('price_from', $safari_id, 0);
$safari_currency  = (string) (safari_field('currency', $safari_id, 'USD') ?: 'USD');
$safari_dest_id   = safari_tour_destination($safari_id);
$safari_dest_name = $safari_dest_id ? get_the_title($safari_dest_id) : '';
$safari_difficulty = safari_difficulty_label($safari_id);
$safari_price_fmt  = safari_price($safari_price, $safari_currency);
$safari_eager     = ! empty($args['eager']);
$safari_compact   = ! empty($args['compact']);
?>

<article <?php post_class('tour-card'); ?> data-reveal="up">
	<div class="tour-card__media" data-parallax="0.06">
		<?php safari_thumbnail($safari_id, 'safari-card-lg', [], $safari_eager); ?>
	</div>

	<?php if ($safari_difficulty) : ?>
		<div class="tour-card__badges">
			<span class="badge badge--gold"><?php echo esc_html($safari_difficulty); ?></span>
		</div>
	<?php endif; ?>

	<div class="tour-card__body">
		<?php if ('' !== $safari_dest_name) : ?>
			<p class="tour-card__eyebrow"><?php echo esc_html($safari_dest_name); ?></p>
		<?php endif; ?>

		<h3 class="tour-card__title">
			<a href="<?php echo esc_url((string) get_permalink($safari_id)); ?>">
				<?php echo esc_html(get_the_title($safari_id)); ?>
			</a>
		</h3>

		<div class="tour-card__meta">
			<?php if ($safari_days > 0) : ?>
				<span class="tour-card__meta-item">
					<?php safari_icon('calendar'); ?>
					<?php echo esc_html(safari_duration_label($safari_id)); ?>
				</span>
			<?php endif; ?>

			<?php $safari_group = (int) safari_field('group_size', $safari_id, 0); ?>
			<?php if ($safari_group > 0) : ?>
				<span class="tour-card__meta-item">
					<?php safari_icon('users'); ?>
					<?php
                    printf(
                        /* translators: %d: maximum group size */
                        esc_html(__('Max %d travellers', 'safari-travel')),
                        esc_html((string) $safari_group)
                    );
					?>
				</span>
			<?php endif; ?>
		</div>

		<?php if (! $safari_compact && safari_excerpt(18, $safari_id)) : ?>
			<p class="tour-card__text" style="font-size:var(--text-sm);color:rgb(253 251 247 / 0.78)">
				<?php echo esc_html(safari_excerpt(18, $safari_id)); ?>
			</p>
		<?php endif; ?>

		<div class="tour-card__foot">
			<?php if ('' !== $safari_price_fmt) : ?>
				<span>
					<span class="tour-card__price-from"><?php esc_html_e('From', 'safari-travel'); ?></span>
					<span class="tour-card__price"><?php echo esc_html($safari_price_fmt); ?></span>
				</span>
			<?php else : ?>
				<span class="tour-card__price-from"><?php esc_html_e('Tailor-made', 'safari-travel'); ?></span>
			<?php endif; ?>

			<?php safari_icon('arrow-right', ['class' => 'tour-card__arrow']); ?>
		</div>
	</div>
</article>
