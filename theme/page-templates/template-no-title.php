<?php
/**
 * Template Name: No Title
 * Template Post Type: page
 *
 * A quiet hero — title, no breadcrumb bar, no image — for utility pages like
 * Privacy Policy where a big hero would be inappropriate.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class(); ?>>

        <header class="page-hero page-hero--solid page-hero--compact">
            <div class="container page-hero__inner">
                <h1 class="page-hero__title"><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?>
                    <p class="page-hero__lead"><?php echo esc_html(get_the_excerpt()); ?></p>
                <?php endif; ?>
                <p class="post-meta" style="margin-block-start:var(--space-sm)">
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                        <?php echo esc_html(get_the_date()); ?>
                    </time>
                </p>
            </div>
        </header>

        <div class="section section--tight">
            <div class="container">
                <div class="prose" data-reveal="up">
                    <?php the_content(); ?>
                </div>
            </div>
        </div>

    </article>
<?php endwhile; ?>

<?php
get_footer();
