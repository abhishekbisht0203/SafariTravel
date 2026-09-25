<?php
/**
 * REST API endpoint for lead submission.
 *
 * POST /wp-json/safari/v1/leads
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * REST endpoint: public create, no public read.
 */
final class Safari_Lead_REST {

	public const NAMESPACE = 'safari/v1';
	public const ROUTE     = '/leads';

	public static function init(): void {
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	public static function register_routes(): void {
		register_rest_route(self::NAMESPACE, self::ROUTE, [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [self::class, 'handle_create'],
			'permission_callback' => '__return_true', // Public — spam protection is in the handler.
			'args'                => self::get_endpoint_args(),
		]);

		// Admin-only read endpoints.
		register_rest_route(self::NAMESPACE, self::ROUTE . '/(?P<id>[\d]+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [self::class, 'handle_get'],
			'permission_callback' => [self::class, 'check_view_permission'],
			'args'                => [
				'id' => [
					'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
					'sanitize_callback' => 'absint',
					'required'          => true,
				],
			],
		]);
	}

	/**
	 * REST argument schema for the create endpoint.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_endpoint_args(): array {
		return [
			'name'               => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'email'              => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email'],
			'phone'              => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'destination_id'     => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
			'tour_id'            => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
			'destination_text'   => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'date_from'          => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'date_to'            => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'dates_flexible'     => ['type' => 'boolean', 'required' => false],
			'adults'             => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
			'children'           => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
			'budget_range'       => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_key'],
			'travel_style'       => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_key'],
			'subject'            => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'message'            => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
			'source_form'        => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_key', 'default' => 'contact'],
			'source_url'         => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'esc_url_raw'],
			'utm_source'         => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'utm_medium'         => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'utm_campaign'       => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'referrer'           => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'esc_url_raw'],
			'consent_privacy'    => ['type' => 'boolean', 'required' => true],
			'consent_marketing'  => ['type' => 'boolean', 'required' => false, 'default' => false],
			// Spam protection fields.
			'_timestamp'         => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
			'website'            => ['type' => 'string', 'required' => false], // honeypot
			'cf_turnstile_token' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
			'_wpnonce'           => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
		];
	}

	/**
	 * Handle POST /safari/v1/leads
	 */
	public static function handle_create(WP_REST_Request $request): WP_REST_Response|WP_Error {
		// ── WP nonce (optional but recommended when JS is available) ──────
		$nonce = (string) $request->get_param('_wpnonce');
		if ('' !== $nonce && ! wp_verify_nonce($nonce, 'safari_lead_submit')) {
			return new WP_Error('safari_invalid_nonce', __('Security check failed. Please refresh the page and try again.', 'safari-leads'), ['status' => 403]);
		}

		$raw = (array) $request->get_params();

		// ── Spam: honeypot + timing ────────────────────────────────────────
		$sent_at = (int) ($raw['_timestamp'] ?? 0);
		$is_spam = Safari_Lead_Save::is_honeypot_spam($raw, $sent_at);

		// ── Spam: Turnstile ────────────────────────────────────────────────
		if (! $is_spam) {
			$token = sanitize_text_field((string) ($raw['cf_turnstile_token'] ?? ''));
			if (! Safari_Lead_Save::verify_turnstile($token)) {
				$is_spam = true;
			}
		}

		// ── Spam: Rate limit ──────────────────────────────────────────────
		if (! $is_spam && Safari_Lead_Save::is_rate_limited()) {
			return new WP_Error(
				'safari_rate_limited',
				__('Too many requests. Please wait a few minutes and try again.', 'safari-leads'),
				['status' => 429]
			);
		}

		// ── Validation (server-authoritative) ────────────────────────────
		$data = Safari_Lead_Save::validate($raw);
		if (is_wp_error($data)) {
			return new WP_Error(
				$data->get_error_code(),
				$data->get_error_message(),
				['status' => 422, 'fields' => $data->get_error_data()]
			);
		}

		// Mark spam — save anyway so real leads are never silently lost.
		if ($is_spam) {
			$data['status'] = 'spam';
		}

		// ── Save to DB (before sending email — plan §5.5 fail-safe) ───────
		$lead_id = Safari_Lead_DB::insert_lead($data);
		if (is_wp_error($lead_id)) {
			return new WP_Error('safari_lead_db_error', $lead_id->get_error_message(), ['status' => 500]);
		}

		// ── Auto-log initial note ──────────────────────────────────────────
		Safari_Lead_DB::add_note($lead_id, 0, sprintf(
			/* translators: %s: source form name */
			__('Lead created via %s form.', 'safari-leads'),
			$data['source_form']
		), 'system');

		// ── Send notifications (non-blocking — email failure != lost lead) ─
		if ('spam' !== $data['status']) {
			Safari_Lead_Email::send_admin_notification($lead_id);
			Safari_Lead_Email::send_auto_reply($lead_id);
		}

		// ── Response ──────────────────────────────────────────────────────
		$thank_you_page = (int) Safari_Lead_Settings::get('thank_you_page');
		$redirect_url   = $thank_you_page
			? get_permalink($thank_you_page)
			: home_url('/thank-you/');

		return new WP_REST_Response([
			'success'      => true,
			'lead_id'      => $lead_id,
			'redirect_url' => $redirect_url,
			'message'      => __('Thank you! We have received your inquiry and will be in touch shortly.', 'safari-leads'),
		], 201);
	}

	/**
	 * Handle GET /safari/v1/leads/:id (admin only).
	 */
	public static function handle_get(WP_REST_Request $request): WP_REST_Response|WP_Error {
		$lead = Safari_Lead_DB::get_lead((int) $request->get_param('id'));
		if (! $lead) {
			return new WP_Error('safari_lead_not_found', __('Lead not found.', 'safari-leads'), ['status' => 404]);
		}
		// Strip IP hash from response.
		$lead->ip_hash = null;
		return new WP_REST_Response($lead, 200);
	}

	/**
	 * Capability check for admin-only routes.
	 */
	public static function check_view_permission(): bool {
		return current_user_can('view_safari_leads');
	}
}
