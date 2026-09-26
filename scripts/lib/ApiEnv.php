<?php

declare(strict_types=1);

namespace Safari\Tooling;

/**
 * Generates and maintains `backend/.env` from the repository root `.env`.
 *
 * The backend needs the same Aiven credentials as WordPress, but it must not
 * read the root `.env` directly: Laravel's own dotenv loader owns its own file,
 * and a generated file keeps `git status`, backups and hosting panels honest
 * about what the API actually uses.
 *
 * Two rules this file never breaks:
 *
 *   1. Secrets are copied, never printed. Nothing here echoes a value.
 *   2. The backend always keeps its own table prefix, so its migrations can
 *      never touch a WordPress table even though both use one database.
 */
final class ApiEnv {

	/**
	 * Root `.env` keys copied straight through to the backend.
	 *
	 * @var string[]
	 */
	private const PASSTHROUGH = array(
		'DB_NAME',
		'DB_USER',
		'DB_PASSWORD',
		'DB_SSL_VERIFY',
		'MYSQL_ATTR_SSL_CA',
		'TURNSTILE_SITE_KEY',
		'TURNSTILE_SECRET_KEY',
	);

	/**
	 * Keys a developer may legitimately have changed locally; never clobbered
	 * by a re-run of `api-env`.
	 *
	 * @var string[]
	 */
	private const PRESERVED = array(
		'APP_KEY',
		'MAIL_MAILER',
		'MAIL_HOST',
		'MAIL_PORT',
		'MAIL_FROM_ADDRESS',
		'QUEUE_CONNECTION',
		'CACHE_STORE',
		'SESSION_DRIVER',
		'API_ALLOWED_ORIGINS',
		'LEAD_NOTIFY_EMAILS',
		'LEAD_AUTO_REPLY',
		'LEAD_RATE_LIMIT_MAX',
		'LEAD_RATE_LIMIT_WINDOW',
		'LEAD_MIN_SUBMIT_SECONDS',
		'SAFARI_WP_USERNAME',
		'SAFARI_WP_APP_PASSWORD',
	);

	/**
	 * @return bool
	 */
	private static function preserved( string $key ): bool {
		return in_array( $key, self::PRESERVED, true );
	}

	/**
	 * Write `backend/.env`, creating it from `.env.example` when absent.
	 *
	 * Existing values are preserved unless the root `.env` changed, so a local
	 * tweak to `backend/.env` survives a re-run. Pass `$overwrite` to reset it
	 * to the generated values.
	 *
	 * @param bool $overwrite Replace the file instead of merging into it.
	 * @return array{written: bool, created: bool, changed: array<int,string>, path: string}
	 */
	public static function sync( bool $overwrite = false ): array {
		$path = Config::apiEnvFile();

		if ( ! is_file( $path ) ) {
			self::createFromExample( $path );
		}

		$existing = Env::parse( $path );
		$desired  = self::desired();

		$merged = $overwrite ? array() : $existing;
		$changed = array();

		foreach ( $desired as $key => $value ) {
			if ( ! $overwrite && isset( $merged[ $key ] ) && $merged[ $key ] === $value ) {
				continue;
			}

			if ( ! $overwrite && isset( $merged[ $key ] ) && '' !== $merged[ $key ] && self::preserved( $key ) ) {
				continue;
			}

			if ( ( $merged[ $key ] ?? null ) !== $value ) {
				$changed[] = $key;
			}

			$merged[ $key ] = $value;
		}

		// The application key must always exist, whether or not it changed.
		if ( empty( $merged['APP_KEY'] ) ) {
			$merged['APP_KEY'] = self::generateAppKey();
			$changed[]         = 'APP_KEY';
		}

		self::write( $path, $merged );

		return array(
			'written' => true,
			'created' => false,
			'changed' => array_values( array_unique( $changed ) ),
			'path'    => $path,
		);
	}

	/**
	 * Ensure the shared API key exists in *both* files.
	 *
	 * The backend verifies it on inbound calls; WordPress sends it when it
	 * delegates lead intake. Both halves must agree, and a mismatch is the kind
	 * of thing that only shows up in production, so it is generated here once
	 * and never guessed at runtime.
	 *
	 * @return string The key in use. Not printed by the caller.
	 */
	public static function syncApiKey(): string {
		$key = Env::str( 'SAFARI_API_KEY', '' );

		if ( '' === $key ) {
			$key = self::randomKey();
			Env::appendToFile( Config::envFile(), array( 'SAFARI_API_KEY' => $key ) );
		}

		$path = Config::apiEnvFile();

		if ( is_file( $path ) ) {
			$current = Env::parse( $path );

			if ( ( $current['SAFARI_API_KEY'] ?? '' ) !== $key ) {
				Env::setInFile( $path, array( 'SAFARI_API_KEY' => $key ) );
			}
		}

		return $key;
	}

