<?php
/**
 * Safari Travel — local development CLI.
 *
 * One cross-platform implementation of the whole local workflow, so Windows
 * (PowerShell), macOS and Linux (bash) all run identical logic. The thin
 * `scripts/setup-local.{ps1,sh}` and `scripts/start-local.{ps1,sh}` wrappers
 * exist purely as entry points.
 *
 * Usage:
 *   php scripts/safari.php setup            Full idempotent local setup
 *   php scripts/safari.php doctor           Environment diagnostics
 *   php scripts/safari.php config [--force] (Re)generate wp-config.php
 *   php scripts/safari.php link             (Re)link theme/plugins/mu-plugins
 *   php scripts/safari.php db-check         Test the Aiven connection
 *   php scripts/safari.php install          First-run WordPress install + activation
 *   php scripts/safari.php seed [--force]   Seed demo content
 *   php scripts/safari.php start [--port=N] Start the PHP dev server
 *   php scripts/safari.php stop             Stop a background dev server
 *   php scripts/safari.php wp <args…>       Run repo-local WP-CLI
 *   php scripts/safari.php reset            Drop all WordPress data and reinstall
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

use Throwable;

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "This script may only be run from the command line.\n" );
}

require_once __DIR__ . '/lib/Env.php';
require_once __DIR__ . '/lib/Console.php';
require_once __DIR__ . '/lib/Process.php';
require_once __DIR__ . '/lib/Config.php';
require_once __DIR__ . '/lib/ContentLinker.php';
require_once __DIR__ . '/lib/WpConfig.php';
require_once __DIR__ . '/lib/WpCli.php';
require_once __DIR__ . '/lib/Installer.php';

Env::load( Config::envFile() );

$argv    = $_SERVER['argv'] ?? array();
$command = $argv[1] ?? 'help';
$args    = array_slice( $argv, 2 );

try {
	exit( ( new Safari( $args ) )->run( $command ) );
} catch ( Throwable $e ) {
	Console::error( $e->getMessage() );
	if ( in_array( '--trace', $args, true ) ) {
		fwrite( STDERR, $e->getTraceAsString() . PHP_EOL );
	}
	exit( 1 );
}

/**
 * Command dispatcher.
 */
final class Safari {

	/**
	 * Positional arguments after the command name.
	 *
	 * @var string[]
	 */
	private array $args;

	/**
	 * Parsed `--key` / `--key=value` flags.
	 *
	 * @var array<string,string|bool>
	 */
	private array $flags = array();

	/**
	 * Positional (non-flag) arguments.
	 *
	 * @var string[]
	 */
	private array $positional = array();

	/**
	 * @param string[] $args Arguments after the command name.
	 */
	public function __construct( array $args ) {
		$this->args = $args;

		foreach ( $args as $arg ) {
			if ( 0 === strpos( $arg, '--' ) ) {
				$body = substr( $arg, 2 );
				$eq   = strpos( $body, '=' );

				if ( false === $eq ) {
					$this->flags[ $body ] = true;
				} else {
					$this->flags[ substr( $body, 0, $eq ) ] = substr( $body, $eq + 1 );
				}

				continue;
			}

			$this->positional[] = $arg;
		}
	}

	/**
	 * Dispatch a command.
	 *
	 * @param string $command Command name.
	 * @return int Exit code.
	 */
	public function run( string $command ): int {
		switch ( $command ) {
			case 'setup':
				return $this->setup();
			case 'doctor':
			case 'check':
				return $this->doctor();
			case 'config':
				return $this->config();
			case 'link':
				return $this->link();
			case 'db-check':
				return $this->dbCheck() ? 0 : 1;
			case 'install':
				return $this->install();
			case 'seed':
				return $this->seed();
			case 'start':
			case 'serve':
				return $this->start();
			case 'stop':
				return $this->stop();
			case 'wp':
				return $this->wp();
			case 'reset':
				return $this->reset();
			case 'help':
			case '--help':
			case '-h':
				$this->help();
				return 0;
			default:
				Console::error( 'Unknown command: ' . $command );
				$this->help();
				return 1;
		}
	}

	/* ---------------------------------------------------------------------
	 * Commands
	 * ------------------------------------------------------------------ */

