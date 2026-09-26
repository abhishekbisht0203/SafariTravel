<?php
/**
 * Template Name: Full Width
 * Template Post Type: page
 *
 * No hero, no constrained measure — the page's own block content owns the
 * canvas. Used for landing pages composed in the editor.
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
        <?php the_content(); ?>
    </article>
<?php endwhile; ?>

<?php
get_footer();
