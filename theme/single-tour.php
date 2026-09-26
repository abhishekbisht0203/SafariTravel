<?php
/**
 * Single: Tour.
 *
 * Structure: hero → quick facts → itinerary → inclusions/exclusions →
 * departures → destination cross-link → related tours → lead form.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $safari_id        = get_the_ID();
    $safari_dest_id   = safari_tour_destination($safari_id);
    $safari_dest_name = $safari_dest_id ? get_the_title($safari_dest_id) : '';
    $safari_days      = (int) safari_field('duration_days', $safari_id, 0);
    $safari_price     = (string) safari_field('price_from', $safari_id, '');
    $safari_currency  = (string) (safari_field('currency', $safari_id, 'USD') ?: 'USD');
    $safari_price_fmt = safari_price($safari_price, $safari_currency);
    $safari_group     = (int) safari_field('group_size', $safari_id, 0);
    $safari_diff      = safari_difficulty_label($safari_id);
    $safari_incl      = safari_field('inclusions', $safari_id, []);
    $safari_excl      = safari_field('exclusions', $safari_id, []);
    $safari_deps      = safari_field('departures', $safari_id, []);
    $safari_types     = get_the_terms($safari_id, 'safari_type');
    $safari_styles    = get_the_terms($safari_id, 'travel_style');
    ?>

    <article <?php post_class(); ?>>

        <?php /* ── Hero ─────────────────────────────────────────────────── */ ?>
        <header class="page-hero">
            <?php if (has_post_thumbnail()) : ?>
                <div class="page-hero__bg" data-parallax="0.14">
                    <?php safari_thumbnail($safari_id, 'safari-hero', [], true); ?>
                </div>
            <?php else : ?>
                <div class="page-hero__bg map-dots"></div>
            <?php endif; ?>

            <div class="container page-hero__inner">
                <?php safari_breadcrumbs(); ?>

                <?php if ($safari_types) : ?>
                    <p class="page-hero__eyebrow">
                        <?php echo esc_html(implode(' · ', wp_list_pluck($safari_types, 'name'))); ?>
                    </p>
                <?php endif; ?>

                <h1 class="page-hero__title"><?php the_title(); ?></h1>

                <?php if (has_excerpt()) : ?>
                    <p class="page-hero__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>

                <ul class="page-hero__meta">
                    <?php if ($safari_days > 0) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('calendar'); ?>
                            <?php echo esc_html(safari_duration_label($safari_id)); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ('' !== $safari_dest_name) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('pin'); ?>
                            <?php echo esc_html($safari_dest_name); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($safari_group > 0) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('users'); ?>
                            <?php
                            printf(
                                /* translators: %d: maximum group size */
                                esc_html(__('Max %d travellers', 'safari-travel')),
                                esc_html((string) $safari_group)
                            );
                            ?>
                        </li>
                    <?php endif; ?>

                    <?php if ('' !== $safari_diff) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('mountain'); ?>
                            <?php echo esc_html($safari_diff); ?>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php safari_schema_tour($safari_id); ?>
            </div>
        </header>

        <?php /* ── Body ──────────────────────────────────────────────────── */ ?>
        <div class="section section--tight">
            <div class="container">
                <div class="with-sidebar" style="align-items:start">

                    <div>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="post-thumbnail" data-reveal="scale">
                                <?php the_post_thumbnail('safari-hero', ['loading' => 'lazy']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (trim((string) get_the_content())) : ?>
                            <div class="prose" data-reveal="up">
                                <?php the_content(); ?>
                            </div>
                        <?php endif; ?>

                        <?php /* ── Itinerary ─────────────────────────────────── */ ?>
                        <?php
                        $safari_itin = safari_field('itinerary', $safari_id, []);
                        if (is_array($safari_itin) && $safari_itin) :
                            ?>
                            <section class="related" style="border-block-start-width:1px" aria-labelledby="itinerary-heading">
                               	<h2 class="related__title" id="itinerary-heading" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
									<?php esc_html_e('Day-by-day itinerary', 'safari-travel'); ?>
								</h2>
								<?php get_template_part('template-parts/global/itinerary', null, ['post_id' => $safari_id]); ?>
							</section>
						<?php endif; ?>

                        <?php /* ── Inclusions ────────────────────────────────── */ ?>
                        <?php if ((is_array($safari_incl) && $safari_incl) || (is_array($safari_excl) && $safari_excl)) : ?>
                            <section class="related" aria-labelledby="includes-heading">
                                <h2 class="related__title" id="includes-heading" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
                                    <?php esc_html_e('What is included', 'safari-travel'); ?>
                                </h2>

                                <div class="grid grid--2">
                                    <?php if (is_array($safari_incl) && $safari_incl) : ?>
                                        <div>
                                            <h3 style="font-size:var(--text-lg);display:flex;align-items:center;gap:var(--space-2xs)">
                                                <?php safari_icon('check-circle'); ?>
                                                <?php esc_html_e('Included', 'safari-travel'); ?>
                                            </h3>
                                            <ul class="feature-list feature-list--include">
                                                <?php foreach ($safari_incl as $safari_item) : ?>
                                                    <?php
                                                    $safari_text = is_array($safari_item)
                                                        ? (string) ($safari_item['item'] ?? reset($safari_item))
                                                        : (string) $safari_item;

                                                    if ('' === trim($safari_text)) {
                                                        continue;
                                                    }
                                                    ?>
                                                    <li class="feature-list__item">
                                                        <span class="feature-list__icon"><?php safari_icon('check'); ?></span>
                                                        <span><?php echo esc_html(trim($safari_text)); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (is_array($safari_excl) && $safari_excl) : ?>
                                        <div>
                                            <h3 style="font-size:var(--text-lg);display:flex;align-items:center;gap:var(--space-2xs)">
                                                <?php safari_icon('alert'); ?>
                                                <?php esc_html_e('Not included', 'safari-travel'); ?>
                                            </h3>
                                            <ul class="feature-list feature-list--exclude">
                                                <?php foreach ($safari_excl as $safari_item) : ?>
                                                    <?php
                                                    $safari_text = is_array($safari_item)
                                                        ? (string) ($safari_item['item'] ?? reset($safari_item))
                                                        : (string) $safari_item;

                                                    if ('' === trim($safari_text)) {
                                                        continue;
                                                    }
                                                    ?>
                                                    <li class="feature-list__item">
                                                        <span class="feature-list__icon"><?php safari_icon('close'); ?></span>
                                                        <span><?php echo esc_html(trim($safari_text)); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php /* ── Departures ─────────────────────────────────── */ ?>
                        <?php if (is_array($safari_deps) && $safari_deps) : ?>
                            <section class="related" aria-labelledby="departures-heading">
                                <h2 class="related__title" id="departures-heading" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
                                    <?php esc_html_e('Confirmed departures', 'safari-travel'); ?>
                                </h2>

                                <ul class="spec-list">
                                    <?php foreach ($safari_deps as $safari_dep) : ?>
                                        <?php
                                        $safari_date = is_array($safari_dep)
                                            ? (string) ($safari_dep['departure_date'] ?? '')
                                            : (string) $safari_dep;

                                        if ('' === $safari_date) {
                                            continue;
                                        }

                                        $safari_ts    = strtotime($safari_date);
                                        $safari_past  = $safari_ts && $safari_ts < strtotime('today');
                                        ?>
                                        <li class="spec-list__row">
                                            <span class="spec-list__key">
                                                <?php echo esc_html(date_i18n('l j F Y', (int) $safari_ts)); ?>
                                            </span>
                                            <span class="spec-list__value">
                                                <?php if ($safari_past) : ?>
                                                    <span class="badge"><?php esc_html_e('Departed', 'safari-travel'); ?></span>
                                                <?php else : ?>
                                                    <span class="badge badge--success">
                                                        <span class="badge__dot"></span>
                                                        <?php esc_html_e('Spaces available', 'safari-travel'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <?php /* ── Destination cross-link ───────────────────── */ ?>
                        <?php
                        if ($safari_dest_id) :
                            $safari_related = new WP_Query([
                                'post_type'      => 'tour',
                                'posts_per_page' => 3,
                                'post__not_in'   => [$safari_id],
                                'orderby'        => 'rand',
                                'fields'         => 'ids',
                                'meta_query'     => [[
                                    'key'   => 'destination',
                                    'value' => $safari_dest_id,
                                ]],
                            ]);

                            if ($safari_related->posts) :
                                ?>
                                <section class="related" aria-labelledby="related-heading">
                                   	<h2 class="related__title" id="related-heading" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
										<?php
                                        printf(
                                            /* translators: %s: destination name */
                                            esc_html__('More in %s', 'safari-travel'),
                                            esc_html($safari_dest_name)
                                        );
										?>
									</h2>

									<div class="grid grid--3" data-reveal-group>
										<?php foreach ($safari_related->posts as $safari_rel_id) : ?>
											<?php
                                            get_template_part('template-parts/cards/card-tour', null, [
					'post_id'  => $safari_rel_id,
					'compact' => true,
				]);
											?>
										<?php endforeach; ?>
									</div>
								</section>
                                <?php
                            endif;
                        endif;
                        ?>
                    </div>

                    <?php /* ── Sticky booking card ──────────────────────── */ ?>
                    <aside class="with-sidebar__aside">
                        <div class="booking-card" data-reveal="up">
                            <h2 class="booking-card__title"><?php esc_html_e('Request this itinerary', 'safari-travel'); ?></h2>

                            <?php if ('' !== $safari_price_fmt) : ?>
                                <p class="booking-card__price">
                                    <span class="booking-card__amount"><?php echo esc_html($safari_price_fmt); ?></span>
                                </p>
                                <p class="booking-card__note">
                                    <?php
                                    printf(
                                        /* translators: %d: number of days */
                                        esc_html(__('Per person sharing, based on %d nights. Park fees billed separately.', 'safari-travel')),
                                        esc_html((string) max(0, $safari_days))
                                    );
                                    ?>
                                </p>
                            <?php else : ?>
                                <p class="booking-card__note"><?php esc_html_e('Priced on request.', 'safari-travel'); ?></p>
                            <?php endif; ?>

                            <div class="booking-card__actions">
                                <a class="btn btn--primary btn--block" href="#tour-inquiry">
                                    <?php safari_icon('mail'); ?>
                                    <?php esc_html_e('Send inquiry', 'safari-travel'); ?>
                                </a>

                                <?php $safari_wa = safari_whatsapp_url(); ?>
                                <?php if ('' !== $safari_wa) : ?>
                                    <a class="btn btn--outline btn--block" href="<?php echo esc_url($safari_wa); ?>" rel="noopener noreferrer" target="_blank">
                                        <?php safari_icon('whatsapp'); ?>
                                        <?php esc_html_e('Ask on WhatsApp', 'safari-travel'); ?>
                                    </a>
                                <?php endif; ?>

                                <?php $safari_tel = safari_tel_url(); ?>
                                <?php if ('' !== $safari_tel) : ?>
                                    <a class="btn btn--quiet btn--block" href="<?php echo esc_url($safari_tel); ?>">
                                        <?php safari_icon('phone'); ?>
                                        <?php echo esc_html(safari_phone()); ?>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="booking-card__assurance">
                                <span class="post-meta__item">
                                    <?php safari_icon('shield'); ?>
                                    <?php esc_html_e('Your details are never sold or shared', 'safari-travel'); ?>
                                </span>
                                <span class="post-meta__item">
                                    <?php safari_icon('clock'); ?>
                                    <?php esc_html_e('Reply within one business day', 'safari-travel'); ?>
                                </span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <?php /* ── Lead form ────────────────────────────────────────────── */ ?>
        <section class="section section--subtle" id="tour-inquiry" aria-labelledby="inquiry-heading">
            <div class="container container--narrow">
                <?php
                get_template_part('template-parts/global/section-heading', null, [
                    'eyebrow' => __('Next step', 'safari-travel'),
                    'title'   => __('Ask about this itinerary', 'safari-travel'),
                    'lead'    => __('Pre-filled with the tour above. Tell us your dates and we will check availability and pricing for you.', 'safari-travel'),
                    'align'   => 'center',
                ]);
                ?>

                <?php
                if (class_exists('Safari_Lead_Form')) {
                    Safari_Lead_Form::render('tour_page', [
                        'destination_id' => $safari_dest_id,
                        'tour_id'        => $safari_id,
                    ]);
                } else {
                    safari_button(safari_plan_url(), safari_plan_label(), 'primary', ['size' => 'lg']);
                }
                ?>
            </div>
        </section>

    </article>

    <?php
endwhile;

get_footer();
