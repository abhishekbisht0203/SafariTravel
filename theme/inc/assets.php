<?php
/**
 * Enqueue scripts and styles (Vite build output).
 *
 * The theme ships one self-contained JS runtime plus one stylesheet. Both are
 * built as IIFE/merged output (see vite.config.js) so they can be enqueued as
 * classic deferred scripts — no module graph, no extra round trips.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Return the Vite dev server URL when running `npm run dev`, otherwise false.
 */
function safari_vite_dev_server(): string|false
{
    $flag = SAFARI_THEME_DIR . '/.vite-dev';
    if (file_exists($flag)) {
        $url = trim((string) file_get_contents($flag));
        return $url !== '' ? $url : false;
    }
    return false;
}

/**
 * Enqueue a local font file if it exists in theme/assets/fonts.
 *
 * @param string $handle Enqueue handle.
 * @param string $family CSS font-family name to declare.
 * @param string $file   File name inside assets/fonts.
 * @param string $weight Font weight.
 * @param string $subset Glyph subset label for the preload comment.
 */
function safari_enqueue_local_font(string $handle, string $family, string $file, string $weight, string $subset = 'latin'): void
{
    $path = SAFARI_THEME_DIR . '/assets/fonts/' . $file;
    if (! file_exists($path)) {
        return;
    }

    $version = (string) filemtime($path);

    wp_enqueue_style(
        $handle,
        SAFARI_THEME_URI . '/assets/fonts/' . $file,
        [],
        $version
    );

    // Preload only the two fonts used above the fold.
    if (in_array($weight, ['400', '600'], true)) {
        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url(SAFARI_THEME_URI . '/assets/fonts/' . $file)
        );
        unset($family, $subset);
    }
}

add_action('wp_enqueue_scripts', static function (): void {
    $dev = safari_vite_dev_server();

    if ($dev) {
        wp_enqueue_script('safari-vite-client', $dev . '/@vite/client', [], null, true);
        wp_enqueue_script(
            'safari-main',
            $dev . '/src/main.js',
            ['safari-vite-client'],
            null,
            true
        );
        return;
    }

    $manifest_path = SAFARI_THEME_DIR . '/assets/dist/manifest.json';
    if (! file_exists($manifest_path)) {
        $manifest_path = SAFARI_THEME_DIR . '/assets/dist/.vite/manifest.json';
        if (! file_exists($manifest_path)) {
            return;
        }
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (! is_array($manifest)) {
        return;
    }

    // Single entry, so the manifest key is whatever Vite was configured with.
    $css = $manifest['style.css']['file']
        ?? $manifest['src/main.css']['file']
        ?? $manifest['src/main.js']['css'][0]
        ?? null;
    $js  = $manifest['src/main.js']['file'] ?? null;

    if ($css) {
        // The whole stylesheet is ~18 KB gzipped and is required for first
        // paint, so it is render-blocking by design (plan §10).
        wp_enqueue_style(
            'safari-main',
            SAFARI_THEME_URI . '/assets/dist/' . $css,
            [],
            SAFARI_THEME_VERSION
        );
    }

    if ($js) {
        wp_enqueue_script(
            'safari-main',
            SAFARI_THEME_URI . '/assets/dist/' . $js,
            [],
            SAFARI_THEME_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    // Local fonts, if the client has licensed and uploaded them.
    safari_enqueue_local_font('safari-font-body', 'Inter', 'inter-latin-400.woff2', '400');
    safari_enqueue_local_font('safari-font-body-semibold', 'Inter', 'inter-latin-600.woff2', '600');
    safari_enqueue_local_font('safari-font-heading', 'Playfair Display', 'playfair-display-latin-700.woff2', '700');
}, 20);

// Defer non-critical third-party scripts (plan §10).
add_action('wp_enqueue_scripts', static function (): void {
    if (is_admin()) {
        return;
    }
    foreach (['google-fonts', 'recaptcha_v3', 'gtag'] as $handle) {
        wp_script_add_data($handle, 'defer', true);
    }
}, 99);

// Remove WooCommerce assets from non-shop pages (plan §7, §10).
add_action('wp_enqueue_scripts', static function (): void {
    if (! class_exists('WooCommerce')) {
        return;
    }
    if (is_cart() || is_checkout() || is_account_page() || is_woocommerce() || is_product()) {
        return;
    }
    wp_dequeue_style('wc-blocks-style');
    wp_dequeue_style('wc-blocks-packages-style');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_script('wc-blocks-packages-script');
    wp_dequeue_script('wc-add-to-cart');
}, 100);

// Preload the hero image so LCP starts downloading before the CSS is parsed
// (plan §10). Only on singular views that actually have a featured image.
add_action('wp_head', static function (): void {
    if (is_admin() || ! is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();
    if (! $post_id || ! has_post_thumbnail($post_id)) {
        return;
    }

    $id  = (int) get_post_thumbnail_id($post_id);
    $src = wp_get_attachment_image_src($id, 'safari-hero');
    if (! $src) {
        return;
    }

    $srcset = wp_get_attachment_image_srcset($id, 'safari-hero');
    printf(
        '<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="100vw" fetchpriority="high">' . "\n",
        esc_url($src[0]),
        esc_attr((string) $srcset)
    );
}, 2);
