<?php
/**
 * Template Name: Thank You
 * Template Post Type: page
 *
 * The post-conversion page. Every lead redirects here, which is what makes
 * GA4 / Google Ads / Meta conversion tracking reliable (plan §5.3).
 *
 * Marked noindex so it never appears in search results.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// The thank-you page is a conversion endpoint, not content.
add_filter('wp_robots', static function (array $robots): array {
    $robots['noindex']  = 'noindex';
    $robots['nofollow'] = 'nofollow';

    return $robots;
}, 20);

get_header();
?>

<section class="section">
    <div class="container">

        <div class="thanks">
            <span class="thanks__seal" aria-hidden="true">
                <?php safari_icon('check'); ?>
            </span>

            <div>
                <p class="section-head__eyebrow" style="justify-content:center" data-reveal="up">
                    <?php esc_html_e('Enquiry received', 'safari-travel'); ?>
                </p>
                <h1 class="thanks__title" data-reveal="up" data-reveal-delay="60">
                    <?php
                    the_title();
                    ?>
                </h1>
                <p class="thanks__text" data-reveal="up" data-reveal-delay="120">
                    <?php
                    echo esc_html(
                        (string) get_post_meta(get_the_ID(), 'safari_page_lead', true)
                        ?: __('Thank you. Your message is with our team and a named safari specialist will come back to you within one business day — usually much sooner.', 'safari-travel')
                    );
                    ?>
                </p>
            </div>

            <?php the_content(); ?>

            <div class="thanks__next" data-reveal="up" data-reveal-delay="180">
                <h2 style="font-size:var(--text-lg);margin:0 0 var(--space-sm)">
                    <?php esc_html_e('While you wait, you could…', 'safari-travel'); ?>
                </h2>
                <ol class="thanks__next-list">
                    <li><?php esc_html_e('Read our destination guides — they cover visas, seasons and what to pack.', 'safari-travel'); ?></li>
                    <li><?php esc_html_e('Browse the itineraries we run, in case something needs adjusting.', 'safari-travel'); ?></li>
                    <li><?php esc_html_e('Save our WhatsApp number, so you can answer us instantly when we call.', 'safari-travel'); ?></li>
                </ol>

                <div class="btn-row btn-row--inline" style="margin-block-start:var(--space-md)">
                    <?php safari_button((string) get_post_type_archive_link('tour'), __('Browse itineraries', 'safari-travel'), 'primary'); ?>
                    <?php safari_button((string) get_post_type_archive_link('destination'), __('Explore destinations', 'safari-travel'), 'outline'); ?>
                    <?php safari_button((string) get_post_type_archive_link('safari_guide'), __('Read the guides', 'safari-travel'), 'outline'); ?>
                </div>
            </div>

            <div class="card card--dark" style="max-width:44rem;background:var(--st-forest-900);border:0" data-reveal="up" data-reveal-delay="240">
                <div class="card__body" style="text-align:center">
                    <h2 style="font-size:var(--text-lg);color:var(--color-text-inverse);margin:0 0 var(--space-2xs)">
                        <?php esc_html_e('Urgent, or already travelling?', 'safari-travel'); ?>
                    </h2>
                    <p style="color:rgb(253 251 247 / 0.72);font-size:var(--text-sm);margin:0 0 var(--space-md)">
                        <?php esc_html_e('If your departure is within the next 48 hours, call or WhatsApp us directly and we will pick it up straight away.', 'safari-travel'); ?>
                    </p>
                    <div class="btn-row btn-row--inline" style="justify-content:center">
                        <?php
                        $safari_tel = safari_tel_url();
                        if ('' !== $safari_tel) {
                            safari_button($safari_tel, safari_phone(), 'inverse', ['magnetic' => true]);
                        }

                        $safari_wa = safari_whatsapp_url();
                        if ('' !== $safari_wa) {
                            safari_button($safari_wa, __('WhatsApp us', 'safari-travel'), 'accent');
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
