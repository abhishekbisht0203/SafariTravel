<?php
/**
 * Terminal output helpers.
 *
 * Colour is enabled only for interactive TTYs, so piping setup output into a
 * log file or a CI job produces clean text.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

final class Console {

	/**
	 * Whether ANSI colour codes should be emitted.
	 *
	 * @var bool|null
	 */
	private static ?bool $colour = null;

	/**
	 * Whether the script is running on Windows.
	 *
	 * @var bool
	 */
	public static function isWindows(): bool {
		return 'WINNT' === PHP_OS || 'WIN32' === PHP_OS || 'CYGWIN' === PHP_OS;
	}

	/**
	 * Whether colour output is active.
	 *
	 * @return bool
	 */
	public static function colour(): bool {
		if ( null !== self::$colour ) {
			return self::$colour;
		}

		if ( ! function_exists( 'posix_isatty' ) ) {
			// Windows: rely on the documented environment overrides.
			$noColor = getenv( 'NO_COLOR' );
			$force   = getenv( 'FORCE_COLOR' );

			self::$colour = ( false !== $noColor && '' !== $noColor ) ? false : ( false !== $force && '' !== $force );
			return self::$colour;
		}

		$interactive = @posix_isatty( STDOUT );
		$noColor     = getenv( 'NO_COLOR' );

		self::$colour = (bool) $interactive && ( false === $noColor || '' === $noColor );
		return self::$colour;
	}

	/**
	 * Wrap text in an ANSI colour code.
	 *
	 * @param string $text Message.
	 * @param string $code ANSI code.
	 * @return string
	 */
	public static function paint( string $text, string $code ): string {
		return self::colour() ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
	}

	/**
	 * A fixed-width status marker for diagnostic rows.
	 *
	 * @param bool $ok       Whether the check passed.
	 * @param bool $optional Whether a failure is only a warning.
	 * @return string
	 */
	public static function mark( bool $ok, bool $optional = false ): string {
		if ( $ok ) {
			return self::paint( '  OK   ', '32' );
		}

		return $optional ? self::paint( '  WARN ', '33' ) : self::paint( '  FAIL ', '31' );
	}

	/**
	 * Write a line to stdout.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function write( string $message = '' ): void {
		fwrite( STDOUT, $message . PHP_EOL );
	}

	/**
	 * Write raw text to stdout without a trailing newline.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	public static function raw( string $text ): void {
		fwrite( STDOUT, $text );
	}

	/**
	 * Success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function success( string $message ): void {
		self::write( self::paint( '  OK   ', '32' ) . $message );
	}

	/**
	 * Informational message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function info( string $message ): void {
		self::write( '  ..   ' . $message );
	}

	/**
	 * Skip/unchanged message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function skip( string $message ): void {
		self::write( self::paint( '  --   ', '90' ) . $message );
	}

	/**
	 * Warning message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function warn( string $message ): void {
		self::write( self::paint( '  WARN ', '33' ) . $message );
	}

	/**
	 * Error message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function error( string $message ): void {
		fwrite( STDERR, self::paint( '  FAIL ', '31' ) . $message . PHP_EOL );
	}

	/**
	 * Section heading.
	 *
	 * @param string $title Title.
	 * @return void
	 */
	public static function step( string $title ): void {
		self::write();
		self::write( self::paint( $title, '1;36' ) );
	}

	/**
	 * Print a boxed banner.
	 *
	 * @param string[] $lines Banner lines.
	 * @return void
	 */
	public static function banner( array $lines ): void {
		$width = 0;
		foreach ( $lines as $line ) {
			$width = max( $width, self::visibleLength( $line ) );
		}
		$width = min( max( $width, 40 ), 78 );

		$bar = str_repeat( '=', $width );

		self::write();
		self::write( self::paint( $bar, '36' ) );
		foreach ( $lines as $line ) {
			self::write( '  ' . $line );
		}
		self::write( self::paint( $bar, '36' ) );
		self::write();
	}

	/**
	 * Count printable characters, ignoring ANSI escapes.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function visibleLength( string $text ): int {
		$stripped = preg_replace( '/\033\[[0-9;]*m/', '', $text );

		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $stripped, 'UTF-8' ) : strlen( (string) $stripped );
	}
}
