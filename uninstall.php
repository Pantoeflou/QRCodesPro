<?php
/**
 * Uninstall script for QR Codes - Made Simple Pro.
 *
 * Runs when the pro add-on is deleted via WordPress admin.
 * Cleans up pro-specific data only (does not touch free plugin data).
 *
 * @package QRC_MS_Pro
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load analytics DB class for table cleanup.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-analytics-db.php';

// Remove license data.
delete_option( 'qrc_ms_pro_license_key' );
delete_option( 'qrc_ms_pro_license_data' );
delete_transient( 'qrc_ms_pro_license_status' );

// Drop analytics table.
QRC_MS_Pro_Analytics_DB::drop();

// Multisite cleanup.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'qrc_ms_pro_license_key' );
		delete_option( 'qrc_ms_pro_license_data' );
		delete_transient( 'qrc_ms_pro_license_status' );
		QRC_MS_Pro_Analytics_DB::drop();
		restore_current_blog();
	}
}
