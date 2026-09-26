<?php
/**
 * Single: Destination.
 *
 * Structure: hero → overview + best-time chart → gallery → wildlife & climate
 * → tours here → FAQ → lead form.
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

    $safari_id      = get_the_ID();
    $safari_country = safari_destination_country($safari_id);
    $safari_months  = safari_best_months($safari_id);
    $safari_types   = get_the_terms($safari_id, 'safari_type');
    $safari_region  = safari_field('region_info', $safari_id, '');
    $safari_gallery = safari_field('gallery', $safari_id, []);

    $safari_tours = new WP_Query([
        'post_type'      => 'tour',
        'posts_per_page' => 6,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [[
            'key'   => 'destination',
            'value' => $safari_id,
        ]],
    ]);

    $safari_faqs = get_posts([
        'post_type'      => 'faq',
        'posts_per_page' => 6,
        'meta_query'     => [[
            'key'   => 'related_destination',
            'value' => $safari_id,
        ]],
    ]);
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

                <?php if ('' !== $safari_country) : ?>
                    <p class="page-hero__eyebrow"><?php echo esc_html($safari_country); ?></p>
                <?php endif; ?>

                <h1 class="page-hero__title"><?php the_title(); ?></h1>

                <?php if (has_excerpt()) : ?>
                    <p class="page-hero__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>

                <ul class="page-hero__meta">
                    <?php if ($safari_types) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('binoculars'); ?>
                            <?php echo esc_html(implode(' · ', wp_list_pluck($safari_types, 'name'))); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($safari_months) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('sun'); ?>
                            <?php
                            printf(
                                /* translators: 1: first month, 2: last month */
                                esc_html(__('Best %1$s – %2$s', 'safari-travel')),
                                esc_html(safari_month_name((string) reset($safari_months))),
                                esc_html(safari_month_name((string) end($safari_months)))
                            );
                            ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($safari_tours->found_posts > 0) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('route'); ?>
                            <?php
                            printf(
                                /* translators: %d: number of itineraries */
                                esc_html(_n('%d itinerary', '%d itineraries', (int) $safari_tours->found_posts, 'safari-travel')),
                                esc_html((string) $safari_tours->found_posts)
                            );
                            ?>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php safari_schema_destination($safari_id); ?>
            </div>
        </header>

        <?php /* ── Overview + seasonality ────────────────────────────────── */ ?>
        <div class="section section--tight">
            <div class="container">
                <div class="split split--media-right" style="align-items:start">

                    <div class="split__body">
                        <div class="prose" data-reveal="up">
                            <?php the_content(); ?>
                        </div>

                        <?php if (is_string($safari_region) && '' !== trim($safari_region)) : ?>
                            <div class="notice notice--accent" data-reveal="up" style="margin-block-start:var(--space-lg)">
                                <?php safari_icon('compass'); ?>
                                <div>
                                    <p class="notice__title"><?php esc_html_e('About the wider region', 'safari-travel'); ?></p>
                                    <?php echo wp_kses_post(wpautop($safari_region)); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="split__media">
                        <div class="card" data-reveal="up" data-reveal-delay="80">
                            <div class="card__body card__body--loose">
                                <?php get_template_part('template-parts/global/month-chart', null, [
                                    'best'    => $safari_months,
                                    'heading' => __('When to go', 'safari-travel'),
                                ]); ?>
                            </div>
                            <div class="card__foot">
                                <span class="card__meta-item">
                                    <?php safari_icon('calendar'); ?>
                                    <?php esc_html_e('Peak wildlife activity', 'safari-travel'); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display:grid;gap:var(--space-2xs);margin-block-start:var(--space-md)">
                            <?php safari_button(safari_plan_url(), sprintf(/* translators: %s: destination */ __('Plan a safari in %s', 'safari-travel'), get_the_title()), 'primary', ['class' => 'btn--block']); ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <?php /* ── Gallery ───────────────────────────────────────────────── */ ?>
        <?php if (is_array($safari_gallery) && $safari_gallery) : ?>
            <div class="section section--tight section--subtle">
                <div class="container">
                    <?php
                    get_template_part('template-parts/global/section-heading', null, [
                        'eyebrow' => __('On the ground', 'safari-travel'),
                        'title'   => __('A look at the place', 'safari-travel'),
                    ]);
                    ?>

                    <div class="gallery gallery--mosaic">
                        <?php foreach (array_slice($safari_gallery, 0, 7) as $safari_img_id) : ?>
                            <figure
                                class="gallery__item"
                                data-lightbox
                               	data-caption="<?php echo esc_attr((string) get_the_title($safari_id)); ?>"
                            >
                                <?php
                                echo wp_get_attachment_image(
                                    (int) $safari_img_id,
                                    'safari-card',
                                    false,
                                    [
                                        'class'   => 'gallery__img',
                                        'loading' => 'lazy',
                                        'alt'     => esc_attr((string) get_the_title($safari_id)),
                                    ]
                                );
                                ?>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ── Tours here ────────────────────────────────────────────── */ ?>
        <?php if ($safari_tours->posts) : ?>
            <div class="section">
                <div class="container">
                    <?php
                    get_template_part('template-parts/global/section-heading', null, [
                        'eyebrow'   => __('Itineraries', 'safari-travel'),
                        'title'     => sprintf(
                            /* translators: %s: destination name */
                            __('Safaris we run in %s', 'safari-travel'),
                            get_the_title()
                        ),
                        'link_url'  => (string) get_post_type_archive_link('tour'),
                        'link_text' => __('All itineraries', 'safari-travel'),
                    ]);
                    ?>

                    <div class="tour-rail" data-rail data-reveal-group>
                        <?php foreach ($safari_tours->posts as $safari_tour) : ?>
                            <?php
                            get_template_part('template-parts/cards/card-tour', null, [
					'post_id'  => $safari_tour,
					'compact' => true,
				]);
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ── FAQ ──────────────────────────────────────────────────── */ ?>
        <?php if ($safari_faqs) : ?>
            <div class="section section--subtle">
                <div class="container container--narrow">
                    <?php
                    get_template_part('template-parts/global/section-heading', null, [
                        'eyebrow' => __('Good to know', 'safari-travel'),
                        'title'   => __('Questions about this destination', 'safari-travel'),
                        'align'   => 'center',
                    ]);
                    ?>

                    <div class="accordion" data-accordion>
                        <?php foreach ($safari_faqs as $safari_faq) : ?>
                            <?php
                            $safari_answer = trim((string) safari_field('short_answer', $safari_faq->ID, ''));
                            $safari_answer = '' !== $safari_answer
                                ? $safari_answer
                                : safari_excerpt(40, $safari_faq->ID);
                            ?>
                            <div class="accordion__item">
                                <h3>
                                    <button
                                        type="button"
                                        class="accordion__trigger"
                                        aria-expanded="false"
                                        aria-controls="faq-<?php echo esc_attr((string) $safari_faq->ID); ?>"
                                    >
                                        <span><?php echo esc_html(get_the_title($safari_faq->ID)); ?></span>
                                        <span class="accordion__icon" aria-hidden="true"></span>
                                    </button>
                                </h3>
                                <div
                                    class="accordion__panel"
                                    id="faq-<?php echo esc_attr((string) $safari_faq->ID); ?>"
                                >
                                    <div class="accordion__panel-inner">
                                        <div class="accordion__content">
                                            <?php echo wp_kses_post(wpautop($safari_answer)); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ── Lead form ────────────────────────────────────────────── */ ?>
        <div class="section" id="destination-inquiry">
            <div class="container container--narrow">
                <?php
                get_template_part('template-parts/global/section-heading', null, [
                    'eyebrow' => __('Next step', 'safari-travel'),
                    'title'   => __('Plan your time here', 'safari-travel'),
                    'lead'    => __('Tell us your dates and how you like to travel. We will come back with an itinerary and a firm price.', 'safari-travel'),
                    'align'   => 'center',
                ]);
                ?>

                <?php
                if (class_exists('Safari_Lead_Form')) {
                    Safari_Lead_Form::render('quick', [
                        'destination_id' => $safari_id,
                        'tour_id'        => 0,
                    ]);
                } else {
                    safari_button(safari_plan_url(), safari_plan_label(), 'primary', ['size' => 'lg']);
                }
                ?>
            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();
