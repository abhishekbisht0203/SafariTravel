<?php
/**
 * Laravel `artisan` wrapper.
 *
 * Kept dependency-free and in the same namespace as the rest of the tooling so
 * `php scripts/safari.php api <args…>` behaves identically on Windows, macOS
 * and Linux. The backend's own Composer autoloader is only loaded by the
 * subprocess, so a broken backend install can never take the WordPress tooling
 * down with it.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

if ( ! defined( 'SAFARI_TOOLING_ARTISAN_LOADED' ) ) {
	define( 'SAFARI_TOOLING_ARTISAN_LOADED', true );

	/**
	 * Runs the Laravel console in backend/.
	 */
	final class Artisan {

		/**
		 * Build the command that runs an artisan subcommand.
		 *
		 * `artisan` has no extension and no usable shebang, so it cannot be
		 * handed to proc_open() as the program directly - least of all on
		 * Windows. It is always run through the current PHP binary, which also
		 * guarantees the subprocess uses the same interpreter and php.ini as the
		 * tooling that launched it.
		 *
		 * @param string[] $args Arguments after `artisan`.
		 * @return string[]
		 */
		private static function command( array $args ): array {
			return array_merge(
				array( PHP_BINARY, Config::apiDir() . '/artisan' ),
				$args,
				array( '--no-interaction' )
			);
		}

		/**
		 * Run an artisan command.
		 *
		 * @param string[] $args Arguments after `artisan`.
		 * @return int Exit code.
		 */
		public static function run( array $args ): int {
			if ( ! is_file( Config::apiDir() . '/artisan' ) ) {
				Console::error( 'backend/artisan not found — the Laravel API is not installed.' );

				return 1;
			}

			if ( ! is_file( Config::apiEnvFile() ) ) {
				Console::error( 'backend/.env is missing. Run: php scripts/safari.php api-env' );

				return 1;
			}

			return Process::run( self::command( $args ), array(), true, Config::apiDir() );
		}

		/**
		 * Run an artisan command and capture its output.
		 *
		 * @param string[] $args Arguments after `artisan`.
		 * @return array{code: int, out: string, err: string}
		 */
		public static function capture( array $args ): array {
			if ( ! is_file( Config::apiDir() . '/artisan' ) ) {
				return array(
					'code' => 1,
					'out'  => '',
					'err'  => 'backend/artisan not found',
				);
			}

			return Process::capture( self::command( $args ), array(), Config::apiDir() );
		}

		/**
		 * Whether the backend's Composer dependencies are installed.
		 *
		 * @return bool
		 */
		public static function installed(): bool {
			return is_file( Config::apiDir() . '/vendor/autoload.php' );
		}
	}
}
