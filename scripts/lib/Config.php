<?php
/**
 * Repository paths and derived environment settings.
 *
 * Single place that answers "where does X live" and "what does the env say",
 * so the setup CLI, the generated wp-config and the docs never drift apart.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

final class Config {

	/**
	 * WordPress content directories that are linked into wp-content.
	 *
	 * Key is the path inside wp-content, value the repository folder it points
	 * at. These mirror the bind mounts in docker-compose.yml exactly, so both
	 * environments load byte-identical code from a single source of truth.
	 *
	 * @var array<string,string>
	 */
	private const LINKS = array(
		'themes/safari-theme'  => 'theme',
		'plugins/safari-core'  => 'plugins/safari-core',
		'plugins/safari-leads' => 'plugins/safari-leads',
		'plugins/safari-search' => 'plugins/safari-search',
		'mu-plugins'           => 'mu-plugins',
	);

	/**
	 * Custom plugins activated on setup, in dependency order.
	 *
	 * @var string[]
	 */
	private const PLUGINS = array( 'safari-core', 'safari-leads', 'safari-search' );

	/**
	 * Repository root (this file lives in <root>/scripts/lib).
	 *
	 * @return string
	 */
	public static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Document root for the local web server — the WordPress core install.
	 *
	 * @return string
	 */
	public static function wpDir(): string {
		return self::root() . '/wordpress';
	}

	/**
	 * WordPress content directory.
	 *
	 * @return string
	 */
	public static function wpContentDir(): string {
		return self::wpDir() . '/wp-content';
	}

	/**
	 * Generated wp-config.php path.
	 *
	 * @return string
	 */
	public static function wpConfigFile(): string {
		return self::wpDir() . '/wp-config.php';
	}

	/**
	 * Generated salts file path.
	 *
	 * @return string
	 */
	public static function wpSaltsFile(): string {
		return self::wpDir() . '/wp-config-salts.php';
	}

	/**
	 * Repository-local WP-CLI phar path.
	 *
	 * @return string
	 */
	public static function wpCliPhar(): string {
		return self::root() . '/wp-cli.phar';
	}

	/**
	 * .env file path.
	 *
	 * @return string
	 */
	public static function envFile(): string {
		return self::root() . '/.env';
	}

	/**
	 * Link definitions, keyed by their path inside wp-content.
	 *
	 * @return array<string,string>
	 */
	public static function links(): array {
		return self::LINKS;
	}

	/**
	 * Custom plugin slugs.
	 *
	 * @return string[]
	 */
	public static function plugins(): array {
		return self::PLUGINS;
	}

	/**
	 * Theme slug.
	 *
	 * @return string
	 */
	public static function theme(): string {
		return 'safari-theme';
	}

	/**
	 * The local HTTP port.
	 *
	 * @return int
	 */
	public static function port(): int {
		$port = Env::int( 'WP_PORT', 8080 );

		return ( $port > 0 && $port < 65536 ) ? $port : 8080;
	}

	/**
	 * The local hostname.
	 *
	 * @return string
	 */
	public static function host(): string {
		$host = Env::str( 'WP_SITE_HOST', 'localhost' );

		return '' !== $host ? $host : 'localhost';
	}

	/**
	 * The public site URL.
	 *
	 * Derived from WP_SITE_URL when set, otherwise from WP_SITE_HOST + WP_PORT
	 * so a fresh clone works with nothing but a database password configured.
	 *
	 * @return string
	 */
	public static function siteUrl(): string {
		$url = Env::str( 'WP_SITE_URL', '' );

		if ( '' === $url ) {
			$url = 'http://' . self::host() . ':' . self::port();
		}

		return rtrim( $url, '/' );
	}

	/**
	 * Environment type: local, development, staging or production.
	 *
	 * @return string
	 */
	public static function environment(): string {
		$env = strtolower( Env::str( 'WP_ENV', 'local' ) );

		$allowed = array( 'local', 'development', 'staging', 'production' );

		return in_array( $env, $allowed, true ) ? $env : 'local';
	}

	/**
	 * Whether this is a production environment.
	 *
	 * @return bool
	 */
	public static function isProduction(): bool {
		return 'production' === self::environment();
	}

	/**
	 * Whether debug output is enabled.
	 *
	 * @return bool
	 */
	public static function debug(): bool {
		return Env::bool( 'WP_DEBUG', ! self::isProduction() );
	}

	/**
	 * Whether debug output may be rendered in HTML.
	 *
	 * Never on in production, even if someone sets WP_DEBUG_DISPLAY=true.
	 *
	 * @return bool
	 */
	public static function debugDisplay(): bool {
		return ! self::isProduction() && Env::bool( 'WP_DEBUG_DISPLAY', false );
	}

	/**
	 * WordPress table prefix.
	 *
	 * @return string
	 */
	public static function tablePrefix(): string {
		$prefix = Env::str( 'DB_PREFIX', 'stv_' );

		// WordPress requires [A-Za-z0-9_]; anything else would break every query.
		$prefix = preg_replace( '/[^A-Za-z0-9_]/', '', $prefix );

		return ( is_string( $prefix ) && '' !== $prefix ) ? $prefix : 'stv_';
	}

	/**
	 * Database host exactly as configured (may include `:port`).
	 *
	 * @return string
	 */
	public static function dbHost(): string {
		return Env::str( 'DB_HOST', '' );
	}

	/**
	 * Database host with the port split off.
	 *
	 * @return array{host:string,port:?int}
	 */
	public static function dbHostParts(): array {
		$host = self::dbHost();

		if ( '' === $host ) {
			return array(
				'host' => '',
				'port' => null,
			);
		}

		if ( substr_count( $host, ':' ) > 1 ) {
			// IPv6 literal, optionally with a port: [::1] or [::1]:3306.
			if ( preg_match( '/^\[(?P<host>[0-9a-fA-F:]+)\](?::(?P<port>\d+))?$/', $host, $m ) ) {
				return array(
					'host' => $m['host'],
					'port' => isset( $m['port'] ) ? (int) $m['port'] : null,
				);
			}

			return array(
				'host' => $host,
				'port' => null,
			);
		}

		if ( preg_match( '/^(?P<host>[^:]+)(?::(?P<port>\d+))?$/', $host, $m ) ) {
			return array(
				'host' => $m['host'],
				'port' => isset( $m['port'] ) ? (int) $m['port'] : null,
			);
		}

		return array(
			'host' => $host,
			'port' => null,
		);
	}

	/**
	 * Whether the database connection should use TLS.
	 *
	 * @return bool
	 */
	public static function dbSsl(): bool {
		return Env::bool( 'DB_SSL', false );
	}

	/**
	 * Whether the TLS server certificate must be verified.
	 *
	 * @return bool
	 */
	public static function dbSslVerify(): bool {
		return Env::bool( 'DB_SSL_VERIFY', true );
	}

	/**
	 * Path to a custom CA bundle for the database connection, if configured.
	 *
	 * @return string
	 */
	public static function dbSslCa(): string {
		return Env::str( 'DB_SSL_CA', '' );
	}

	/**
	 * Whether third-party WordPress plugins should be installed by Composer.
	 *
	 * @return bool
	 */
	public static function installWordPressPlugins(): bool {
		return Env::bool( 'WP_INSTALL_PLUGINS', true );
	}

	/**
	 * Whether the seeder should run as part of setup.
	 *
	 * @return bool
	 */
	public static function seedOnSetup(): bool {
		return Env::bool( 'WP_SEED_ON_SETUP', true );
	}

	/**
	 * Whether assets should be built during setup.
	 *
	 * @return bool
	 */
	public static function buildOnSetup(): bool {
		return Env::bool( 'WP_BUILD_ON_SETUP', true );
	}

	/**
	 * Content linking strategy: `link` (default) or `copy`.
	 *
	 * @return string
	 */
	public static function linkMode(): string {
		$mode = strtolower( Env::str( 'WP_CONTENT_LINK_MODE', 'link' ) );

		return 'copy' === $mode ? 'copy' : 'link';
	}

	/**
	 * Pinned WP-CLI version.
	 *
	 * @return string
	 */
	public static function wpCliVersion(): string {
		$version = Env::str( 'WP_CLI_VERSION', '2.12.0' );

		return ltrim( $version, 'v' );
	}

	/**
	 * Admin user for the first-run install.
	 *
	 * @return string
	 */
	public static function adminUser(): string {
		$user = Env::str( 'WP_ADMIN_USER', 'admin' );

		return preg_match( '/^[A-Za-z0-9 ._\-@]{1,60}$/', $user ) ? $user : 'admin';
	}

	/**
	 * Admin password for the first-run install.
	 *
	 * @return string
	 */
	public static function adminPassword(): string {
		return Env::str( 'WP_ADMIN_PASS', '' );
	}

	/**
	 * Admin email for the first-run install.
	 *
	 * @return string
	 */
	public static function adminEmail(): string {
		$email = Env::str( 'WP_ADMIN_EMAIL', '' );

		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : 'admin@localhost.test';
	}

	/**
	 * Site title for the first-run install.
	 *
	 * @return string
	 */
	public static function siteTitle(): string {
		return Env::str( 'WP_SITE_TITLE', 'Safari Travel' );
	}

	/**
	 * Environment variables that must be present before setup can continue.
	 *
	 * @return string[]
	 */
	public static function requiredEnvKeys(): array {
		return array( 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD' );
	}
}
