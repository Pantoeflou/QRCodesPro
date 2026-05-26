<?php
/**
 * Pro module loader.
 *
 * Bootstraps all pro feature modules when a valid license is active.
 * Each module hooks into the free plugin's extension points.
 *
 * Adding a new pro module:
 * 1. Create class in modules/ directory
 * 2. Require it in load_modules()
 * 3. Initialize it in init_modules()
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro loader class.
 *
 * @since 1.0.0
 */
class QRC_MS_Pro_Loader {

	/**
	 * Whether modules have been initialized.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Initialize all pro modules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		self::load_modules();
		self::init_modules();

		/**
		 * Fires after all pro modules are loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'qrc_ms_pro/modules_loaded' );
	}

	/**
	 * Load pro module files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function load_modules(): void {
		$modules_dir  = QRC_MS_PRO_PLUGIN_DIR . 'modules/';
		$includes_dir = QRC_MS_PRO_PLUGIN_DIR . 'includes/';

		// Dashboard.
		require_once $modules_dir . 'class-dashboard.php';

		// Dynamic QR Codes (redirect handler is already loaded in qrc_ms_pro_init).
		require_once $includes_dir . 'class-redirect-handler.php';
		require_once $modules_dir . 'class-dynamic-qr.php';

		// Scan Analytics.
		require_once $includes_dir . 'class-analytics-db.php';
		require_once $modules_dir . 'class-analytics.php';

		// Campaigns.
		require_once $modules_dir . 'class-campaigns.php';

		// Bulk Generator.
		require_once $modules_dir . 'class-bulk-generator.php';

		// Advanced Branding.
		require_once $modules_dir . 'class-branding.php';

		// Automation Rules.
		require_once $modules_dir . 'class-automation.php';

		// Elementor Integration.
		require_once $modules_dir . 'class-elementor.php';

		// Team / Multi-user.
		require_once $modules_dir . 'class-team.php';

		// Export & Reporting.
		require_once $modules_dir . 'class-export.php';
	}

	/**
	 * Initialize pro modules (register hooks).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function init_modules(): void {
		// Dashboard (registered early so it appears first in the menu).
		QRC_MS_Pro_Dashboard::init();

		// Dynamic QR Codes.
		QRC_MS_Pro_Dynamic_QR::init();

		// Scan Analytics (install schema if needed, then init).
		QRC_MS_Pro_Analytics_DB::maybe_install();
		QRC_MS_Pro_Analytics::init();

		// Campaigns.
		QRC_MS_Pro_Campaigns::init();

		// Bulk Generator.
		QRC_MS_Pro_Bulk_Generator::init();

		// Advanced Branding.
		QRC_MS_Pro_Branding::init();

		// Automation Rules.
		QRC_MS_Pro_Automation::init();

		// Elementor Integration.
		QRC_MS_Pro_Elementor::init();

		// Team / Multi-user.
		QRC_MS_Pro_Team::init();

		// Export & Reporting.
		QRC_MS_Pro_Export::init();
	}

	/**
	 * Check if pro modules are active.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_active(): bool {
		return self::$initialized;
	}
}
