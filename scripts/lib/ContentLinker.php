<?php
/**
 * Links the repository's custom theme, plugins and MU-plugins into
 * wp-content so WordPress loads them from a single source of truth.
 *
 * This is the local, Docker-free equivalent of the bind mounts declared in
 * docker-compose.yml. Two strategies are supported:
 *
 *   link (default) — a directory symlink (POSIX) or NTFS junction (Windows).
 *                    `theme/style.css` and `wordpress/wp-content/themes/safari-theme/style.css`
 *                    are the same file, so edits in your editor land in the
 *                    running site immediately.
 *   copy          — a plain directory copy, for the rare environment where
 *                    links are unavailable (locked-down Windows policies, some
 *                    network shares, certain container volumes). Setup then
 *                    re-syncs on every run so the copy cannot drift silently.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

use RuntimeException;

final class ContentLinker {

	/**
	 * Create or refresh every link required by the project.
	 *
	 * @param bool $force Re-sync even when nothing appears out of date.
	 * @return array{linked:int,updated:int,skipped:int,mode:string}
	 */
	public static function sync( bool $force = false ): array {
		$mode    = Config::linkMode();
		$content = Config::wpContentDir();

		self::ensureDir( $content );
		self::ensureDir( $content . '/themes' );
		self::ensureDir( $content . '/plugins' );
		self::ensureDir( $content . '/uploads' );
		self::writeUploadGuard( $content . '/uploads' );

		$linked  = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( Config::links() as $relative => $source ) {
			$target = Config::root() . '/' . $source;
			$link   = $content . '/' . $relative;

			if ( ! is_dir( $target ) ) {
				// safari-search is an empty scaffold; skip quietly rather than fail.
				Console::skip( sprintf( '%s — source folder missing, skipped', $relative ) );
				++$skipped;
				continue;
			}

			$state = self::state( $link, $target );

			if ( 'link' === $state ) {
				++$skipped;
				continue;
			}

			if ( 'copy-fresh' === $state ) {
				++$skipped;
				continue;
			}

			if ( 'copy-stale' === $state && ! $force ) {
				// Cheap staleness check: newest mtime on either side.
				if ( ! self::copyIsStale( $link, $target ) ) {
					++$skipped;
					continue;
				}
			}

			if ( 'stale' === $state ) {
				self::remove( $link );
			}

			if ( 'copy' === $mode ) {
				self::copyDirectory( $target, $link );
			} else {
				self::createLink( $target, $link );
			}

			if ( 'missing' === $state || 'stale' === $state ) {
				Console::success( sprintf( '%s %s', $relative, 'link' === $mode ? 'linked' : 'copied' ) );
				++$linked;
			} else {
				Console::success( sprintf( '%s %s', $relative, 'link' === $mode ? 'refreshed' : 're-copied' ) );
				++$updated;
			}
		}

		return array(
			'linked'  => $linked,
			'updated' => $updated,
			'skipped' => $skipped,
			'mode'    => $mode,
		);
	}

	/**
	 * Classify the current state of a link path.
	 *
	 * `is_link()` returns false for NTFS junctions and `is_dir()` can return
	 * false for them too, so resolution is detected through `realpath()`, which
	 * follows both symlinks and junctions.
	 *
	 * @param string $link   Link path inside wp-content.
	 * @param string $target Repository folder it must resolve to.
	 * @return string One of missing|link|stale|copy-fresh|copy-stale
	 */
	public static function state( string $link, string $target ): string {
		$real = @realpath( $link );
		$want = @realpath( $target );

		if ( false === $real ) {
			// Nothing resolves: either nothing is there, or a broken link is.
			return file_exists( $link ) ? 'stale' : 'missing';
		}

		if ( false !== $want && 0 === strcasecmp( self::normalise( $real ), self::normalise( $want ) ) ) {
			return 'link';
		}

		// It resolves, but to somewhere else: a copy, or a link that went stale.
		return ( is_dir( $link ) || is_link( $link ) ) ? 'copy-stale' : 'stale';
	}

	/**
	 * Remove a link, junction or stale directory.
	 *
	 * @param string $path Path to remove.
	 * @return void
	 */
	public static function remove( string $path ): void {
		if ( ! file_exists( $path ) && ! @realpath( $path ) ) {
			return;
		}

		$content = @realpath( Config::wpContentDir() );
		$target  = @realpath( $path );
		$inside  = ( false !== $content && false !== $target && 0 === strpos( self::normalise( $target ), self::normalise( $content ) ) );

		// Never touch anything outside wp-content, and never wp-content itself.
		if ( ! $inside || ( false !== $target && $target === $content ) ) {
			self::deleteTree( $path );
			return;
		}

		if ( is_link( $path ) ) {
			@unlink( $path );
			return;
		}

		// A junction or symlinked directory: rmdir removes the link, not the target.
		if ( @rmdir( $path ) ) {
			return;
		}

		if ( is_file( $path ) ) {
			@unlink( $path );
			return;
		}

		if ( Console::isWindows() ) {
			Process::run( array( 'cmd.exe', '/d', '/s', '/c', 'rmdir', $path ) );
			return;
		}

		// A real directory inside wp-content: remove its contents, then the folder.
		self::deleteTree( $path );
	}

	/**
	 * Normalise a path for comparison.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalise( string $path ): string {
		return rtrim( str_replace( '\\', '/', $path ), '/' );
	}

	/**
	 * Create a directory link.
	 *
	 * @param string $target Absolute target path.
	 * @param string $link   Absolute link path.
	 * @return void
	 * @throws RuntimeException When the link cannot be created.
	 */
	public static function createLink( string $target, string $link ): void {
		self::ensureDir( dirname( $link ) );

		$error = null;

		if ( Console::isWindows() ) {
			// Junctions need no elevated privileges, unlike symlinks.
			$result = Process::capture( array( 'cmd.exe', '/d', '/s', '/c', 'mklink', '/J', $link, $target ) );
			if ( 0 !== $result['code'] ) {
				$error = trim( $result['out'] . ' ' . $result['err'] );
			}
		} else {
			$result = Process::capture( array( 'ln', '-s', $target, $link ) );
			if ( 0 !== $result['code'] ) {
				$error = trim( $result['out'] . ' ' . $result['err'] );
			}
		}

		if ( null !== $error || 'link' !== self::state( $link, $target ) ) {
			throw new RuntimeException(
				sprintf(
					"Could not link %s → %s.%s%sSet WP_CONTENT_LINK_MODE=copy in .env to fall back to copying.",
					$link,
					$target,
					PHP_EOL . ( null !== $error && '' !== $error ? '  ' . $error : '' ),
					PHP_EOL
				)
			);
		}
	}

	/**
	 * Recursively copy a directory, replacing the destination.
	 *
	 * @param string $source      Source directory.
	 * @param string $destination Destination directory.
	 * @return void
	 */
	public static function copyDirectory( string $source, string $destination ): void {
		self::remove( $destination );
		self::ensureDir( $destination );

		// DirectoryIterator + manual copy keeps behaviour identical on all
		// platforms and avoids shelling out to xcopy/robocopy.
		$stack = array( $source );

		while ( $stack ) {
			$current = array_pop( $stack );

			foreach ( new \DirectoryIterator( $current ) as $item ) {
				if ( $item->isDot() ) {
					continue;
				}

				$name    = $item->getFilename();
				$from    = $item->getPathname();
				$to      = $destination . '/' . substr( $from, strlen( $source ) + 1 );

				if ( in_array( $name, array( '.git', 'node_modules', '.vite-dev' ), true ) ) {
					continue;
				}

				if ( $item->isDir() ) {
					self::ensureDir( $to );
					$stack[] = $from;
					continue;
				}

				@copy( $from, $to );
			}
		}
	}

	/**
	 * Determine whether a copied directory is behind its source.
	 *
	 * Compares the newest modification time on both sides. Cheap, and wrong
	 * only when a file is edited without changing its mtime (essentially never).
	 *
	 * @param string $link   Copied destination.
	 * @param string $target Source directory.
	 * @return bool
	 */
	private static function copyIsStale( string $link, string $target ): bool {
		return self::newestMtime( $target ) > self::newestMtime( $link );
	}

	/**
	 * Newest mtime found in a directory tree.
	 *
	 * @param string $dir Directory.
	 * @param int    $depth Current recursion depth.
	 * @return int
	 */
	private static function newestMtime( string $dir, int $depth = 0 ): int {
		if ( $depth > 6 || ! is_dir( $dir ) ) {
			return 0;
		}

		$newest = 0;

		foreach ( new \DirectoryIterator( $dir ) as $item ) {
			if ( $item->isDot() ) {
				continue;
			}

			$name = $item->getFilename();

			if ( in_array( $name, array( '.git', 'node_modules' ), true ) ) {
				continue;
			}

			if ( $item->isDir() ) {
				$newest = max( $newest, self::newestMtime( $item->getPathname(), $depth + 1 ) );
				continue;
			}

			$newest = max( $newest, (int) $item->getMTime() );
		}

		return $newest;
	}

	/**
	 * Resolve a symlink (or junction) to its final target.
	 *
	 * @param string $path Link path.
	 * @return string
	 */
	private static function resolveLink( string $path ): string {
		$real = @realpath( $path );

		return false === $real ? '' : str_replace( '\\', '/', $real );
	}

	/**
	 * Recursively delete a directory tree.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function deleteTree( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			@unlink( $dir );
			return;
		}

		foreach ( new \DirectoryIterator( $dir ) as $item ) {
			if ( $item->isDot() ) {
				continue;
			}

			if ( $item->isDir() && ! $item->isLink() ) {
				self::deleteTree( $item->getPathname() );
				continue;
			}

			@unlink( $item->getPathname() );
		}

		@rmdir( $dir );
	}

	/**
	 * Create a directory (recursively) if it does not exist.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	public static function ensureDir( string $dir ): void {
		if ( is_dir( $dir ) ) {
			return;
		}

		if ( ! @mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new RuntimeException( 'Unable to create directory: ' . $dir );
		}
	}

	/**
	 * Drop a minimal index.html in uploads so the folder is never listable.
	 *
	 * @param string $dir Uploads directory.
	 * @return void
	 */
	private static function writeUploadGuard( string $dir ): void {
		$guard = $dir . '/index.html';

		if ( ! file_exists( $guard ) ) {
			@file_put_contents( $guard, '' );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n"
				. "<IfModule mod_php.c>\n"
				. "php_flag engine off\n"
				. "</IfModule>\n";
			@file_put_contents( $htaccess, $rules );
		}
	}
}
