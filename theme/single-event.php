<?php
/**
 * Single: Event / Offer.
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

    $safari_id     = get_the_ID();
    $safari_start  = (string) safari_field('start_date', $safari_id, '');
    $safari_end    = (string) safari_field('end_date', $safari_id, '');
    $safari_code   = (string) safari_field('promo_code', $safari_id, '');
    $safari_dest   = (int) safari_field('related_destination', $safari_id, 0);
    $safari_types  = get_the_terms($safari_id, 'event_type');
    $safari_is_past = '' !== $safari_end && strtotime($safari_end) < strtotime('today');
    ?>

    <article <?php post_class(); ?>>

        <header class="page-hero page-hero--compact">
            <?php if (has_post_thumbnail()) : ?>
                <div class="page-hero__bg" data-parallax="0.12">
                    <?php safari_thumbnail($safari_id, 'safari-hero', [], true); ?>
                </div>
            <?php else : ?>
                <div class="page-hero__bg map-dots"></div>
            <?php endif; ?>

            <div class="container page-hero__inner">
                <?php safari_breadcrumbs(); ?>

                <p class="page-hero__eyebrow">
                    <?php echo esc_html(
                        $safari_is_past
                            ? __('This offer has ended', 'safari-travel')
                            : __('Limited availability', 'safari-travel')
                    ); ?>
                </p>

                <h1 class="page-hero__title"><?php the_title(); ?></h1>

                <?php if (has_excerpt()) : ?>
                    <p class="page-hero__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>

                <ul class="page-hero__meta">
                    <?php if ('' !== $safari_start) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('calendar'); ?>
                            <?php
                            if ('' !== $safari_end && $safari_end !== $safari_start) {
                                printf(
                                    /* translators: 1: start date, 2: end date */
                                    esc_html(__('%1$s – %2$s', 'safari-travel')),
                                    esc_html(date_i18n(get_option('date_format'), (int) strtotime($safari_start))),
                                    esc_html(date_i18n(get_option('date_format'), (int) strtotime($safari_end)))
                                );
                            } else {
                                echo esc_html(date_i18n(get_option('date_format'), (int) strtotime($safari_start)));
                            }
                            ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($safari_types) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('sparkle'); ?>
                            <?php echo esc_html(implode(' · ', wp_list_pluck($safari_types, 'name'))); ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($safari_dest) : ?>
                        <li class="page-hero__meta-item">
                            <?php safari_icon('pin'); ?>
                            <a href="<?php echo esc_url((string) get_permalink($safari_dest)); ?>">
                                <?php echo esc_html(get_the_title($safari_dest)); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </header>

        <div class="section section--tight">
            <div class="container container--narrow">
                <div class="prose" data-reveal="up">
                    <?php the_content(); ?>
                </div>

                <?php if ('' !== $safari_code && ! $safari_is_past) : ?>
                    <div class="notice notice--accent" data-reveal="up" style="margin-block-start:var(--space-xl)">
                        <?php safari_icon('sparkle'); ?>
                        <div>
                            <p class="notice__title"><?php esc_html_e('Your promo code', 'safari-travel'); ?></p>
                            <p style="margin:0">
                                <?php
                                printf(
                                    /* translators: %s: promo code */
                                    esc_html(__('Quote %s when you enquire, and we will apply it before you commit to anything.', 'safari-travel')),
                                    '<strong>' . esc_html($safari_code) . '</strong>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (! $safari_is_past) : ?>
                    <div class="card" data-reveal="up" style="margin-block-start:var(--space-xl)">
                        <div class="card__body card__body--loose">
                            <h2 style="font-size:var(--text-xl)">
                                <?php esc_html_e('Claim this offer', 'safari-travel'); ?>
                            </h2>
                            <p class="text-muted measure">
                                <?php esc_html_e('Availability is limited and confirmed on a first-come basis. Send us your dates and we will hold a place while you decide.', 'safari-travel'); ?>
                            </p>

                            <div style="margin-block-start:var(--space-md)">
                                <?php
                                if (class_exists('Safari_Lead_Form')) {
                                    Safari_Lead_Form::render('quick', [
                                        'destination_id' => $safari_dest,
                                        'tour_id'        => 0,
                                    ]);
                                } else {
                                    safari_button(safari_plan_url(), safari_plan_label(), 'primary');
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();
