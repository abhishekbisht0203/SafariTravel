<?php
/**
 * Template Name: FAQ
 * Template Post Type: page
 *
 * Faceted accordion with a category jump-nav and live text filtering.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

// Group FAQ posts by category.
$safari_groups = get_terms([
    'taxonomy'   => 'faq_category',
    'hide_empty' => true,
    'orderby'    => 'name',
]);

$safari_by_cat = [];

if (! is_wp_error($safari_groups)) {
    foreach ($safari_groups as $safari_group) {
        $safari_items = get_posts([
            'post_type'      => 'faq',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order date',
            'order'          => 'ASC',
            'tax_query'      => [[
                'taxonomy' => 'faq_category',
                'field'    => 'term_id',
                'terms'    => $safari_group->term_id,
            ]],
        ]);

        if ($safari_items) {
            $safari_by_cat[ $safari_group->term_id ] = [
                'name'  => $safari_group->name,
                'items' => $safari_items,
            ];
        }
    }
}

// Anything with no category assigned.
$safari_orphans = get_posts([
    'post_type'      => 'faq',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
    'tax_query'      => [[
        'taxonomy' => 'faq_category',
        'operator' => 'NOT EXISTS',
    ]],
]);

if ($safari_orphans) {
    $safari_by_cat['uncategorised'] = [
        'name'  => __('General', 'safari-travel'),
        'items' => $safari_orphans,
    ];
}

$safari_total = array_sum(array_map(static fn (array $g): int => count($g['items']), $safari_by_cat));
?>

<section class="page-hero page-hero--compact page-hero--solid">
    <div class="container page-hero__inner">
        <?php safari_breadcrumbs(); ?>
        <p class="page-hero__eyebrow">
            <?php
            printf(
                /* translators: %s: number of questions */
                esc_html(_n('%s question answered', '%s questions answered', $safari_total, 'safari-travel')),
                esc_html(number_format_i18n((int) $safari_total))
            );
            ?>
        </p>
        <h1 class="page-hero__title"><?php the_title(); ?></h1>
        <p class="page-hero__lead">
            <?php
            echo esc_html(
                (string) get_post_meta(get_the_ID(), 'safari_page_lead', true)
                ?: __('If the answer is not here, ask us directly — we would rather answer a question than have you guess.', 'safari-travel')
            );
            ?>
        </p>
    </div>
</section>

<div class="section section--tight">
    <div class="container">
        <?php if ($safari_by_cat) : ?>

            <div class="faq-layout">

                <aside class="faq-nav">
                    <h2 class="faq-nav__title"><?php esc_html_e('Jump to', 'safari-travel'); ?></h2>
                    <ul class="faq-nav__list">
                        <?php foreach ($safari_by_cat as $safari_slug => $safari_group) : ?>
                            <li>
                                <a class="faq-nav__link" href="#faq-group-<?php echo esc_attr((string) $safari_slug); ?>">
                                    <?php echo esc_html($safari_group['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li>
                            <a class="faq-nav__link" href="#faq-ask">
                                <?php esc_html_e('Still stuck? Ask us', 'safari-travel'); ?>
                            </a>
                        </li>
                    </ul>

                    <div style="margin-block-start:var(--space-lg)">
                        <?php safari_button(safari_page_url('contact'), __('Contact us', 'safari-travel'), 'outline', ['size' => 'sm', 'class' => 'btn--block']); ?>
                    </div>
                </aside>

                <div>
                    <?php foreach ($safari_by_cat as $safari_slug => $safari_group) : ?>
                        <section class="faq-group" id="faq-group-<?php echo esc_attr((string) $safari_slug); ?>">
                            <h2 class="faq-group__title" data-reveal="up">
                                <?php echo esc_html($safari_group['name']); ?>
                            </h2>

                            <div class="accordion" data-accordion>
                                <?php foreach ($safari_group['items'] as $safari_faq) : ?>
                                    <?php
                                    $safari_answer = (string) safari_field('short_answer', $safari_faq->ID, '');
                                    $safari_answer = '' !== trim($safari_answer)
                                        ? $safari_answer
                                        : safari_excerpt(50, $safari_faq->ID);
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
                        </section>
                    <?php endforeach; ?>

                    <div class="faq-group" id="faq-ask">
                        <h2 class="faq-group__title" data-reveal="up">
                            <?php esc_html_e('Still have a question?', 'safari-travel'); ?>
                        </h2>
                        <p class="lead" data-reveal="up" data-reveal-delay="60">
                            <?php esc_html_e('Send it over. If it is something other travellers have asked before, we will point you at the answer above as well.', 'safari-travel'); ?>
                        </p>
                        <div class="btn-row btn-row--inline" style="margin-block-start:var(--space-md)">
                            <?php safari_button(safari_page_url('contact'), __('Ask our team', 'safari-travel'), 'primary'); ?>
                            <?php safari_button(safari_plan_url(), safari_plan_label(), 'outline'); ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else : ?>

            <div class="empty-state">
                <h2 class="empty-state__title"><?php esc_html_e('No questions published yet', 'safari-travel'); ?></h2>
                <p class="empty-state__text">
                    <?php esc_html_e('Our FAQ is being written. In the meantime, ask us anything and we will answer it properly.', 'safari-travel'); ?>
                </p>
               	<div class="btn-row btn-row--inline" style="justify-content:center;margin-block-start:var(--space-md)">
                    <?php safari_button(safari_page_url('contact'), __('Contact us', 'safari-travel'), 'primary'); ?>
                </div>
            </div>

        <?php endif; ?>

        <?php the_content(); ?>
    </div>
</div>

<?php
get_footer();
