<?php
/**
 * 404 — Not found.
 *
 * Suggests popular destinations and puts the search box and lead form front
 * and centre, so a dead end becomes a lead (plan §12).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$safari_popular = get_posts([
    'post_type'      => 'destination',
    'posts_per_page' => 4,
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
]);
?>

<section class="section" aria-labelledby="error-heading">
    <div class="container">

        <div class="error-404">
            <p class="error-404__code" aria-hidden="true">404</p>

            <div>
                <h1 class="error-404__title" id="error-heading" data-reveal="up">
                    <?php esc_html_e('This trail goes nowhere', 'safari-travel'); ?>
                </h1>
                <p class="error-404__text" data-reveal="up" data-reveal-delay="60">
                    <?php esc_html_e('The page you were after has moved, or never existed. Nothing has gone wrong with your enquiry — try a search, or pick up one of the routes below.', 'safari-travel'); ?>
                </p>
            </div>

            <div style="width:100%;max-width:34rem" data-reveal="up" data-reveal-delay="120">
                <?php get_search_form(); ?>
            </div>

            <div class="error-404__links" data-reveal="up" data-reveal-delay="180">
                <?php safari_button(home_url('/'), __('Back to home', 'safari-travel'), 'outline'); ?>
                <?php safari_button((string) get_post_type_archive_link('tour'), __('Browse tours', 'safari-travel'), 'outline'); ?>
                <?php safari_button(safari_plan_url(), safari_plan_label(), 'primary'); ?>
            </div>
        </div>

        <?php if ($safari_popular) : ?>
            <div style="margin-block-start:var(--space-2xl)">
                <?php
                get_template_part('template-parts/global/section-heading', null, [
                    'eyebrow' => __('Popular', 'safari-travel'),
                    'title'   => __('Where most people start', 'safari-travel'),
                    'align'   => 'center',
                ]);
                ?>

                <div class="grid grid--4" data-reveal-group>
                    <?php foreach ($safari_popular as $safari_dest) : ?>
                        <?php
                        set_query_var('post', $safari_dest);
                        get_template_part('template-parts/cards/card-destination');
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
get_footer();