	/**
	 * Full, idempotent local setup.
	 *
	 * @return int
	 */
	private function setup(): int {
		Console::banner( array( 'Safari Travel — local setup', '' ) );

		$missing = $this->assertEnv();

		$this->checkTooling( $missing );

		Console::step( '1/8  PHP dependencies (Composer + WordPress core)' );
		$this->composerInstall();

		Console::step( '2/8  Repository-local WP-CLI' );
		WpCli::ensureInstalled();

		Console::step( '3/8  WordPress configuration' );
		$result = WpConfig::ensure( isset( $this->flags['force-config'] ) );
		if ( $result['created'] ) {
			Console::success( 'Generated wordpress/wp-config.php' );
		} else {
			Console::skip( 'wordpress/wp-config.php already present (use --force-config to regenerate)' );
		}

		if ( $result['salts_created'] ) {
			Console::success( 'Generated wordpress/wp-config-salts.php' );
		}

		Console::step( '4/8  Theme, plugins and MU-plugins' );
		$links = ContentLinker::sync();
		Console::success(
			sprintf(
				'Content linked (%s mode): %d linked, %d refreshed, %d unchanged',
				$links['mode'],
				$links['linked'],
				$links['updated'],
				$links['skipped']
			)
		);

		Console::step( '5/8  Database connectivity' );
		$dbOk = $this->dbCheck( true );
		if ( ! $dbOk ) {
			return 1;
		}

		Console::step( '6/8  Frontend dependencies' );
		$this->npmInstall();

		Console::step( '7/8  WordPress install, activation and rewrite rules' );
		$installed = Installer::run( $this->flags );
		if ( ! $installed ) {
			return 1;
		}

		Console::step( '8/8  Theme assets' );
		$this->buildAssets();

		$this->summary();

		return 0;
	}

	/**
	 * Diagnostics.
	 *
	 * @return int
	 */
	private function doctor(): int {
		Console::banner( array( 'Safari Travel — environment check', '' ) );

		$problems = 0;

		$php = PHP_VERSION;
		$ok  = version_compare( $php, '8.2', '>=' );
		$problems += $ok ? 0 : 1;
		$this->line( 'PHP', $php . ( $ok ? '' : '  (8.2+ required)' ), $ok );

		foreach ( array(
			'mysqli'   => 'mysqli extension',
			'json'     => 'json extension',
			'curl'     => 'curl extension',
			'mbstring' => 'mbstring extension',
			'zip'      => 'zip extension',
			'openssl'  => 'openssl extension',
			'gd'       => 'gd extension',
		) as $ext => $label ) {
			$soft = in_array( $ext, array( 'zip', 'gd' ), true );
			$problems += $this->extLine( $ext, $label, $soft );
		}

		foreach ( array(
			'composer' => 'Composer',
			'node'     => 'Node.js',
			'npm'      => 'npm',
			'git'      => 'Git',
		) as $bin => $label ) {
			$found = Process::which( $bin );
			$problems += $found ? 0 : 1;
			$this->line( $label, $found ? $this->versionOf( $bin ) : 'not found', $found );
		}

		$this->line( 'Web server', 'PHP built-in server (development only)', true );

		Console::step( 'Environment' );
		$envFile = Config::envFile();
		$hasEnv  = is_file( $envFile );
		$this->line( '.env', $hasEnv ? $envFile : 'missing — copy .env.example to .env', $hasEnv );
		$problems += $hasEnv ? 0 : 1;

		foreach ( Config::requiredEnvKeys() as $key ) {
			$value = Env::str( $key, '' );
			$set   = '' !== $value;
			$problems += $set ? 0 : 1;

			if ( 'DB_PASSWORD' === $key ) {
				$shown = $set ? str_repeat( '*', min( 8, strlen( $value ) ) ) : '(empty)';
			} else {
				$shown = $set ? $value : '(empty)';
			}

			$this->line( $key, $shown, $set );
		}

		$parts = Config::dbHostParts();
		$this->line(
			'DB_HOST parsed',
			sprintf( 'host=%s port=%s', $parts['host'] ?: '?', $parts['port'] ?: 'default (3306)' ),
			'' !== $parts['host']
		);
		$this->line( 'DB_SSL', Config::dbSsl() ? 'enabled' : 'disabled', true );
		$this->line( 'DB_SSL_VERIFY', Config::dbSslVerify() ? 'on' : 'off', true );
		$this->line( 'DB_SSL_CA', Config::dbSslCa() ?: '(system trust store)', true );
		$this->line( 'DB_PREFIX', Config::tablePrefix(), true );
		$this->line( 'WP_ENV', Config::environment(), true );
		$this->line( 'WP_DEBUG', Config::debug() ? 'on' : 'off', true );
		$this->line( 'Site URL', Config::siteUrl(), true );

		if ( Config::isProduction() && Config::debug() ) {
			Console::error( 'WP_DEBUG is on while WP_ENV=production. This combination is refused at runtime.' );
			++$problems;
		}

		Console::step( 'WordPress' );
		$core = Config::wpDir() . '/wp-includes/version.php';
		if ( is_file( $core ) ) {
			$version = self::coreVersion();
			$target  = self::pinnedCoreVersion();
			$match   = ( $version === $target );
			$problems += $match ? 0 : 1;
			$this->line( 'WordPress core', $version . ( $match ? '' : '  (composer.json pins ' . $target . ')' ), $match );
		} else {
			$this->line( 'WordPress core', 'not installed — run composer install', false );
			++$problems;
		}

		$config = Config::wpConfigFile();
		$this->line( 'wp-config.php', is_file( $config ) ? $config : 'missing — run: php scripts/safari.php config', is_file( $config ) );

		foreach ( Config::links() as $relative => $source ) {
			$link  = Config::wpContentDir() . '/' . $relative;
			$state = ContentLinker::state( $link, Config::root() . '/' . $source );
			$this->line( $relative, $state, in_array( $state, array( 'link', 'copy-fresh' ), true ) );
		}

		$this->line(
			'wp-cli.phar',
			is_file( Config::wpCliPhar() ) ? 'present' : 'missing — run: php scripts/safari.php wp -- --info',
			is_file( Config::wpCliPhar() )
		);

		Console::step( 'Database' );
		$dbOk = $this->dbCheck( true );

		Console::write();
		if ( 0 === $problems && $dbOk ) {
			Console::success( 'Everything checks out. Run: php scripts/safari.php start' );
			return 0;
		}

		Console::error( sprintf( '%d problem(s) found. Fix the items above, then re-run setup.', $problems ) );
		if ( ! $dbOk ) {
			Console::error( 'Database is unreachable — see the message above.' );
		}

		return 1;
	}

