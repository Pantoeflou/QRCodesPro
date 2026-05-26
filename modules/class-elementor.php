<?php
/**
 * Elementor Integration module.
 *
 * Provides Elementor widgets for displaying QR codes within
 * Elementor page builder layouts.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor integration class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Elementor {

	/**
	 * Initialize the Elementor module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );

		// Only proceed if Elementor is loaded.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
	}

	/**
	 * Register the Elementor feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Elementor Integration', 'qrc-ms-pro' ),
			'description' => __( 'Display QR codes using Elementor widgets with full styling controls.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register a custom Elementor widget category.
	 *
	 * @since 1.2.0
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 * @return void
	 */
	public static function register_category( \Elementor\Elements_Manager $elements_manager ): void {
		$elements_manager->add_category(
			'qrc-ms-pro',
			array(
				'title' => __( 'QR Codes', 'qrc-ms-pro' ),
				'icon'  => 'eicon-barcode',
			)
		);
	}

	/**
	 * Register Elementor widgets.
	 *
	 * @since 1.2.0
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets( \Elementor\Widgets_Manager $widgets_manager ): void {
		// Load widget files.
		require_once QRC_MS_PRO_PLUGIN_DIR . 'modules/elementor/class-qr-code-widget.php';
		require_once QRC_MS_PRO_PLUGIN_DIR . 'modules/elementor/class-dynamic-qr-widget.php';

		// Register widgets.
		$widgets_manager->register( new QRC_MS_Pro_Elementor_QR_Code_Widget() );
		$widgets_manager->register( new QRC_MS_Pro_Elementor_Dynamic_QR_Widget() );
	}
}
