<?php
/**
 * Uninstall script for WP Forever Pro.
 *
 * Runs when the pro add-on is deleted via WordPress admin.
 * Cleans up pro-specific data only (does not touch free plugin data).
 *
 * @package WP_Forever_Pro
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove license data.
delete_option( 'wp_forever_pro_license_key' );
delete_option( 'wp_forever_pro_license_data' );
delete_transient( 'wp_forever_pro_license_status' );

// Multisite cleanup.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'wp_forever_pro_license_key' );
		delete_option( 'wp_forever_pro_license_data' );
		delete_transient( 'wp_forever_pro_license_status' );
		restore_current_blog();
	}
}
