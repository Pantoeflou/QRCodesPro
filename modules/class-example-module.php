<?php
/**
 * Example pro module.
 *
 * Demonstrates how a pro module hooks into the free plugin.
 * Replace this with your actual pro features.
 *
 * Pattern:
 * - Each module is a self-contained class
 * - init() registers hooks into the free plugin's extension points
 * - The module only runs when the pro loader calls init()
 *
 * @package    WP_Forever_Pro
 * @subpackage WP_Forever_Pro/modules
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Example pro module.
 *
 * @since 1.0.0
 */
class WP_Forever_Pro_Example_Module {

	/**
	 * Initialize the module.
	 *
	 * Hook into the free plugin's extension points here.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		// Example: Add pro features to the free plugin's feature list.
		add_filter( 'wp_forever/feature_list', array( __CLASS__, 'add_pro_features' ) );

		// Example: Add content after the free plugin's output.
		add_action( 'wp_forever/after_output', array( __CLASS__, 'render_pro_content' ) );

		// Example: Add a pro settings section to the free plugin's settings.
		add_action( 'wp_forever/settings_section_advanced', array( __CLASS__, 'add_pro_settings' ) );

		// Example: Extend the free plugin's REST API.
		add_action( 'rest_api_init', array( __CLASS__, 'register_pro_routes' ) );
	}

	/**
	 * Add pro features to the feature list.
	 *
	 * @since 1.0.0
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function add_pro_features( array $features ): array {
		$features[] = array(
			'name'        => __( 'Advanced Analytics', 'wp-forever-pro' ),
			'description' => __( 'Detailed usage analytics and reporting.', 'wp-forever-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Render pro content after free plugin output.
	 *
	 * @since 1.0.0
	 * @param array $data Context data from the free plugin.
	 * @return void
	 */
	public static function render_pro_content( array $data = array() ): void {
		// Render pro-specific output here.
		echo '<div class="wp-forever-pro-content">';
		echo '<p>' . esc_html__( 'Pro feature content here.', 'wp-forever-pro' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Add pro settings fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_pro_settings(): void {
		// Register additional settings fields in the free plugin's settings page.
	}

	/**
	 * Register pro REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_pro_routes(): void {
		register_rest_route( 'wp-forever/v1', '/pro/stats', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_stats' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	/**
	 * Pro REST endpoint callback.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_stats( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array(
			'ok'      => true,
			'message' => __( 'Pro stats endpoint.', 'wp-forever-pro' ),
		), 200 );
	}
}
