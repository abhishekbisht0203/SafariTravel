<?php
/**
 * Lead save / validation / privacy integrations.
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Core business logic for storing and managing leads.
 */
final class Safari_Lead_Save {

	/**
	 * Wire up hooks.
	 */
	public static function init(): void {
		add_action('safari_leads_retention_cron', [self::class, 'purge_expired']);
	}

	// ── Validation ────────────────────────────────────────────────────────

	/**
	 * Validate and sanitize raw form input.
	 *
	 * @param array<string, mixed> $raw POST data.
	 * @return array<string, mixed>|WP_Error Sanitized data or validation error.
	 */
	public static function validate(array $raw): array|WP_Error {
		$errors = [];

		// ── Required fields ──────────────────────────────────────────────
		$name = sanitize_text_field((string) ($raw['name'] ?? ''));
		if ('' === $name) {
			$errors['name'] = __('Please enter your name.', 'safari-leads');
		} elseif (mb_strlen($name) > 190) {
			$errors['name'] = __('Name is too long (max 190 characters).', 'safari-leads');
		}

		$email = sanitize_email((string) ($raw['email'] ?? ''));
		if ('' === $email || ! is_email($email)) {
			$errors['email'] = __('Please enter a valid email address.', 'safari-leads');
		}

		$consent_privacy = ! empty($raw['consent_privacy']) ? 1 : 0;
		if (! $consent_privacy) {
			$errors['consent_privacy'] = __('You must agree to the Privacy Policy.', 'safari-leads');
		}

		// ── Source form ──────────────────────────────────────────────────
		$allowed_forms = ['plan_my_safari', 'tour_page', 'contact', 'popup', 'event', 'product'];
		$source_form   = sanitize_key((string) ($raw['source_form'] ?? 'contact'));
		if (! in_array($source_form, $allowed_forms, true)) {
			$source_form = 'contact';
		}

		// ── Optional fields ──────────────────────────────────────────────
		$phone = sanitize_text_field((string) ($raw['phone'] ?? ''));
		if (mb_strlen($phone) > 50) {
			$phone = mb_substr($phone, 0, 50);
		}

		$destination_id = absint($raw['destination_id'] ?? 0);
		$tour_id        = absint($raw['tour_id'] ?? 0);

		$destination_text = sanitize_text_field((string) ($raw['destination_text'] ?? ''));
		if (mb_strlen($destination_text) > 190) {
			$destination_text = mb_substr($destination_text, 0, 190);
		}

		// Validate date_from / date_to.
		$date_from = self::sanitize_date((string) ($raw['date_from'] ?? ''));
		$date_to   = self::sanitize_date((string) ($raw['date_to'] ?? ''));

		$adults   = $raw['adults'] ?? null;
		$children = $raw['children'] ?? null;
		if (null !== $adults) {
			$adults = min(max(0, (int) $adults), 999);
		}
		if (null !== $children) {
			$children = min(max(0, (int) $children), 999);
		}

		$allowed_budgets = ['under_2000', '2000_5000', '5000_10000', '10000_20000', 'over_20000', ''];
		$budget_range    = sanitize_key((string) ($raw['budget_range'] ?? ''));
		if (! in_array($budget_range, $allowed_budgets, true)) {
			$budget_range = '';
		}

		$allowed_styles = ['luxury', 'mid-range', 'budget', ''];
		$travel_style   = sanitize_key((string) ($raw['travel_style'] ?? ''));
		if (! in_array($travel_style, $allowed_styles, true)) {
			$travel_style = '';
		}

		$subject = sanitize_text_field((string) ($raw['subject'] ?? ''));
		if (mb_strlen($subject) > 190) {
			$subject = mb_substr($subject, 0, 190);
		}

		$message = sanitize_textarea_field((string) ($raw['message'] ?? ''));

		if (! empty($errors)) {
			return new WP_Error('safari_lead_validation', __('Please correct the highlighted fields.', 'safari-leads'), $errors);
		}

		return [
			'name'              => $name,
			'email'             => $email,
			'phone'             => $phone !== '' ? $phone : null,
			'destination_id'    => $destination_id ?: null,
			'tour_id'           => $tour_id ?: null,
			'destination_text'  => $destination_text !== '' ? $destination_text : null,
			'date_from'         => $date_from ?: null,
			'date_to'           => $date_to ?: null,
			'dates_flexible'    => ! empty($raw['dates_flexible']) ? 1 : 0,
			'adults'            => $adults,
			'children'          => $children,
			'budget_range'      => $budget_range !== '' ? $budget_range : null,
			'travel_style'      => $travel_style !== '' ? $travel_style : null,
			'subject'           => $subject !== '' ? $subject : null,
			'message'           => $message !== '' ? $message : null,
			'source_form'       => $source_form,
			'source_url'        => esc_url_raw(sanitize_text_field((string) ($raw['source_url'] ?? ''))),
			'utm_source'        => sanitize_text_field(mb_substr((string) ($raw['utm_source'] ?? ''), 0, 100)),
			'utm_medium'        => sanitize_text_field(mb_substr((string) ($raw['utm_medium'] ?? ''), 0, 100)),
			'utm_campaign'      => sanitize_text_field(mb_substr((string) ($raw['utm_campaign'] ?? ''), 0, 100)),
			'referrer'          => esc_url_raw(mb_substr(sanitize_text_field((string) ($raw['referrer'] ?? '')), 0, 500)),
			'consent_privacy'   => $consent_privacy,
			'consent_marketing' => ! empty($raw['consent_marketing']) ? 1 : 0,
			'ip_hash'           => self::hash_ip(),
			'status'            => 'new',
		];
	}

