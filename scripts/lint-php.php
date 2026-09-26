<?php
/**
 * Cross-platform syntax check for every first-party PHP file.
 *
 * `php -l` is the one lint that is always available and needs no configuration,
 * but spawning one process per file is far too slow on Windows for a repository
 * of this size. PHP's own parser is used instead: `token_get_all()` in
 * TOKEN_PARSE mode raises a ParseError on exactly the same input `php -l`
 * rejects, in a single process.
 *
 * This is the floor under the whole project. It covers scripts/ (standalone
 * tooling, not WordPress, so checked by syntax rather than by WordPress Coding
 * Standards) and backend/ (which has its own Pint configuration) as well as the
 * plugins, mu-plugins and theme.
 *
 * Excluded: vendor directories, node_modules, and the generated WordPress
 * install under wordpress/.
 *
 * Usage: php scripts/lint-php.php [--verbose]
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

use FilesystemIterator;
use ParseError;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit( "This script may only be run from the command line.\n" );
}

$root    = dirname( __DIR__ );
$verbose = in_array( '--verbose', $_SERVER['argv'] ?? array(), true );

/**
 * Directories that contain no first-party source.
 *
 * @var string[]
 */
$skip = array(
	$root . '/vendor',
	$root . '/node_modules',
	$root . '/wordpress',
	$root . '/backend/vendor',
	$root . '/backend/node_modules',
	$root . '/uploads',
	$root . '/.git',
	$root . '/.phpunit.cache',
);

$files   = list_php_files( $root, $skip );
$failed  = array();

foreach ( $files as $file ) {
	$source = file_get_contents( $file );

	if ( false === $source ) {
		$failed[ $file ] = 'Could not read the file.';
		continue;
	}

	try {
		// TOKEN_PARSE makes the tokenizer validate the grammar, which is what
		// `php -l` does. Without it, invalid code is silently tokenised.
		token_get_all( $source, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		$failed[ $file ] = sprintf(
			'  Parse error on line %d: %s',
			$e->getLine(),
			$e->getMessage()
		);
	} catch ( Throwable $e ) {
		$failed[ $file ] = '  ' . $e->getMessage();
	}

	if ( $verbose && ! isset( $failed[ $file ] ) ) {
		echo '  ok  ' . substr( $file, strlen( $root ) + 1 ) . PHP_EOL;
	}
}

printf( '%d PHP file(s) checked, %d with syntax errors.%s', count( $files ), count( $failed ), PHP_EOL );

foreach ( $failed as $file => $message ) {
	printf( PHP_EOL . '%s%s%s', $file, PHP_EOL, $message . PHP_EOL );
}

exit( [] === $failed ? 0 : 1 );

/**
 * Recursively collect PHP files, skipping excluded directories.
 *
 * @param string   $dir  Directory to walk.
 * @param string[] $skip Absolute paths to skip.
 * @return string[] Absolute file paths, sorted.
 */
function list_php_files( string $dir, array $skip ): array {
	// Normalise separators so the same skip list works on Windows and POSIX.
	$normalise = static fn ( string $path ): string => str_replace( '\\', '/', $path );

	$skip  = array_map( $normalise, array_map( 'strtolower', $skip ) );
	$files = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( SplFileInfo $current ) use ( $skip, $normalise ): bool {
				$path = $normalise( strtolower( $current->getPathname() ) );

				foreach ( $skip as $excluded ) {
					if ( $path === $excluded || str_starts_with( $path, $excluded . '/' ) ) {
						return false;
					}
				}

				return true;
			}
		),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}
