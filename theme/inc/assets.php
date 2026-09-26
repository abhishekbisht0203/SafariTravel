<?php
/**
 * Enqueue scripts and styles (Vite).
 *
 * Two modes, one entry point per mode:
 *
 *   - `npm run dev` running: CSS and JS come straight from the Vite dev
 *     server, which also provides HMR. The mount URL is read from
 *     theme/.vite-dev rather than hard-coded, so it always matches the base
 *     path declared in vite.config.js.
 *   - otherwise: the hashed, minified files named by the build manifest in
 *     theme/assets/dist/manifest.json.
 *
 * The production bundle is IIFE (see vite.config.js) so it can be enqueued as
 * a classic deferred script — no module graph, no extra round trips.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How long a `.vite-dev` flag may go unrefreshed before it is treated as dead.
 *
 * The Vite dev server rewrites the flag every few seconds (see
 * HEARTBEAT_MS in vite.config.js). A flag that stops being touched means the
 * dev server is gone — closed terminal, killed process, rebooted machine —
 * and enqueueing its URLs would render the site completely unstyled.
 */
const SAFARI_VITE_DEV_STALE_AFTER = 20;

/**
 * Return the Vite dev server mount URL when `npm run dev` is running.
 *
 * The flag file holds the full mount path, origin and base, exactly as Vite
 * resolved it — e.g. http://localhost:5173/wp-content/themes/safari-theme/assets/dist
 * — so WordPress never has to re-derive (or guess) the base path that
 * vite.config.js declares. Returns false when the built assets should be used.
 *
 * @return string|false Dev server base URL without trailing slash, or false.
 */
function safari_vite_dev_base_url(): string|false
{
    $flag = SAFARI_THEME_DIR . '/.vite-dev';

    if (! is_file($flag)) {
        return false;
    }

    $mtime = @filemtime($flag);
    if ($mtime === false || (time() - $mtime) > SAFARI_VITE_DEV_STALE_AFTER) {
        return false;
    }

    $url = trim((string) @file_get_contents($flag));

    return $url !== '' ? untrailingslashit($url) : false;
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

/**
 * Force `type="module"` on the handles that need it.
 *
 * Both the Vite dev server and the production bundle are ES modules. That is
 * not cosmetic:
 *
 *   - `@vite/client` and the dev entry are served with bare specifiers, which
 *     a classic script cannot resolve at all.
 *   - the HMR client can only patch modules that were loaded as modules.
 *
 * `wp_script_add_data( $handle, 'type', 'module' )` is silently ignored for
 * classic scripts, so the attribute is added to the tag instead.
 *
 * @param string $tag    Script tag markup.
 * @param string $handle Enqueue handle.
 * @return string Filtered markup.
 */
add_filter('script_loader_tag', static function (string $tag, string $handle): string {
    if (is_admin() || ! in_array($handle, ['safari-main', 'safari-vite-client'], true)) {
        return $tag;
    }

    if (str_contains($tag, 'type="module"')) {
        return $tag;
    }

    return str_replace('<script ', '<script type="module" ', $tag);
}, 10, 2);

add_action('wp_enqueue_scripts', static function (): void {
    $dev = safari_vite_dev_base_url();

    if ($dev !== false) {
        /*
         * Development: the Vite dev server is the asset host. Every URL is
         * built from the mount path Vite itself reported, so the two sides
         * cannot drift.
         */
        wp_enqueue_script(
            'safari-vite-client',
            $dev . '/@vite/client',
            [],
            null,
            ['in_footer' => true, 'strategy' => 'defer']
        );

        // Render-blocking by design: the whole design system is token-driven,
        // so loading it as a stylesheet keeps first paint fully styled.
        wp_enqueue_style('safari-main', $dev . '/src/main.css', [], null);

        /*
         * `type="module"` (added by the script_loader_tag filter above) is
         * required, not cosmetic. In dev the dev server hands back real ES
         * modules with bare specifiers; a classic script cannot resolve them,
         * and the HMR client can only patch modules loaded as modules.
         */
        wp_enqueue_script(
            'safari-main',
            $dev . '/src/main.js',
            ['safari-vite-client'],
            null,
            ['in_footer' => true, 'strategy' => 'defer']
        );

        return;
    }

    /*
     * Production: resolve the hashed filenames from the build manifest. The
     * manifest keys mirror the Vite entry names (src/main.js, src/main.css);
     * the extra fallbacks keep older or differently-keyed manifests working.
     */
    $manifest_path = SAFARI_THEME_DIR . '/assets/dist/manifest.json';
    if (! is_file($manifest_path)) {
        return;
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (! is_array($manifest)) {
        return;
    }

    $css = $manifest['src/main.css']['file']
        ?? $manifest['style.css']['file']
        ?? $manifest['src/main.js']['css'][0]
        ?? null;
    $js  = $manifest['src/main.js']['file'] ?? null;

    // Skip anything the manifest names but the build did not emit, so a stale
    // manifest can never turn into a 404 in the page.
    if (is_string($css) && is_file(SAFARI_THEME_DIR . '/assets/dist/' . $css)) {
        // The whole stylesheet is required for first paint, so it is
        // render-blocking by design (plan §10).
        wp_enqueue_style(
            'safari-main',
            SAFARI_THEME_URI . '/assets/dist/' . $css,
            [],
            SAFARI_THEME_VERSION
        );
    }

    if (is_string($js) && is_file(SAFARI_THEME_DIR . '/assets/dist/' . $js)) {
        /*
         * The production bundle is a single self-contained ES module (it has no
         * imports of its own), so `type="module"` gives it the same
         * defer-by-default semantics as development and keeps one code path for
         * both modes.
         */
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
