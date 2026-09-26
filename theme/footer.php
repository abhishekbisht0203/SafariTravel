<?php
/**
 * The site footer.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$safari_phone = safari_phone();
$safari_tel   = safari_tel_url();
$safari_wa    = safari_whatsapp_url();
$safari_email = safari_contact_email();
$safari_note  = (string) safari_option('footer_note', '');
?>
	</main><!-- #main -->

<footer class="site-footer" role="contentinfo">

	<div class="container site-footer__main">
		<div class="site-footer__grid">

			<div>
				<a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
					<svg class="site-brand__mark" viewBox="0 0 40 40" aria-hidden="true" focusable="false">
						<circle cx="20" cy="20" r="19" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.35"/>
						<circle cx="20" cy="14" r="5" fill="currentColor" opacity="0.5"/>
						<path d="M2 30 L13 18 L20 25 L27 15 L38 30 Z" fill="currentColor"/>
					</svg>
					<span class="site-brand__text">
						<span><?php bloginfo('name'); ?></span>
						<span class="site-brand__tagline"><?php bloginfo('description'); ?></span>
					</span>
				</a>

				<?php if ('' !== $safari_note) : ?>
					<p class="site-footer__blurb"><?php echo wp_kses_post(wpautop($safari_note)); ?></p>
				<?php else : ?>
					<p class="site-footer__blurb">
						<?php esc_html_e('Tailor-made safaris across East and Southern Africa, designed by specialists and run by people who know the ground.', 'safari-travel'); ?>
					</p>
				<?php endif; ?>

				<?php
				$safari_socials = safari_social_links();
				if ($safari_socials) :
					?>
					<div class="social-links">
						<?php foreach ($safari_socials as $safari_network => $safari_url) : ?>
							<a href="<?php echo esc_url($safari_url); ?>" rel="noopener noreferrer" target="_blank">
								<?php safari_icon($safari_network); ?>
								<span class="visually-hidden">
									<?php
									/* translators: %s: social network name */
                                    echo esc_html(sprintf(__('Follow us on %s', 'safari-travel'), ucfirst($safari_network)));
									?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div>
				<h2 class="footer-col__title"><?php esc_html_e('Explore', 'safari-travel'); ?></h2>
				<?php
				if (has_nav_menu('footer')) {
					wp_nav_menu([
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-links',
						'depth'          => 1,
						'fallback_cb'    => false,
					]);
				} else {
					echo '<ul class="footer-links">';
					foreach (safari_primary_nav_items() as $safari_item) {
						printf(
							'<li><a href="%s">%s</a></li>',
							esc_url($safari_item['url']),
							esc_html($safari_item['label'])
						);
					}
					echo '</ul>';
				}
				?>
			</div>

			<div>
				<h2 class="footer-col__title"><?php esc_html_e('Plan Your Trip', 'safari-travel'); ?></h2>
				<ul class="footer-links">
					<li><a href="<?php echo esc_url(safari_plan_url()); ?>"><?php esc_html_e('Plan My Safari', 'safari-travel'); ?></a></li>
					<li><a href="<?php echo esc_url(safari_page_url('contact')); ?>"><?php esc_html_e('Contact Us', 'safari-travel'); ?></a></li>
					<li><a href="<?php echo esc_url(safari_page_url('faq')); ?>"><?php esc_html_e('Frequently Asked Questions', 'safari-travel'); ?></a></li>
					<?php if (function_exists('wc_get_page_permalink')) : ?>
						<li><a href="<?php echo esc_url((string) wc_get_page_permalink('myaccount')); ?>"><?php esc_html_e('My Account', 'safari-travel'); ?></a></li>
					<?php endif; ?>
				</ul>

				<h2 class="footer-col__title" style="margin-block-start:var(--space-lg)">
					<?php esc_html_e('Popular Destinations', 'safari-travel'); ?>
				</h2>
				<?php
				$safari_popular = get_posts([
					'post_type'      => 'destination',
					'posts_per_page' => 4,
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
				]);

				if ($safari_popular) :
					?>
					<ul class="footer-links">
						<?php foreach ($safari_popular as $safari_dest) : ?>
							<li>
								<a href="<?php echo esc_url((string) get_permalink($safari_dest)); ?>">
									<?php echo esc_html(get_the_title($safari_dest)); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div>
				<h2 class="footer-col__title"><?php esc_html_e('Get In Touch', 'safari-travel'); ?></h2>
				<address class="footer-contact">
					<?php if ('' !== $safari_tel) : ?>
						<div class="footer-contact__row">
							<?php safari_icon('phone'); ?>
							<a href="<?php echo esc_url($safari_tel); ?>"><?php echo esc_html($safari_phone); ?></a>
						</div>
					<?php endif; ?>

					<?php if ('' !== $safari_wa) : ?>
						<div class="footer-contact__row">
							<?php safari_icon('whatsapp'); ?>
							<a href="<?php echo esc_url($safari_wa); ?>" rel="noopener noreferrer" target="_blank">
								<?php esc_html_e('Chat on WhatsApp', 'safari-travel'); ?>
							</a>
						</div>
					<?php endif; ?>

					<div class="footer-contact__row">
						<?php safari_icon('mail'); ?>
						<a href="mailto:<?php echo esc_attr($safari_email); ?>"><?php echo esc_html($safari_email); ?></a>
					</div>

					<div class="footer-contact__row">
						<?php safari_icon('clock'); ?>
						<span>
							<?php esc_html_e('Mon–Sat, 8am – 6pm EAT', 'safari-travel'); ?><br>
							<?php esc_html_e('Sun & public holidays: on-call', 'safari-travel'); ?>
						</span>
					</div>
				</address>

				<div style="margin-block-start:var(--space-lg)">
					<?php safari_button(safari_plan_url(), safari_plan_label(), 'accent', ['size' => 'sm', 'magnetic' => true]); ?>
				</div>
			</div>
		</div>
	</div>

	<div class="container">
		<?php safari_trust_bar(true); ?>
	</div>

	<div class="site-footer__bottom container">
		<p style="margin:0">
			<?php
			printf(
                /* translators: 1: year, 2: site name */
                esc_html__('© %1$s %2$s. All rights reserved.', 'safari-travel'),
                esc_html((string) gmdate('Y')),
                esc_html(get_bloginfo('name'))
            );
			?>
		</p>

		<?php
		if (has_nav_menu('legal')) {
			wp_nav_menu([
				'theme_location' => 'legal',
				'container'      => false,
				'menu_class'     => 'footer-legal',
				'depth'          => 1,
				'fallback_cb'    => false,
			]);
		} else {
			?>
			<ul class="footer-legal">
				<li><a href="<?php echo esc_url(safari_page_url('privacy-policy')); ?>"><?php esc_html_e('Privacy Policy', 'safari-travel'); ?></a></li>
				<li><a href="<?php echo esc_url(safari_page_url('terms')); ?>"><?php esc_html_e('Terms & Conditions', 'safari-travel'); ?></a></li>
				<li><a href="<?php echo esc_url(safari_page_url('cookie-policy')); ?>"><?php esc_html_e('Cookie Policy', 'safari-travel'); ?></a></li>
			</ul>
			<?php
		}
		?>
	</div>
