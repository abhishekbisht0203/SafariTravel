<?php
/**
 * Card: Guide article.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int|WP_Post $post  Guide post.
 * @var bool       $thumb Show a thumbnail.
 */

$safari_id = safari_card_post_id($args);

if ($safari_id <= 0) {
    return;
}
$safari_thumb = ! isset($args['thumb']) || ! empty($args['thumb']);
$safari_read  = safari_reading_time($safari_id);
$safari_topics = get_the_terms($safari_id, 'guide_topic');
$safari_topic = (is_array($safari_topics) && $safari_topics) ? $safari_topics[0]->name : '';
?>

<article <?php post_class('article-card'); ?> data-reveal="up">

	<?php if ($safari_thumb && has_post_thumbnail($safari_id)) : ?>
		<div class="article-card__thumb">
			<?php safari_thumbnail($safari_id, 'safari-card'); ?>
		</div>
	<?php endif; ?>

	<?php if ('' !== $safari_topic) : ?>
		<p class="card__eyebrow"><?php echo esc_html($safari_topic); ?></p>
	<?php endif; ?>

	<h3 class="article-card__title">
		<a href="<?php echo esc_url((string) get_permalink($safari_id)); ?>">
			<?php echo esc_html(get_the_title($safari_id)); ?>
		</a>
	</h3>

	<p class="article-card__text clamp-3"><?php echo esc_html(safari_excerpt(24, $safari_id)); ?></p>

	<div class="post-meta">
		<span class="post-meta__item">
			<?php safari_icon('clock'); ?>
			<?php
            printf(
                /* translators: %d: number of minutes */
                esc_html(_n('%d min read', '%d min read', $safari_read, 'safari-travel')),
                esc_html((string) $safari_read)
            );
			?>
		</span>
		<span class="post-meta__item">
			<?php safari_icon('calendar'); ?>
			<time datetime="<?php echo esc_attr(get_the_date('c', $safari_id)); ?>">
				<?php echo esc_html(get_the_date('', $safari_id)); ?>
			</time>
		</span>
		<span class="article-card__more"><?php esc_html_e('Read', 'safari-travel'); ?></span>
	</div>
</article>
