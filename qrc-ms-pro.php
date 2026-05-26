<?php
/**
 * Plugin Name:       QR Codes - Made Simple Pro
 * Plugin URI:        https://example.com/qrc-ms-pro
 * Description:       Pro add-on for QR Codes - Made Simple. Unlocks advanced features, priority support, and automatic updates.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Dev Team
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qrc-ms-pro
 * Domain Path:       /languages
 *
 * @package QRC_MS_Pro
 * @since   1.0.0
 *
 * This is the pro add-on for QR Codes - Made Simple. It requires the free plugin
 * to be installed and active. It extends the free plugin by:
 * - Adding pro feature modules via hooks/filters
 * - Managing license key validation
 * - Handling self-hosted updates from your server
 *
 * This plugin is NOT distributed on wp.org. It is sold on your website
 * and delivered via your update server.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

define( 'QRC_MS_PRO_VERSION', '1.0.0' );
define( 'QRC_MS_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QRC_MS_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QRC_MS_PRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum required version of the free plugin.
 *
 * Bump this when pro features depend on free plugin changes.
 */
define( 'QRC_MS_PRO_MIN_FREE_VERSION', '1.0.0' );

/*
|--------------------------------------------------------------------------
| Dependency Check
|--------------------------------------------------------------------------
|
| The pro add-on requires the free plugin to be active and at a minimum
| version. If not met, show an admin notice and bail.
|
*/

/**
 * Check if the free plugin is active and compatible.
 *
 * @since 1.0.0
 * @return bool True if dependency is met.
 */
function qrc_ms_pro_check_dependency(): bool {
	// Check if free plugin is active (its main constant is defined).
	if ( ! defined( 'QRC_MS_VERSION' ) ) {
		return false;
	}

	// Check minimum version.
	if ( version_compare( QRC_MS_VERSION, QRC_MS_PRO_MIN_FREE_VERSION, '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Show admin notice when dependency is not met.
 *
 * @since 1.0.0
 * @return void
 */
function qrc_ms_pro_dependency_notice(): void {
	if ( ! defined( 'QRC_MS_VERSION' ) ) {
		$message = sprintf(
			/* translators: %s: Plugin name */
			__( '%1$s requires %2$s to be installed and activated.', 'qrc-ms-pro' ),
			'<strong>QR Codes - Made Simple Pro</strong>',
			'<strong>QR Codes - Made Simple</strong>'
		);
	} else {
		$message = sprintf(
			/* translators: %1$s: Plugin name, %2$s: Required version */
			__( '%1$s requires %2$s version %3$s or higher. Please update the free plugin.', 'qrc-ms-pro' ),
			'<strong>QR Codes - Made Simple Pro</strong>',
			'<strong>QR Codes - Made Simple</strong>',
			QRC_MS_PRO_MIN_FREE_VERSION
		);
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
}

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
|
| Wait for plugins_loaded (after the free plugin initializes), then check
| dependency and load pro functionality.
|
*/

/**
 * Initialize the pro add-on.
 *
 * @since 1.0.0
 * @return void
 */
function qrc_ms_pro_init(): void {
	// Check dependency.
	if ( ! qrc_ms_pro_check_dependency() ) {
		add_action( 'admin_notices', 'qrc_ms_pro_dependency_notice' );
		return;
	}

	// Load pro files.
	require_once QRC_MS_PRO_PLUGIN_DIR . 'includes/class-license-manager.php';
	require_once QRC_MS_PRO_PLUGIN_DIR . 'includes/class-updater.php';
	require_once QRC_MS_PRO_PLUGIN_DIR . 'includes/class-pro-loader.php';

	// Always load and initialize the redirect handler so existing dynamic QR
	// codes continue to work even if the license expires. Printed QR codes
	// must never break.
	require_once QRC_MS_PRO_PLUGIN_DIR . 'includes/class-redirect-handler.php';
	QRC_MS_Pro_Redirect_Handler::init();

	// Initialize license management.
	QRC_MS_Pro_License_Manager::init();

	// Initialize self-hosted updater.
	QRC_MS_Pro_Updater::init();

	// Load pro modules (only if licensed).
	if ( QRC_MS_Pro_License_Manager::is_valid() ) {
		// Tell the free plugin that pro access is available.
		add_filter( 'qrc_ms/has_pro_access', '__return_true' );

		QRC_MS_Pro_Loader::init();
	}

	/**
	 * Fires after QR Codes - Made Simple Pro is fully loaded.
	 *
	 * @since 1.0.0
	 */
	do_action( 'qrc_ms_pro/loaded' );
}

// Hook after the free plugin (priority 20, free uses default 10).
add_action( 'plugins_loaded', 'qrc_ms_pro_init', 20 );
