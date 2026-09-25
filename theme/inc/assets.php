<?php
/**
 * Enqueue scripts and styles (Vite build output).
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

add_action('wp_enqueue_scripts', static function (): void {
    $dev = safari_vite_dev_server();

    if ($dev) {
        // Vite dev client + entry (HMR).
        wp_enqueue_script('safari-vite-client', $dev . '/@vite/client', [], null, true);
        wp_enqueue_script('safari-main', $dev . '/src/main.js', ['safari-vite-client'], null, true);
        wp_add_inline_style('global', ':root{}');
        return;
    }

    $manifest_path = SAFARI_THEME_DIR . '/assets/dist/manifest.json';
    if (! file_exists($manifest_path)) {
        return;
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (! is_array($manifest)) {
        return;
    }

    $css = $manifest['src/main.css']['file'] ?? null;
    $js  = $manifest['src/main.js']['file'] ?? null;

    if ($css) {
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
}, 20);

// Defer non-critical third-party scripts after idle (plan §10).
add_action('wp_enqueue_scripts', static function (): void {
    if (is_admin()) {
        return;
    }
    wp_script_add_data('google-fonts', 'defer', true);
}, 99);

// Remove Woo styles on non-shop pages (plan §7).
add_action('wp_enqueue_scripts', static function (): void {
    if (! class_exists('WooCommerce')) {
        return;
    }
    if (is_cart() || is_checkout() || is_account_page() || is_woocommerce() || is_product()) {
        return;
    }
    wp_dequeue_style('wc-blocks-style');
}, 100);
