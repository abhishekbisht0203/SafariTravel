<?php
/**
 * Plugin Name: Safari Hardening
 * Description: Security headers, WordPress hardening measures, and REST API protection.
 *              Loaded as an MU-plugin so it cannot be deactivated from the admin.
 * Version: 1.0.0
 * Author: Safari Travel
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

// ── Security headers ───────────────────────────────────────────────────────

add_action('send_headers', static function (): void {
	// Prevent MIME-type sniffing.
	header('X-Content-Type-Options: nosniff');

	// Clickjacking protection.
	header('X-Frame-Options: SAMEORIGIN');

	// Referrer policy.
	header('Referrer-Policy: strict-origin-when-cross-origin');

	// Permissions policy — disable unneeded browser features.
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=()');

	// HSTS (only send over HTTPS; browsers enforce on subsequent visits).
	if (is_ssl()) {
		header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
	}

	// Content Security Policy — report-only mode first; tighten per environment.
	// Admin is excluded to avoid breaking the editor.
	if (! is_admin()) {
		/*
		 * When the Vite dev server is running, its origin is added to the
		 * directives that have to reach it: the page loads CSS and ES modules
		 * from :5173, and the HMR client opens a WebSocket back to it. Without
		 * this, every asset request is reported as a violation and — the moment
		 * the policy moves from report-only to enforce — local development stops
		 * loading entirely.
		 *
		 * The dev origin comes from the same .vite-dev flag the theme's enqueue
		 * logic reads (theme/inc/assets.php), so there is still one place that
		 * knows the dev server address. It resolves to false in production,
		 * which is why the production policy is unchanged.
		 */
		$vite_dev = function_exists('safari_vite_dev_base_url') ? safari_vite_dev_base_url() : false;
		$vite_src = '';
		$vite_ws  = '';
		if (is_string($vite_dev) && $vite_dev !== '') {
			$parts = wp_parse_url($vite_dev);
			if (! empty($parts['host'])) {
				$origin = (isset($parts['scheme']) ? $parts['scheme'] : 'http')
					. '://' . $parts['host']
					. (isset($parts['port']) ? ':' . $parts['port'] : '');
				$vite_src = ' ' . $origin;
				// CSP does not let an http:// source stand in for ws:// in
				// connect-src, and the HMR channel is a WebSocket.
				$vite_ws = ' ' . preg_replace('/^http/', 'ws', $origin);
			}
		}

		$csp  = "default-src 'self'; ";
		$csp .= "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://www.googletagmanager.com" . $vite_src . "; ";
		$csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com" . $vite_src . "; ";
		$csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
		$csp .= "img-src 'self' data: https:" . $vite_src . "; ";
		$csp .= "connect-src 'self' https://challenges.cloudflare.com" . $vite_src . $vite_ws . "; ";
		$csp .= "frame-src https://challenges.cloudflare.com; ";
		$csp .= "object-src 'none'; ";
		$csp .= "base-uri 'self';";

		// Start in report-only; change header name to enforce when ready.
		header('Content-Security-Policy-Report-Only: ' . $csp);
	}
});

// ── Disable XML-RPC ────────────────────────────────────────────────────────

add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', static function (array $headers): array {
	unset($headers['X-Pingback']);
	return $headers;
});

// ── Hide WordPress version from public output ──────────────────────────────

remove_action('wp_head', 'wp_generator');

add_filter('the_generator', '__return_empty_string');

// Remove version from enqueued scripts/styles.
add_filter('script_loader_src', 'safari_remove_query_strings', 15);
add_filter('style_loader_src', 'safari_remove_query_strings', 15);

function safari_remove_query_strings(string $src): string {
	if (strpos($src, '?ver=') !== false) {
		$src = remove_query_arg('ver', $src);
	}
	return $src;
}

// ── Disable REST API user enumeration ──────────────────────────────────────

add_filter('rest_endpoints', static function (array $endpoints): array {
	// Block unauthenticated access to /wp/v2/users.
	if (isset($endpoints['/wp/v2/users']) && ! current_user_can('list_users')) {
		unset($endpoints['/wp/v2/users']);
	}
	if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)']) && ! current_user_can('list_users')) {
		unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
	}
	return $endpoints;
});

// Block REST author slug exposure (user enumeration via /?author=N).
add_action('template_redirect', static function (): void {
	if (is_author() && ! current_user_can('list_users')) {
		wp_safe_redirect(home_url('/'), 301);
		exit;
	}
});

// ── Remove unnecessary default WordPress meta tags ─────────────────────────

remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

// ── Disable file editing from admin (belt-and-suspenders) ─────────────────

if (! defined('DISALLOW_FILE_EDIT')) {
	define('DISALLOW_FILE_EDIT', true);
}
if (! defined('DISALLOW_FILE_MODS')) {
	// Uncomment on production if all updates are managed via Git/CI.
	// define('DISALLOW_FILE_MODS', true);
}

// ── Limit login attempts storage (block brute-force without Wordfence) ─────
// Note: in production, offload to Cloudflare WAF rate-limit rule on /wp-login.php.

add_filter('authenticate', static function ($user, string $username, string $password) {
	if (empty($username) || empty($password)) {
		return $user;
	}

	$ip_hash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . (string) wp_salt('auth'));
	$key     = 'safari_login_attempts_' . $ip_hash;
	$data    = get_transient($key);

	if (is_array($data) && isset($data['count']) && $data['count'] >= 10) {
		return new WP_Error(
			'too_many_retries',
			__('<strong>Error</strong>: Too many failed login attempts. Please try again in 15 minutes.', 'safari-travel')
		);
	}

	return $user;
}, 1, 3);

add_action('wp_login_failed', static function (): void {
	$ip_hash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . (string) wp_salt('auth'));
	$key     = 'safari_login_attempts_' . $ip_hash;
	$data    = get_transient($key);
	$count   = is_array($data) ? ((int) $data['count'] + 1) : 1;
	set_transient($key, ['count' => $count], 15 * MINUTE_IN_SECONDS);
});

add_action('wp_login', static function (): void {
	$ip_hash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . (string) wp_salt('auth'));
	delete_transient('safari_login_attempts_' . $ip_hash);
});

// ── Remove emoji scripts (not needed; saves ~10 KB) ───────────────────────

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_filter('the_content_feed', 'wp_staticize_emoji');
remove_filter('comment_text_rss', 'wp_staticize_emoji');
remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

// ── Remove oEmbed / embed (not needed for this site) ──────────────────────

remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');

// ── Block PHP execution in uploads via .htaccess hint ─────────────────────
// Also add a static file fallback; real enforcement is via server config.

add_filter('upload_mimes', static function (array $mimes): array {
	// Disallow PHP / executable uploads.
	$blocked = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pl', 'py', 'cgi'];
	foreach ($blocked as $ext) {
		unset($mimes[$ext]);
	}
	return $mimes;
});
