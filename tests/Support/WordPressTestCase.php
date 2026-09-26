<?php
/**
 * Base class for the WordPress-side unit tests.
 *
 * The plugin classes under test are real, unmodified repository code. WordPress
 * itself is replaced by Brain Monkey, which intercepts the handful of functions
 * these classes call and lets each test declare exactly what they return. That
 * means the assertions run against the shipped logic rather than a re-implementation
 * of it, which is the only way "we did not change the business logic" can be
 * checked rather than asserted.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * @phpstan-ignore-next-line
 */
abstract class WordPressTestCase extends PhpUnitTestCase {

	/**
	 * Values returned by the stubbed option API, keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = array();

	/**
	 * Values returned by the stubbed transient API.
	 *
	 * @var array<string, mixed>
	 */
	protected array $transients = array();

	/**
	 * IP addresses handed to wp_salt()/REMOTE_ADDR, per test.
	 */
	protected string $remoteAddr = '203.0.113.10';

	/**
	 * Set up WordPress function stubs shared by every plugin class.
	 */
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->options     = array();
		$this->transients = array();
		$this->remoteAddr = '203.0.113.10';

		$_SERVER['REMOTE_ADDR'] = $this->remoteAddr;

		$this->stubEscaping();
		$this->stubOptions();
		$this->stubTransients();
		$this->stubWpErrors();
		$this->stubHttp();
		$this->stubTime();
	}

	protected function tearDown(): void {
		unset( $_SERVER['REMOTE_ADDR'] );

		Monkey\tearDown();
		Mockery::close();

		parent::tearDown();
	}

	/*
	|--------------------------------------------------------------------------
	| Stubs
	|--------------------------------------------------------------------------
	*/

	/**
	 * The sanitising and escaping helpers every plugin class relies on.
	 *
	 * These mirror WordPress's real behaviour closely enough for the validation
	 * logic to be meaningfully exercised: tags are stripped, text is trimmed,
	 * slugs are lower-cased and URLs are normalised.
	 */
	private function stubEscaping(): void {
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				$value = (string) $value;
				$value = wp_strip_all_tags_compat( $value );
				$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

				return trim( (string) $value );
			}
		);

		Functions\when( 'sanitize_textarea_field' )->alias(
			static function ( $value ) {
				$value = wp_strip_all_tags_compat( (string) $value );
				$value = preg_replace( '/[\r\n\t ]+/', "\n", (string) $value );

				return trim( (string) $value );
			}
		);

		Functions\when( 'sanitize_email' )->alias(
			static function ( $value ) {
				$value = filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL );

				return is_string( $value ) ? trim( $value ) : '';
			}
		);

		Functions\when( 'sanitize_key' )->alias(
			static function ( $value ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
			}
		);

		Functions\when( 'is_email' )->alias(
			static fn ( $value ): bool => (bool) filter_var( (string) $value, FILTER_VALIDATE_EMAIL )
		);

		Functions\when( 'esc_url_raw' )->alias(
			static function ( $value ) {
				$value = trim( (string) $value );

				if ( '' === $value ) {
					return '';
				}

				$filtered = filter_var( $value, FILTER_SANITIZE_URL );

				return is_string( $filtered ) ? $filtered : '';
			}
		);

		Functions\when( 'esc_url' )->alias( static fn ( $value ): string => (string) $value );

		Functions\when( 'esc_html' )->alias(
			static fn ( $value ): string => htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' )
		);

		Functions\when( 'esc_attr' )->alias(
			static fn ( $value ): string => htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' )
		);

		Functions\when( 'absint' )->alias( static fn ( $value ): int => abs( (int) $value ) );

		Functions\when( '__' )->alias( static fn ( $text, $domain = 'default' ): string => (string) $text );

		Functions\when( 'esc_html__' )->alias( static fn ( $text, $domain = 'default' ): string => (string) $text );

		Functions\when( '_n' )->alias(
			static fn ( $single, $plural, $number, $domain = 'default' ): string => 1 === (int) $number ? (string) $single : (string) $plural
		);

		Functions\when( 'wp_salt' )->alias( static fn ( $scheme = 'auth' ): string => 'test-auth-salt' );
	}

	private function stubOptions(): void {
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value, $autoload = null ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);

		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults = array() ) {
				$args = is_array( $args ) ? $args : array();

				return array_merge( $defaults, $args );
			}
		);

		Functions\when( 'apply_filters' )->alias( static fn ( $hook, $value, ...$rest ) => $value );
	}

	private function stubTransients(): void {
		Functions\when( 'get_transient' )->alias(
			fn ( $key ) => $this->transients[ $key ] ?? false
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $expiry = 0 ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( $key ): bool {
				unset( $this->transients[ $key ] );

				return true;
			}
		);
	}

	/**
	 * WP_Error and is_wp_error.
	 *
	 * The repository ships its own WP_Error stub; loading it here (rather than
	 * relying on the bootstrap) keeps this base class self-contained.
	 */
	private function stubWpErrors(): void {
		if ( ! class_exists( 'WP_Error' ) ) {
			require_once __DIR__ . '/../_stubs/class-wp-error.php';
		}

		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
	}

	private function stubHttp(): void {
		Functions\when( 'wp_remote_post' )->alias(
			fn ( $url, $args = array() ) => new class() {
				/**
				 * @return array{body: string}
				 */
				public function to_array(): array {
					return array( 'body' => '{"success":true}' );
				}
			}
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn ( $response ): string => '{"success":true}'
		);
	}

	private function stubTime(): void {
		Functions\when( 'current_time' )->alias(
			static fn ( $type = 'mysql', $gmt = 0 ) => 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Load a plugin class exactly once.
	 *
	 * The classes call `exit` when ABSPATH is undefined, which is the correct
	 * guard for production and only an obstacle in a unit test.
	 */
	protected function loadPluginClass( string $relative ): void {
		$this->loadPluginFile( $relative, true );
	}

	/**
	 * Load a side-effect-only plugin file for every test.
	 *
	 * Registration files such as post-types.php and taxonomies.php do nothing
	 * but call add_action(), so including them once per test is safe and
	 * necessary: with require_once the second test would find the file already
	 * loaded, the hook would never be re-registered against the current test's
	 * stub, and every assertion would fail. Only use this for files that declare
	 * no functions or classes; anything else must go through loadPluginClass().
	 */
	protected function loadPluginFile( string $relative, bool $once = false ): void {
		$path = dirname( __DIR__, 2 ) . '/' . ltrim( $relative, '/' );

		if ( ! is_file( $path ) ) {
			$this->fail( sprintf( 'Plugin file not found: %s', $path ) );
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/tests/_stubs/' );
		}

		if ( $once ) {
			require_once $path;

			return;
		}

		require $path;
	}

	/**
	 * Read a repository file.
	 */
	protected function readRepoFile( string $relative ): string {
		$path = dirname( __DIR__, 2 ) . '/' . ltrim( $relative, '/' );

		$this->assertFileExists( $path );

		return (string) file_get_contents( $path );
	}
}

/**
 * strip_tags() plus a whitespace squeeze — the behaviour the WordPress
 * sanitising helpers are expected to have.
 */
function wp_strip_all_tags_compat( string $value ): string {
	$value = strip_tags( $value );
	$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	return $value;
}
