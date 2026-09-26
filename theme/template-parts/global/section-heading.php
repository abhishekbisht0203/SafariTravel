<?php
/**
 * Section header: eyebrow + title + lead (+ optional aside link).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var string $eyebrow Small kicker above the title.
 * @var string $title   Heading text.
 * @var string $lead    Supporting sentence.
 * @var string $align   'left' (default) or 'center'.
 * @var string $link_url  Optional "view all" link target.
 * @var string $link_text Optional "view all" link label.
 * @var string $variant   'left'|'center'|'split'.
 */

$safari_variant = $args['variant'] ?? (isset($args['link_url']) ? 'split' : 'left');
$safari_align    = ($args['align'] ?? 'left') === 'center' ? 'section-head--center' : '';
$safari_split    = 'split' === $safari_variant ? 'section-head--split' : '';
?>

<header class="section-head <?php echo esc_attr(trim($safari_align . ' ' . $safari_split)); ?>">

	<div>
		<?php if (! empty($args['eyebrow'])) : ?>
			<p class="section-head__eyebrow" data-reveal="up"><?php echo esc_html((string) $args['eyebrow']); ?></p>
		<?php endif; ?>

		<?php if (! empty($args['title'])) : ?>
			<h2 class="section-head__title" data-reveal="up" data-reveal-delay="60">
				<?php echo esc_html((string) $args['title']); ?>
			</h2>
		<?php endif; ?>

		<?php if (! empty($args['lead'])) : ?>
			<p class="section-head__lead" data-reveal="up" data-reveal-delay="120">
				<?php echo wp_kses_post((string) $args['lead']); ?>
			</p>
		<?php endif; ?>
	</div>

	<?php if (! empty($args['link_url'])) : ?>
		<div class="section-head__aside" data-reveal="up" data-reveal-delay="180">
			<a class="btn btn--outline btn--sm" href="<?php echo esc_url((string) $args['link_url']); ?>">
				<?php echo esc_html((string) ($args['link_text'] ?? __('View all', 'safari-travel'))); ?>
				<?php safari_icon('arrow-right'); ?>
			</a>
		</div>
	<?php endif; ?>
</header>
