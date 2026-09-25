<?php
/**
 * Custom database tables for Safari Leads.
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

final class Safari_Lead_DB {

	/**
	 * Schema version stored in the database option.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Lead statuses (plan.md section 5).
	 *
	 * @var array<string, string>
	 */
	public const STATUSES = array(
		'new'          => 'New',
		'in_progress'  => 'In progress',
		'contacted'    => 'Contacted',
		'closed_won'   => 'Closed won',
		'closed_lost'  => 'Closed lost',
		'spam'         => 'Spam',
	);

	/**
	 * Leads table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'safari_leads';
	}

	/**
	 * Lead notes table name.
	 */
	public static function notes_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'safari_lead_notes';
	}

	/**
	 * Create or update the tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$leads           = self::table();
		$notes           = self::notes_table();

		$sql = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta builds queries itself.
		$sql[] = "CREATE TABLE {$leads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			phone VARCHAR(50) NULL,
			destination_id BIGINT UNSIGNED NULL,
			tour_id BIGINT UNSIGNED NULL,
			destination_text VARCHAR(190) NULL,
			travel_style VARCHAR(100) NULL,
			date_from DATE NULL,
			date_to DATE NULL,
			dates_flexible TINYINT(1) NOT NULL DEFAULT 0,
			adults SMALLINT UNSIGNED NULL,
			children SMALLINT UNSIGNED NULL,
			budget_range VARCHAR(50) NULL,
			subject VARCHAR(190) NULL,
			message TEXT NULL,
			source_form VARCHAR(50) NOT NULL DEFAULT 'contact',
			source_url VARCHAR(500) NULL,
			utm_source VARCHAR(100) NULL,
			utm_medium VARCHAR(100) NULL,
			utm_campaign VARCHAR(100) NULL,
			referrer VARCHAR(500) NULL,
			consent_privacy TINYINT(1) NOT NULL DEFAULT 0,
			consent_marketing TINYINT(1) NOT NULL DEFAULT 0,
			ip_hash CHAR(64) NULL,
			assigned_to BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY email (email),
			KEY destination_id (destination_id),
			KEY assigned_to (assigned_to)
		) {$charset_collate};";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta builds queries itself.
		$sql[] = "CREATE TABLE {$notes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'note',
			content TEXT NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		foreach ($sql as $query) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta builds queries itself.
			dbDelta($query);
		}

		update_option('safari_leads_db_version', self::DB_VERSION);
	}

	/**
	 * Run dbDelta on init when the schema version changed.
	 *
	 * @return void
	 */
	public static function maybe_create_tables(): void {
		if (get_option('safari_leads_db_version') !== self::DB_VERSION) {
			self::create_tables();
		}
	}

	/**
	 * Insert a lead row.
	 *
	 * @param array<string, mixed> $data Column => value pairs (already sanitized).
	 * @return int|WP_Error New lead ID or error.
	 */
	public static function insert_lead(array $data) {
		global $wpdb;

		$data['created_at'] = current_time('mysql');
		$data['updated_at'] = current_time('mysql');

		$result = $wpdb->insert(self::table(), $data);

		if (false === $result) {
			return new WP_Error('safari_lead_db_error', __('The lead could not be saved. Please try again.', 'safari-leads'));
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a lead row.
	 *
	 * @param int                 $lead_id Lead ID.
	 * @param array<string, mixed> $data   Column => value pairs (already sanitized).
	 * @return bool
	 */
	public static function update_lead(int $lead_id, array $data): bool {
		global $wpdb;

		$data['updated_at'] = current_time('mysql');

		$result = $wpdb->update(
			self::table(),
			$data,
			array('id' => $lead_id),
			null,
			array('%d')
		);

		return false !== $result;
	}

	/**
	 * Fetch a single lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public static function get_lead(int $lead_id): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $lead_id));

		return $row ?? null;
	}

	/**
	 * Add a note/status entry to a lead.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param int    $user_id User ID (0 for system).
	 * @param string $content Note content.
	 * @param string $type    One of note|status_change|email_sent|system.
	 * @return int|WP_Error
	 */
	public static function add_note(int $lead_id, int $user_id, string $content, string $type = 'note') {
		global $wpdb;

		$result = $wpdb->insert(
			self::notes_table(),
			array(
				'lead_id'    => $lead_id,
				'user_id'    => $user_id,
				'created_at' => current_time('mysql'),
				'type'       => $type,
				'content'    => $content,
			)
		);

		if (false === $result) {
			return new WP_Error('safari_lead_note_error', __('The note could not be saved.', 'safari-leads'));
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get notes for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public static function get_notes(int $lead_id): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::notes_table() . ' WHERE lead_id = %d ORDER BY created_at DESC, id DESC', $lead_id));

		return is_array($rows) ? $rows : array();
	}

	/**
	 * Count leads per status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status(): array {
		global $wpdb;

		$counts = array_fill_keys(array_keys(self::STATUSES), 0);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::table() . ' GROUP BY status');

		if (is_array($rows)) {
			foreach ($rows as $row) {
				$counts[ (string) $row->status ] = (int) $row->total;
			}
		}

		return $counts;
	}
}
