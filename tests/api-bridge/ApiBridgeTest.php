<?php
/**
 * The WordPress-side API bridge.
 *
 * mu-plugins/safari-api-bridge.php is the only new WordPress code the Laravel
 * backend needs. It must be inert by default: a checkout that has never heard
 * of the API has to behave exactly as it did before.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests\Api_Bridge;

use Safari\Tests\WordPressTestCase;

final class ApiBridgeTest extends WordPressTestCase {

	/**
	 * @var array<int, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private array $routes = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $httpResponse = array( 'success' => true );

	protected function setUp(): void {
		parent::setUp();

		$this->routes = array();

		\Brain\Monkey\Functions\when( 'register_rest_route' )->alias(
			function ( $namespace, $route, $args = array() ): bool {
				foreach ( (array) ( $args['methods'] ?? '' ) as $method ) {
					$this->routes[] = array( (string) $namespace, (string) $route, (array) $args + array( 'method' => $method ) );
				}

				return true;
			}
		);

		\Brain\Monkey\Functions\when( 'add_action' )->justReturn( true );
		\Brain\Monkey\Functions\when( '__' )->alias( static fn ( $text, $domain = 'default' ): string => (string) $text );
	}

	/*
	|--------------------------------------------------------------------------
	| Inert by default
	|--------------------------------------------------------------------------
	*/

	public function test_it_registers_nothing_without_a_configured_key(): void {
		$this->assertSame( array(), $this->registeredRoutes( null ) );
	}

	public function test_it_registers_its_routes_once_a_key_is_configured(): void {
		$paths = array_map(
			static fn (array $route): string => $route[0] . $route[1],
			$this->registeredRoutes( 'unit-test-key' )
		);

		$this->assertContains( 'safari-api/v1/leads', $paths );
		$this->assertContains( 'safari-api/v1/leads/(?P<id>[\d]+)', $paths );
		$this->assertContains( 'safari-api/v1/health', $paths );
	}

	public function test_it_uses_a_namespace_of_its_own(): void {
		// The private bridge must never be published under the public namespace
		// the theme and the API client use.
		$this->assertStringNotContainsString("'safari/v1'", $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Authorisation
	|--------------------------------------------------------------------------
	*/

	public function test_the_authorization_helper_returns_wp_error(): void {
		// The method is the permission callback on every bridge route, so its
		// return type is the whole security boundary. WordPress allows a
		// permission callback to return a WP_Error, which is what carries the
		// 401 status back to the caller.
		$source = $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' );

		$this->assertStringContainsString( 'public static function authorize(): bool|WP_Error', $source );
		$this->assertStringContainsString( 'hash_equals', $source );

		// Every route must use it.
		$count = substr_count( $source, "'permission_callback' => array( self::class, 'authorize' )" );

		$this->assertSame( 3, $count, 'Every bridge route must require the shared key.' );
	}

	public function test_it_never_handles_a_request_without_the_header(): void {
		putenv( 'SAFARI_API_KEY=unit-test-key' );

		try {
			$this->loadBridgeClass();

			unset( $_SERVER['HTTP_X_SAFARI_API_KEY'] );

			$result = \Safari_API_Bridge::authorize();

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'safari_api_unauthorized', $result->get_error_code() );
		} finally {
			putenv( 'SAFARI_API_KEY' );
		}
	}

	public function test_a_wrong_key_is_refused(): void
	{
		putenv( 'SAFARI_API_KEY=unit-test-key' );

		try {
			$this->loadBridgeClass();

			$_SERVER['HTTP_X_SAFARI_API_KEY'] = 'not-the-key';

			$this->assertInstanceOf( \WP_Error::class, \Safari_API_Bridge::authorize() );
		} finally {
			unset( $_SERVER['HTTP_X_SAFARI_API_KEY'] );
			putenv( 'SAFARI_API_KEY' );
		}
	}

	public function test_the_matching_key_is_accepted(): void
	{
		putenv( 'SAFARI_API_KEY=unit-test-key' );

		try {
			$this->loadBridgeClass();

			$_SERVER['HTTP_X_SAFARI_API_KEY'] = 'unit-test-key';

			$this->assertTrue( \Safari_API_Bridge::authorize() );
		} finally {
			unset( $_SERVER['HTTP_X_SAFARI_API_KEY'] );
			putenv( 'SAFARI_API_KEY' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Containment
	|--------------------------------------------------------------------------
	*/

	public function test_it_does_not_touch_the_public_intake_route(): void
	{
		$source = $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' );

		// The public endpoint and its behaviour are owned by safari-leads. The
		// bridge must neither re-register nor re-implement it, so it may only
		// ever use its own `safari-api/v1` namespace. Note that the bridge does
		// own a `/leads` path — that is the server-to-server mirror endpoint.
		$this->assertStringContainsString( "public const NAMESPACE = 'safari-api/v1';", $source );
		$this->assertStringNotContainsString( 'SAFARI_LEADS_ROUTE', $source );
		$this->assertStringNotContainsString( 'Safari_Lead_Validation', $source );
	}

	public function test_it_never_sends_email(): void
	{
		$source = $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' );

		// The visitor has already been told by whichever application accepted
		// the submission; mirroring exists only to keep the wp-admin list whole.
		$this->assertStringNotContainsString( 'wp_mail', $source );
		$this->assertStringNotContainsString( 'Safari_Lead_Email', $source );
	}

	public function test_it_only_writes_to_the_lead_tables(): void
	{
		$source = $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' );

		$this->assertStringContainsString( 'Safari_Lead_DB::insert_lead', $source );
		$this->assertStringContainsString( 'Safari_Lead_DB::update_lead', $source );

		// No direct table writes of its own.
		$this->assertStringNotContainsString( '$wpdb->insert', $source );
		$this->assertStringNotContainsString( '$wpdb->update', $source );
		$this->assertStringNotContainsString( '$wpdb->query', $source );
	}

	public function test_it_never_forwards_the_ip_hash(): void
	{
		$source = $this->readRepoFile( 'mu-plugins/safari-api-bridge.php' );

		// The API salts the hash differently and the WordPress store has no use
		// for it, so a mirror must not copy it across.
		$this->assertStringContainsString( "'ip_hash'           => null", $source );
	}

	/**
	 * Make the bridge class available without triggering its bootstrap.
	 *
	 * `authorize()` reads the shared key on every call rather than caching it,
	 * so the class can be loaded once and still be exercised against different
	 * keys. Route registration is the load-time decision and is tested in
	 * registeredRoutes() instead.
	 */
	private function loadBridgeClass(): void {
		if ( class_exists( '\Safari_API_Bridge', false ) ) {
			return;
		}

		$this->loadPluginClass( 'mu-plugins/safari-api-bridge.php' );
	}

	/**
	 * Load the mu-plugin in an isolated PHP process and report the REST routes
	 * it registers.
	 *
	 * The bridge is a mu-plugin: PHP loads it exactly once per request, and
	 * whether it registers anything is decided at that moment from
	 * SAFARI_API_KEY. Loading it in-process with require_once would therefore
	 * make the result depend on which test happened to run first, so each
	 * scenario gets a clean process with its own environment instead.
	 *
	 * @param string|null $apiKey Value for SAFARI_API_KEY, or null to leave it unset.
	 *
	 * @return array<int, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private function registeredRoutes( ?string $apiKey ): array {
		$runner = __DIR__ . '/bridge-runner.php';

		$this->assertFileExists( $runner );

		$environment = array( 'SAFARI_BRIDGE_RUNNER' => '1' );

		if ( null !== $apiKey ) {
			$environment['SAFARI_API_KEY'] = $apiKey;
		}

		$command = sprintf(
			'%s %s 2>&1',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $runner )
		);

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( $command, $descriptors, $pipes, dirname( __DIR__, 2 ), $environment );

		$this->assertIsResource( $process, 'Could not start the bridge runner.' );

		fclose( $pipes[0] );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$status = proc_close( $process );

		$this->assertSame( 0, $status, 'The bridge runner failed: ' . $stdout . $stderr );

		$decoded = json_decode( trim( $stdout ), true );

		$this->assertIsArray( $decoded, 'The bridge runner did not return JSON: ' . $stdout . $stderr );

		/** @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> $decoded */
		return $decoded;
	}
}
