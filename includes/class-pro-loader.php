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
 * @package    WP_Forever_Pro
 * @subpackage WP_Forever_Pro/includes
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
class WP_Forever_Pro_Loader {

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
		do_action( 'wp_forever_pro/modules_loaded' );
	}

	/**
	 * Load pro module files.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function load_modules(): void {
		$modules_dir = WP_FOREVER_PRO_PLUGIN_DIR . 'modules/';

		// Load each module file:
		// require_once $modules_dir . 'class-advanced-analytics.php';
		// require_once $modules_dir . 'class-premium-templates.php';
		// require_once $modules_dir . 'class-export-import.php';

		// Example module (remove after building real modules):
		if ( file_exists( $modules_dir . 'class-example-module.php' ) ) {
			require_once $modules_dir . 'class-example-module.php';
		}
	}

	/**
	 * Initialize pro modules (register hooks).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function init_modules(): void {
		// Initialize each module:
		// WP_Forever_Pro_Advanced_Analytics::init();
		// WP_Forever_Pro_Premium_Templates::init();
		// WP_Forever_Pro_Export_Import::init();

		// Example module:
		if ( class_exists( 'WP_Forever_Pro_Example_Module' ) ) {
			WP_Forever_Pro_Example_Module::init();
		}
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
