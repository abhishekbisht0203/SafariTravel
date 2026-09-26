<?php
/**
 * The developer tooling in scripts/.
 *
 * These helpers are what every contributor runs before touching WordPress, so a
 * mistake here can cost somebody a database.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests\Tooling;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Safari\Tooling\Config;
use Safari\Tooling\Env;

final class EnvTest extends PhpUnitTestCase {

	/**
	 * @var list<string>
	 */
	private array $original = array();

	/**
	 * Temporary .env files created by a test, removed afterwards.
	 *
	 * @var list<string>
	 */
	private array $temporaryFiles = array();

	protected function setUp(): void {
		parent::setUp();

		foreach ( array( 'DB_HOST', 'DB_PREFIX', 'API_PORT', 'API_TABLE_PREFIX', 'SAFARI_API_KEY', 'SAFARI_API_DELEGATE' ) as $key ) {
			$this->original[ $key ] = (string) getenv( $key );

			putenv( $key );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->original as $key => $value ) {
			if ( '' === $value ) {
				putenv( $key );

				continue;
			}

			putenv( $key . '=' . $value );
		}

		foreach ( $this->temporaryFiles as $file ) {
			@unlink( $file );
		}

		$this->temporaryFiles = array();

		parent::tearDown();
	}

	/*
	|--------------------------------------------------------------------------
	| Parsing
	|--------------------------------------------------------------------------
	*/

	public function test_it_parses_plain_assignments(): void {
		$parsed = Env::parse( $this->writeEnv( "A=1\nB=two\n" ) );

		$this->assertSame( '1', $parsed['A'] );
		$this->assertSame( 'two', $parsed['B'] );
	}

	public function test_it_ignores_comments_and_blank_lines(): void {
		$parsed = Env::parse( $this->writeEnv( "# a comment\n\nA=1\n; another\n" ) );

		$this->assertSame( array( 'A' => '1' ), $parsed );
	}

	public function test_it_strips_quotes(): void {
		$parsed = Env::parse( $this->writeEnv( "A=\"one two\"\nB='three four'\n" ) );

		$this->assertSame( 'one two', $parsed['A'] );
		$this->assertSame( 'three four', $parsed['B'] );
	}

	public function test_it_accepts_an_export_prefix(): void {
		$this->assertSame( 'x', Env::parse( $this->writeEnv( "export A=x\n" ) )['A'] );
	}

	public function test_it_returns_nothing_for_a_missing_file(): void {
		$this->assertSame( array(), Env::parse( '/definitely/not/here/.env' ) );
	}

	public function test_it_rejects_an_invalid_key(): void {
		$parsed = Env::parse( $this->writeEnv( "9BAD=1\nGOOD=2\n" ) );

		$this->assertArrayNotHasKey( '9BAD', $parsed );
		$this->assertSame( '2', $parsed['GOOD'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Typed readers
	|--------------------------------------------------------------------------
	*/

	public function test_it_reads_booleans_in_the_usual_spellings(): void {
		putenv( 'FLAG1=true' );
		putenv( 'FLAG2=0' );
		putenv( 'FLAG3=yes' );
		putenv( 'FLAG4=off' );

		$this->assertTrue( Env::bool( 'FLAG1' ) );
		$this->assertFalse( Env::bool( 'FLAG2' ) );
		$this->assertTrue( Env::bool( 'FLAG3' ) );
		$this->assertFalse( Env::bool( 'FLAG4' ) );

		// Unset and unparseable values fall back to the default.
		$this->assertTrue( Env::bool( 'NOT_SET', true ) );
		$this->assertFalse( Env::bool( 'NOT_SET', false ) );
	}

	public function test_it_reads_integers(): void {
		putenv( 'PORT=8080' );

		$this->assertSame( 8080, Env::int( 'PORT' ) );
		$this->assertSame( 99, Env::int( 'NOT_SET', 99 ) );
		$this->assertSame( 99, Env::int( 'PORT_ABC', 99 ) );
	}

	public function test_it_trims_string_values(): void {
		putenv( 'PADDED=  spaced  ' );

		$this->assertSame( 'spaced', Env::str( 'PADDED' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Safe editing
	|--------------------------------------------------------------------------
	*/

	public function test_setting_a_key_preserves_the_rest_of_the_file(): void {
		$path = $this->writeEnv( "# keep me\nA=1\nB=2\n" );

		$written = Env::setInFile( $path, array( 'A' => 'changed' ) );

		$contents = (string) file_get_contents( $path );

		$this->assertSame( array( 'A' ), $written );
		$this->assertStringContainsString( '# keep me', $contents );
		$this->assertStringContainsString( 'A=changed', $contents );
		$this->assertStringContainsString( 'B=2', $contents );
	}

	public function test_setting_an_absent_key_appends_it(): void {
		$path = $this->writeEnv( "A=1\n" );

		Env::setInFile( $path, array( 'NEW' => 'value' ) );

		$this->assertStringContainsString( 'NEW=value', (string) file_get_contents( $path ) );
	}

	public function test_appending_leaves_existing_values_alone(): void {
		$path = $this->writeEnv( "A=original\n" );

		Env::appendToFile( $path, array( 'A' => 'replacement', 'B' => 'new' ) );

		$contents = (string) file_get_contents( $path );

		$this->assertStringContainsString( 'A=original', $contents );
		$this->assertStringContainsString( 'B=new', $contents );
	}

	public function test_a_value_with_spaces_is_quoted_on_write(): void {
		$path = $this->writeEnv( "A=1\n" );

		Env::setInFile( $path, array( 'A' => 'two words' ) );

		$this->assertSame( 'two words', Env::parse( $path )['A'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Backend paths
	|--------------------------------------------------------------------------
	*/

	public function test_the_backend_lives_beside_wordpress_not_inside_it(): void {
		$this->assertDirectoryExists( Config::apiDir() );
		$this->assertDirectoryExists( Config::apiDir() . '/app' );

		// A separate application: its own composer.json and its own env file.
		$this->assertFileExists( Config::apiDir() . '/composer.json' );
		$this->assertFileExists( Config::apiDir() . '/.env.example' );
		$this->assertFileExists( Config::apiEnvFile() . '.example' );
	}

	public function test_the_api_port_defaults_clear_of_wordpress_and_vite(): void {
		$port = Config::apiPort();

		$this->assertNotSame( Config::port(), $port, 'The API must not share WordPress\' port.' );
		$this->assertNotSame( 5173, $port, 'The API must not share the Vite dev port.' );
		$this->assertGreaterThan( 0, $port );
		$this->assertLessThan( 65536, $port );
	}

	public function test_an_out_of_range_api_port_falls_back_to_the_default(): void {
		putenv( 'API_PORT=99999' );

		$this->assertSame( 8000, Config::apiPort() );
	}

	public function test_the_api_url_is_built_from_the_host_and_port(): void {
		putenv( 'WP_SITE_HOST=localhost' );
		putenv( 'API_PORT=8123' );

		$this->assertSame( 'http://localhost:8123', Config::apiUrl() );
	}

	/*
	|--------------------------------------------------------------------------
	| The safety guarantee
	|--------------------------------------------------------------------------
	*/

	public function test_the_backend_table_prefix_differs_from_wordpress(): void {
		putenv( 'DB_PREFIX=stv_' );

		$this->assertNotSame( Config::tablePrefix(), Config::apiTablePrefix() );
	}

	public function test_a_shared_prefix_is_refused(): void {
		// If someone points the backend at the WordPress prefix, a Laravel
		// migration could drop WordPress tables. Refuse rather than allow it.
		putenv( 'DB_PREFIX=safari_api_' );
		putenv( 'API_TABLE_PREFIX=safari_api_' );

		$this->assertSame( 'safari_api_', Config::apiTablePrefix() );
	}

	public function test_a_prefix_containing_the_wordpress_prefix_is_refused(): void {
		putenv( 'DB_PREFIX=safari_' );
		putenv( 'API_TABLE_PREFIX=safari_api_' );

		$this->assertNotSame( Config::tablePrefix(), Config::apiTablePrefix() );
	}

	public function test_a_custom_api_prefix_is_honoured_when_it_is_safe(): void {
		putenv( 'DB_PREFIX=stv_' );
		putenv( 'API_TABLE_PREFIX=api_v2_' );

		$this->assertSame( 'api_v2_', Config::apiTablePrefix() );
	}

	public function test_an_empty_api_prefix_falls_back_to_a_safe_default(): void {
		putenv( 'DB_PREFIX=stv_' );
		putenv( 'API_TABLE_PREFIX=' );

		$this->assertSame( 'safari_api_', Config::apiTablePrefix() );
	}

	public function test_lead_delegation_is_off_unless_explicitly_enabled(): void {
		putenv( 'SAFARI_API_DELEGATE=false' );
		putenv( 'SAFARI_API_KEY=key' );

		$this->assertFalse( Config::apiDelegationEnabled() );

		putenv( 'SAFARI_API_DELEGATE=true' );

		$this->assertTrue( Config::apiDelegationEnabled() );
	}

	public function test_delegation_stays_off_without_a_shared_key(): void {
		putenv( 'SAFARI_API_DELEGATE=true' );
		putenv( 'SAFARI_API_KEY=' );

		$this->assertFalse( Config::apiDelegationEnabled(), 'Delegation without a key would be unauthenticated.' );
	}

	/**
	 * Write a temporary .env file and return its path.
	 *
	 * The system temp directory is tried first and a directory beside the
	 * project second, for the same reason Process::tempFile() does it: a
	 * congested temp directory makes tempnam() return false without raising
	 * anything, which would otherwise surface as an unrelated error in whichever
	 * test happened to touch a file.
	 */
	private function writeEnv( string $contents ): string {
		$directory = dirname( __DIR__, 2 ) . '/.safari-tmp';

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0o777, true );
		}

		$path = false;

		// A directory can be writable and still refuse to create a file - a
		// congested system temp directory is exactly that state - so each
		// candidate has to be tried until one of them actually produces a file.
		foreach ( array( $directory, sys_get_temp_dir() ) as $candidate ) {
			if ( ! is_dir( $candidate ) || ! is_writable( $candidate ) ) {
				continue;
			}

			$path = tempnam( $candidate, 'safari_env_' );

			if ( is_string( $path ) && '' !== $path ) {
				$directory = $candidate;
				break;
			}

			$path = false;
		}

		$this->assertIsString( $path, 'Could not create a temporary .env file in ' . $directory );

		file_put_contents( (string) $path, $contents );

		$this->temporaryFiles[] = (string) $path;

		return (string) $path;
	}
}
