<?php
/**
 * Search results.
 *
 * Groups results by content type so visitors can tell a tour from a guide at a
 * glance, and always offers a way onward when nothing matches (plan §6.1).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$safari_query = get_search_query();
$safari_total = (int) ($GLOBALS['wp_query']->found_posts ?? 0);

// Search across the custom types as well as posts.
$safari_types = [
    'destination'  => __('Destinations', 'safari-travel'),
    'tour'         => __('Tours', 'safari-travel'),
    'event'        => __('Offers', 'safari-travel'),
    'safari_guide' => __('Guides', 'safari-travel'),
    'product'      => __('Shop', 'safari-travel'),
];
?>

<section class="page-hero page-hero--compact page-hero--solid">
    <div class="container page-hero__inner">
        <?php safari_breadcrumbs(); ?>
        <p class="page-hero__eyebrow"><?php esc_html_e('Search', 'safari-travel'); ?></p>
        <h1 class="page-hero__title">
            <?php
            if ('' !== $safari_query) {
                printf(
                    /* translators: %s: search term */
                    esc_html__('Results for “%s”', 'safari-travel'),
                    esc_html($safari_query)
                );
            } else {
                esc_html_e('What are you looking for?', 'safari-travel');
            }
            ?>
        </h1>

        <div style="max-width:34rem;margin-block-start:var(--space-md)">
            <?php get_search_form(); ?>
        </div>
    </div>
</section>

<div class="section section--tight">
    <div class="container container--narrow">
        <?php if (have_posts()) : ?>

            <p class="search-results__summary">
                <?php
                printf(
                    /* translators: %s: result count */
                    esc_html(_n('%s result found', '%s results found', $safari_total, 'safari-travel')),
                    '<strong>' . esc_html(number_format_i18n($safari_total)) . '</strong>'
                );
				?>
            </p>

            <?php
            while (have_posts()) :
                the_post();
                $safari_id  = get_the_ID();
                $safari_type = get_post_type();
                $safari_label = $safari_types[$safari_type] ?? __('Page', 'safari-travel');
                ?>
                <article <?php post_class('search-result'); ?> data-reveal="up">
                    <p class="search-result__type"><?php echo esc_html($safari_label); ?></p>
                    <h2 class="search-result__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <p class="search-result__text clamp-2"><?php echo esc_html(safari_excerpt(28, $safari_id)); ?></p>
                    <p class="search-result__url"><?php echo esc_html((string) wp_make_link_relative((string) get_permalink())); ?></p>
                </article>
            <?php endwhile; ?>

            <?php safari_pagination(); ?>

        <?php else : ?>

            <div class="empty-state">
                <h2 class="empty-state__title">
                    <?php
                    printf(
                        /* translators: %s: search term */
                        esc_html__('Nothing matched “%s”', 'safari-travel'),
                        esc_html($safari_query)
                    );
                    ?>
                </h2>
                <p class="empty-state__text">
                    <?php esc_html_e('Try a destination name, a safari type, or a month. Failing that, tell us what you are after and we will point you in the right direction.', 'safari-travel'); ?>
                </p>

                <div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
                    <?php safari_button((string) get_post_type_archive_link('destination'), __('Browse destinations', 'safari-travel'), 'outline'); ?>
                    <?php safari_button((string) get_post_type_archive_link('tour'), __('Browse tours', 'safari-travel'), 'outline'); ?>
                    <?php safari_button(safari_plan_url(), safari_plan_label(), 'primary'); ?>
                </div>
            </div>

            <?php
            $safari_popular = get_posts([
                'post_type'      => 'destination',
                'posts_per_page' => 4,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            ]);

            if ($safari_popular) :
                ?>
                <div style="margin-block-start:var(--space-2xl)">
                    <?php
                    get_template_part('template-parts/global/section-heading', null, [
                        'eyebrow' => __('Or start here', 'safari-travel'),
                        'title'   => __('Popular destinations', 'safari-travel'),
                    ]);
                    ?>

                    <div class="grid grid--4" data-reveal-group>
                        <?php foreach ($safari_popular as $safari_dest) : ?>
                            <?php
                            set_query_var('post', $safari_dest);
                            get_template_part('template-parts/cards/card-destination', null, [
                                'variant' => 'grid',
                            ]);
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
