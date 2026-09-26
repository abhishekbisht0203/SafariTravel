<?php
/**
 * Small HTTP download helper.
 *
 * Uses cURL when available and streams to a file, so large downloads (the
 * WordPress phar, the WP-CLI phar) never depend on `allow_url_fopen` or on
 * buffering the whole response in memory. Redirects are followed and HTTPS is
 * enforced for the known hosts.
 *
 * @package Safari_Travel\Tooling
 */

declare(strict_types=1);

namespace Safari\Tooling;

final class Download {

	/**
	 * Download a URL to a local file.
	 *
	 * @param string $url     Source URL.
	 * @param string $dest    Destination path.
	 * @param int    $timeout Timeout in seconds.
	 * @return bool True on success.
	 */
	public static function to( string $url, string $dest, int $timeout = 180 ): bool {
		if ( 0 !== strpos( $url, 'https://' ) && ! self::allowInsecure() ) {
			Console::error( 'Refusing to download over plain HTTP: ' . $url );
			return false;
		}

		ContentLinker::ensureDir( dirname( $dest ) );

		if ( function_exists( 'curl_init' ) ) {
			return self::viaCurl( $url, $dest, $timeout );
		}

		return self::viaStream( $url, $dest, $timeout );
	}

	/**
	 * cURL download.
	 *
	 * @param string $url     Source URL.
	 * @param string $dest    Destination path.
	 * @param int    $timeout Timeout.
	 * @return bool
	 */
	private static function viaCurl( string $url, string $dest, int $timeout ): bool {
		$handle = @fopen( $dest, 'wb' );

		if ( ! is_resource( $handle ) ) {
			Console::error( 'Cannot write to ' . $dest );
			return false;
		}

		$curl = curl_init( $url );

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_FILE           => $handle,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS      => 5,
				CURLOPT_CONNECTTIMEOUT => 20,
				CURLOPT_TIMEOUT        => $timeout,
				CURLOPT_USERAGENT      => 'safari-travel-setup',
				CURLOPT_FAILONERROR    => true,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			)
		);

		$ok  = curl_exec( $curl );
		$err = curl_error( $curl );
		$code = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );

		curl_close( $curl );
		fclose( $handle );

		if ( true !== $ok || $code >= 400 ) {
			Console::error( sprintf( 'Download failed (%d): %s %s', $code, $url, $err ) );
			@unlink( $dest );
			return false;
		}

		return true;
	}

	/**
	 * Stream-wrapper download used when cURL is unavailable.
	 *
	 * @param string $url     Source URL.
	 * @param string $dest    Destination path.
	 * @param int    $timeout Timeout.
	 * @return bool
	 */
	private static function viaStream( string $url, string $dest, int $timeout ): bool {
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'      => $timeout,
					'follow_location' => 1,
					'max_redirects' => 5,
					'user_agent'   => 'safari-travel-setup',
					'header'       => "Accept: */*\r\n",
				),
				'ssl'  => array(
					'verify_peer'      => true,
					'verify_peer_name' => true,
				),
			)
		);

		$source = @fopen( $url, 'rb', false, $context );

		if ( ! is_resource( $source ) ) {
			Console::error( 'Download failed: ' . $url );
			return false;
		}

		$dest_handle = @fopen( $dest, 'wb' );

		if ( ! is_resource( $dest_handle ) ) {
			fclose( $source );
			Console::error( 'Cannot write to ' . $dest );
			return false;
		}

		stream_copy_to_stream( $source, $dest_handle );

		fclose( $source );
		fclose( $dest_handle );

		return true;
	}

	/**
	 * Escape hatch for corporate mirrors that only offer HTTP.
	 *
	 * @return bool
	 */
	private static function allowInsecure(): bool {
		$flag = getenv( 'SAFARI_ALLOW_INSECURE_DOWNLOAD' );

		return ( false !== $flag && '' !== $flag && '0' !== $flag && 'false' !== strtolower( (string) $flag ) );
	}
}
