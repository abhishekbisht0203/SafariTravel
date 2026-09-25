<?php
/**
 * Plugin settings for Safari Leads.
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings API wrapper around the `safari_leads_settings` option.
 */
final class Safari_Lead_Settings {

	/**
	 * Option name where all lead settings are stored as a single array.
	 *
	 * @var string
	 */
	const OPTION = 'safari_leads_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$defaults = array(
			'currency'             => 'USD',
			'notify_emails'        => get_option( 'admin_email' ),
			'routing_rules'        => '',
			'auto_reply'           => 0,
			'auto_reply_subject'   => __( 'Thanks for contacting Safari Travel', 'safari-leads' ),
			'auto_reply_message'   => __( "Hi {name},\r\n\r\nThanks for getting in touch. We have received your enquiry and one of our safari specialists will reply within one business day.\r\n\r\nWarm regards,\r\nThe Safari Travel team", 'safari-leads' ),
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
			'min_submit_seconds'   => 3,
			'rate_limit_max'       => 5,
			'rate_limit_window'    => 600,
			'retention_months'     => 24,
			'thank_you_page'       => 0,
			'status_labels'        => '',
			'enable_intl_tel'      => 1,
			'telegram_webhook'     => '',
		);

		/**
		 * Filters the default Safari Leads settings.
		 *
		 * @param array<string, mixed> $defaults Default settings keyed by option key.
		 */
		return (array) apply_filters( 'safari_leads_default_settings', $defaults );
	}

	/**
	 * All stored settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/**
	 * Retrieve a single setting by key.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$settings = self::all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Persist the defaults for any keys that are not yet stored.
	 *
	 * Used on activation so the settings page always has something to show.
	 */
	public static function merge_defaults(): void {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$merged = wp_parse_args( $stored, self::defaults() );

		if ( $merged !== $stored ) {
			update_option( self::OPTION, $merged, false );
		}
	}

	/**
	 * Register the option with the Settings API.
	 *
	 * Hooks itself to `admin_init` so the bootstrap does not need to.
	 *
	 * @return array<string, mixed> The registered setting arguments.
	 */
	public static function register(): array {
		add_action(
			'admin_init',
			static function (): void {
				register_setting(
					'safari_leads_group',
					self::OPTION,
					array(
						'type'              => 'array',
						'sanitize_callback' => array( self::class, 'sanitize' ),
						'default'           => self::defaults(),
					)
				);
			}
		);

		return array();
	}

	/**
	 * Sanitize the settings array before it is saved.
	 *
	 * @param array<string, mixed> $input Raw input from the settings form.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$settings = wp_parse_args( $input, self::defaults() );

		$settings['notify_emails']        = self::sanitize_email_list( (string) $settings['notify_emails'] );
		$settings['routing_rules']        = sanitize_textarea_field( (string) $settings['routing_rules'] );
		$settings['auto_reply_subject']   = sanitize_text_field( (string) $settings['auto_reply_subject'] );
		$settings['auto_reply_message']   = sanitize_textarea_field( (string) $settings['auto_reply_message'] );
		$settings['turnstile_site_key']   = sanitize_text_field( (string) $settings['turnstile_site_key'] );
		$settings['turnstile_secret_key'] = sanitize_text_field( (string) $settings['turnstile_secret_key'] );
		$settings['status_labels']        = sanitize_textarea_field( (string) $settings['status_labels'] );
		$settings['telegram_webhook']     = esc_url_raw( (string) $settings['telegram_webhook'] );

		$settings['currency'] = sanitize_text_field( (string) $settings['currency'] );
		if ( '' === $settings['currency'] ) {
			$settings['currency'] = 'USD';
		}

		$settings['min_submit_seconds']   = max( 0, (int) $settings['min_submit_seconds'] );
		$settings['rate_limit_max']       = max( 1, (int) $settings['rate_limit_max'] );
		$settings['rate_limit_window']    = max( 60, (int) $settings['rate_limit_window'] );
		$settings['retention_months']     = max( 0, (int) $settings['retention_months'] );
		$settings['thank_you_page']       = absint( $settings['thank_you_page'] );
		$settings['auto_reply']           = empty( $settings['auto_reply'] ) ? 0 : 1;
		$settings['enable_intl_tel']      = empty( $settings['enable_intl_tel'] ) ? 0 : 1;

		/**
		 * Filters sanitized Safari Leads settings before they are stored.
		 *
		 * @param array<string, mixed> $settings Sanitized settings.
		 * @param array<string, mixed> $input    Raw input.
		 */
		return (array) apply_filters( 'safari_leads_sanitized_settings', $settings, $input );
	}

	/**
	 * Sanitize a comma-separated list of email addresses.
	 *
	 * @param string $value Raw list.
	 * @return string Comma-separated list of valid emails only.
	 */
	private static function sanitize_email_list( string $value ): string {
		$emails = array_filter(
			array_map( 'trim', explode( ',', str_replace( array( "\r", "\n" ), ',', $value ) ) ),
			static function ( string $email ): bool {
				return (bool) is_email( $email );
			}
		);

		return implode( ',', $emails );
	}
}
