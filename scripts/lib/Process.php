<?php
/**
 * Child process helpers.
 *
 * Commands are normally passed as an *array* of arguments, which makes
 * proc_open() execute them without a shell. That is what makes this reliable on
 * Windows: there is no cmd.exe quoting layer to mangle paths containing spaces
 * or backslashes, and no dependency on `tar`, `sh` or `mklink` being on PATH.
 *
 * stdout and stderr are redirected to temporary files rather than pipes, for
 * two reasons:
 *   - Windows has no proc_select(), and stream_select() on proc_open pipes is
 *     not dependable there, so live streaming is not portable.
 *   - Pipes dead-lock: read stdout while the child fills the stderr buffer and
 *     neither side moves. Files cannot.
 *
 * A command may still be given as a string, in which case it is handed to the
 * platform shell — use that only for things that genuinely need shell features
 * (pipes, redirection).
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
		$result = self::execute( $command, $env, $cwd, $echo );

		@unlink( $result['out_file'] );
		@unlink( $result['err_file'] );

		return $result['code'];
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
		$result = self::execute( $command, $env, $cwd, false );

		$out = (string) @file_get_contents( $result['out_file'] );
		$err = (string) @file_get_contents( $result['err_file'] );

		@unlink( $result['out_file'] );
		@unlink( $result['err_file'] );

		return array(
			'code' => $result['code'],
			'out'  => $out,
			'err'  => $err,
		);
	}

	/**
	 * Start a command and return the process handle plus its output files.
	 *
	 * Used by long-running commands (the dev server) where the caller wants to
	 * keep polling itself.
	 *
	 * @param string|string[] $command Command and arguments.
	 * @param string[]        $env     Extra environment variables.
	 * @param string|null     $cwd     Working directory.
	 * @return array{process:resource,in:string,out:string,err:string,offset:int}
	 */
	public static function start( string|array $command, array $env = array(), ?string $cwd = null ): array {
		$outFile = tempnam( sys_get_temp_dir(), 'safari_out_' );
		$errFile = tempnam( sys_get_temp_dir(), 'safari_err_' );

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'file', $outFile, 'w' ),
			2 => array( 'file', $errFile, 'w' ),
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

		if ( isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
			fclose( $pipes[0] );
		}

		return array(
			'process' => $process,
			'in'      => $pipes[0] ?? null,
			'out'     => $outFile,
			'err'     => $errFile,
			'offset'  => 0,
		);
	}

	/**
	 * Core execution loop shared by run() and capture().
	 *
	 * @param string|string[] $command Command.
	 * @param string[]        $env     Environment.
	 * @param string|null     $cwd     Working directory.
	 * @param bool            $echo    Stream output.
	 * @return array{code:int,out_file:string,err_file:string}
	 */
	private static function execute( string|array $command, array $env, ?string $cwd, bool $echo ): array {
		$outFile = tempnam( sys_get_temp_dir(), 'safari_out_' );
		$errFile = tempnam( sys_get_temp_dir(), 'safari_err_' );

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'file', $outFile, 'w' ),
			2 => array( 'file', $errFile, 'w' ),
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

		if ( isset( $pipes[0] ) && is_resource( $pipes[0] ) ) {
			fclose( $pipes[0] );
		}

		$offsets = array( $outFile => 0, $errFile => 0 );

		while ( true ) {
			$status = proc_get_status( $process );
			$busy   = false;

			foreach ( $offsets as $file => $offset ) {
				$size = @filesize( $file );

				if ( false === $size || $size <= $offset ) {
					continue;
				}

				$handle = @fopen( $file, 'rb' );

				if ( ! is_resource( $handle ) ) {
					continue;
				}

				fseek( $handle, $offset );
				$chunk = (string) stream_get_contents( $handle );
				fclose( $handle );

				if ( '' === $chunk ) {
					continue;
				}

				$offsets[ $file ] = $offset + strlen( $chunk );
				$busy = true;

				if ( $echo ) {
					fwrite( ( $file === $errFile ) ? STDERR : STDOUT, $chunk );
				}
			}

			if ( ! $status['running'] ) {
				// Drain whatever landed after the final size check.
				foreach ( $offsets as $file => $offset ) {
					$handle = @fopen( $file, 'rb' );

					if ( ! is_resource( $handle ) ) {
						continue;
					}

					fseek( $handle, $offset );
					$chunk = (string) stream_get_contents( $handle );
					fclose( $handle );

					if ( '' !== $chunk && $echo ) {
						fwrite( ( $file === $errFile ) ? STDERR : STDOUT, $chunk );
					}
				}

				break;
			}

			if ( ! $busy ) {
				usleep( 20000 );
			}
		}

		$code = proc_close( $process );

		return array(
			'code'     => ( -1 === $code ) ? 1 : $code,
			'out_file' => $outFile,
			'err_file' => $errFile,
		);
	}

	/**
	 * Check whether an executable resolves on PATH.
	 *
	 * @param string $executable Executable name.
	 * @return bool
	 */
	public static function which( string $executable ): bool {
		return null !== self::locate( $executable );
	}

	/**
	 * Resolve an executable to an absolute path, or return null.
	 *
	 * @param string $executable Executable name.
	 * @return string|null
	 */
	public static function locate( string $executable ): ?string {
		// Deliberately bypasses normalise(): normalise() resolves executables
		// through this method, and going back through it here would recurse
		// forever looking up `where`/`which` themselves.
		$command = Console::isWindows()
			? array( 'where.exe', $executable )
			: array( '/usr/bin/which', $executable );

		$result = self::execute( $command, array(), null, false );

		if ( 0 !== $result['code'] ) {
			@unlink( $result['out_file'] );
			@unlink( $result['err_file'] );

			return null;
		}

		$out = (string) @file_get_contents( $result['out_file'] );

		@unlink( $result['out_file'] );
		@unlink( $result['err_file'] );

		$lines = array_filter( array_map( 'trim', preg_split( '/\R/', $out ) ?: array() ) );

		return $lines ? (string) $lines[0] : null;
	}

	/**
	 * Prepare a command for proc_open.
	 *
	 * On Windows the bare name of a shim such as `composer` or `npm` cannot be
	 * executed: what is on PATH is `composer.bat` / `npm.cmd`, and proc_open
	 * will not append PATHEXT for an array command. A path that is an existing
	 * PHP script is also not directly executable there. Both are resolved here
	 * so callers can keep passing the portable, obvious form.
	 *
	 * @param string|string[] $command Command.
	 * @return array{0:string}|string
	 */
	private static function normalise( string|array $command ): array|string {
		if ( is_string( $command ) ) {
			return $command;
		}

		$parts = array_values( array_filter( array_map( 'strval', $command ), static fn (string $a): bool => '' !== $a ) );

		if ( ! $parts ) {
			return $parts;
		}

		$program = self::resolveExecutable( $parts[0] );

		if ( null === $program ) {
			// Run it with the interpreter, keeping the script as argv[1].
			array_unshift( $parts, PHP_BINARY );
		} else {
			$parts[0] = $program;
		}

		return $parts;
	}

	/**
	 * Turn a program name into something this platform can actually execute.
	 *
	 * Returns null when the program is a PHP script that has to be run through
	 * the interpreter, so the caller can prepend PHP_BINARY and keep the script
	 * as its first argument.
	 *
	 * @param string $program Program name or path.
	 * @return string|null
	 */
	private static function resolveExecutable( string $program ): ?string {
		if ( ! Console::isWindows() ) {
			return $program;
		}

		// An explicit path to an existing file needs no help.
		if ( is_file( $program ) ) {
			return $program;
		}

		foreach ( array( '.bat', '.cmd', '.exe', '.com' ) as $extension ) {
			if ( is_file( $program . $extension ) ) {
				return $program . $extension;
			}
		}

		$resolved = self::locate( $program );

		if ( null !== $resolved ) {
			return $resolved;
		}

		// Nothing matched. Some tooling is a plain PHP script with no extension
		// and no shebang - Laravel's artisan is the case in this repository -
		// which proc_open cannot execute directly on Windows.
		return ( str_ends_with( strtolower( $program ), '.php' ) || str_ends_with( strtolower( $program ), '/artisan' ) )
			? null
			: $program;
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
