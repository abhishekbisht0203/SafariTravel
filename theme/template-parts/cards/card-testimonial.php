<?php
/**
 * Testimonial card.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int|WP_Post $post Testimonial post.
 * @var bool       $dark Render for a dark surface.
 */

$safari_id = safari_card_post_id($args);

if ($safari_id <= 0) {
    return;
}
$safari_name   = (string) (safari_field('person_name', $safari_id, '') ?: get_the_title($safari_id));
$safari_role   = (string) safari_field('role', $safari_id, '');
$safari_rating = (int) safari_field('rating', $safari_id, 5);
$safari_dark   = ! empty($args['dark']);
$safari_quote  = safari_excerpt(45, $safari_id);
?>

<figure <?php post_class('quote' . ($safari_dark ? ' quote--dark' : '')); ?> data-reveal="up">
	<?php safari_icon('quote', ['class' => 'quote__mark']); ?>

	<blockquote class="quote__text">
		<?php
        if (has_excerpt($safari_id)) {
            echo esc_html(get_the_excerpt($safari_id));
        } else {
            echo esc_html($safari_quote);
        }
		?>
	</blockquote>

	<?php if ($safari_rating > 0) : ?>
		<div style="margin-block-end:var(--space-2xs)">
			<?php safari_rating($safari_rating); ?>
		</div>
	<?php endif; ?>

	<figcaption class="quote__author">
		<?php if (has_post_thumbnail($safari_id)) : ?>
			<?php
			echo get_the_post_thumbnail(
                $safari_id,
                'thumbnail',
                [
                    'class'   => 'quote__avatar',
                    'alt'     => esc_attr($safari_name),
                    'loading' => 'lazy',
                ]
            );
			?>
		<?php else : ?>
			<span class="quote__avatar" aria-hidden="true"></span>
		<?php endif; ?>

		<span>
			<span class="quote__name"><?php echo esc_html($safari_name); ?></span>
			<?php if ('' !== $safari_role) : ?>
				<span class="quote__meta"><?php echo esc_html($safari_role); ?></span>
			<?php endif; ?>
		</span>
	</figcaption>
</figure>
