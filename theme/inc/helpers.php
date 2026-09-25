<?php
/**
 * Template helper functions.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Post thumbnail with responsive srcset, or a placeholder SVG.
 *
 * @param int         $post_id Post ID.
 * @param string      $size    Registered image size.
 * @param string|null $class   Extra CSS classes.
 */
function safari_post_thumbnail(int $post_id, string $size = 'safari-card', ?string $class = null): void
{
    if (! has_post_thumbnail($post_id)) {
        echo safari_placeholder_svg($class);
        return;
    }

    the_post_thumbnail($size, [
        'class'   => trim('safari-thumb ' . (string) $class),
        'loading' => 'lazy',
        'alt'     => esc_attr(get_the_title($post_id)),
    ]);
}

/**
 * Inline SVG placeholder when no featured image exists.
 */
function safari_placeholder_svg(?string $class = null): string
{
    $classes = esc_attr(trim('safari-thumb safari-thumb--placeholder ' . (string) $class));
    return '<svg class="' . $classes . '" viewBox="0 0 640 426" role="img" aria-label="' . esc_attr__('Image coming soon', 'safari-travel') . '" focusable="false"><rect width="640" height="426" fill="#F0EDE6"/><path d="M160 280 L280 160 L360 240 L440 180 L520 280 Z" fill="#1B5E43" opacity="0.35"/><circle cx="480" cy="120" r="28" fill="#D4A017" opacity="0.5"/></svg>';
}

/**
 * Reading time estimate in minutes.
 */
function safari_reading_time(int $post_id): int
{
    $words = str_word_count(strip_tags((string) get_post_field('post_content', $post_id)));
    return max(1, (int) ceil($words / 200));
}

/**
 * Safe excerpt length.
 */
function safari_excerpt(int $length = 28, ?int $post_id = null): string
{
    $post_id = $post_id ?: get_the_ID();
    if (! $post_id) {
        return '';
    }
    $text = get_the_excerpt($post_id);
    if (function_exists('wp_trim_words')) {
        return wp_trim_words($text, $length, '…');
    }
    return $text;
}

/**
 * Get site contact email from lead settings, fall back to admin_email.
 */
function safari_contact_email(): string
{
    $option = get_option('safari_leads_settings', []);
    $email  = $option['notification_emails'] ?? '';
    if (is_string($email) && is_email($email)) {
        $first = explode(',', $email)[0];
        return trim($first);
    }
    return (string) get_option('admin_email');
}

/**
 * Render a primary CTA button (used across templates).
 */
function safari_cta_button(string $url, string $label, string $variant = 'primary'): string
{
    $variant = in_array($variant, ['primary', 'secondary', 'ghost'], true) ? $variant : 'primary';
    return sprintf(
        '<a class="safari-btn safari-btn--%1$s" href="%2$s">%3$s</a>',
        esc_attr($variant),
        esc_url($url),
        esc_html($label)
    );
}
