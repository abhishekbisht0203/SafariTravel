<?php
/**
 * The front page.
 *
 * Section order is deliberate: hook → proof → product → process → reassurance
 * → content → conversion. Every section queries real content and degrades to
 * nothing rather than rendering an empty shell, so the page works on day one
 * and improves as the client adds material.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$safari_hero_id   = (int) get_option('safari_hero_post_id', 0);
$safari_dest_ids  = get_posts([
    'post_type'      => 'destination',
    'posts_per_page' => 6,
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
    'fields'         => 'ids',
]);
$safari_tour_ids  = get_posts([
    'post_type'      => 'tour',
    'posts_per_page' => 8,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
]);
$safari_event_ids = get_posts([
    'post_type'      => 'event',
    'posts_per_page' => 3,
    'orderby'        => 'meta_value',
    'meta_key'       => 'start_date',
    'order'          => 'ASC',
    'fields'         => 'ids',
]);
$safari_guide_ids = get_posts([
    'post_type'      => 'safari_guide',
    'posts_per_page' => 3,
    'fields'         => 'ids',
]);
$safari_quote_ids = get_posts([
    'post_type'      => 'testimonial',
    'posts_per_page' => 6,
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
    'fields'         => 'ids',
]);
?>

<?php /* ── Scroll progress rail ─────────────────────────────────────────── */ ?>
<div class="scroll-progress" aria-hidden="true">
	<div class="scroll-progress__bar" data-scroll-progress="document"></div>
</div>

<?php /* ══ 1. HERO ════════════════════════════════════════════════════════ */ ?>
<section class="hero" data-glow>
	<?php if ($safari_hero_id && has_post_thumbnail($safari_hero_id)) : ?>
		<div class="hero__media" data-parallax="0.12">
			<?php safari_thumbnail($safari_hero_id, 'safari-hero', ['fetchpriority' => 'high'], true); ?>
		</div>
	<?php else : ?>
		<div class="hero__media map-dots" data-parallax="0.1"></div>
	<?php endif; ?>

	<div class="hero__veil" aria-hidden="true"></div>
	<div class="hero__glow" aria-hidden="true"></div>

	<div class="container hero__inner">
		<p class="hero__eyebrow"><?php esc_html_e('East & Southern Africa · Since 2009', 'safari-travel'); ?></p>

		<h1 class="hero__title" data-split="lines">
			<?php
            if ($safari_hero_id) {
                echo esc_html(get_the_title($safari_hero_id));
            } else {
                esc_html_e('Where the wild still', 'safari-travel');
            }
            ?>
		</h1>

		<p class="hero__lead" data-reveal="up" data-reveal-delay="200">
			<?php
            echo esc_html(
                (string) get_option('safari_hero_subtitle', '')
                ?: __('Tailor-made safaris built by specialists who have walked the ground. Small groups, private reserves, and itineraries shaped around what you actually want to see.', 'safari-travel')
            );
			?>
		</p>

		<div class="hero__actions" data-reveal="up" data-reveal-delay="320">
			<?php safari_button(safari_plan_url(), safari_plan_label(), 'accent', ['size' => 'lg', 'magnetic' => true]); ?>
			<?php
            safari_button(
                (string) get_post_type_archive_link('tour'),
                __('Browse all safaris', 'safari-travel'),
                'inverse-outline',
                ['size' => 'lg']
            );
			?>
		</div>

		<ul class="hero__trust" data-reveal="up" data-reveal-delay="440">
			<li class="hero__trust-item">
				<?php safari_icon('shield'); ?>
				<?php esc_html_e('Licensed & bonded', 'safari-travel'); ?>
			</li>
			<li class="hero__trust-item">
				<?php safari_icon('users'); ?>
				<?php esc_html_e('Max 6 travellers per vehicle', 'safari-travel'); ?>
			</li>
			<li class="hero__trust-item">
				<?php safari_icon('headset'); ?>
				<?php esc_html_e('24/7 in-destination support', 'safari-travel'); ?>
			</li>
		</ul>
	</div>

	<div class="hero__scroll" aria-hidden="true">
		<span><?php esc_html_e('Scroll', 'safari-travel'); ?></span>
		<span class="hero__scroll-line"></span>
	</div>
</section>

<?php /* ══ 2. PROOF — animated statistics ═════════════════════════════════ */ ?>
<section class="section section--tight section--brand section--grain" aria-label="<?php esc_attr_e('Why travellers choose us', 'safari-travel'); ?>">
	<div class="dust" aria-hidden="true"></div>
	<div class="container">
		<div class="stats stats--4 stats--ruled" data-reveal-group>
			<div class="stat" data-reveal="up">
				<span class="stat__number" data-target="16" data-suffix="+">0</span>
				<span class="stat__label"><?php esc_html_e('Years on the ground', 'safari-travel'); ?></span>
			</div>
			<div class="stat" data-reveal="up">
				<span class="stat__number" data-target="24000" data-suffix="+">0</span>
				<span class="stat__label"><?php esc_html_e('Travellers guided', 'safari-travel'); ?></span>
			</div>
			<div class="stat" data-reveal="up">
				<span class="stat__number" data-target="4.9" data-decimals="1">0</span>
				<span class="stat__label"><?php esc_html_e('Average rating', 'safari-travel'); ?></span>
			</div>
			<div class="stat" data-reveal="up">
				<span class="stat__number" data-target="98" data-suffix="%">0</span>
				<span class="stat__label"><?php esc_html_e('Would travel with us again', 'safari-travel'); ?></span>
			</div>
		</div>
	</div>