	/**
	 * Regenerate wp-config.php.
	 *
	 * @return int
	 */
	private function config(): int {
		$this->assertEnv();

		if ( ! is_file( Config::wpDir() . '/wp-includes/version.php' ) ) {
			Console::warn( 'WordPress core is not installed yet — run composer install first.' );
		}

		$result = WpConfig::ensure( isset( $this->flags['force'] ) );

		Console::success( $result['created'] ? 'Wrote ' . $result['config'] : $result['config'] . ' left untouched (pass --force to rewrite)' );

		if ( $result['salts_created'] ) {
			Console::success( 'Generated wordpress/wp-config-salts.php' );
		} else {
			Console::skip( 'Salts preserved (delete wordpress/wp-config-salts.php to rotate)' );
		}

		return 0;
	}

	/**
	 * (Re)link content directories.
	 *
	 * @return int
	 */
	private function link(): int {
		$force = isset( $this->flags['force'] );
		$links = ContentLinker::sync( $force );

		Console::success(
			sprintf(
				'%d linked, %d refreshed, %d unchanged (%s mode)',
				$links['linked'],
				$links['updated'],
				$links['skipped'],
				$links['mode']
			)
		);

		return 0;
	}

	/**
	 * Test the database connection using the same settings WordPress will use.
	 *
	 * @param bool $quiet Suppress the per-step chatter.
	 * @return bool
	 */
	private function dbCheck( bool $quiet = false ): bool {
		$missing = array();
		foreach ( Config::requiredEnvKeys() as $key ) {
			if ( '' === Env::str( $key, '' ) ) {
				$missing[] = $key;
			}
		}

		if ( $missing ) {
			Console::error( 'Missing in .env: ' . implode( ', ', $missing ) . '. Copy .env.example to .env and fill in your Aiven MySQL details.' );
			return false;
		}

		$parts = Config::dbHostParts();
		$port  = $parts['port'] ?? 3306;

		if ( ! extension_loaded( 'mysqli' ) ) {
			Console::error( 'The mysqli PHP extension is required to reach MySQL.' );
			return false;
		}

		mysqli_report( MYSQLI_REPORT_OFF );
		$link = @mysqli_init();

		$flags = 0;
		if ( Config::dbSsl() ) {
			$flags |= MYSQLI_CLIENT_SSL;
			if ( ! Config::dbSslVerify() ) {
				$flags |= MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
			}
		}

		$timeout = $this->flagInt( 'db-timeout', 8 );
		@mysqli_options( $link, MYSQLI_OPT_CONNECT_TIMEOUT, $timeout );

		$name = Env::str( 'DB_NAME', '' );

		$started = microtime( true );
		$ok      = @mysqli_real_connect(
			$link,
			$parts['host'],
			Env::str( 'DB_USER', '' ),
			(string) Env::get( 'DB_PASSWORD', '' ),
			$name,
			(int) $port,
			null,
			$flags
		);
		$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( ! $ok ) {
			$error = mysqli_connect_error();

			Console::error( sprintf( 'Cannot reach %s:%d — %s', $parts['host'], $port, $error ) );
			$this->explainDbError( (string) $error );

			return false;
		}

		$version = (string) @mysqli_get_server_info( $link );

		if ( ! $quiet ) {
			Console::success(
				sprintf(
					'Connected to %s:%d as %s in %d ms (server %s, TLS %s)',
					$parts['host'],
					$port,
					$name,
					$elapsed,
					$version,
					Config::dbSsl() ? ( Config::dbSslVerify() ? 'on, verified' : 'on, unverified' ) : 'off'
				)
			);
		}

		@mysqli_close( $link );

		return true;
	}

