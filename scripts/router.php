<?php
/**
 * Router for PHP's built-in web server.
 *
 * `php -S` has no rewrite engine, so pretty permalinks, /wp-admin/*.php and
 * /wp-login.php all need help. This router implements the same rules Apache and
 * nginx use for a stock WordPress install:
 *
 *   1. Reject path traversal and NUL bytes outright.
 *   2. Serve an existing static file from the document root, or from the
 *      repository when it is reached through a wp-content symlink.
 *   3. Execute an existing PHP file (wp-admin, wp-login, wp-cron, …).
 *   4. Hand everything else to WordPress's index.php, which resolves
 *      permalinks, feeds, the REST API and 404s.
 *
 * This is a development server. It is single-threaded, has no TLS, no caching
 * and no access control — do not point a public site at it. wp-config.php
 * refuses to boot under cli-server when WP_ENV=production.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

// The built-in server provides these; guard anyway so the file is also lintable.
$docRoot = rtrim( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ), '/\\' );
$uri     = (string) parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
$uri     = rawurldecode( $uri );

/**
 * Send a plain text response and stop.
 *
 * @param int    $status HTTP status.
 * @param string $body   Response body.
 * @return void
 */
$safari_router_die = static function ( int $status, string $body ): void {
	http_response_code( $status );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $body;
	exit;
};

if ( '' === $docRoot ) {
	$safari_router_die( 500, "DOCUMENT_ROOT is not set. Start the server with: php scripts/safari.php start\n" );
}

if ( '' === $uri || '/' === $uri ) {
	$uri = '/';
}

if ( false !== strpos( $uri, "\0" ) ) {
	$safari_router_die( 400, "Bad request\n" );
}

/**
 * Reject any path that contains a traversal segment.
 *
 * @param string $path URI path.
 * @return void
 */
$safari_router_reject_traversal = static function ( string $path ): void {
	foreach ( explode( '/', $path ) as $segment ) {
		if ( '..' === $segment ) {
			http_response_code( 400 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo "Bad request\n";
			exit;
		}
	}
};

$safari_router_reject_traversal( $uri );

/**
 * Whether a resolved path lives inside one of the allowed roots.
 *
 * The wp-content tree is symlinked into the repository, so realpath() of a
 * theme asset points at <repo>/theme/... — outside DOCUMENT_ROOT but still
 * legitimate. Both roots are allowed; nothing else is.
 *
 * @param string   $real       Resolved absolute path.
 * @param string[] $allowRoots Allowed absolute roots.
 * @return bool
 */
$safari_router_is_allowed = static function ( string $real, array $allowRoots ): bool {
	$real = str_replace( '\\', '/', $real );

	foreach ( $allowRoots as $root ) {
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );

		if ( '' === $root ) {
			continue;
		}

		if ( $real === $root || 0 === strpos( $real, $root . '/' ) ) {
			return true;
		}
	}

	return false;
};

$candidate = $docRoot . $uri;
$real      = realpath( $candidate );

// Allowed roots: the WordPress install, plus the repository root that holds the
// linked theme/plugins (one level above the document root).
$allowRoots = array( $docRoot, dirname( $docRoot ) );

/**
 * Hand a request to WordPress's front controller.
 *
 * This is the `try_files $uri $uri/ /index.php?$args` fallback that Apache and
 * nginx use for a stock WordPress install, and the only reason pretty permalinks
 * work at all. `/destinations/` has no directory on disk - it is a rewrite
 * target - so without this step every permalink, archive, feed and the REST API
 * would 404 while only `/?p=123` style URLs worked.
 *
 * SCRIPT_FILENAME/SCRIPT_NAME/PHP_SELF are set to the *requested* path so
 * WordPress builds correct URLs, and the query string is left untouched so
 * `?s=`, `?p=` and `?rest_route=` keep working.
 *
 * @param string $docRoot WordPress document root.
 * @param string $uri     Requested path.
 * @return void
 */
$safari_router_wordpress = static function ( string $docRoot, string $uri ): void {
	$front = $docRoot . '/index.php';

	if ( ! is_file( $front ) ) {
		$safari_router_die( 500, "WordPress index.php not found in {$docRoot}\n" );
	}

	$_SERVER['SCRIPT_FILENAME'] = $front;
	$_SERVER['SCRIPT_NAME']     = '/index.php';
	$_SERVER['PHP_SELF']        = '/index.php';

	chdir( $docRoot );
	require $front;
};

