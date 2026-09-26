<?php
/**
 * Repository-local WP-CLI.
 *
 * A globally installed `wp` is never required. The phar lives at the project
 * root (git-ignored) and is downloaded at a pinned version on first setup, so
 * every developer and CI run uses the same CLI.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

use RuntimeException;

require_once __DIR__ . '/Download.php';

final class WpCli {

	/**
	 * Download WP-CLI if it is not already present.
	 *
	 * @param bool $quiet Suppress progress output.
	 * @return bool True when the phar is available afterwards.
	 */
	public static function ensureInstalled( bool $quiet = false ): bool {
		$phar = Config::wpCliPhar();

		if ( is_file( $phar ) && filesize( $phar ) > 100000 ) {
			if ( ! $quiet ) {
				Console::skip( 'wp-cli.phar already present' );
			}

			return true;
		}

		$version = Config::wpCliVersion();
		$url     = sprintf( 'https://github.com/wp-cli/wp-cli/releases/download/v%s/wp-cli-%s.phar', $version, $version );

		if ( ! $quiet ) {
			Console::info( sprintf( 'Downloading WP-CLI %s…', $version ) );
		}

		$tmp = $phar . '.download';

		$ok = Download::to( $url, $tmp );

		if ( ! $ok ) {
			// Fall back to the always-latest URL, then to a minimal shim.
			$ok = Download::to( 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar', $tmp );
		}

		if ( ! $ok ) {
			Console::warn( 'Could not download WP-CLI. Plugin activation and seeding are unavailable; the site still runs.' );
			@unlink( $tmp );
			return false;
		}

		if ( ! @rename( $tmp, $phar ) ) {
			throw new RuntimeException( 'Unable to move the downloaded WP-CLI phar into place: ' . $phar );
		}

		@chmod( $phar, 0755 );

		if ( ! $quiet ) {
			Console::success( 'WP-CLI ' . $version . ' installed at wp-cli.phar' );
		}

		return true;
	}

	/**
	 * The argument list that invokes the repository-local WP-CLI.
	 *
	 * Returned as an array so proc_open() runs it without a shell — the only
	 * reliable way to pass Windows paths with spaces and backslashes.
	 *
	 * @return string[]
	 */
	public static function parts(): array {
		$php = ( '' !== PHP_BINARY ) ? PHP_BINARY : 'php';

		return array(
			$php,
			Config::wpCliPhar(),
			'--path=' . Config::wpDir(),
			'--allow-root',
		);
	}

	/**
	 * The full command line that invokes the repository-local WP-CLI.
	 *
	 * @return string
	 */
	public static function command(): string {
		return implode( ' ', array_map( 'escapeshellarg', self::parts() ) );
	}

	/**
	 * Run WP-CLI with the given arguments.
	 *
	 * @param string[] $args Arguments (without the leading `wp`).
	 * @param bool     $echo Stream output.
	 * @return int Exit code.
	 */
	public static function run( array $args, bool $echo = true ): int {
		if ( ! is_file( Config::wpCliPhar() ) ) {
			self::ensureInstalled();
		}

		if ( ! is_file( Config::wpCliPhar() ) ) {
			Console::error( 'WP-CLI is not available. Run: php scripts/safari.php setup' );
			return 1;
		}

		if ( ! is_file( Config::wpConfigFile() ) ) {
			Console::error( 'wordpress/wp-config.php is missing. Run: php scripts/safari.php config' );
			return 1;
		}

		return Process::run( array_merge( self::parts(), $args ), array(), $echo, Config::root() );
	}

	/**
	 * Run WP-CLI and capture stdout.
	 *
	 * @param string[] $args Arguments.
	 * @return array{code:int,out:string,err:string}
	 */
	public static function capture( array $args ): array {
		if ( ! is_file( Config::wpCliPhar() ) || ! is_file( Config::wpConfigFile() ) ) {
			return array(
				'code' => 1,
				'out'  => '',
				'err'  => 'WP-CLI is not available.',
			);
		}

		return Process::capture( array_merge( self::parts(), $args ), array(), Config::root() );
	}
}
