<?php
/**
 * Card: Destination.
 *
 * Used on the homepage showcase, the destinations archive and the related
 * rails on tour pages.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int|WP_Post $post      Destination post.
 * @var string     $variant   'grid' (default) or 'row' (compact list item).
 * @var bool       $eager     Load the image eagerly.
 */

$safari_id       = is_object($post) ? (int) $post->ID : (int) $post;
$safari_country  = safari_destination_country($safari_id);
$safari_terms    = get_the_terms($safari_id, 'safari_type');
$safari_type     = (is_array($safari_terms) && $safari_terms) ? $safari_terms[0]->name : '';
$safari_months   = safari_best_months($safari_id);
$safari_tour_cnt = (int) (new WP_Query([
    'post_type'      => 'tour',
    'posts_per_page' => 1,
    'fields'         => 'ids',
    'meta_query'     => [[
        'key'   => 'destination',
        'value' => $safari_id,
    ]],
]))->found_posts;

$safari_variant = $args['variant'] ?? 'grid';
$safari_eager   = ! empty($args['eager']);
?>

<?php if ('row' === $safari_variant) : ?>

	<?php
    $safari_preview = has_post_thumbnail($safari_id)
        ? (string) wp_get_attachment_image_url((int) get_post_thumbnail_id($safari_id), 'safari-portrait')
        : '';
    ?>

	<article
		class="destination-row"
		data-reveal="left"
		<?php if ('' !== $safari_preview) : ?>
			data-showcase-row
			data-image="<?php echo esc_url($safari_preview); ?>"
			data-image-alt="<?php echo esc_attr((string) get_the_title($safari_id)); ?>"
		<?php endif; ?>
	>
		<span class="destination-row__index" aria-hidden="true"><?php echo esc_html((string) ($args['index'] ?? '')); ?></span>
		<span>
			<span class="destination-row__name"><?php echo esc_html(get_the_title($safari_id)); ?></span>
			<span class="destination-row__meta">
				<?php echo esc_html(implode(' · ', array_filter([$safari_country, $safari_type]))); ?>
			</span>
		</span>
		<?php if ($safari_tour_cnt > 0) : ?>
			<span class="destination-row__count">
				<?php
                printf(
                    /* translators: %d: number of tours */
                    esc_html(_n('%d tour', '%d tours', $safari_tour_cnt, 'safari-travel')),
                    esc_html((string) $safari_tour_cnt)
                );
				?>
			</span>
		<?php endif; ?>
		<a class="destination-row__link" href="<?php echo esc_url((string) get_permalink($safari_id)); ?>">
			<span class="visually-hidden">
				<?php
                /* translators: %s: destination name */
                echo esc_html(sprintf(__('Explore %s', 'safari-travel'), get_the_title($safari_id)));
				?>
			</span>
			<?php safari_icon('arrow-right', ['class' => 'destination-row__arrow']); ?>
		</a>
	</article>

<?php else : ?>

	<article <?php post_class('card'); ?> data-reveal="up">
		<div class="card__media">
			<?php safari_thumbnail($safari_id, 'safari-card', [], $safari_eager); ?>

			<?php if ($safari_badges = array_filter([$safari_country])) : ?>
				<div class="card__badges">
					<?php foreach ($safari_badges as $safari_badge) : ?>
						<span class="badge badge--brand"><?php echo esc_html($safari_badge); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="card__body">
			<?php if ('' !== $safari_type) : ?>
				<p class="card__eyebrow"><?php echo esc_html($safari_type); ?></p>
			<?php endif; ?>

			<h3 class="card__title">
				<a href="<?php echo esc_url((string) get_permalink($safari_id)); ?>">
					<?php echo esc_html(get_the_title($safari_id)); ?>
				</a>
			</h3>

			<p class="card__text clamp-3"><?php echo esc_html(safari_excerpt(22, $safari_id)); ?></p>

			<?php if ($safari_months) : ?>
				<div class="card__meta">
					<span class="card__meta-item">
						<?php safari_icon('sun'); ?>
						<?php
                        printf(
                            /* translators: 1: first month, 2: last month */
                            esc_html(__('Best: %1$s – %2$s', 'safari-travel')),
                            esc_html(safari_month_name($safari_months[0])),
                            esc_html(safari_month_name((string) end($safari_months)))
                        );
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<div class="card__foot">
			<span class="link-arrow"><?php esc_html_e('Explore', 'safari-travel'); ?></span>
			<?php if ($safari_tour_cnt > 0) : ?>
				<span class="card__meta-item">
					<?php
                    printf(
                        /* translators: %d: number of tours */
                        esc_html(_n('%d tour', '%d tours', $safari_tour_cnt, 'safari-travel')),
                        esc_html((string) $safari_tour_cnt)
                    );
					?>
				</span>
			<?php endif; ?>
		</div>
	</article>

<?php endif; ?>