if ( false === $real ) {
	/*
	 * Nothing exists at that path. A path with a file extension is a request for
	 * a specific asset or script that genuinely is not there, so it gets a plain
	 * 404 rather than being handed to WordPress - that keeps a missing
	 * stylesheet or image from being reported as, say, a missing page, and stops
	 * WordPress from burning time on URLs it can never serve.
	 *
	 * An extension-less path is a permalink: hand it to WordPress, which either
	 * matches a rewrite rule or produces its own proper 404 page.
	 */
	$extension = strtolower( (string) pathinfo( (string) parse_url( $uri, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	if ( '' !== $extension ) {
		$safari_router_die( 404, "Not found\n" );
	}

	$safari_router_wordpress( $docRoot, $uri );

	return true;
}

if ( ! $safari_router_is_allowed( $real, $allowRoots ) ) {
	$safari_router_die( 403, "Forbidden\n" );
}

$isDir = is_dir( $real );

if ( $isDir ) {
	// Directory request: prefer index.php, then index.html.
	$index = rtrim( $real, '/\\' ) . '/index.php';

	if ( is_file( $index ) ) {
		$real = $index;
		$isDir = false;
	} else {
		$safari_router_die( 403, "Directory listing is disabled\n" );
	}
}

$extension = strtolower( (string) pathinfo( $real, PATHINFO_EXTENSION ) );

/**
 * Content type for static assets.
 *
 * @param string $extension Lowercase extension without the dot.
 * @return string
 */
$safari_router_mime = static function ( string $extension ): string {
	$types = array(
		'css'   => 'text/css; charset=utf-8',
		'js'    => 'text/javascript; charset=utf-8',
		'mjs'   => 'text/javascript; charset=utf-8',
		'json'  => 'application/json; charset=utf-8',
		'map'   => 'application/json; charset=utf-8',
		'html'  => 'text/html; charset=utf-8',
		'htm'   => 'text/html; charset=utf-8',
		'txt'   => 'text/plain; charset=utf-8',
		'xml'   => 'application/xml; charset=utf-8',
		'csv'   => 'text/csv; charset=utf-8',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'jpg'   => 'image/jpeg',
		'jpeg'  => 'image/jpeg',
		'gif'   => 'image/gif',
		'webp'  => 'image/webp',
		'avif'  => 'image/avif',
		'ico'   => 'image/x-icon',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'eot'   => 'application/vnd.ms-fontobject',
		'mp4'   => 'video/mp4',
		'webm'  => 'video/webm',
		'mp3'   => 'audio/mpeg',
		'wav'   => 'audio/wav',
		'pdf'   => 'application/pdf',
		'zip'   => 'application/zip',
		'wasm'  => 'application/wasm',
	);

	return $types[ $extension ] ?? 'application/octet-stream';
};

if ( 'php' === $extension ) {
	/*
	 * Never hand wp-config.php (or any file that is not an entry point) to the
	 * interpreter through the dev server.
	 */
	$basename = strtolower( basename( $real ) );

	if ( 'wp-config.php' === $basename ) {
		$safari_router_die( 403, "Forbidden\n" );
	}

	// WordPress resolves its own paths relative to the entry script's directory.
	$_SERVER['SCRIPT_FILENAME'] = $real;
	$_SERVER['SCRIPT_NAME']     = $uri;
	$_SERVER['PHP_SELF']        = $uri;

	chdir( dirname( $real ) );
	require $real;

	return true;
}

if ( '' === $extension && $isDir ) {
	$safari_router_die( 404, "Not found\n" );
}

// ── Static asset ────────────────────────────────────────────────────────────

$mime = $safari_router_mime( $extension );

header( 'Content-Type: ' . $mime );

// The built-in server is single-threaded, so long-lived assets are cheap to cache
// in development. WordPress appends ?ver= to its own enqueued assets.
header( 'Cache-Control: public, max-age=3600' );

$size = filesize( $real );

if ( false !== $size ) {
	header( 'Content-Length: ' . $size );
}

if ( 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
	$handle = fopen( $real, 'rb' );

	if ( is_resource( $handle ) ) {
		fpassthru( $handle );
		fclose( $handle );
	}
}

return true;
