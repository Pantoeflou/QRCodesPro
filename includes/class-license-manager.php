<?php
/**
 * License manager for the pro add-on.
 *
 * Handles license key storage, validation, activation, deactivation,
 * and status caching. Communicates with your license server.
 *
 * Also hooks into the free plugin's settings page to add the License tab.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License manager class.
 *
 * @since 1.0.0
 */
class QRC_MS_Pro_License_Manager {

	/**
	 * Option key for storing the license key.
	 */
	private const LICENSE_OPTION = 'qrc_ms_pro_license_key';

	/**
	 * Transient key for caching license status.
	 */
	private const STATUS_TRANSIENT = 'qrc_ms_pro_license_status';

	/**
	 * Option key for storing license metadata (expiry, plan, etc.).
	 */
	private const DATA_OPTION = 'qrc_ms_pro_license_data';

	/**
	 * Your license API base URL.
	 * Update this to your actual license server endpoint.
	 */
	private const API_URL = 'https://example.com/wp-json/license/v1/';

	/**
	 * How long to cache the license status (seconds).
	 */
	private const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Initialize the license manager.
	 *
	 * Hooks into the free plugin's settings page and registers AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		// Add License tab to the free plugin's settings page.
		add_filter( 'qrc_ms/settings_tabs', array( __CLASS__, 'add_license_tab' ) );

		// Register the license tab content.
		add_action( 'qrc_ms/settings_tab_content_license', array( __CLASS__, 'render_license_tab' ) );

		// AJAX handlers for license activation/deactivation.
		add_action( 'wp_ajax_qrc_ms_pro_activate_license', array( __CLASS__, 'ajax_activate' ) );
		add_action( 'wp_ajax_qrc_ms_pro_deactivate_license', array( __CLASS__, 'ajax_deactivate' ) );

		// Add plugin action links.
		add_filter( 'plugin_action_links_' . QRC_MS_PRO_PLUGIN_BASENAME, array( __CLASS__, 'add_action_links' ) );
	}

	/**
	 * Add the License tab to the free plugin's settings.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public static function add_license_tab( array $tabs ): array {
		$tabs['license'] = __( 'License', 'qrc-ms-pro' );
		return $tabs;
	}

	/**
	 * Render the License tab content.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_license_tab(): void {
		$template = QRC_MS_PRO_PLUGIN_DIR . 'views/license-tab.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Add action links to the Plugins page.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public static function add_action_links( array $links ): array {
		$license_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=qrc-ms-settings&tab=license' ) ),
			esc_html__( 'License', 'qrc-ms-pro' )
		);
		array_unshift( $links, $license_link );
		return $links;
	}

	/**
	 * Check if the current license is valid.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_valid(): bool {
		$key = self::get_license_key();
		if ( empty( $key ) ) {
			return false;
		}

		// Check cached status.
		$cached = get_transient( self::STATUS_TRANSIENT );
		if ( $cached !== false ) {
			return $cached === 'valid';
		}

		// Validate remotely.
		$status = self::remote_validate( $key );
		set_transient( self::STATUS_TRANSIENT, $status, self::CACHE_DURATION );

		return $status === 'valid';
	}

	/**
	 * Get the stored license key.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_license_key(): string {
		return (string) get_option( self::LICENSE_OPTION, '' );
	}

	/**
	 * Get stored license metadata.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_license_data(): array {
		return (array) get_option( self::DATA_OPTION, array() );
	}

	/**
	 * Get the current status label.
	 *
	 * @since 1.0.0
	 * @return string 'active', 'expired', 'invalid', or 'inactive'.
	 */
	public static function get_status(): string {
		$key = self::get_license_key();
		if ( empty( $key ) ) {
			return 'inactive';
		}

		$cached = get_transient( self::STATUS_TRANSIENT );
		if ( $cached === 'valid' ) {
			return 'active';
		}

		$data = self::get_license_data();
		if ( ! empty( $data['expired'] ) ) {
			return 'expired';
		}

		return 'invalid';
	}

	/**
	 * AJAX: Activate a license key.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_activate(): void {
		check_ajax_referer( 'qrc_ms_pro_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'qrc-ms-pro' ) ), 403 );
		}

		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a license key.', 'qrc-ms-pro' ) ) );
		}

		$result = self::activate( $key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Deactivate the license.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_deactivate(): void {
		check_ajax_referer( 'qrc_ms_pro_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'qrc-ms-pro' ) ), 403 );
		}

		$result = self::deactivate();
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Activate a license key.
	 *
	 * @since 1.0.0
	 * @param string $key License key.
	 * @return array{success: bool, message: string}
	 */
	public static function activate( string $key ): array {
		$response = self::api_request( 'activate', array(
			'license_key' => $key,
			'site_url'    => home_url(),
			'plugin'      => 'qrc-ms-pro',
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		if ( ! empty( $response['valid'] ) ) {
			update_option( self::LICENSE_OPTION, $key );
			update_option( self::DATA_OPTION, $response['data'] ?? array() );
			set_transient( self::STATUS_TRANSIENT, 'valid', self::CACHE_DURATION );
			return array( 'success' => true, 'message' => __( 'License activated successfully.', 'qrc-ms-pro' ) );
		}

		return array( 'success' => false, 'message' => $response['message'] ?? __( 'Invalid license key.', 'qrc-ms-pro' ) );
	}

	/**
	 * Deactivate the current license.
	 *
	 * @since 1.0.0
	 * @return array{success: bool, message: string}
	 */
	public static function deactivate(): array {
		$key = self::get_license_key();
		if ( empty( $key ) ) {
			return array( 'success' => false, 'message' => __( 'No license to deactivate.', 'qrc-ms-pro' ) );
		}

		// Notify server (best effort).
		self::api_request( 'deactivate', array(
			'license_key' => $key,
			'site_url'    => home_url(),
		) );

		// Clear local data.
		delete_option( self::LICENSE_OPTION );
		delete_option( self::DATA_OPTION );
		delete_transient( self::STATUS_TRANSIENT );

		return array( 'success' => true, 'message' => __( 'License deactivated.', 'qrc-ms-pro' ) );
	}

	/**
	 * Validate remotely.
	 *
	 * @since 1.0.0
	 * @param string $key License key.
	 * @return string 'valid' or 'invalid'.
	 */
	private static function remote_validate( string $key ): string {
		$response = self::api_request( 'validate', array(
			'license_key' => $key,
			'site_url'    => home_url(),
		) );

		if ( is_wp_error( $response ) ) {
			return 'valid'; // Graceful degradation on network error.
		}

		if ( ! empty( $response['valid'] ) ) {
			if ( ! empty( $response['data'] ) ) {
				update_option( self::DATA_OPTION, $response['data'] );
			}
			return 'valid';
		}

		if ( ! empty( $response['data'] ) ) {
			update_option( self::DATA_OPTION, $response['data'] );
		}

		return 'invalid';
	}

	/**
	 * Make a request to the license API.
	 *
	 * @since 1.0.0
	 * @param string $endpoint Endpoint name.
	 * @param array  $body     Request body.
	 * @return array|WP_Error
	 */
	private static function api_request( string $endpoint, array $body ): array|WP_Error {
		$url = trailingslashit( self::API_URL ) . $endpoint;

		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => $body,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || ! is_array( $data ) ) {
			return new WP_Error( 'license_api_error', __( 'Unable to reach license server.', 'qrc-ms-pro' ) );
		}

		return $data;
	}
}