	/**
	 * Run the WordPress install / activation / rewrite routine.
	 *
	 * @return int
	 */
	private function install(): int {
		$this->assertEnv();

		if ( ! is_file( Config::wpConfigFile() ) ) {
			WpConfig::ensure();
		}

		return Installer::run( $this->flags ) ? 0 : 1;
	}

	/**
	 * Seed demo content.
	 *
	 * @return int
	 */
	private function seed(): int {
		$args = array( 'safari', 'seed' );

		if ( isset( $this->flags['force'] ) ) {
			$args[] = '--force';
		}

		if ( isset( $this->flags['no-images'] ) ) {
			$args[] = '--no-images';
		}

		if ( isset( $this->flags['posts-per-destination'] ) && is_string( $this->flags['posts-per-destination'] ) ) {
			$args[] = '--posts-per-destination=' . $this->flags['posts-per-destination'];
		}

		$code = WpCli::run( $args );

		if ( 0 !== $code ) {
			Console::error( 'Seeding failed. If WordPress is not installed yet, run: php scripts/safari.php install' );
		}

		return $code;
	}

	/**
	 * Start the PHP built-in web server.
	 *
	 * @return int
	 */
	private function start(): int {
		$port = $this->flagInt( 'port', Config::port() );
		$doc  = Config::wpDir();

		if ( ! is_file( $doc . '/wp-settings.php' ) ) {
			Console::error( 'WordPress core is missing. Run: php scripts/safari.php setup' );
			return 1;
		}

		if ( ! is_file( $doc . '/wp-config.php' ) ) {
			Console::error( 'wordpress/wp-config.php is missing. Run: php scripts/safari.php config' );
			return 1;
		}

		if ( Config::isProduction() ) {
			Console::error( 'Refusing to start the PHP built-in server with WP_ENV=production. Use Nginx or Apache.' );
			return 1;
		}

		$busy = $this->portInUse( '127.0.0.1', $port );
		if ( $busy ) {
			Console::error( sprintf( 'Port %d is already in use. Stop the other process or pass --port=<other>.', $port ) );
			return 1;
		}

		$host = Config::host();
		$bind = ( 'localhost' === $host ) ? '127.0.0.1' : $host;

		Console::banner(
			array(
				'Safari Travel — local server',
				'',
				'Site   : http://' . $host . ':' . $port,
				'Admin  : http://' . $host . ':' . $port . '/wp-admin',
				'Assets : ' . ( is_file( $doc . '/wp-content/themes/safari-theme/.vite-dev' ) ? 'Vite dev server' : 'built assets' ),
				'',
				'Development only. Press Ctrl+C to stop.',
			)
		);

		$env = array(
			'WP_ENV'   => Config::environment(),
			'WP_DEBUG' => Config::debug() ? 'true' : 'false',
			'WP_PORT'  => (string) $port,
		);

		$code = Process::run(
			array(
				self::phpBinary(),
				'-d',
				'memory_limit=512M',
				'-d',
				'max_execution_time=0',
				'-S',
				$bind . ':' . $port,
				'-t',
				$doc,
				Config::root() . '/scripts/router.php',
			),
			$env,
			true,
			Config::root()
		);

		Console::write();
		Console::skip( 'Server stopped.' );

		return 0 === $code ? 0 : $code;
	}

