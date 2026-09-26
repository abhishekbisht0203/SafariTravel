<?php
/**
 * Single: Blog post.
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
    $safari_id    = get_the_ID();
    $safari_read  = safari_reading_time($safari_id);
    $safari_cats  = get_the_category();
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
                <h1 class="page-hero__title"><?php the_title(); ?></h1>

                <div class="post-meta" style="margin-block-start:var(--space-md);color:rgb(253 251 247 / 0.82)">
                    <span class="post-meta__item">
                        <?php safari_icon('clock'); ?>
                        <?php
                        printf(
                            /* translators: %d: number of minutes */
                            esc_html(_n('%d min read', '%d min read', $safari_read, 'safari-travel')),
                            esc_html((string) $safari_read)
                        );
                       	?>
                    </span>
                    <span class="post-meta__sep" aria-hidden="true"></span>
                    <span class="post-meta__item">
                        <?php safari_icon('calendar'); ?>
                        <time datetime="<?php echo esc_attr(get_the_date('c', $safari_id)); ?>">
                            <?php echo esc_html(get_the_date('', $safari_id)); ?>
                        </time>
                    </span>
                    <?php if ($safari_cats) : ?>
                        <span class="post-meta__sep" aria-hidden="true"></span>
                        <span class="post-meta__item">
                            <?php echo esc_html(implode(' · ', wp_list_pluck($safari_cats, 'name'))); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="section section--tight">
            <div class="container">
                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail" data-reveal="scale" style="max-width:60rem;margin-inline:auto">
                        <?php the_post_thumbnail('safari-hero', ['loading' => 'lazy']); ?>
                    </div>
                <?php endif; ?>

                <div class="prose" data-reveal="up" style="margin-inline:auto">
                    <?php the_content(); ?>
                </div>

                <?php if (has_tag()) : ?>
                    <p class="post-meta" style="justify-content:center;margin-block-start:var(--space-lg)">
                        <?php the_tags('', '', ''); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $safari_related = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post__not_in'   => [$safari_id],
            'category__in'   => wp_list_pluck($safari_cats, 'term_id'),
            'orderby'        => 'rand',
        ]);

        if ($safari_related->posts) :
            ?>
            <section class="section section--subtle">
                <div class="container">
                    <h2 class="related__title" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
                        <?php esc_html_e('Related reading', 'safari-travel'); ?>
                    </h2>

                    <div class="grid grid--3" data-reveal-group>
                        <?php foreach ($safari_related->posts as $safari_rel) : ?>
                            <?php
                            set_query_var('post', $safari_rel);
                            get_template_part('template-parts/cards/card-guide');
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    </article>

    <?php
endwhile;

get_footer();
