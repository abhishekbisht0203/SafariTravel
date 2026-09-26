<?php
/**
 * First-run WordPress installation and activation.
 *
 * Every step is guarded so re-running setup is a no-op rather than a data-loss
 * event: an existing install is never re-installed and never re-configured.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

final class Installer {

	/**
	 * Run the full install routine.
	 *
	 * @param array<string,string|bool> $flags CLI flags.
	 * @return bool
	 */
	public static function run( array $flags = array() ): bool {
		if ( ! is_file( Config::wpDir() . '/wp-settings.php' ) ) {
			Console::error( 'WordPress core is missing. Run: composer install' );
			return false;
		}

		WpCli::ensureInstalled( true );

		if ( ! is_file( Config::wpCliPhar() ) ) {
			Console::warn( 'WP-CLI unavailable — skipping install and activation.' );
			return true;
		}

		if ( ! self::ensureInstalled() ) {
			return false;
		}

		self::activateTheme();
		self::activatePlugins();
		self::applyPermalinks();

		if ( Config::seedOnSetup() && ! isset( $flags['no-seed'] ) ) {
			self::seed( isset( $flags['force-seed'] ) );
		}

		WpCli::run( array( 'cache', 'flush' ), false );

		return true;
	}

	/**
	 * Install WordPress when it is not installed yet.
	 *
	 * @return bool
	 */
	public static function ensureInstalled(): bool {
		$check = WpCli::capture( array( 'core', 'is-installed' ) );

		if ( 0 === $check['code'] ) {
			Console::skip( 'WordPress is already installed — not touching existing content' );
			self::ensureSiteUrls();
			return true;
		}

		$user     = Config::adminUser();
		$password = Config::adminPassword();
		$email    = Config::adminEmail();
		$title    = Config::siteTitle();
		$url      = Config::siteUrl();

		if ( '' === $password ) {
			$password = self::generatePassword();
			// Recorded so the developer can actually log in.
			self::appendToEnv( 'WP_ADMIN_PASS', $password );
			Console::success( 'Generated a random WP_ADMIN_PASS and appended it to .env' );
		}

		$args = array(
			'core',
			'install',
			'--url=' . $url,
			'--title=' . $title,
			'--admin_user=' . $user,
			'--admin_password=' . $password,
			'--admin_email=' . $email,
			'--skip-email',
		);

		Console::info( 'Installing WordPress…' );

		$result = WpCli::capture( $args );

		if ( 0 !== $result['code'] ) {
			Console::error( 'WordPress install failed.' );
			Console::write( trim( $result['err'] . PHP_EOL . $result['out'] ) );
			self::explainInstallFailure( $result['err'] . $result['out'] );
			return false;
		}

		Console::success( 'WordPress installed at ' . $url );

		return true;
	}

	/**
	 * Align the stored site URL with the configured one.
	 *
	 * Only ever *adds* the values; an existing production URL is never
	 * overwritten silently, it is reported instead.
	 *
	 * @return void
	 */
	private static function ensureSiteUrls(): void {
		$configured = Config::siteUrl();

		$result = WpCli::capture( array( 'option', 'get', 'siteurl' ) );
		$current = ( 0 === $result['code'] ) ? trim( $result['out'] ) : '';

		if ( '' !== $current && rtrim( $current, '/' ) !== rtrim( $configured, '/' ) ) {
			Console::warn(
				sprintf(
					'Database siteurl is %s but .env says %s. Fix the database value, or set WP_SITE_URL to match, before browsing.',
					$current,
					$configured
				)
			);
		}
	}

	/**
	 * Activate the custom theme when it is available.
	 *
	 * @return void
	 */
	private static function activateTheme(): void {
		$theme = Config::theme();

		$check = WpCli::capture( array( 'theme', 'is-installed', $theme ) );

		if ( 0 !== $check['code'] ) {
			Console::error( 'Theme "' . $theme . '" was not found in wp-content/themes. Run: php scripts/safari.php link' );
			return;
		}

		$active = WpCli::capture( array( 'theme', 'list', '--status=active', '--field=name' ) );
		$name   = ( 0 === $active['code'] ) ? trim( $active['out'] ) : '';

		if ( $name === $theme ) {
			Console::skip( 'Theme already active: ' . $theme );
			return;
		}

		$result = WpCli::capture( array( 'theme', 'activate', $theme ) );

		if ( 0 === $result['code'] ) {
			Console::success( 'Activated theme: ' . $theme );
		} else {
			Console::error( 'Could not activate theme ' . $theme . ': ' . trim( $result['err'] . ' ' . $result['out'] ) );
		}
	}

	/**
	 * Activate every custom plugin that is present.
	 *
	 * @return void
	 */
	private static function activatePlugins(): void {
		foreach ( Config::plugins() as $slug ) {
			$isInstalled = WpCli::capture( array( 'plugin', 'is-installed', $slug ) );

			if ( 0 !== $isInstalled['code'] ) {
				Console::skip( sprintf( 'Plugin %s is not present in wp-content/plugins — skipped', $slug ) );
				continue;
			}

			$isActive = WpCli::capture( array( 'plugin', 'is-active', $slug ) );

			if ( 0 === $isActive['code'] ) {
				Console::skip( 'Plugin already active: ' . $slug );
				continue;
			}

			$result = WpCli::capture( array( 'plugin', 'activate', $slug ) );

			if ( 0 === $result['code'] ) {
				Console::success( 'Activated plugin: ' . $slug );
			} else {
				Console::error( 'Could not activate ' . $slug . ': ' . trim( $result['err'] . ' ' . $result['out'] ) );
			}
		}
	}

	/**
	 * Use pretty permalinks — the theme's archive routes depend on them.
	 *
	 * @return void
	 */
	private static function applyPermalinks(): void {
		$current = WpCli::capture( array( 'option', 'get', 'permalink_structure' ) );
		$value   = ( 0 === $current['code'] ) ? trim( $current['out'] ) : '';

		if ( '' === $value ) {
			WpCli::run( array( 'rewrite', 'structure', '/%postname%/' ), false );
			Console::success( 'Pretty permalinks enabled' );
		} else {
			Console::skip( 'Permalink structure already set: ' . $value );
		}

		WpCli::run( array( 'rewrite', 'flush', '--hard' ), false );
	}

	/**
	 * Run the demo-content seeder.
	 *
	 * @param bool $force Delete previously seeded content first.
	 * @return void
	 */
	public static function seed( bool $force = false ): void {
		$available = WpCli::capture( array( 'safari', 'seed', '--help' ) );

		if ( 0 !== $available['code'] ) {
			Console::warn( 'The `safari seed` command is unavailable (safari-core did not load). Skipping demo content.' );
			return;
		}

		$args = array( 'safari', 'seed' );

		if ( $force ) {
			$args[] = '--force';
		}

		if ( Env::bool( 'WP_SEED_NO_IMAGES', false ) ) {
			$args[] = '--no-images';
		}

		Console::info( 'Seeding demo content (this downloads images on first run)…' );
		WpCli::run( $args );
	}

	/**
	 * Every table WordPress creates for a given prefix.
	 *
	 * @param string $prefix Table prefix.
	 * @return string[]
	 */
	public static function tableNames( string $prefix ): array {
		$base = array(
			'comments',
			'commentmeta',
			'links',
			'options',
			'postmeta',
			'posts',
			'term_relationships',
			'termmeta',
			'terms',
			'user_roles',
			'users',
			'usermeta',
		);

		// Custom tables created by the project's own plugins.
		$custom = array( 'safari_leads', 'safari_lead_notes' );

		$tables = array();

		foreach ( $base as $table ) {
			$tables[] = $prefix . $table;
		}

		foreach ( $custom as $table ) {
			$tables[] = $prefix . $table;
		}

		return $tables;
	}

	/**
	 * Generate a strong password for the local admin account.
	 *
	 * @return string
	 */
	private static function generatePassword(): string {
		$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%^&*';
		$length   = 20;
		$out      = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}

		return $out;
	}

	/**
	 * Append or update a key in the git-ignored .env file.
	 *
	 * @param string $key   Variable name.
	 * @param string $value Variable value.
	 * @return void
	 */
	private static function appendToEnv( string $key, string $value ): void {
		$file = Config::envFile();
		$line = $key . '=' . $value;

		if ( is_file( $file ) ) {
			$contents = (string) file_get_contents( $file );
			$pattern  = '/^' . preg_quote( $key, '/' ) . '=.*$/m';

			if ( preg_match( $pattern, $contents ) ) {
				$updated = preg_replace( $pattern, $line, $contents, 1 );
				@file_put_contents( $file, (string) $updated );
				return;
			}

			@file_put_contents( $file, rtrim( $contents, "\r\n" ) . PHP_EOL . $line . PHP_EOL );
			return;
		}

		@file_put_contents( $file, $line . PHP_EOL );
	}

	/**
	 * Turn WP-CLI output into an actionable hint.
	 *
	 * @param string $output Combined stdout/stderr.
	 * @return void
	 */
	private static function explainInstallFailure( string $output ): void {
		$lower = strtolower( $output );

		$hints = array(
			'unable to connect to the database' => 'Database unreachable. Run: php scripts/safari.php db-check',
			'access denied'                     => 'DB_USER / DB_PASSWORD are wrong, or the Aiven user lacks access to this database.',
			'error establishing a database connection' => 'Database unreachable. Run: php scripts/safari.php db-check',
			'already defined'                  => 'A constant is defined twice — wordpress/wp-config.php is probably hand-edited. Regenerate it with: php scripts/safari.php config --force',
		);

		foreach ( $hints as $needle => $hint ) {
			if ( false !== strpos( $lower, $needle ) ) {
				Console::write( '  Hint: ' . $hint );
				return;
			}
		}
	}
}