	/**
	 * Placeholder stop command: the server runs in the foreground by design.
	 *
	 * @return int
	 */
	private function stop(): int {
		$pidFile = Config::wpDir() . '/.safari-server.pid';

		if ( ! is_file( $pidFile ) ) {
			Console::info( 'No background server recorded. The dev server runs in the foreground — press Ctrl+C.' );
			return 0;
		}

		$pid = (int) trim( (string) file_get_contents( $pidFile ) );
		@unlink( $pidFile );

		if ( $pid <= 0 ) {
			return 0;
		}

		if ( Console::isWindows() ) {
			Process::run( array( 'taskkill', '/PID', (string) $pid, '/T', '/F' ) );
		} else {
			Process::run( array( 'kill', (string) $pid ) );
		}

		Console::success( 'Stopped background server (pid ' . $pid . ').' );

		return 0;
	}

	/**
	 * Proxy arbitrary arguments to the repository-local WP-CLI.
	 *
	 * @return int
	 */
	private function wp(): int {
		return WpCli::run( $this->args );
	}

	/**
	 * Destructive: drop every WordPress table, then reinstall from scratch.
	 *
	 * @return int
	 */
	private function reset(): int {
		$this->assertEnv();

		if ( ! isset( $this->flags['yes'] ) ) {
			Console::banner(
				array(
					'This will permanently delete all data in the configured',
					'database — every WordPress table, all posts, all leads, and',
					'media references. The database itself is NOT dropped.',
					'',
					'Database : ' . Config::dbHost() . '/' . Env::str( 'DB_NAME', '' ),
					'',
					'Re-run with --yes to confirm.',
				)
			);

			return 1;
		}

		$prefix = Config::tablePrefix();
		$name   = Env::str( 'DB_NAME', '' );

		$sql = '';
		foreach ( Installer::tableNames( $prefix ) as $table ) {
			$sql .= 'DROP TABLE IF EXISTS `' . $table . '`; ';
		}

		$parts = Config::dbHostParts();

		$result = Process::capture(
			array(
				self::mysqlBinary(),
				'--host=' . $parts['host'],
				'--port=' . (string) ( $parts['port'] ?? 3306 ),
				'--user=' . Env::str( 'DB_USER', '' ),
				'--password=' . (string) Env::get( 'DB_PASSWORD', '' ),
				'--database=' . $name,
				'--execute=' . $sql,
			)
		);

		if ( 0 !== $result['code'] ) {
			Console::error( 'Failed to drop tables: ' . trim( $result['err'] . ' ' . $result['out'] ) );
			return 1;
		}

		Console::success( 'All WordPress tables dropped.' );

		return $this->install();
	}

	/* ---------------------------------------------------------------------
	 * Shared helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Verify the required environment variables are present.
	 *
	 * @return string[] Missing keys (already reported).
	 */
	private function assertEnv(): array {
		$missing = array();

		foreach ( Config::requiredEnvKeys() as $key ) {
			if ( '' === Env::str( $key, '' ) ) {
				$missing[] = $key;
			}
		}

		if ( $missing ) {
			Console::error( 'Missing in .env: ' . implode( ', ', $missing ) );
			Console::write( '  Copy .env.example to .env, then paste the Aiven MySQL values:' );
			Console::write( '    DB_HOST     host:port  (Aiven service "Host & Port" column)' );
			Console::write( '    DB_NAME     database name (Aiven default: defaultdb)' );
			Console::write( '    DB_USER     username   (Aiven default: avnadmin)' );
			Console::write( '    DB_PASSWORD password   (Aiven → Service credentials → Reset/Show password)' );
			Console::write( '    DB_SSL      true' );
			Console::write( '' );
		}

		return $missing;
	}

	/**
	 * Check the tooling setup depends on.
	 *
	 * @param string[] $missing Missing env keys.
	 * @return void
	 */
	private function checkTooling( array $missing ): void {
		Console::step( '0/8  Tooling' );

		$this->line( 'PHP', PHP_VERSION, version_compare( PHP_VERSION, '8.2', '>=' ) );

		foreach ( array( 'mysqli', 'json', 'curl' ) as $ext ) {
			$this->extLine( $ext, $ext . ' extension' );
		}

		$this->line( 'Composer', Process::which( 'composer' ) ? $this->versionOf( 'composer' ) : 'not found', Process::which( 'composer' ) );
		$this->line( 'Node.js', Process::which( 'node' ) ? $this->versionOf( 'node' ) : 'not found', Process::which( 'node' ) );
		$this->line( 'npm', Process::which( 'npm' ) ? $this->versionOf( 'npm' ) : 'not found', Process::which( 'npm' ) );

		if ( ! Process::which( 'composer' ) ) {
			Console::error( 'Composer is required: https://getcomposer.org/download/' );
			exit( 1 );
		}

		if ( $missing ) {
			Console::error( 'Cannot continue without database credentials. See the message above.' );
			exit( 1 );
		}
	}

