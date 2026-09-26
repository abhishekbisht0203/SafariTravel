<?php
/**
 * Isolated harness for mu-plugins/safari-api-bridge.php.
 *
 * mu-plugins are loaded once per PHP request, and the bridge decides at load
 * time whether to register anything by reading SAFARI_API_KEY. That decision
 * cannot be re-made inside a single test process, so this script runs in its own
 * process with a controlled environment, stubs the three WordPress functions the
 * bridge touches at registration time, and prints the registered routes as JSON
 * on stdout.
 *
 * Usage: php tests/api-bridge/bridge-runner.php
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

if ( '1' !== ( getenv( 'SAFARI_BRIDGE_RUNNER' ) ?: '' ) ) {
	fwrite( STDERR, "This script is a test helper and must not be run directly.\n" );

	exit( 1 );
}

$root = dirname( __DIR__, 2 );

/** @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> $routes */
$routes = array();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/tests/_stubs/class-wp-error.php';
require_once $root . '/tests/_stubs/class-wp-rest-server.php';

class WP_REST_Request {}

/**
 * Capture a route registration.
 */
function register_rest_route( $namespace, $route, $args = array() ): bool {
	global $routes;

	foreach ( (array) ( $args['methods'] ?? '' ) as $method ) {
		$routes[] = array(
			(string) $namespace,
			(string) $route,
			array_merge( (array) $args, array( 'method' => $method ) ),
		);
	}

	return true;
}

/**
 * Fire `rest_api_init` immediately: the bridge hooks route registration there.
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
	if ( 'rest_api_init' === $hook && is_callable( $callback ) ) {
		$callback();
	}

	return true;
}

function __( $text, $domain = 'default' ) {
	return (string) $text;
}

function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

require_once $root . '/mu-plugins/safari-api-bridge.php';

echo wp_json_encode_fallback( $routes );

/**
 * WordPress is not loaded, so encode by hand.
 *
 * @param array<int, mixed> $value
 */
function wp_json_encode_fallback( array $value ): string {
	return (string) json_encode(
		array_map(
			static function ( $route ) {
				$route[2]['permission_callback'] = is_callable( $route[2]['permission_callback'] ?? null )
					? 'callable'
					: 'missing';
				$route[2]['callback'] = is_callable( $route[2]['callback'] ?? null ) ? 'callable' : 'missing';

				return $route;
			},
			$value
		)
	);
}