	/**
	 * Sanitize a date string to Y-m-d or return ''.
	 */
	private static function sanitize_date(string $date): string {
		$date = sanitize_text_field($date);
		if ('' === $date) {
			return '';
		}
		$ts = strtotime($date);
		if (false === $ts) {
			return '';
		}
		$formatted = date('Y-m-d', $ts);
		// Reject obviously invalid dates (e.g. before 2000 or more than 10 years ahead).
		$year = (int) date('Y', $ts);
		if ($year < 2000 || $year > (int) date('Y') + 10) {
			return '';
		}
		return $formatted;
	}

	/**
	 * SHA-256 hash of the visitor IP, salted with WP auth salt.
	 * Never stored raw — GDPR-friendly.
	 */
	private static function hash_ip(): string {
		$ip   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		$salt = (string) wp_salt('auth');
		return hash('sha256', $ip . $salt);
	}

	// ── Spam checks ───────────────────────────────────────────────────────

	/**
	 * Check honeypot + timing.
	 *
	 * @param array<string, mixed> $raw     Raw POST data.
	 * @param int                  $sent_at Unix timestamp from the form (nonce-protected field).
	 * @return bool True = spam detected.
	 */
	public static function is_honeypot_spam(array $raw, int $sent_at): bool {
		// Honeypot field must be empty.
		if (! empty($raw['website'])) {
			return true;
		}
		// Minimum time-to-submit (bots are fast).
		$min = (int) Safari_Lead_Settings::get('min_submit_seconds');
		if ($min > 0 && (time() - $sent_at) < $min) {
			return true;
		}
		return false;
	}

	/**
	 * Rate-limit check per IP hash (transient-based).
	 *
	 * @return bool True = limit exceeded.
	 */
	public static function is_rate_limited(): bool {
		$ip_hash = self::hash_ip();
		$key     = 'safari_lead_rl_' . $ip_hash;
		$count   = (int) get_transient($key);
		$max     = (int) Safari_Lead_Settings::get('rate_limit_max');
		$window  = (int) Safari_Lead_Settings::get('rate_limit_window');

		if ($count >= $max) {
			return true;
		}

		set_transient($key, $count + 1, $window);
		return false;
	}

	// ── Turnstile verification ────────────────────────────────────────────

