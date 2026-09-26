<?php
/**
 * The fallback template. Also used for the posts index.
 *
 * Must exist: WordPress requires index.php to render anything.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section class="page-hero page-hero--compact">
    <div class="page-hero__bg map-dots" data-parallax="0.08"></div>
    <div class="container page-hero__inner">
        <?php safari_breadcrumbs(); ?>
        <h1 class="page-hero__title"><?php echo esc_html(bloginfo('name')); ?></h1>
        <p class="page-hero__lead"><?php esc_html_e('Stories, advice and field notes from our specialists.', 'safari-travel'); ?></p>
    </div>
</section>

<div class="section section--tight">
    <div class="container">
        <?php if (have_posts()) : ?>

            <?php if (is_home() && ! is_paged()) : ?>
                <?php
                get_template_part('template-parts/global/section-heading', null, [
                    'eyebrow' => __('Latest', 'safari-travel'),
                    'title'   => __('From the field', 'safari-travel'),
                ]);
                ?>
            <?php endif; ?>

            <div class="grid grid--3" data-reveal-group>
                <?php
                while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/cards/card-guide');
                endwhile;
                ?>
            </div>

            <?php safari_pagination(); ?>

        <?php else : ?>
            <div class="empty-state">
                <h2 class="empty-state__title"><?php esc_html_e('Nothing published yet', 'safari-travel'); ?></h2>
                <p class="empty-state__text"><?php esc_html_e('Check back shortly, or get in touch about planning a trip.', 'safari-travel'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