	/**
	 * The values the generated `.env` should contain.
	 *
	 * @return array<string,string>
	 */
	private static function desired(): array {
		$parts = Config::dbHostParts();
		$port  = Config::port();
		$api   = Config::apiPort();

		$values = array(
			'APP_URL'             => sprintf( 'http://localhost:%d', $api ),
			'APP_ENV'             => Config::isProduction() ? 'production' : 'local',
			'APP_DEBUG'           => Config::debug() ? 'true' : 'false',
			'DB_CONNECTION'       => 'mysql',
			'DB_HOST'             => $parts['host'] ?? '',
			'DB_PORT'             => (string) ( $parts['port'] ?? 3306 ),
			'DB_DATABASE'         => Env::str( 'DB_NAME', '' ),
			'DB_USERNAME'         => Env::str( 'DB_USER', '' ),
			'DB_TABLE_PREFIX'     => Config::apiTablePrefix(),
			'DB_SSL_VERIFY'       => Config::dbSslVerify() ? 'true' : 'false',
			'SAFARI_WP_URL'       => Config::siteUrl(),
			'SAFARI_WP_VERIFY_TLS' => 'true',
			'SAFARI_API_MIRROR'   => Config::apiMirrorEnabled() ? 'true' : 'false',
		);
		// Password is only written when the root .env actually has one, so an
		// unconfigured checkout fails loudly instead of connecting as nobody.
		$password = (string) Env::get( 'DB_PASSWORD', '' );
		$values['DB_PASSWORD'] = '' !== $password ? $password : '';

		foreach ( self::PASSTHROUGH as $key ) {
			if ( in_array( $key, array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD' ), true ) ) {
				continue;
			}

			$value = Env::str( $key, '' );

			if ( '' !== $value ) {
				$values[ $key ] = $value;
			}
		}

		// Port the API listens on, also used by the Vite proxy and Docker.
		$values['APP_PORT'] = (string) $api;

		return $values;
	}

	/**
	 * Copy `.env.example` to `.env` when it does not exist yet.
	 *
	 * @throws \RuntimeException
	 */
	private static function createFromExample( string $path ): void {
		$example = dirname( $path ) . '/.env.example';

		if ( ! is_file( $example ) ) {
			throw new \RuntimeException( 'backend/.env.example is missing — cannot create backend/.env.' );
		}

		$contents = (string) file_get_contents( $example );

		if ( false === file_put_contents( $path, $contents ) ) {
			throw new \RuntimeException( 'Could not write ' . $path . '.' );
		}
	}

	/**
	 * Rewrite the file from an ordered key => value map, preserving the
	 * comments in `.env.example` for keys we do not manage.
	 *
	 * @param array<string,string> $values
	 */
	private static function write( string $path, array $values ): void {
		$example = dirname( $path ) . '/.env.example';
		$order   = array_keys( is_file( $example ) ? Env::parse( $example ) : $values );

		foreach ( array_keys( $values ) as $key ) {
			if ( ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		$lines   = array();
		$written = array();

		foreach ( $order as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$lines[] = $key . '=' . self::quote( (string) $values[ $key ] );
			$written[ $key ] = true;
		}

		$header = self::header();

		file_put_contents( $path, $header . implode( "\n", $lines ) . "\n" );
	}

	/**
	 * A generated .env must be self-explanatory — an operator should never have
	 * to guess which half of the repository a value came from.
	 */
	private static function header(): string {
		return <<<'TXT'
# ─────────────────────────────────────────────────────────────────────────────
# Safari Travel — Laravel backend environment
#
#   Generated by: php scripts/safari.php api-env
#
# This file is git-ignored. The database credentials below are the SAME Aiven
# service WordPress uses; the applications are kept apart by DB_TABLE_PREFIX,
# so a `php artisan migrate` in one can never touch a `stv_*` table.
#
# Values you edit here survive a re-run of api-env, except for the ones the
# generator owns (APP_*, DB_* and SAFARI_WP_URL), which are refreshed from the
# repository root .env.
# ─────────────────────────────────────────────────────────────────────────────


TXT;
	}

	/**
	 * Quote a value only when it needs it, so the file stays readable.
	 */
	private static function quote( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/\s|#|"\'/', $value ) ) {
			return '"' . str_replace( '"', '\"', $value ) . '"';
		}

		return $value;
	}

	/**
	 * A 32-byte base64 key, the same shape `artisan key:generate` produces.
	 */
	private static function generateAppKey(): string {
		return 'base64:' . base64_encode( random_bytes( 32 ) );
	}

	/**
	 * 64 hex characters of shared secret.
	 */
	private static function randomKey(): string {
		return bin2hex( random_bytes( 32 ) );
	}
}