</section>

<?php /* ══ 3. DESTINATIONS — showcase with a preview stage ═════════════════ */ ?>
<?php if ($safari_dest_ids) : ?>
	<section class="section showcase" data-showcase aria-labelledby="destinations-heading">
		<div class="showcase__map" aria-hidden="true"></div>

		<div class="container">
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow'  => __('Where we go', 'safari-travel'),
                'title'    => __('Nine countries. One obsession.', 'safari-travel'),
                'lead'     => __('From the Mara river crossings to gorilla trekking in the Virunga mist, we only run itineraries our own specialists have walked.', 'safari-travel'),
                'link_url' => (string) get_post_type_archive_link('destination'),
                'link_text'=> __('All destinations', 'safari-travel'),
            ]);
			?>

			<div class="showcase__grid">
				<div>
					<?php if ($safari_dest_ids) : ?>
						<div class="frame frame--tall frame--accent" data-showcase-stage data-reveal="zoom" style="max-width:26rem;margin-inline:auto">
							<?php safari_thumbnail((int) $safari_dest_ids[0], 'safari-portrait', [], true); ?>
						</div>
					<?php endif; ?>
				</div>

				<div>
					<ul class="destination-list" data-reveal-group>
						<?php foreach ($safari_dest_ids as $safari_i => $safari_dest_id) : ?>
							<li>
								<?php
                                get_template_part(
                                    'template-parts/cards/card-destination',
                                    null,
                                    [
                                        'post_id' => $safari_dest_id,
						'variant' => 'row',
                                        'index'   => str_pad((string) ($safari_i + 1), 2, '0', STR_PAD_LEFT),
                                    ]
                                );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php /* ══ 4. TOURS — the product ════════════════════════════════════════ */ ?>
<?php if ($safari_tour_ids) : ?>
	<section class="section section--subtle" aria-labelledby="tours-heading">
		<div class="container">
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow'   => __('Signature itineraries', 'safari-travel'),
                'title'     => __('Journeys our specialists rate highest', 'safari-travel'),
                'lead'      => __('Every itinerary below has been walked end-to-end by someone on our team. Swap a camp, extend a stay, or start from scratch — it is all negotiable.', 'safari-travel'),
                'link_url'  => (string) get_post_type_archive_link('tour'),
                'link_text' => __('All safaris', 'safari-travel'),
            ]);
			?>

			<div class="tour-rail" data-rail data-reveal-group>
				<?php foreach (array_values($safari_tour_ids) as $safari_i => $safari_tour_id) : ?>
					<?php
                    get_template_part('template-parts/cards/card-tour', null, [
                        'post_id' => $safari_tour_id,
					'eager'   => (0 === $safari_i),
                        'compact' => true,
                    ]);
					?>
				<?php endforeach; ?>
			</div>

			<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-lg)">
				<?php safari_button((string) get_post_type_archive_link('tour'), __('See every itinerary', 'safari-travel'), 'primary'); ?>
				<?php safari_button(safari_plan_url(), __('Or design your own', 'safari-travel'), 'outline'); ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php /* ══ 5. WHY US — differentiators ═════════════════════════════════════ */ ?>