	/**
	 * Install Composer dependencies (includes the pinned WordPress core).
	 *
	 * @return void
	 */
	private function composerInstall(): void {
		$vendor = Config::root() . '/vendor/autoload.php';

		if ( is_file( $vendor ) && is_file( Config::wpDir() . '/wp-settings.php' ) && ! $this->composerLockChanged() ) {
			Console::skip( 'Composer dependencies up to date' );
			return;
		}

		$code = Process::run( array( 'composer', 'install', '--no-interaction', '--no-progress' ), array(), true, Config::root() );

		if ( 0 !== $code ) {
			Console::error( 'composer install failed.' );
			exit( 1 );
		}

		Console::success( 'Composer dependencies installed (WordPress core included)' );
	}

	/**
	 * Whether composer.lock is newer than the installed autoloader.
	 *
	 * @return bool
	 */
	private function composerLockChanged(): bool {
		$lock = Config::root() . '/composer.lock';
		$auto = Config::root() . '/vendor/autoload.php';

		if ( ! is_file( $lock ) || ! is_file( $auto ) ) {
			return true;
		}

		return filemtime( $lock ) > filemtime( $auto );
	}

	/**
	 * Install npm dependencies.
	 *
	 * @return void
	 */
	private function npmInstall(): void {
		if ( ! Process::which( 'npm' ) ) {
			Console::warn( 'npm not found — skipping frontend dependencies. Install Node.js 18+ to build assets.' );
			return;
		}

		$modules = Config::root() . '/node_modules';
		$lock    = Config::root() . '/package-lock.json';

		if ( is_dir( $modules ) && is_file( $lock ) && filemtime( $modules ) >= filemtime( $lock ) ) {
			Console::skip( 'npm dependencies up to date' );
			return;
		}

		$code = Process::run( array( 'npm', 'ci' ), array(), true, Config::root() );

		if ( 0 !== $code ) {
			// package-lock.json is occasionally out of sync with package.json.
			Console::warn( 'npm ci failed — retrying with npm install' );
			$code = Process::run( array( 'npm', 'install' ), array(), true, Config::root() );
		}

		if ( 0 !== $code ) {
			Console::error( 'npm install failed.' );
			exit( 1 );
		}

		Console::success( 'npm dependencies installed' );
	}

	/**
	 * Build production assets.
	 *
	 * @return void
	 */
	private function buildAssets(): void {
		if ( ! Config::buildOnSetup() ) {
			Console::skip( 'Asset build skipped (WP_BUILD_ON_SETUP=false)' );
			return;
		}

		if ( ! Process::which( 'npm' ) ) {
			Console::warn( 'npm not found — run `npm ci && npm run build` manually.' );
			return;
		}

		$code = Process::run( array( 'npm', 'run', 'build' ), array(), true, Config::root() );

		if ( 0 !== $code ) {
			Console::error( 'npm run build failed.' );
			exit( 1 );
		}

		Console::success( 'Theme assets built into theme/assets/dist' );
	}

	/**
	 * Print the closing summary.
	 *
	 * @return void
	 */
	private function summary(): void {
		$url     = Config::siteUrl();
		$plugins = array();

		foreach ( Config::plugins() as $slug ) {
			$state = WpCli::capture( array( 'plugin', 'is-active', $slug ) );
			if ( 0 === $state['code'] ) {
				$plugins[] = $slug;
			}
		}

		$theme = WpCli::capture( array( 'theme', 'list', '--status=active', '--format=json' ) );
		$name  = 'unknown';

		if ( 0 === $theme['code'] ) {
			$decoded = json_decode( $theme['out'], true );
			if ( is_array( $decoded ) && isset( $decoded[0]['name'] ) ) {
				$name = (string) $decoded[0]['name'];
			}
		}

		Console::banner(
			array(
				'Setup complete.',
				'',
				'Site        : ' . $url,
				'Admin       : ' . $url . '/wp-admin',
				'Theme       : ' . $theme,
				'Plugins     : ' . ( $plugins ? implode( ', ', $plugins ) : 'none active' ),
				'',
				$this->adminHint(),
				'',
				'Next:  php scripts/safari.php start     (or .\\scripts\\start-local.ps1)',
				'       npm run dev                       (in a second terminal)',
			)
		);
	}

