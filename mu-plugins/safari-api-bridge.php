<?php
/**
 * Plugin Name:       Safari API Bridge
 * Description:       Server-to-server endpoints the Laravel backend uses to mirror leads into the WordPress store. Registers nothing unless SAFARI_API_KEY is set, so a stock install is unaffected.
 * Version:           1.0.0
 * Requires PHP:      8.2
 * Requires at least: 6.4
 * Author:            Safari Travel
 * Text Domain:       safari-travel
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge between the Laravel backend and the WordPress lead store.
 *
 * This is the *only* new WordPress-side code the backend needs, and it is
 * deliberately inert by default:
 *
 *   - It registers no routes unless SAFARI_API_KEY is present in the
 *     environment, so an installation that has never heard of the API behaves
 *     exactly as it did before.
 *   - It never touches the public `safari/v1/leads` route. The existing intake
 *     endpoint, its validation and its emails are untouched.
 *   - It never reads or writes any other table.
 *
 * The Laravel backend is the caller. It authenticates with the shared key in
 * the `X-Safari-Api-Key` header, and only ever reaches WordPress over HTTP.
 */
final class Safari_API_Bridge {

	/**
	 * REST namespace. Kept separate from `safari/v1` so a future change to the
	 * public API can never collide with the private one.
	 */
	public const NAMESPACE = 'safari-api/v1';

	/**
	 * Shared-secret header.
	 */
	public const HEADER = 'X-Safari-Api-Key';

	/**
	 * Wire up the bridge.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( '' === self::shared_key() ) {
			// No key configured: the bridge does not exist.
			return;
		}

		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * The configured shared secret.
	 *
	 * @return string
	 */
	private static function shared_key(): string {
		$key = (string) getenv( 'SAFARI_API_KEY' );

		return '' !== $key ? $key : '';
	}