	/**
	 * Verify a Cloudflare Turnstile token.
	 *
	 * @param string $token Token from the form field `cf-turnstile-response`.
	 * @return bool True = valid.
	 */
	public static function verify_turnstile(string $token): bool {
		$secret = (string) Safari_Lead_Settings::get('turnstile_secret_key');
		if ('' === $secret || '' === $token) {
			// Turnstile not configured — skip verification.
			return true;
		}

		$response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
			],
		]);

		if (is_wp_error($response)) {
			// Network failure → fail open (save as spam, not rejected silently).
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		return is_array($body) && ! empty($body['success']);
	}

	// ── Privacy: Exporter / Eraser ────────────────────────────────────────

	/**
	 * Register the privacy data exporter.
	 *
	 * @param array<int, array<string, mixed>> $exporters Registered exporters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_exporter(array $exporters): array {
		$exporters[] = [
			'exporter_friendly_name' => __('Safari Travel Leads', 'safari-leads'),
			'callback'               => [self::class, 'export_leads_for_email'],
		];
		return $exporters;
	}

	/**
	 * Export personal data for a given email address.
	 *
	 * @param string $email     Email address to export.
	 * @param int    $page      Page number (pagination not needed here; WP passes it).
	 * @return array<string, mixed>
	 */
	public static function export_leads_for_email(string $email, int $page = 1): array {
		global $wpdb;

		$table = Safari_Lead_DB::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$leads = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", sanitize_email($email))
		);

		$data = [];
		foreach ((array) $leads as $lead) {
			$data[] = [
				'group_id'          => 'safari-lead',
				'group_label'       => __('Safari Travel — Inquiry', 'safari-leads'),
				'item_id'           => 'lead-' . (int) $lead->id,
				'data'              => [
					['name' => __('Name', 'safari-leads'),    'value' => esc_html($lead->name)],
					['name' => __('Email', 'safari-leads'),   'value' => esc_html($lead->email)],
					['name' => __('Phone', 'safari-leads'),   'value' => esc_html((string) $lead->phone)],
					['name' => __('Message', 'safari-leads'), 'value' => esc_html((string) $lead->message)],
					['name' => __('Date', 'safari-leads'),    'value' => esc_html($lead->created_at)],
					['name' => __('Status', 'safari-leads'),  'value' => esc_html($lead->status)],
				],
			];
		}

		return [
			'data' => $data,
			'done' => true,
		];
	}

	/**
	 * Register the privacy data eraser.
	 *
	 * @param array<int, array<string, mixed>> $erasers Registered erasers.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_eraser(array $erasers): array {
		$erasers[] = [
			'eraser_friendly_name' => __('Safari Travel Leads', 'safari-leads'),
			'callback'             => [self::class, 'erase_leads_for_email'],
		];
		return $erasers;
	}

	/**
	 * Anonymise personal data for a given email address.
	 *
	 * @param string $email Email address to erase.
	 * @param int    $page  Page number.
	 * @return array<string, mixed>
	 */
	public static function erase_leads_for_email(string $email, int $page = 1): array {
		global $wpdb;

		$table = Safari_Lead_DB::table();
		// Anonymise: replace identifying fields, keep for reporting.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			[
				'name'    => __('[Anonymised]', 'safari-leads'),
				'email'   => 'anonymised@example.invalid',
				'phone'   => null,
				'message' => null,
				'ip_hash' => null,
			],
			['email' => sanitize_email($email)]
		);

		return [
			'items_removed'  => (false !== $result) ? (int) $result : 0,
			'items_retained' => 0,
			'messages'       => [],
			'done'           => true,
		];
	}

	// ── Retention cron ────────────────────────────────────────────────────

	/**
	 * Delete or anonymise closed leads older than the configured retention period.
	 * Hooked to `safari_leads_retention_cron` (daily).
	 */
	public static function purge_expired(): void {
		global $wpdb;

		$months = (int) Safari_Lead_Settings::get('retention_months');
		if ($months < 1) {
			return;
		}

		$cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$months} months"));
		$table  = Safari_Lead_DB::table();

		// Anonymise (not hard-delete) to preserve aggregate reporting.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET name = %s, email = %s, phone = NULL, message = NULL, ip_hash = NULL
				 WHERE created_at < %s
				   AND status IN ('closed_won','closed_lost','spam')
				   AND email != %s",
				__('[Anonymised]', 'safari-leads'),
				'anonymised@example.invalid',
				$cutoff,
				'anonymised@example.invalid'
			)
		);
	}
}