<section class="section" aria-labelledby="why-heading">
	<div class="container">
		<?php
        get_template_part('template-parts/global/section-heading', null, [
            'eyebrow' => __('The Safari Travel difference', 'safari-travel'),
            'title'   => __('We are the people actually going with you', 'safari-travel'),
            'align'   => 'center',
        ]);
		?>

		<div class="feature-grid" data-reveal-group>
			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('compass'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Specialists, not agents', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('Our team has guided in Kenya, Tanzania, Botswana, Uganda and Zimbabwe. When we say a camp is worth the drive, we have slept there.', 'safari-travel'); ?>
				</p>
			</div>

			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('route'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Tailor-made from day one', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('No copy-pasted packages. Your itinerary is built around your pace, your interests and the season the animals are actually active.', 'safari-travel'); ?>
				</p>
			</div>

			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('users'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Small groups only', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('Six guests per vehicle, maximum. It is the single biggest factor in how good a safari feels, and we will not put a seventh seat in.', 'safari-travel'); ?>
				</p>
			</div>

			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('shield'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Priced honestly', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('One quote, itemised, with park fees and conservancy levies shown separately. What you see is what you pay.', 'safari-travel'); ?>
				</p>
			</div>

			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('camera'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Photographer-friendly', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('We plan around golden hour, allow beanbags and trackers, and know which camps hire out serious photographic vehicles.', 'safari-travel'); ?>
				</p>
			</div>

			<div class="feature" data-reveal="up">
				<span class="feature__icon"><?php safari_icon('headset'); ?></span>
				<h3 class="feature__title"><?php esc_html_e('Support that does not stop', 'safari-travel'); ?></h3>
				<p class="feature__text">
					<?php esc_html_e('A WhatsApp line to a real person in-region, at any hour, from the moment you land until the day you fly home.', 'safari-travel'); ?>
				</p>
			</div>
		</div>
	</div>
</section>

<?php /* ══ 6. PROCESS — how planning works ════════════════════════════════ */ ?>
<section class="section section--brand section--grain" aria-labelledby="process-heading">
	<div class="dust" aria-hidden="true"></div>
	<div class="container">
		<?php
        get_template_part('template-parts/global/section-heading', null, [
            'eyebrow' => __('How it works', 'safari-travel'),
            'title'   => __('Four steps, then you are on a plane', 'safari-travel'),
            'lead'    => __('No back-and-forth over email attachments. One form, one specialist, one clear proposal.', 'safari-travel'),
            'align'   => 'center',
        ]);
		?>

		<div class="grid grid--4 process" data-reveal-group>
			<div class="process__item" data-reveal="up">
				<h3 class="process__title"><?php esc_html_e('Tell us the shape of it', 'safari-travel'); ?></h3>
				<p class="process__text"><?php esc_html_e('Dates, budget, who is travelling, and what would make the trip genuinely memorable.', 'safari-travel'); ?></p>
			</div>
			<div class="process__item" data-reveal="up">
				<h3 class="process__title"><?php esc_html_e('A specialist picks it up', 'safari-travel'); ?></h3>
				<p class="process__text"><?php esc_html_e('Usually the same day. Not a queue — a named person who has done the trip.', 'safari-travel'); ?></p>
			</div>
			<div class="process__item" data-reveal="up">
				<h3 class="process__title"><?php esc_html_e('You get a real proposal', 'safari-travel'); ?></h3>
				<p class="process__text"><?php esc_html_e('Day-by-day, itemised, with alternatives where the price or season does not work.', 'safari-travel'); ?></p>
			</div>
			<div class="process__item" data-reveal="up">
				<h3 class="process__title"><?php esc_html_e('We handle the rest', 'safari-travel'); ?></h3>
				<p class="process__text"><?php esc_html_e('Flights, transfers, park fees, visas. One contact, one invoice, no surprises.', 'safari-travel'); ?></p>
			</div>
		</div>

		<div style="display:flex;justify-content:center;margin-block-start:var(--space-2xl)">
			<?php safari_button(safari_plan_url(), safari_plan_label(), 'accent', ['size' => 'lg', 'magnetic' => true]); ?>
		</div>
	</div>
</section>

<?php /* ══ 7. OFFERS / EVENTS ═════════════════════════════════════════════ */ ?>
<?php if ($safari_event_ids) : ?>
	<section class="section" aria-labelledby="offers-heading">
		<div class="container">
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow'   => __('Limited availability', 'safari-travel'),
                'title'     => __('Offers & fixed departures', 'safari-travel'),
                'link_url'  => (string) get_post_type_archive_link('event'),
                'link_text' => __('See all offers', 'safari-travel'),
            ]);
			?>

			<div class="grid" data-reveal-group>
				<?php foreach ($safari_event_ids as $safari_event_id) : ?>
					<?php
                    get_template_part('template-parts/cards/card-event', null, ['post_id' => $safari_event_id]);
                    ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php /* ══ 8. TESTIMONIALS ═══════════════════════════════════════════════ */ ?>
<?php if ($safari_quote_ids) : ?>
	<section class="section section--brand section--grain" aria-labelledby="quotes-heading">
		<div class="container">
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow' => __('Traveller stories', 'safari-travel'),
                'title'   => __('The word on the street', 'safari-travel'),
                'align'   => 'center',
            ]);
			?>

			<div class="grid grid--3" data-reveal-group>
				<?php foreach ($safari_quote_ids as $safari_quote_id) : ?>
					<?php
                    get_template_part('template-parts/cards/card-testimonial', null, [
					'post_id' => $safari_quote_id,
					'dark'   => true,
				]);
                    ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php /* ══ 9. GUIDES ══════════════════════════════════════════════════════ */ ?>
<?php if ($safari_guide_ids) : ?>
	<section class="section" aria-labelledby="guides-heading">
		<div class="container">
			<?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow'   => __('Before you go', 'safari-travel'),
                'title'     => __('Guides, packing lists & honest advice', 'safari-travel'),
                'link_url'  => (string) get_post_type_archive_link('safari_guide'),
                'link_text' => __('All guides', 'safari-travel'),
            ]);
			?>

			<div class="grid grid--3" data-reveal-group>
				<?php foreach ($safari_guide_ids as $safari_guide_id) : ?>
					<?php
                    get_template_part('template-parts/cards/card-guide', null, ['post_id' => $safari_guide_id]);
                    ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php /* ══ 10. CONVERSION ════════════════════════════════════════════════ */ ?>
<?php get_template_part('template-parts/global/cta-banner'); ?>

<?php
get_footer();