	/**
	 * Register the bridge routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'handle_create' ),
				'permission_callback' => array( self::class, 'authorize' ),
				'args'                => array(
					'name'              => array(
						'type'     => 'string',
						'required' => true,
					),
					'email'             => array(
						'type'     => 'string',
						'required' => true,
					),
					'wordpress_lead_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/leads/(?P<id>[\d]+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( self::class, 'handle_update' ),
				'permission_callback' => array( self::class, 'authorize' ),
				'args'                => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'handle_health' ),
				'permission_callback' => array( self::class, 'authorize' ),
			)
		);
	}

	/**
	 * Constant-time shared-secret check.
	 *
	 * WordPress accepts `true` or a WP_Error from a permission callback; the
	 * error is what produces the 401 body, so returning false instead would
	 * leak a generic "rest_forbidden" with no explanation.
	 *
	 * @return true|WP_Error
	 */
	public static function authorize(): bool|WP_Error {
		$provided = (string) ( $_SERVER['HTTP_X_SAFARI_API_KEY'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' === $provided || ! hash_equals( self::shared_key(), $provided ) ) {
			return new WP_Error(
				'safari_api_unauthorized',
				__( 'Invalid or missing API key.', 'safari-travel' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * POST /safari-api/v1/leads — mirror a lead captured by the API.
	 *
	 * Deliberately does not send email: the visitor has already been notified by
	 * whichever application accepted the submission, and mirroring exists only
	 * so the wp-admin lead list stays complete.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create( WP_REST_Request $request ) {
		if ( ! class_exists( 'Safari_Lead_DB' ) ) {
			return new WP_Error(
				'safari_api_missing_plugin',
				__( 'The safari-leads plugin is not active.', 'safari-travel' ),
				array( 'status' => 503 )
			);
		}

		$raw    = (array) $request->get_json_params();
		$source = (int) ( $raw['wordpress_lead_id'] ?? 0 );

		$columns = array(
			'name'              => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'email'             => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'phone'             => self::nullable_text( $raw['phone'] ?? null, 50 ),
			'destination_id'    => self::nullable_int( $raw['destination_id'] ?? null ),
			'tour_id'           => self::nullable_int( $raw['tour_id'] ?? null ),
			'destination_text'  => self::nullable_text( $raw['destination_text'] ?? null, 190 ),
			'travel_style'      => self::nullable_text( $raw['travel_style'] ?? null, 100 ),
			'date_from'         => self::nullable_date( $raw['date_from'] ?? null ),
			'date_to'           => self::nullable_date( $raw['date_to'] ?? null ),
			'dates_flexible'    => empty( $raw['dates_flexible'] ) ? 0 : 1,
			'adults'            => self::nullable_int( $raw['adults'] ?? null ),
			'children'          => self::nullable_int( $raw['children'] ?? null ),
			'budget_range'      => self::nullable_text( $raw['budget_range'] ?? null, 50 ),
			'subject'           => self::nullable_text( $raw['subject'] ?? null, 190 ),
			'message'           => self::nullable_textarea( $raw['message'] ?? null ),
			'source_form'       => sanitize_key( (string) ( $raw['source_form'] ?? 'contact' ) ),
			'source_url'        => self::nullable_url( $raw['source_url'] ?? null ),
			'utm_source'        => self::nullable_text( $raw['utm_source'] ?? null, 100 ),
			'utm_medium'        => self::nullable_text( $raw['utm_medium'] ?? null, 100 ),
			'utm_campaign'      => self::nullable_text( $raw['utm_campaign'] ?? null, 100 ),
			'referrer'          => self::nullable_url( $raw['referrer'] ?? null ),
			'consent_privacy'   => empty( $raw['consent_privacy'] ) ? 0 : 1,
			'consent_marketing' => empty( $raw['consent_marketing'] ) ? 0 : 1,
			// The API never forwards its IP hash: it is salted differently and
			// the WordPress store has no need for it.
			'ip_hash'           => null,
			'status'            => in_array( (string) ( $raw['status'] ?? '' ), array_keys( Safari_Lead_DB::STATUSES ), true )
				? (string) $raw['status']
				: 'new',
		);

		$lead_id = Safari_Lead_DB::insert_lead( $columns );

		if ( is_wp_error( $lead_id ) ) {
			return $lead_id;
		}

		$message = sprintf(
			/* translators: %d: originating API lead ID */
			__( 'Mirrored from the Laravel API (API lead #%d).', 'safari-travel' ),
			$source
		);

		Safari_Lead_DB::add_note( (int) $lead_id, 0, $message, 'system' );

		return new WP_REST_Response(
			array(
				'success'       => true,
				'lead_id'       => (int) $lead_id,
				'mirrored_from' => $source,
				'notifications' => false,
			),
			201
		);
	}

	/**
	 * PATCH /safari-api/v1/leads/{id} — mirror a status change.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update( WP_REST_Request $request ) {
		if ( ! class_exists( 'Safari_Lead_DB' ) ) {
			return new WP_Error(
				'safari_api_missing_plugin',
				__( 'The safari-leads plugin is not active.', 'safari-travel' ),
				array( 'status' => 503 )
			);
		}

		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );

		if ( ! array_key_exists( $status, Safari_Lead_DB::STATUSES ) ) {
			return new WP_Error(
				'safari_api_bad_status',
				__( 'Unknown lead status.', 'safari-travel' ),
				array( 'status' => 422 )
			);
		}

		$lead = Safari_Lead_DB::get_lead( $id );

		if ( ! $lead ) {
			return new WP_Error(
				'safari_api_not_found',
				__( 'Lead not found.', 'safari-travel' ),
				array( 'status' => 404 )
			);
		}

		$from = (string) $lead->status;

		Safari_Lead_DB::update_lead( $id, array( 'status' => $status ) );

		Safari_Lead_DB::add_note(
			$id,
			0,
			sprintf(
				/* translators: 1: previous status, 2: new status */
				__( 'Status mirrored from the Laravel API: %1$s -> %2$s.', 'safari-travel' ),
				$from,
				$status
			),
			'status_change'
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'lead_id'  => $id,
				'status'   => $status,
				'previous' => $from,
			),
			200
		);
	}

	/**
	 * GET /safari-api/v1/health — is the WordPress side ready to be called?
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_health(): WP_REST_Response {
		global $wpdb;

		$table = class_exists( 'Safari_Lead_DB' ) ? Safari_Lead_DB::table() : $wpdb->prefix . 'safari_leads';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return new WP_REST_Response(
			array(
				'bridge'   => true,
				'leads'    => class_exists( 'Safari_Lead_DB' ),
				'table'    => $table,
				'table_ok' => (string) $exists === $table,
				'php'      => PHP_VERSION,
			),
			200
		);
	}

	/**
	 * Sanitised text or null.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum length.
	 * @return string|null
	 */
	private static function nullable_text( $value, int $limit ) {
		$value = sanitize_text_field( (string) $value );

		return '' === $value ? null : mb_substr( $value, 0, $limit );
	}

	/**
	 * Sanitised textarea or null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function nullable_textarea( $value ) {
		$value = sanitize_textarea_field( (string) $value );

		return '' === $value ? null : $value;
	}

	/**
	 * Sanitised URL or null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function nullable_url( $value ) {
		$value = esc_url_raw( (string) $value );

		return '' === $value ? null : mb_substr( $value, 0, 500 );
	}

	/**
	 * Y-m-d date or null.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	private static function nullable_date( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return null;
		}

		$year = (int) gmdate( 'Y', $timestamp );

		if ( $year < 2000 || $year > (int) gmdate( 'Y' ) + 10 ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Clamped integer or null.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private static function nullable_int( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		return min( max( 0, (int) $value ), 999 );
	}
}

Safari_API_Bridge::init();