	/**
	 * Describe where the admin password came from, without printing it.
	 *
	 * @return string
	 */
	private function adminHint(): string {
		$pass = Config::adminPassword();

		if ( '' === $pass ) {
			return 'Admin user: ' . Config::adminUser() . '  (password was generated; see .env for WP_ADMIN_PASS)';
		}

		return 'Admin user: ' . Config::adminUser() . '  (password from .env WP_ADMIN_PASS)';
	}

	/**
	 * Turn a MySQL error into something actionable.
	 *
	 * @param string $error Raw driver error.
	 * @return void
	 */
	private function explainDbError( string $error ): void {
		$lower = strtolower( $error );

		$hints = array(
			'access denied'  => 'Wrong DB_USER / DB_PASSWORD, or the Aiven user is not granted access to this database.',
			'unknown database' => 'DB_NAME does not exist. Aiven services normally use "defaultdb".',
			'getaddrinfo'    => 'DB_HOST could not be resolved. Use the Aiven hostname exactly as shown, without https:// and without a trailing slash.',
			'connection refused' => 'Wrong port, or the Aiven service is paused. Copy the host:port pair from Aiven.',
			'unknown ca'     => 'The server certificate was not trusted. Download the Aiven CA certificate and set DB_SSL_CA to its path, or set DB_SSL_VERIFY=false for local work only.',
			'ssl'            => 'TLS negotiation failed. Aiven requires DB_SSL=true; check that the port matches the Aiven MySQL (not PostgreSQL) service port.',
			'timed out'      => 'The connection timed out. Check your network/VPN and that outbound TCP to the Aiven port is allowed.',
		);

		foreach ( $hints as $needle => $hint ) {
			if ( false !== strpos( $lower, $needle ) ) {
				Console::write( '  Hint: ' . $hint );
				return;
			}
		}
	}

	/**
	 * Print one diagnostic row.
	 *
	 * @param string $label    Row label.
	 * @param string $value    Value.
	 * @param bool   $ok       Success flag.
	 * @param bool   $optional Whether a "false" is acceptable.
	 * @return void
	 */
	private function line( string $label, string $value, bool $ok = true, bool $optional = false ): void {
		Console::write( Console::mark( $ok, $optional ) . str_pad( $label, 26 ) . $value );
	}

	/**
	 * Print a diagnostic row for a required PHP extension, with remediation.
	 *
	 * @param string $ext     Extension name.
	 * @param string $label   Row label.
	 * @param bool   $optional Whether a missing extension is only a warning.
	 * @return int 1 when the extension is missing and required.
	 */
	private function extLine( string $ext, string $label, bool $optional = false ): int {
		$has = extension_loaded( $ext );

		$this->line( $label, $has ? 'loaded' : ( $optional ? 'missing (optional)' : 'MISSING' ), $has, $optional );

		if ( $has || $optional ) {
			return 0;
		}

		Console::write( '         ' . $this->extensionHint( $ext ) );

		return 1;
	}

	/**
	 * Platform-specific instructions for enabling a missing PHP extension.
	 *
	 * @param string $ext Extension name.
	 * @return string
	 */
	private function extensionHint( string $ext ): string {
		if ( Console::isWindows() ) {
			$loaded = (string) ( php_ini_loaded_file() ?: 'php.ini' );
			$dir    = (string) ini_get( 'extension_dir' );

			$exists = ( '' !== $dir && is_file( rtrim( $dir, '/\\' ) . '\\php_' . $ext . '.dll' ) )
				|| ( '' !== $dir && is_file( rtrim( $dir, '/\\' ) . '/' . $ext . '.so' ) );

			if ( $exists ) {
				return sprintf(
					'php_%s ships with your PHP build but is commented out in %s — add "extension=%s" under [PHP] and reopen the terminal.',
					$ext,
					$loaded,
					$ext
				);
			}

			return sprintf( 'Install a PHP build that includes %s (windows.php.net/download/), or use a distribution package such as XAMPP.', $ext );
		}

		return sprintf( 'sudo apt install php%s-%s   (Debian/Ubuntu)', PHP_MAJOR_VERSION . PHP_MINOR_VERSION, $ext );
	}

