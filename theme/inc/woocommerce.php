<?php
/**
 * WooCommerce integration — plan §7.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    if (! class_exists('WooCommerce')) {
        return;
    }
    add_theme_support('woocommerce');
});

// Custom archive columns.
add_filter('loop_shop_columns', static function (): int {
    return 3;
});

// Products per page.
add_filter('loop_shop_per_page', static function (): int {
    return 12;
});

// Remove default Woo sidebar on shop (use full-width layout).
add_filter('woocommerce_output_content_wrapper', static function (): void {
    echo '<div class="safari-woo-wrapper site-main container">';
});

add_filter('woocommerce_output_content_wrapper_end', static function (): void {
    echo '</div>';
});

/**
 * When catalogue mode is enabled (no payment gateway), turn "Add to cart"
 * into a lead-request link (plan §7 fallback).
 */
add_filter('woocommerce_loop_add_to_cart_link', static function (string $html, $product): string {
    if (! safari_is_catalogue_mode()) {
        return $html;
    }
    if (! $product instanceof WC_Product) {
        return $html;
    }

    $url = add_query_arg(
        [
            'lead_form' => 'product',
            'product'   => $product->get_id(),
        ],
        home_url('/')
    );

    return sprintf(
        '<a class="button safari-btn safari-btn--primary" href="%s">%s</a>',
        esc_url($url),
        esc_html__('Request this item', 'safari-travel')
    );
}, 10, 2);

/**
 * Catalogue mode = no enabled payment gateways or explicitly filtered on.
 */
function safari_is_catalogue_mode(): bool
{
    if (! class_exists('WC_Payment_Gateways')) {
        return true;
    }
    $filter = apply_filters('safari_catalogue_mode', null);
    if (is_bool($filter)) {
        return $filter;
    }
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    return empty($gateways);
}
