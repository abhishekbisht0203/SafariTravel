<?php
/**
 * Template Name: Default Page
 * Template Post Type: page
 *
 * A normal page: hero, content, optional sidebar CTA.
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
    $safari_id = get_the_ID();
    ?>

    <article <?php post_class(); ?>>

        <header class="page-hero <?php echo has_post_thumbnail() ? '' : 'page-hero--solid'; ?>">
            <?php if (has_post_thumbnail()) : ?>
                <div class="page-hero__bg" data-parallax="0.12">
                    <?php safari_thumbnail($safari_id, 'safari-hero', [], true); ?>
                </div>
            <?php endif; ?>

            <div class="container page-hero__inner">
                <?php safari_breadcrumbs(); ?>
                <h1 class="page-hero__title"><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p class="page-hero__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <div class="section section--tight">
            <div class="container">
                <?php if (has_post_thumbnail() && ! is_front_page()) : ?>
                    <div class="post-thumbnail" data-reveal="scale" style="max-width:60rem;margin-inline:auto">
                        <?php the_post_thumbnail('safari-hero', ['loading' => 'lazy']); ?>
                    </div>
                <?php endif; ?>

                <div class="prose" data-reveal="up" style="margin-inline:auto">
                    <?php the_content(); ?>
                </div>

                <?php wp_link_pages(['before' => '<nav class="pagination">', 'after' => '</nav>']); ?>
            </div>
        </div>

        <section class="section section--brand section--grain" aria-labelledby="page-cta">
            <div class="dust" aria-hidden="true"></div>
            <div class="container">
                <div style="display:grid;gap:var(--space-md);justify-items:center;text-align:center;max-width:44rem;margin-inline:auto">
                    <h2 class="section-head__title" id="page-cta" style="max-width:20ch" data-reveal="up">
                        <?php esc_html_e('Ready when you are', 'safari-travel'); ?>
                    </h2>
                    <p class="lead measure" data-reveal="up" data-reveal-delay="60">
                        <?php esc_html_e('Tell us your dates and how you like to travel. A specialist will come back within one business day.', 'safari-travel'); ?>
                    </p>
                    <div class="btn-row btn-row--inline" data-reveal="up" data-reveal-delay="120">
                        <?php safari_button(safari_plan_url(), safari_plan_label(), 'accent', ['size' => 'lg', 'magnetic' => true]); ?>
                        <?php safari_button(safari_page_url('contact'), __('Contact us', 'safari-travel'), 'inverse-outline', ['size' => 'lg']); ?>
                    </div>
                </div>
            </div>
        </section>

    </article>

    <?php
endwhile;

get_footer();
