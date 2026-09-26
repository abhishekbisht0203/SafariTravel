<?php
/**
 * Single: Travel Guide article.
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
    $safari_read   = safari_reading_time($safari_id);
    $safari_topics = get_the_terms($safari_id, 'guide_topic');
    $safari_date   = get_the_date('c', $safari_id);
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

                <?php if ($safari_topics) : ?>
                    <p class="page-hero__eyebrow"><?php echo esc_html(implode(' · ', wp_list_pluck($safari_topics, 'name'))); ?></p>
                <?php endif; ?>

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
                        <time datetime="<?php echo esc_attr($safari_date); ?>"><?php echo esc_html(get_the_date('', $safari_id)); ?></time>
                    </span>
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
                    <?php if (has_excerpt()) : ?>
                        <p class="lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                    <?php endif; ?>

                    <?php the_content(); ?>
                </div>

                <div class="share-rail" data-reveal="up" style="justify-content:center;margin-block-start:var(--space-2xl)">
                    <span class="share-rail__label"><?php esc_html_e('Share', 'safari-travel'); ?></span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode((string) get_permalink()); ?>"
                       rel="noopener noreferrer" target="_blank">
                        <?php safari_icon('facebook'); ?>
                        <span class="visually-hidden"><?php esc_html_e('Share on Facebook', 'safari-travel'); ?></span>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode((string) get_permalink()); ?>&amp;text=<?php echo rawurlencode((string) get_the_title()); ?>"
                       rel="noopener noreferrer" target="_blank">
                        <?php safari_icon('arrow-up'); ?>
                        <span class="visually-hidden"><?php esc_html_e('Share on X', 'safari-travel'); ?></span>
                    </a>
                    <a href="mailto:?subject=<?php echo rawurlencode((string) get_the_title()); ?>&amp;body=<?php echo rawurlencode((string) get_permalink()); ?>">
                        <?php safari_icon('mail'); ?>
                        <span class="visually-hidden"><?php esc_html_e('Share by email', 'safari-travel'); ?></span>
                    </a>
                </div>

                <?php
                $safari_related = new WP_Query([
                    'post_type'      => 'safari_guide',
                    'posts_per_page' => 3,
                    'post__not_in'   => [$safari_id],
                    'orderby'        => 'rand',
                ]);

                if ($safari_related->posts) :
                    ?>
                    <section class="related" aria-labelledby="more-guides">
                        <h2 class="related__title" id="more-guides" style="font-family:var(--font-heading);font-size:var(--text-2xl)">
                            <?php esc_html_e('Keep reading', 'safari-travel'); ?>
                        </h2>

                        <div class="grid grid--3" data-reveal-group>
                            <?php foreach ($safari_related->posts as $safari_rel) : ?>
                                <?php
                                get_template_part('template-parts/cards/card-guide', null, ['post_id' => $safari_rel]);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>

    </article>

    <?php
endwhile;

get_footer();
