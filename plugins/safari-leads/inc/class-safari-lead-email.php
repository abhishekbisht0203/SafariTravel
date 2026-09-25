<?php
/**
 * Email notifications for the lead system.
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Sends admin notification and visitor auto-reply emails.
 */
final class Safari_Lead_Email {

	public static function init(): void {
		// Ensure wp_mail uses HTML content type for our emails.
		// We add/remove this filter per-send so it does not affect other plugins.
	}

	// ── Admin notification ────────────────────────────────────────────────

	/**
	 * Send admin notification for a newly created lead.
	 *
	 * @param int $lead_id Lead ID.
	 */
	public static function send_admin_notification(int $lead_id): void {
		$lead = Safari_Lead_DB::get_lead($lead_id);
		if (! $lead) {
			return;
		}

		$recipients = self::resolve_recipients($lead);
		if (empty($recipients)) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: lead ID, 2: destination or source form */
			__('[Safari Travel] New inquiry #%1$d — %2$s', 'safari-leads'),
			$lead_id,
			esc_html(self::lead_destination_label($lead))
		);

		$body = self::build_admin_email_body($lead, $lead_id);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . esc_html($lead->name) . ' <' . sanitize_email($lead->email) . '>',
		];

		$sent = wp_mail($recipients, $subject, $body, $headers);

		// Log the attempt regardless of outcome.
		$note = $sent
			? __('Admin notification sent.', 'safari-leads')
			: __('Admin notification FAILED — please check SMTP settings.', 'safari-leads');
		Safari_Lead_DB::add_note($lead_id, 0, $note, 'email_sent');
	}

	/**
	 * Send visitor auto-reply if configured.
	 *
	 * @param int $lead_id Lead ID.
	 */
	public static function send_auto_reply(int $lead_id): void {
		if (! (bool) Safari_Lead_Settings::get('auto_reply')) {
			return;
		}

		$lead = Safari_Lead_DB::get_lead($lead_id);
		if (! $lead) {
			return;
		}

		$subject_tpl = (string) Safari_Lead_Settings::get('auto_reply_subject');
		$body_tpl    = (string) Safari_Lead_Settings::get('auto_reply_message');

		$subject = self::interpolate($subject_tpl, $lead);
		$body_text = self::interpolate($body_tpl, $lead);

		// Wrap plain text in minimal HTML email.
		$body    = self::build_plain_email_body($body_text);
		$headers = ['Content-Type: text/html; charset=UTF-8'];

		$sent = wp_mail(sanitize_email($lead->email), wp_strip_all_tags($subject), $body, $headers);

		$note = $sent
			? __('Auto-reply sent to visitor.', 'safari-leads')
			: __('Auto-reply FAILED.', 'safari-leads');
		Safari_Lead_DB::add_note($lead_id, 0, $note, 'email_sent');
	}

	/**
	 * Resend admin notification (called from the single-lead admin screen).
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public static function resend_notification(int $lead_id): bool {
		$lead = Safari_Lead_DB::get_lead($lead_id);
		if (! $lead) {
			return false;
		}
		self::send_admin_notification($lead_id);
		return true;
	}

	// ── Recipient routing ─────────────────────────────────────────────────

	/**
	 * Resolve email recipients using routing rules.
	 *
	 * Routing rules format (one per line):
	 *   keyword:email1@example.com,email2@example.com
	 *
	 * Example:
	 *   Kenya:africa@safarico.com
	 *   Tanzania:africa@safarico.com
	 *   default:leads@safarico.com
	 *
	 * @param object $lead Lead row object.
	 * @return string[] Array of email addresses.
	 */
	private static function resolve_recipients(object $lead): array {
		$default  = (string) Safari_Lead_Settings::get('notify_emails');
		$rules_raw = (string) Safari_Lead_Settings::get('routing_rules');

		if ('' === $rules_raw) {
			return self::parse_email_list($default);
		}

		$destination_label = strtolower(self::lead_destination_label($lead));
		$lines             = preg_split('/\r?\n/', trim($rules_raw)) ?: [];

		$default_list = self::parse_email_list($default);

		foreach ($lines as $line) {
			$line = trim($line);
			if ('' === $line || str_starts_with($line, '#')) {
				continue;
			}
			[$keyword, $emails] = array_pad(explode(':', $line, 2), 2, '');
			$keyword = strtolower(trim($keyword));
			if ('default' === $keyword) {
				$default_list = self::parse_email_list($emails);
				continue;
			}
			if ('' !== $keyword && str_contains($destination_label, $keyword)) {
				return self::parse_email_list($emails);
			}
		}

		return $default_list;
	}

	/**
	 * @return string[]
	 */
	private static function parse_email_list(string $raw): array {
		$emails = array_filter(
			array_map('trim', explode(',', $raw)),
			static fn(string $e) => is_email($e)
		);
		return array_values($emails);
	}

	// ── Email body builders ────────────────────────────────────────────────

	/**
	 * Build the rich HTML admin notification body.
	 *
	 * @param object $lead     Lead row.
	 * @param int    $lead_id  Lead ID.
	 * @return string HTML body.
	 */
	private static function build_admin_email_body(object $lead, int $lead_id): string {
		$admin_url = admin_url('admin.php?page=safari-leads-view&id=' . $lead_id);
		$status    = Safari_Lead_DB::STATUSES[$lead->status] ?? ucfirst($lead->status);

		$rows = [
			__('Lead ID', 'safari-leads')    => '#' . $lead_id,
			__('Name', 'safari-leads')        => esc_html($lead->name),
			__('Email', 'safari-leads')       => '<a href="mailto:' . esc_attr($lead->email) . '">' . esc_html($lead->email) . '</a>',
			__('Phone', 'safari-leads')       => $lead->phone ? '<a href="tel:' . esc_attr((string) $lead->phone) . '">' . esc_html((string) $lead->phone) . '</a>' : '—',
			__('Destination', 'safari-leads') => esc_html(self::lead_destination_label($lead)),
			__('Dates', 'safari-leads')       => self::format_dates($lead),
			__('Travellers', 'safari-leads')  => self::format_travellers($lead),
			__('Budget', 'safari-leads')      => $lead->budget_range ? esc_html(str_replace(['_', 'over', 'under'], [' - ', '> ', '< '], (string) $lead->budget_range)) : '—',
			__('Style', 'safari-leads')       => $lead->travel_style ? esc_html((string) $lead->travel_style) : '—',
			__('Message', 'safari-leads')     => $lead->message ? nl2br(esc_html((string) $lead->message)) : '—',
			__('Source', 'safari-leads')      => esc_html($lead->source_form),
			__('Status', 'safari-leads')      => esc_html($status),
			__('Received', 'safari-leads')    => esc_html($lead->created_at),
		];

		$table_rows = '';
		foreach ($rows as $label => $value) {
			$table_rows .= '<tr><th style="text-align:left;padding:6px 12px;background:#f9f4ef;border:1px solid #e8e0d4;white-space:nowrap;">' . esc_html($label) . '</th><td style="padding:6px 12px;border:1px solid #e8e0d4;">' . $value . '</td></tr>';
		}

		$site_name = esc_html(get_bloginfo('name'));

		return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head><body style="font-family:\'Inter\',system-ui,sans-serif;color:#1F1B16;background:#F4ECDF;padding:0;margin:0;">'
			. '<div style="max-width:620px;margin:32px auto;background:#FBF8F3;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">'
			. '<div style="background:#3D4A2F;color:#F4ECDF;padding:24px 32px;">'
			. '<h1 style="margin:0;font-size:20px;font-weight:700;">' . $site_name . ' — ' . esc_html__('New Inquiry', 'safari-leads') . ' #' . $lead_id . '</h1>'
			. '</div>'
			. '<div style="padding:24px 32px;">'
			. '<table style="width:100%;border-collapse:collapse;margin-bottom:24px;">' . $table_rows . '</table>'
			. '<p style="text-align:center;margin:0;">'
			. '<a href="' . esc_url($admin_url) . '" style="display:inline-block;background:#C8762B;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;">'
			. esc_html__('Open Lead in Admin', 'safari-leads')
			. '</a></p>'
			. '</div>'
			. '<div style="padding:16px 32px;border-top:1px solid #e8e0d4;font-size:12px;color:#6E655A;">'
			. sprintf(esc_html__('This email was sent by %s lead management system.', 'safari-leads'), esc_html($site_name))
			. '</div></div></body></html>';
	}

	/**
	 * Wrap plain text in a minimal branded HTML email.
	 *
	 * @param string $text_body Plain text message (already interpolated).
	 * @return string HTML body.
	 */
	private static function build_plain_email_body(string $text_body): string {
		$site_name = esc_html(get_bloginfo('name'));
		$html_body = nl2br(esc_html($text_body));

		return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"></head>'
			. '<body style="font-family:\'Inter\',system-ui,sans-serif;color:#1F1B16;background:#F4ECDF;padding:0;margin:0;">'
			. '<div style="max-width:580px;margin:32px auto;background:#FBF8F3;border-radius:12px;overflow:hidden;">'
			. '<div style="background:#3D4A2F;color:#F4ECDF;padding:24px 32px;"><h1 style="margin:0;font-size:18px;">' . $site_name . '</h1></div>'
			. '<div style="padding:24px 32px;line-height:1.7;">' . $html_body . '</div>'
			. '<div style="padding:16px 32px;border-top:1px solid #e8e0d4;font-size:12px;color:#6E655A;">'
			. esc_html(home_url('/'))
			. '</div></div></body></html>';
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Interpolate {placeholders} in a string with lead data.
	 *
	 * @param string $template  Template string with {name}, {email} etc.
	 * @param object $lead      Lead row object.
	 * @return string
	 */
	private static function interpolate(string $template, object $lead): string {
		$map = [
			'{name}'        => esc_html($lead->name),
			'{email}'       => esc_html($lead->email),
			'{destination}' => esc_html(self::lead_destination_label($lead)),
			'{site_name}'   => esc_html(get_bloginfo('name')),
			'{site_url}'    => esc_url(home_url('/')),
		];
		return str_replace(array_keys($map), array_values($map), $template);
	}

	/**
	 * Human-readable destination label for a lead.
	 *
	 * @param object $lead Lead row.
	 * @return string
	 */
	private static function lead_destination_label(object $lead): string {
		if (! empty($lead->destination_id)) {
			$title = get_the_title((int) $lead->destination_id);
			if ($title) {
				return $title;
			}
		}
		if (! empty($lead->destination_text)) {
			return (string) $lead->destination_text;
		}
		return (string) $lead->source_form;
	}

	/**
	 * Format travel dates for display.
	 *
	 * @param object $lead Lead row.
	 * @return string
	 */
	private static function format_dates(object $lead): string {
		if (! empty($lead->dates_flexible)) {
			return __('Flexible', 'safari-leads');
		}
		$from = ! empty($lead->date_from) ? esc_html((string) $lead->date_from) : '';
		$to   = ! empty($lead->date_to)   ? esc_html((string) $lead->date_to)   : '';
		if ($from && $to) {
			return $from . ' → ' . $to;
		}
		return $from ?: $to ?: '—';
	}

	/**
	 * Format traveller counts for display.
	 *
	 * @param object $lead Lead row.
	 * @return string
	 */
	private static function format_travellers(object $lead): string {
		$parts = [];
		if (! empty($lead->adults)) {
			$parts[] = sprintf(
				/* translators: %d: number of adults */
				_n('%d adult', '%d adults', (int) $lead->adults, 'safari-leads'),
				(int) $lead->adults
			);
		}
		if (! empty($lead->children)) {
			$parts[] = sprintf(
				/* translators: %d: number of children */
				_n('%d child', '%d children', (int) $lead->children, 'safari-leads'),
				(int) $lead->children
			);
		}
		return implode(', ', $parts) ?: '—';
	}
}
