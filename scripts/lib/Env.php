<?php
/**
 * Minimal, dependency-free .env reader.
 *
 * This file is intentionally standalone: it is loaded by the setup CLI, by
 * WP-CLI wrappers, by the router used with PHP's built-in server, and — most
 * importantly — by the generated `wp-config.php`. That last case is why it must
 * not require Composer's autoloader or anything else from the repository.
 *
 * Parsing rules (deliberately small and predictable):
 *   - `KEY=value`, one per line
 *   - `#` or `;` starts a comment when it is the first non-space character
 *   - values may be wrapped in single or double quotes (escapes handled for `"\`)
 *   - `export KEY=value` is accepted for shell-flavoured .env files
 *   - blank lines are ignored
 *   - no variable expansion, no command substitution
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

if ( ! defined( 'SAFARI_TOOLING_ENV_LOADED' ) ) {
	define( 'SAFARI_TOOLING_ENV_LOADED', true );

	/**
	 * Reads a repository `.env` file and exposes the values to the process.
	 */
	final class Env {

		/**
		 * Values parsed from the file(s) loaded so far.
		 *
		 * @var array<string,string>
		 */
		private static array $values = array();

		/**
		 * Whether values from the file were allowed to override real env vars.
		 *
		 * @var bool
		 */
		private static bool $allowOverride = false;

		/**
		 * Parse a .env file into an associative array without touching globals.
		 *
		 * @param string $file Absolute path to the .env file.
		 * @return array<string,string> Parsed values (empty when the file is absent).
		 */
		public static function parse( string $file ): array {
			if ( ! is_file( $file ) || ! is_readable( $file ) ) {
				return array();
			}

			$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
			if ( false === $lines ) {
				return array();
			}

			$out = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );

				if ( '' === $line || 0 === strpos( $line, '#' ) || 0 === strpos( $line, ';' ) ) {
					continue;
				}

				if ( 0 === stripos( $line, 'export ' ) ) {
					$line = trim( substr( $line, 7 ) );
				}

				$eq = strpos( $line, '=' );
				if ( false === $eq ) {
					continue;
				}

				$key = trim( substr( $line, 0, $eq ) );
				$raw = trim( substr( $line, $eq + 1 ) );

				if ( '' === $key || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_.]*$/', $key ) ) {
					continue;
				}

				$out[ $key ] = self::unquote( $raw );
			}

			return $out;
		}

		/**
		 * Strip surrounding quotes and resolve escapes.
		 *
		 * @param string $value Raw value.
		 * @return string
		 */
		private static function unquote( string $value ): string {
			$length = strlen( $value );

			if ( $length >= 2 ) {
				$first = $value[0];
				$last  = $value[ $length - 1 ];

				if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
					$inner = substr( $value, 1, -1 );

					// Single quotes are literal; double quotes honour backslash escapes.
					return "'" === $first ? $inner : str_replace( array( '\\"', '\\n', '\\r', '\\t', '\\\\' ), array( '"', "\n", "\r", "\t", '\\' ), $inner );
				}
			}

			// Unquoted: an inline comment is only honoured when preceded by whitespace.
			return trim( (string) preg_replace( '/\s+#.*$/', '', $value ) );
		}

		/**
		 * Load a .env file and publish the values to the current process.
		 *
		 * Values are pushed into `getenv()`, `$_ENV` and `$_SERVER` so that both
		 * WordPress and any third-party plugin reading them see the same data.
		 * Real environment variables win by default, which lets CI and hosting
		 * panels inject secrets without a .env file.
		 *
		 * @param string $file  Absolute path to the .env file.
		 * @param bool   $force Set true to let file values override real env vars.
		 * @return array<string,string> The values that were published.
		 */
		public static function load( string $file, bool $force = false ): array {
			$parsed = self::parse( $file );

			self::$allowOverride = $force;

			foreach ( $parsed as $key => $value ) {
				self::$values[ $key ] = $value;
				self::publish( $key, $value );
			}

			return $parsed;
		}

		/**
		 * Publish a single value to the process environment.
		 *
		 * @param string $key   Variable name.
		 * @param string $value Variable value.
		 * @return void
		 */
		private static function publish( string $key, string $value ): void {
			$existing = getenv( $key );
			$isSet    = ( false !== $existing );

			if ( $isSet && ! self::$allowOverride && '' !== $existing ) {
				// Real environment variable already provides a value — keep it.
				self::$values[ $key ] = $existing;
				return;
			}

			putenv( $key . '=' . $value );
			$_ENV[ $key ]    = $value;
			$_SERVER[ $key ] = $value;
		}

		/**
		 * Read a value, preferring real environment variables.
		 *
		 * @param string               $key     Variable name.
		 * @param string|array|null    $default Fallback when unset/empty.
		 * @return string|array|null
		 */
		public static function get( string $key, $default = null ) {
			$value = getenv( $key );

			if ( false === $value || '' === $value ) {
				if ( array_key_exists( $key, $_SERVER ) && '' !== $_SERVER[ $key ] ) {
					$value = (string) $_SERVER[ $key ];
				} elseif ( array_key_exists( $key, self::$values ) && '' !== self::$values[ $key ] ) {
					$value = self::$values[ $key ];
				} else {
					return $default;
				}
			}

			return $value;
		}

		/**
		 * Read a value as a trimmed string.
		 *
		 * @param string $key     Variable name.
		 * @param string $default Fallback value.
		 * @return string
		 */
		public static function str( string $key, string $default = '' ): string {
			$value = self::get( $key );

			return is_string( $value ) ? trim( $value ) : $default;
		}

		/**
		 * Read a value as a boolean.
		 *
		 * Accepts true/false, 1/0, yes/no, on/off.
		 *
		 * @param string $key     Variable name.
		 * @param bool   $default Fallback when unset or unparseable.
		 * @return bool
		 */
		public static function bool( string $key, bool $default = false ): bool {
			$value = self::get( $key );

			if ( null === $value || '' === $value || is_array( $value ) ) {
				return $default;
			}

			$parsed = filter_var( (string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			return null === $parsed ? $default : $parsed;
		}

		/**
		 * Read a value as an integer.
		 *
		 * @param string $key     Variable name.
		 * @param int    $default Fallback when unset or not numeric.
		 * @return int
		 */
		public static function int( string $key, int $default = 0 ): int {
			$value = self::get( $key );

			if ( null === $value || '' === $value || is_array( $value ) || ! is_numeric( $value ) ) {
				return $default;
			}

			return (int) $value;
		}
	}
}