	/**
	 * Read a numeric flag.
	 *
	 * @param string $name    Flag name.
	 * @param int    $default Fallback.
	 * @return int
	 */
	private function flagInt( string $name, int $default ): int {
		$value = $this->flags[ $name ] ?? null;

		return ( is_string( $value ) && is_numeric( $value ) ) ? (int) $value : $default;
	}

	/**
	 * Ask a tool for its version, best effort.
	 *
	 * @param string $bin Executable.
	 * @return string
	 */
	private function versionOf( string $bin ): string {
		$probe = match ( $bin ) {
			'composer' => array( 'composer', '--version', '--no-ansi' ),
			'node'     => array( 'node', '--version' ),
			'npm'      => array( 'npm', '--version' ),
			'git'      => array( 'git', '--version' ),
			default    => array( $bin, '--version' ),
		};

		$result = Process::capture( $probe );
		$out    = trim( $result['out'] . ' ' . $result['err'] );
		$out    = preg_replace( '/\s+/', ' ', $out );

		return is_string( $out ) ? substr( $out, 0, 60 ) : '';
	}

	/**
	 * Whether a TCP port already has a listener.
	 *
	 * @param string $host Host.
	 * @param int    $port Port.
	 * @return bool
	 */
	private function portInUse( string $host, int $port ): bool {
		$socket = @fsockopen( $host, $port, $errno, $errstr, 1.0 );

		if ( is_resource( $socket ) ) {
			fclose( $socket );
			return true;
		}

		return false;
	}

	/**
	 * PHP binary that is running this script.
	 *
	 * @return string
	 */
	private static function phpBinary(): string {
		return ( '' !== PHP_BINARY ) ? PHP_BINARY : 'php';
	}

	/**
	 * Locate a mysql client for `reset`, if one is installed.
	 *
	 * @return string
	 */
	private static function mysqlBinary(): string {
		foreach ( array( 'mysql', 'mariadb' ) as $bin ) {
			$path = Process::locate( $bin );

			if ( null !== $path ) {
				return $path;
			}
		}

		Console::error( 'The `reset` command needs a mysql client (mysql or mariadb) on PATH.' );
		Console::error( 'Alternative: drop the tables from the Aiven console, then run: php scripts/safari.php install' );
		exit( 1 );
	}

	/**
	 * WordPress version currently installed in wordpress/.
	 *
	 * @return string
	 */
	private static function coreVersion(): string {
		$file = Config::wpDir() . '/wp-includes/version.php';

		if ( ! is_file( $file ) ) {
			return 'unknown';
		}

		$contents = (string) file_get_contents( $file );

		return preg_match( "/\\\$wp_version\s*=\s*'([^']+)'/", $contents, $m ) ? (string) $m[1] : 'unknown';
	}

	/**
	 * WordPress version pinned in composer.json.
	 *
	 * @return string
	 */
	private static function pinnedCoreVersion(): string {
		$file = Config::root() . '/composer.json';

		if ( ! is_file( $file ) ) {
			return 'unknown';
		}

		$json = json_decode( (string) file_get_contents( $file ), true );

		$constraint = ( is_array( $json ) && isset( $json['require']['roots/wordpress-no-content'] ) )
			? (string) $json['require']['roots/wordpress-no-content']
			: '';

		return ltrim( $constraint, 'v^~>=< ');
	}

	/**
	 * Print usage.
	 *
	 * @return void
	 */
	private function help(): void {
		Console::write( <<<TXT
Safari Travel local development CLI

  php scripts/safari.php setup             One-shot, idempotent local setup
  php scripts/safari.php doctor            Environment diagnostics
  php scripts/safari.php config [--force]  (Re)generate wordpress/wp-config.php
  php scripts/safari.php link [--force]    (Re)link theme/plugins/mu-plugins
  php scripts/safari.php db-check          Test the Aiven MySQL connection
  php scripts/safari.php install           First-run WordPress install + activation
  php scripts/safari.php seed [--force]    Seed demo content (idempotent)
  php scripts/safari.php start [--port=N]  Start the PHP dev server on 127.0.0.1
  php scripts/safari.php stop              Stop a recorded background server
  php scripts/safari.php wp <args...>      Run the repository-local WP-CLI
  php scripts/safari.php reset --yes       Drop all WordPress tables, then reinstall

TXT );
	}
}
