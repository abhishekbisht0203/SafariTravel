<?php
/**
 * Child process helpers.
 *
 * Commands are normally passed as an *array* of arguments, which makes
 * proc_open() execute them without a shell. That is what makes this reliable on
 * Windows: there is no cmd.exe quoting layer to mangle paths containing spaces
 * or backslashes, and no dependency on `tar`, `sh` or `mklink` being on PATH.
 *
 * A command may still be given as a string, in which case it is handed to the
 * platform shell — use that only for things that genuinely need shell features
 * (pipes, redirection, `2>&1`).
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

use RuntimeException;

final class Process {

	/**
	 * Run a command, streaming its output, and return the exit code.
	 *
	 * @param string|string[] $command Command and arguments.
	 * @param string[]        $env     Extra environment variables.
	 * @param bool            $echo    Stream output to the terminal.
	 * @param string|null     $cwd     Working directory.
	 * @return int Exit code.
	 */
	public static function run( string|array $command, array $env = array(), bool $echo = true, ?string $cwd = null ): int {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = @proc_open(
			self::normalise( $command ),
			$descriptors,
			$pipes,
			$cwd,
			self::mergeEnv( $env )
		);

		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Unable to start process: ' . self::describe( $command ) );
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		while ( true ) {
			$read   = array_values( $pipes );
			$write  = null;
			$except = null;

			if ( @proc_select( $read, $write, $except, 0, 200000 ) < 1 ) {
				$status = proc_get_status( $process );

				if ( ! $status['running'] ) {
					break;
				}

				continue;
			}

			foreach ( $read as $stream ) {
				$key = array_search( $stream, $pipes, true );

				if ( false === $key ) {
					continue;
				}

				$chunk = fread( $stream, 8192 );

				if ( false === $chunk || '' === $chunk ) {
					continue;
				}

				if ( $echo ) {
					fwrite( ( 2 === $key ) ? STDERR : STDOUT, $chunk );
				}
			}
		}

		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe );
			}
		}

		$code = proc_close( $process );

		// proc_close can return -1 on some platforms when the child was reaped.
		return ( -1 === $code ) ? 1 : $code;
	}

	/**
	 * Run a command and capture stdout and stderr.
	 *
	 * @param string|string[] $command Command and arguments.
	 * @param string[]        $env     Extra environment variables.
	 * @param string|null     $cwd     Working directory.
	 * @return array{code:int,out:string,err:string}
	 */
	public static function capture( string|array $command, array $env = array(), ?string $cwd = null ): array {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = @proc_open(
			self::normalise( $command ),
			$descriptors,
			$pipes,
			$cwd,
			self::mergeEnv( $env )
		);

		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Unable to start process: ' . self::describe( $command ) );
		}

		fclose( $pipes[0] );
		$out = (string) stream_get_contents( $pipes[1] );
		$err = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$code = proc_close( $process );

		return array(
			'code' => ( -1 === $code ) ? 1 : $code,
			'out'  => $out,
			'err'  => $err,
		);
	}

	/**
	 * Check whether an executable resolves on PATH.
	 *
	 * @param string $executable Executable name.
	 * @return bool
	 */
	public static function which( string $executable ): bool {
		if ( Console::isWindows() ) {
			// `where` ships with Windows; on the PATH separator is `;`.
			$result = self::capture( array( 'where', $executable ) );

			return 0 === $result['code'] && '' !== trim( $result['out'] );
		}

		$result = self::capture( array( 'which', $executable ) );

		return 0 === $result['code'];
	}

	/**
	 * Resolve an executable to an absolute path, or return null.
	 *
	 * @param string $executable Executable name.
	 * @return string|null
	 */
	public static function locate( string $executable ): ?string {
		$probe = Console::isWindows()
			? array( 'where', $executable )
			: array( 'which', $executable );

		$result = self::capture( $probe );

		if ( 0 !== $result['code'] ) {
			return null;
		}

		$lines = array_filter( array_map( 'trim', preg_split( '/\R/', $result['out'] ) ?: array() ) );

		return $lines ? (string) $lines[0] : null;
	}

	/**
	 * Prepare a command for proc_open.
	 *
	 * @param string|string[] $command Command.
	 * @return array{0:string}|string
	 */
	private static function normalise( string|array $command ): array|string {
		if ( is_array( $command ) ) {
			// Drop empty arguments so callers can pass optional flags freely.
			return array_values( array_filter( array_map( 'strval', $command ), static fn (string $a): bool => '' !== $a ) );
		}

		return $command;
	}

	/**
	 * Human-readable form of a command, for error messages.
	 *
	 * @param string|string[] $command Command.
	 * @return string
	 */
	private static function describe( string|array $command ): string {
		return is_array( $command ) ? implode( ' ', $command ) : $command;
	}

	/**
	 * Build the environment array for proc_open.
	 *
	 * @param string[] $extra Extra variables.
	 * @return array<string,string>|null
	 */
	private static function mergeEnv( array $extra ): ?array {
		$base = array();

		foreach ( $_SERVER as $key => $value ) {
			if ( is_string( $key ) && is_scalar( $value ) ) {
				$base[ $key ] = (string) $value;
			}
		}

		foreach ( $extra as $key => $value ) {
			$base[ $key ] = (string) $value;
		}

		return $base ?: null;
	}
}