</footer>

<?php
// ── Sticky mobile CTA (plan §9.2) ───────────────────────────────────────────
if (! is_singular() || ! in_array(get_post_type(), ['tour', 'destination'], true)) :
	?>
	<div class="st-mobile-cta" data-hide-after="700">
		<?php safari_button(safari_plan_url(), safari_plan_label(), 'primary', ['class' => 'st-mobile-cta__btn--primary']); ?>
		<?php if ('' !== $safari_tel) : ?>
			<a class="st-mobile-cta__btn st-mobile-cta__btn--outline" href="<?php echo esc_url($safari_tel); ?>">
				<?php safari_icon('phone'); ?>
				<span class="visually-hidden"><?php esc_html_e('Call us', 'safari-travel'); ?></span>
			</a>
		<?php endif; ?>
		<?php if ('' !== $safari_wa) : ?>
			<a class="st-mobile-cta__btn st-mobile-cta__btn--outline" href="<?php echo esc_url($safari_wa); ?>" rel="noopener noreferrer" target="_blank">
				<?php safari_icon('whatsapp'); ?>
				<span class="visually-hidden"><?php esc_html_e('Chat on WhatsApp', 'safari-travel'); ?></span>
			</a>
		<?php endif; ?>
	</div>
	<?php
endif;

get_template_part('template-parts/global/lightbox');
?>

<button type="button" class="to-top" aria-label="<?php esc_attr_e('Back to top', 'safari-travel'); ?>">
	<?php safari_icon('arrow-up'); ?>
</button>

<?php wp_footer(); ?>
</body>
</html>
