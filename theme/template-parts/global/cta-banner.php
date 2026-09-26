<?php
/**
 * Conversion band: heading, reassurance, and the lead CTA.
 *
 * Used at the foot of the homepage, archives and tour pages (plan §5.3).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var string $title    Heading.
 * @var string $text     Supporting copy.
 * @var string $label    Primary button label.
 * @var string $variant  'brand' (dark) or 'subtle' (light).
 * @var array  $context  Lead form context: destination_id, tour_id.
 */

$safari_variant = ($args['variant'] ?? 'brand') === 'brand' ? 'section--brand map-dots' : 'section--subtle';
$safari_dust    = ($args['variant'] ?? 'brand') === 'brand';
$safari_context = $args['context'] ?? [];
$safari_dust_html = '';

if ($safari_dust) {
    ob_start();
    echo '<div class="dust" aria-hidden="true"></div>';
    $safari_dust_html = (string) ob_get_clean();
}
?>

<section class="section <?php echo esc_attr($safari_variant); ?> section--grain" aria-labelledby="cta-heading">
	<?php echo $safari_dust_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="container">
		<div class="split" style="align-items:center">

			<div class="split__body">
				<p class="section-head__eyebrow" data-reveal="up">
					<?php esc_html_e('Start your journey', 'safari-travel'); ?>
				</p>
				<h2 class="section-head__title" id="cta-heading" data-reveal="up" data-reveal-delay="60" style="max-width:18ch">
					<?php echo esc_html((string) ($args['title'] ?? __('Tell us what you dream of. We will build it.', 'safari-travel'))); ?>
				</h2>
				<p class="lead measure" data-reveal="up" data-reveal-delay="120" style="max-width:48ch">
					<?php
                    echo esc_html(
                        (string) (
                            $args['text']
                            ?? __('Share your dates, budget and must-see wildlife. A safari specialist replies within one business day with a tailored, no-obligation proposal.', 'safari-travel')
                        )
                    );
					?>
				</p>

				<ul class="tick-list" data-reveal="up" data-reveal-delay="180" style="margin-block-start:var(--space-lg)">
					<li class="tick-list__item">
						<?php safari_icon('check-circle', ['class' => 'tick-list__icon']); ?>
						<span><?php esc_html_e('Free, no-obligation itinerary proposal', 'safari-travel'); ?></span>
					</li>
					<li class="tick-list__item">
						<?php safari_icon('check-circle', ['class' => 'tick-list__icon']); ?>
						<span><?php esc_html_e('Written by specialists, not a call centre', 'safari-travel'); ?></span>
					</li>
					<li class="tick-list__item">
						<?php safari_icon('check-circle', ['class' => 'tick-list__icon']); ?>
						<span><?php esc_html_e('We reply within one business day', 'safari-travel'); ?></span>
					</li>
				</ul>
			</div>

			<div class="split__media" data-reveal="zoom" data-reveal-delay="120">
				<?php
				/*
				 * Render the multi-step lead form directly rather than through
				 * the shortcode, so the preset context is explicit and no
				 * shortcode re-registration is needed.
				 */
				if (class_exists('Safari_Lead_Form')) {
					Safari_Lead_Form::render(
						'plan_my_safari',
						[
							'destination_id' => (int) ($safari_context['destination_id'] ?? 0),
							'tour_id'        => (int) ($safari_context['tour_id'] ?? 0),
							'class'          => 'safari-lead-form--onbrand',
						]
					);
				} else {
					safari_button(safari_plan_url(), safari_plan_label(), 'inverse', [
						'size'     => 'lg',
						'magnetic' => true,
					]);
				}
				?>
			</div>
		</div>
	</div>
</section>
