<?php
/**
 * Child process helpers.
 *
 * Everything the setup flow shells out to (composer, npm, wp-cli, the PHP
 * built-in server) goes through here so quoting, exit codes and stream
 * forwarding behave identically on Windows and Unix.
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
	 * @param string   $command   Command line (executable plus arguments).
	 * @param string[] $env       Extra environment variables.
	 * @param bool     $echo      Stream output to the terminal.
	 * @param string   $cwd       Working directory.
	 * @return int Exit code.
	 */
	public static function run( string $command, array $env = array(), bool $echo = true, ?string $cwd = null ): int {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$merged = self::mergeEnv( $env );

		$process = @proc_open( self::wrap( $command ), $descriptors, $pipes, $cwd, $merged );

		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Unable to start process: ' . $command );
		}

		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$buffers = array( 1 => '', 2 => '' );

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
					$target = ( 2 === $key ) ? STDERR : STDOUT;
					fwrite( $target, $chunk );
				}

				$buffers[ $key ] .= $chunk;
			}
		}

		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe );
			}
		}

		$code = proc_close( $process );

		// proc_close can return -1 on some platforms; fall back to the last status.
		return ( -1 === $code ) ? 1 : $code;
	}

	/**
	 * Run a command and capture stdout as a string.
	 *
	 * @param string   $command Command line.
	 * @param string[] $env     Extra environment variables.
	 * @param string   $cwd     Working directory.
	 * @return array{code:int,out:string,err:string}
	 */
	public static function capture( string $command, array $env = array(), ?string $cwd = null ): array {
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = @proc_open( self::wrap( $command ), $descriptors, $pipes, $cwd, self::mergeEnv( $env ) );

		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Unable to start process: ' . $command );
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
	 * Check whether an executable can be found on PATH.
	 *
	 * @param string $executable Executable name.
	 * @return bool
	 */
	public static function which( string $executable ): bool {
		$probe = Console::isWindows()
			? 'where ' . escapeshellarg( $executable )
			: 'command -v ' . escapeshellarg( $executable ) . ' >/dev/null 2>&1';

		$result = self::capture( $probe );

		return 0 === $result['code'] && '' !== trim( $result['out'] . $result['err'] );
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

		if ( ! $extra ) {
			return $base ?: null;
		}

		foreach ( $extra as $key => $value ) {
			$base[ $key ] = (string) $value;
		}

		return $base;
	}

	/**
	 * Wrap a command for the current platform.
	 *
	 * On Windows `cmd.exe /d /s /c` is required so that batch files such as
	 * `npm.cmd` resolve correctly.
	 *
	 * @param string $command Command line.
	 * @return string
	 */
	private static function wrap( string $command ): string {
		if ( ! Console::isWindows() ) {
			return $command;
		}

		return 'cmd.exe /d /s /c ' . escapeshellcmd( $command );
	}
}
