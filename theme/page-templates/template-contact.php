<?php
/**
 * Template Name: Contact
 * Template Post Type: page
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$safari_phone = safari_phone();
$safari_tel   = safari_tel_url();
$safari_wa    = safari_whatsapp_url();
$safari_email = safari_contact_email();
?>

<section class="page-hero page-hero--compact page-hero--solid">
    <div class="container page-hero__inner">
        <?php safari_breadcrumbs(); ?>
        <p class="page-hero__eyebrow"><?php esc_html_e('We reply within one business day', 'safari-travel'); ?></p>
        <h1 class="page-hero__title"><?php the_title(); ?></h1>
        <p class="page-hero__lead">
            <?php
            echo esc_html(
                (string) get_post_meta(get_the_ID(), 'safari_page_lead', true)
                ?: __('Questions about a route, a visa, a season, or whether your dates will work? Ask. There is no such thing as a silly question before a safari.', 'safari-travel')
            );
            ?>
        </p>
    </div>
</section>

<div class="section section--tight">
    <div class="container">
        <div class="contact-grid">

            <div>
                <div class="stack" style="display:grid;gap:var(--space-md)">

                    <?php if ('' !== $safari_tel) : ?>
                        <div class="contact-card" data-reveal="up">
                            <span class="contact-card__icon"><?php safari_icon('phone'); ?></span>
                            <span class="contact-card__label"><?php esc_html_e('Call us', 'safari-travel'); ?></span>
                            <a class="contact-card__value" href="<?php echo esc_url($safari_tel); ?>">
                                <?php echo esc_html($safari_phone); ?>
                            </a>
                            <span class="contact-card__note">
                                <?php esc_html_e('Mon–Sat, 8am – 6pm EAT', 'safari-travel'); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ('' !== $safari_wa) : ?>
                        <div class="contact-card" data-reveal="up" data-reveal-delay="60">
                            <span class="contact-card__icon"><?php safari_icon('whatsapp'); ?></span>
                            <span class="contact-card__label"><?php esc_html_e('WhatsApp', 'safari-travel'); ?></span>
                            <a class="contact-card__value" href="<?php echo esc_url($safari_wa); ?>" rel="noopener noreferrer" target="_blank">
                                <?php esc_html_e('Message us', 'safari-travel'); ?>
                            </a>
                            <span class="contact-card__note">
                                <?php esc_html_e('Fastest reply — usually within the hour', 'safari-travel'); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="contact-card" data-reveal="up" data-reveal-delay="120">
                        <span class="contact-card__icon"><?php safari_icon('mail'); ?></span>
                        <span class="contact-card__label"><?php esc_html_e('Email', 'safari-travel'); ?></span>
                        <a class="contact-card__value" href="mailto:<?php echo esc_attr($safari_email); ?>">
                            <?php echo esc_html($safari_email); ?>
                        </a>
                        <span class="contact-card__note">
                            <?php esc_html_e('We read everything, even on Sundays', 'safari-travel'); ?>
                        </span>
                    </div>

                    <div class="contact-card" data-reveal="up" data-reveal-delay="180">
                        <span class="contact-card__icon"><?php safari_icon('pin'); ?></span>
                        <span class="contact-card__label"><?php esc_html_e('Where we are', 'safari-travel'); ?></span>
                        <span class="contact-card__value" style="font-size:var(--text-base)">
                            <?php
                            $safari_address = (string) safari_option('address', '');
                            echo esc_html('' !== $safari_address ? $safari_address : __('Nairobi, Kenya', 'safari-travel'));
                            ?>
                        </span>
                    </div>
                </div>

                <div class="card card--dark" data-reveal="up" style="margin-block-start:var(--space-lg);background:var(--st-forest-900);border:0">
                    <div class="card__body">
                        <h2 style="font-size:var(--text-xl);color:var(--color-text-inverse);margin:0 0 var(--space-2xs)">
                            <?php esc_html_e('Prefer to start with a plan?', 'safari-travel'); ?>
                        </h2>
                        <p style="color:rgb(253 251 247 / 0.72);font-size:var(--text-sm);margin:0 0 var(--space-md)">
                            <?php esc_html_e('The Plan My Safari form gets you a written itinerary faster than back-and-forth email.', 'safari-travel'); ?>
                        </p>
                        <?php safari_button(safari_plan_url(), safari_plan_label(), 'accent', ['class' => 'btn--block']); ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" data-reveal="up" style="box-shadow:var(--shadow-lg)">
                    <div class="card__body card__body--loose">
                        <?php
                        get_template_part('template-parts/global/section-heading', null, [
                            'title' => __('Send us a message', 'safari-travel'),
                            'lead'  => __('Tell us as much or as little as you like. The more you share, the more useful our first reply will be.', 'safari-travel'),
                        ]);
                        ?>

                        <?php
                        if (class_exists('Safari_Lead_Form')) {
                            Safari_Lead_Form::render('contact', [
                                'destination_id' => 0,
                                'tour_id'        => 0,
                            ]);
                        } else {
                            echo '<p class="text-muted">' . esc_html__('Our contact form is unavailable right now. Please email or call us instead.', 'safari-travel') . '</p>';
                        }
                        ?>
                    </div>
                </div>

                <?php the_content(); ?>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
