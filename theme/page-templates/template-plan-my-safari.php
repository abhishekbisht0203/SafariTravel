<?php
/**
 * Template Name: Plan My Safari
 * Template Post Type: page
 *
 * The primary conversion page (plan §5.3). Split layout: reassurance on the
 * left, the multi-step lead form on the right, reassurance and process below.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>

<section class="plan-hero section--grain">
    <div class="dust" aria-hidden="true"></div>
    <div class="container">
        <div class="plan-hero__grid">

            <div data-reveal-group>
                <p class="hero__eyebrow" data-reveal="up">
                    <?php esc_html_e('Start here', 'safari-travel'); ?>
                </p>

                <h1 class="plan-hero__title" data-reveal="up" data-reveal-delay="60">
                    <?php
                    the_title();
                    ?>
                </h1>

                <p class="lead plan-hero__lead" data-reveal="up" data-reveal-delay="120">
                    <?php
                    echo esc_html(
                        (string) get_post_meta(get_the_ID(), 'safari_page_lead', true)
                        ?: __('Three short steps. A named safari specialist reads it, checks the camps and availability for your dates, and sends back a written itinerary with a firm price — usually within one business day, and always at no cost.', 'safari-travel')
                    );
                    ?>
                </p>

                <ul class="plan-hero__assurances" data-reveal-group>
                    <li data-reveal="up" data-reveal-delay="180">
                        <?php safari_icon('check-circle'); ?>
                        <span><?php esc_html_e('A real person reads every enquiry', 'safari-travel'); ?></span>
                    </li>
                    <li data-reveal="up" data-reveal-delay="240">
                        <?php safari_icon('check-circle'); ?>
                        <span><?php esc_html_e('No obligation, no pressure, no sales calls you did not ask for', 'safari-travel'); ?></span>
                    </li>
                    <li data-reveal="up" data-reveal-delay="300">
                        <?php safari_icon('check-circle'); ?>
                        <span><?php esc_html_e('Park fees and conservancy levies shown separately and honestly', 'safari-travel'); ?></span>
                    </li>
                    <li data-reveal="up" data-reveal-delay="360">
                        <?php safari_icon('lock'); ?>
                        <span><?php esc_html_e('Your details are stored securely and never sold or shared', 'safari-travel'); ?></span>
                    </li>
                </ul>
            </div>

            <div data-reveal="up" data-reveal-delay="120">
                <?php
                if (class_exists('Safari_Lead_Form')) {
                    Safari_Lead_Form::render('plan_my_safari', [
                        'destination_id' => 0,
                        'tour_id'        => 0,
                    ]);
                } else {
                    safari_button(safari_page_url('contact'), __('Contact us instead', 'safari-travel'), 'primary', ['size' => 'lg']);
                }
                ?>
            </div>
        </div>
    </div>
</section>

<?php /* ── What happens next ──────────────────────────────────────────── */ ?>
<section class="section" aria-labelledby="next-heading">
    <div class="container">
        <?php
        get_template_part('template-parts/global/section-heading', null, [
            'eyebrow' => __('What happens next', 'safari-travel'),
            'title'   => __('From your message to a confirmed booking', 'safari-travel'),
            'align'   => 'center',
        ]);
        ?>

        <div class="grid grid--4 process" data-reveal-group>
            <div class="process__item" data-reveal="up">
                <h3 class="process__title"><?php esc_html_e('We read it properly', 'safari-travel'); ?></h3>
                <p class="process__text"><?php esc_html_e('Your enquiry goes to the specialist who knows the destinations you are asking about — not a shared inbox.', 'safari-travel'); ?></p>
            </div>
            <div class="process__item" data-reveal="up">
                <h3 class="process__title"><?php esc_html_e('We check the details', 'safari-travel'); ?></h3>
                <p class="process__text"><?php esc_html_e('Camp availability for your dates, migration timing, seasonal pricing, and whether the route makes geographic sense.', 'safari-travel'); ?></p>
            </div>
            <div class="process__item" data-reveal="up">
                <h3 class="process__title"><?php esc_html_e('You get a written plan', 'safari-travel'); ?></h3>
                <p class="process__text"><?php esc_html_e('Day by day, camp by camp, with alternatives where budget or season would make a better fit.', 'safari-travel'); ?></p>
            </div>
            <div class="process__item" data-reveal="up">
                <h3 class="process__title"><?php esc_html_e('You decide, calmly', 'safari-travel'); ?></h3>
                <p class="process__text"><?php esc_html_e('We refine as many times as you need. When it is right, we take a deposit and handle the rest.', 'safari-travel'); ?></p>
            </div>
        </div>
    </div>
</section>

<?php /* ── Why travellers come to us ───────────────────────────────────── */ ?>
<section class="section section--brand section--grain" aria-labelledby="plan-trust">
    <div class="dust" aria-hidden="true"></div>
    <div class="container">
        <div class="stats stats--4 stats--ruled" data-reveal-group>
            <div class="stat" data-reveal="up">
                <span class="stat__number" data-target="16" data-suffix="+">0</span>
                <span class="stat__label"><?php esc_html_e('Years guiding', 'safari-travel'); ?></span>
            </div>
            <div class="stat" data-reveal="up">
                <span class="stat__number" data-target="24000" data-suffix="+">0</span>
                <span class="stat__label"><?php esc_html_e('Travellers guided', 'safari-travel'); ?></span>
            </div>
            <div class="stat" data-reveal="up">
                <span class="stat__number" data-target="1" data-suffix=" day">0</span>
                <span class="stat__label"><?php esc_html_e('Typical reply time', 'safari-travel'); ?></span>
            </div>
            <div class="stat" data-reveal="up">
                <span class="stat__number" data-target="100" data-suffix="%">0</span>
                <span class="stat__label"><?php esc_html_e('Free proposals', 'safari-travel'); ?></span>
            </div>
        </div>
    </div>
</section>

<?php /* ── FAQ strip ──────────────────────────────────────────────────── */ ?>
<?php
$safari_faqs = get_posts([
    'post_type'      => 'faq',
    'posts_per_page' => 4,
    'orderby'        => 'menu_order date',
    'order'          => 'ASC',
]);

if ($safari_faqs) :
    ?>
    <section class="section" aria-labelledby="plan-faq">
        <div class="container container--narrow">
            <?php
            get_template_part('template-parts/global/section-heading', null, [
                'eyebrow' => __('Before you send', 'safari-travel'),
                'title'   => __('Quick answers', 'safari-travel'),
                'align'   => 'center',
            ]);
            ?>

            <div class="accordion" data-accordion>
                <?php foreach ($safari_faqs as $safari_faq) : ?>
                    <?php
                    $safari_answer = (string) safari_field('short_answer', $safari_faq->ID, '');
                    $safari_answer = '' !== trim($safari_answer) ? $safari_answer : safari_excerpt(45, $safari_faq->ID);
                    ?>
                    <div class="accordion__item">
                        <h3>
                            <button
                                type="button"
                                class="accordion__trigger"
                                aria-expanded="false"
                                aria-controls="plan-faq-<?php echo esc_attr((string) $safari_faq->ID); ?>"
                            >
                                <span><?php echo esc_html(get_the_title($safari_faq->ID)); ?></span>
                                <span class="accordion__icon" aria-hidden="true"></span>
                            </button>
                        </h3>
                        <div class="accordion__panel" id="plan-faq-<?php echo esc_attr((string) $safari_faq->ID); ?>">
                            <div class="accordion__panel-inner">
                                <div class="accordion__content"><?php echo wp_kses_post(wpautop($safari_answer)); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
endif;

get_footer();
