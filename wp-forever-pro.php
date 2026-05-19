<?php
/**
 * Plugin Name:       WP Forever Pro
 * Plugin URI:        https://your-site.com/wp-forever-pro
 * Description:       Pro add-on for WP Forever. Unlocks advanced features, priority support, and automatic updates.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://your-site.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-forever-pro
 * Domain Path:       /languages
 *
 * @package WP_Forever_Pro
 * @since   1.0.0
 *
 * This is the pro add-on for WP Forever. It requires the free plugin
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

define( 'WP_FOREVER_PRO_VERSION', '1.0.0' );
define( 'WP_FOREVER_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FOREVER_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_FOREVER_PRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum required version of the free plugin.
 *
 * Bump this when pro features depend on free plugin changes.
 */
define( 'WP_FOREVER_PRO_MIN_FREE_VERSION', '0.2.0' );

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
function wp_forever_pro_check_dependency(): bool {
	// Check if free plugin is active (its main constant is defined).
	if ( ! defined( 'WP_FOREVER_VERSION' ) ) {
		return false;
	}

	// Check minimum version.
	if ( version_compare( WP_FOREVER_VERSION, WP_FOREVER_PRO_MIN_FREE_VERSION, '<' ) ) {
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
function wp_forever_pro_dependency_notice(): void {
	if ( ! defined( 'WP_FOREVER_VERSION' ) ) {
		$message = sprintf(
			/* translators: %s: Plugin name */
			__( '%1$s requires %2$s to be installed and activated.', 'wp-forever-pro' ),
			'<strong>WP Forever Pro</strong>',
			'<strong>WP Forever</strong>'
		);
	} else {
		$message = sprintf(
			/* translators: %1$s: Plugin name, %2$s: Required version */
			__( '%1$s requires %2$s version %3$s or higher. Please update the free plugin.', 'wp-forever-pro' ),
			'<strong>WP Forever Pro</strong>',
			'<strong>WP Forever</strong>',
			WP_FOREVER_PRO_MIN_FREE_VERSION
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
function wp_forever_pro_init(): void {
	// Check dependency.
	if ( ! wp_forever_pro_check_dependency() ) {
		add_action( 'admin_notices', 'wp_forever_pro_dependency_notice' );
		return;
	}

	// Load pro files.
	require_once WP_FOREVER_PRO_PLUGIN_DIR . 'includes/class-license-manager.php';
	require_once WP_FOREVER_PRO_PLUGIN_DIR . 'includes/class-updater.php';
	require_once WP_FOREVER_PRO_PLUGIN_DIR . 'includes/class-pro-loader.php';

	// Initialize license management.
	WP_Forever_Pro_License_Manager::init();

	// Initialize self-hosted updater.
	WP_Forever_Pro_Updater::init();

	// Load pro modules (only if licensed).
	if ( WP_Forever_Pro_License_Manager::is_valid() ) {
		// Tell the free plugin that pro access is available.
		add_filter( 'wp_forever/has_pro_access', '__return_true' );

		WP_Forever_Pro_Loader::init();
	}

	/**
	 * Fires after WP Forever Pro is fully loaded.
	 *
	 * @since 1.0.0
	 */
	do_action( 'wp_forever_pro/loaded' );
}

// Hook after the free plugin (priority 20, free uses default 10).
add_action( 'plugins_loaded', 'wp_forever_pro_init', 20 );
