<?php
/**
 * Analytics database schema management.
 *
 * Handles creation, upgrade, and removal of the scans table.
 * Uses dbDelta() for safe schema migrations.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/includes
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics DB class.
 *
 * @since 1.1.0
 */
class QRC_MS_Pro_Analytics_DB {

	/**
	 * Current schema version.
	 *
	 * @since 1.1.0
	 */
	private const SCHEMA_VERSION = '1.0.0';

	/**
	 * Option key for tracking schema version.
	 *
	 * @since 1.1.0
	 */
	private const VERSION_OPTION = 'qrc_ms_pro_analytics_db_version';

	/**
	 * Get the scans table name.
	 *
	 * @since 1.1.0
	 * @return string Full table name with prefix.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'qrc_ms_pro_scans';
	}

	/**
	 * Check if the schema needs to be installed or upgraded.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function maybe_install(): void {
		$installed_version = get_option( self::VERSION_OPTION, '' );

		if ( self::SCHEMA_VERSION !== $installed_version ) {
			self::install();
		}
	}

	/**
	 * Create or update the scans table.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			qr_code_id BIGINT(20) UNSIGNED NOT NULL,
			short_code VARCHAR(16) NOT NULL,
			scanned_at DATETIME NOT NULL,
			ip_hash VARCHAR(64) NOT NULL,
			country VARCHAR(2) DEFAULT '',
			device_type VARCHAR(20) DEFAULT '',
			user_agent TEXT DEFAULT '',
			referer VARCHAR(500) DEFAULT '',
			PRIMARY KEY  (id),
			KEY qr_code_id (qr_code_id),
			KEY short_code (short_code),
			KEY scanned_at (scanned_at),
			KEY country (country)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, true );
	}

	/**
	 * Drop the scans table.
	 *
	 * Used during uninstall. This is destructive and irreversible.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		delete_option( self::VERSION_OPTION );
	}
}
