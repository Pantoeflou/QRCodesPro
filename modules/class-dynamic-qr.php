<?php
/**
 * Dynamic QR Codes module.
 *
 * Adds dynamic QR code functionality to the pro add-on. Dynamic QR codes
 * encode a redirect URL instead of the final destination, allowing the
 * destination to be changed after the QR code has been printed.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic QR module class.
 *
 * @since 1.0.0
 */
class QRC_MS_Pro_Dynamic_QR {

	/**
	 * Nonce action for AJAX requests.
	 *
	 * @since 1.0.0
	 */
	private const AJAX_NONCE_ACTION = 'qrc_ms_pro_dynamic_qr';

	/**
	 * Initialize the dynamic QR module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		// Register 'dynamic_qr' in the pro feature list.
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );

		// Add 'dynamic' to the QR code types.
		add_filter( 'qrc_ms/qr_code_types', array( __CLASS__, 'add_dynamic_type' ) );

		// Admin UI hooks.
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_qrc_ms_code', array( __CLASS__, 'save_dynamic_meta' ), 20, 2 );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_qrc_ms_pro_update_destination', array( __CLASS__, 'ajax_update_destination' ) );
		add_action( 'wp_ajax_qrc_ms_pro_toggle_dynamic', array( __CLASS__, 'ajax_toggle_dynamic' ) );

		// Add expired badge to list table columns.
		add_action( 'manage_qrc_ms_code_posts_custom_column', array( __CLASS__, 'render_expired_badge' ), 20, 2 );
	}

	/**
	 * Register the dynamic QR feature in the feature list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Dynamic QR Codes', 'qrc-ms-pro' ),
			'description' => __( 'Change QR code destinations after printing. Encode a redirect URL that you control.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Add 'dynamic' to the available QR code types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $types Existing QR code types.
	 * @return array Modified types.
	 */
	public static function add_dynamic_type( array $types ): array {
		$types['dynamic'] = __( 'Dynamic URL', 'qrc-ms-pro' );

		return $types;
	}

	/**
	 * Register the Dynamic QR meta box.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'qrc_ms_pro_dynamic_qr',
			__( 'Dynamic QR Code', 'qrc-ms-pro' ),
			array( __CLASS__, 'render_meta_box' ),
			'qrc_ms_code',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Dynamic QR meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$is_dynamic   = (bool) get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, true );
		$short_code   = get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, true );
		$redirect_url = get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_URL, true );
		$history      = get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_HISTORY, true );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$full_redirect_url = '';
		if ( ! empty( $short_code ) ) {
			$full_redirect_url = QRC_MS_Pro_Redirect_Handler::build_redirect_url( $short_code );
		}

		// Expiry fields.
		$expiry_date    = get_post_meta( $post->ID, '_qrc_ms_pro_expiry_date', true );
		$fallback_url   = get_post_meta( $post->ID, '_qrc_ms_pro_fallback_url', true );
		$expiry_message = get_post_meta( $post->ID, '_qrc_ms_pro_expiry_message', true );

		if ( empty( $expiry_message ) ) {
			$expiry_message = __( 'This QR code has expired.', 'qrc-ms-pro' );
		}

		include QRC_MS_PRO_PLUGIN_DIR . 'modules/views/dynamic-panel.php';
	}

	/**
	 * Save dynamic QR meta data on post save.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_dynamic_meta( int $post_id, \WP_Post $post ): void {
		// Check for our specific field to avoid running on unrelated saves.
		if ( ! isset( $_POST['qrc_ms_pro_dynamic_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_dynamic_nonce'] ) ),
			'qrc_ms_pro_save_dynamic'
		) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_dynamic = ! empty( $_POST['qrc_ms_pro_is_dynamic'] );

		// Update dynamic flag.
		update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, $is_dynamic ? '1' : '' );

		if ( $is_dynamic ) {
			// Generate short code if not already set.
			$existing_code = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, true );
			if ( empty( $existing_code ) ) {
				$short_code = QRC_MS_Pro_Redirect_Handler::generate_short_code();
				update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, $short_code );
			}

			// Save redirect destination.
			if ( isset( $_POST['qrc_ms_pro_redirect_url'] ) ) {
				$new_url = esc_url_raw( wp_unslash( $_POST['qrc_ms_pro_redirect_url'] ) );
				$old_url = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_URL, true );

				// Log history if URL changed.
				if ( ! empty( $old_url ) && $old_url !== $new_url ) {
					self::log_redirect_change( $post_id, $old_url, $new_url );
				}

				update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_URL, $new_url );
			}
		}

		// Save expiry meta (regardless of dynamic mode — applies to all dynamic QR codes).
		$expiry_date = '';
		if ( ! empty( $_POST['qrc_ms_pro_expiry_date'] ) ) {
			$raw_date = sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_expiry_date'] ) );
			// Validate Y-m-d format.
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
				$expiry_date = $raw_date;
			}
		}
		update_post_meta( $post_id, '_qrc_ms_pro_expiry_date', $expiry_date );

		$fallback_url = '';
		if ( ! empty( $_POST['qrc_ms_pro_fallback_url'] ) ) {
			$fallback_url = esc_url_raw( wp_unslash( $_POST['qrc_ms_pro_fallback_url'] ) );
		}
		update_post_meta( $post_id, '_qrc_ms_pro_fallback_url', $fallback_url );

		$expiry_message = __( 'This QR code has expired.', 'qrc-ms-pro' );
		if ( isset( $_POST['qrc_ms_pro_expiry_message'] ) ) {
			$raw_message = sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_expiry_message'] ) );
			if ( ! empty( $raw_message ) ) {
				$expiry_message = $raw_message;
			}
		}
		update_post_meta( $post_id, '_qrc_ms_pro_expiry_message', $expiry_message );
	}

	/**
	 * Enqueue admin assets for the dynamic QR panel.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		global $post_type;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( 'qrc_ms_code' !== $post_type ) {
			return;
		}

		wp_enqueue_script(
			'qrc-ms-pro-dynamic-qr',
			QRC_MS_PRO_PLUGIN_URL . 'assets/js/dynamic-qr.js',
			array( 'jquery' ),
			QRC_MS_PRO_VERSION,
			true
		);

		wp_localize_script( 'qrc-ms-pro-dynamic-qr', 'qrcMsProDynamic', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
			'i18n'    => array(
				'updating'    => __( 'Updating...', 'qrc-ms-pro' ),
				'updated'     => __( 'Destination updated successfully.', 'qrc-ms-pro' ),
				'error'       => __( 'An error occurred. Please try again.', 'qrc-ms-pro' ),
				'confirmToggle' => __( 'Are you sure you want to disable dynamic mode? The redirect URL will stop working.', 'qrc-ms-pro' ),
			),
		) );

		wp_enqueue_style(
			'qrc-ms-pro-dynamic-qr',
			QRC_MS_PRO_PLUGIN_URL . 'assets/css/dynamic-qr.css',
			array(),
			QRC_MS_PRO_VERSION
		);
	}

	/**
	 * AJAX handler: Update the redirect destination URL.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_update_destination(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$new_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : '';

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permission denied.', 'qrc-ms-pro' ),
			), 403 );
		}

		if ( empty( $new_url ) ) {
			wp_send_json_error( array(
				'message' => __( 'Please provide a valid URL.', 'qrc-ms-pro' ),
			), 400 );
		}

		// Verify this is a dynamic QR code.
		$is_dynamic = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, true );
		if ( ! $is_dynamic ) {
			wp_send_json_error( array(
				'message' => __( 'This QR code is not in dynamic mode.', 'qrc-ms-pro' ),
			), 400 );
		}

		$old_url = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_URL, true );

		// Log history if URL changed.
		if ( ! empty( $old_url ) && $old_url !== $new_url ) {
			self::log_redirect_change( $post_id, $old_url, $new_url );
		}

		update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_URL, $new_url );

		wp_send_json_success( array(
			'message'      => __( 'Destination updated successfully.', 'qrc-ms-pro' ),
			'redirect_url' => $new_url,
		) );
	}

	/**
	 * AJAX handler: Toggle dynamic mode on/off.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function ajax_toggle_dynamic(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$enable  = isset( $_POST['enable'] ) && '1' === $_POST['enable'];

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permission denied.', 'qrc-ms-pro' ),
			), 403 );
		}

		// Verify post type.
		$post = get_post( $post_id );
		if ( ! $post || 'qrc_ms_code' !== $post->post_type ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid QR code.', 'qrc-ms-pro' ),
			), 400 );
		}

		update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, $enable ? '1' : '' );

		$short_code       = '';
		$full_redirect_url = '';

		if ( $enable ) {
			// Generate short code if needed.
			$short_code = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, true );
			if ( empty( $short_code ) ) {
				$short_code = QRC_MS_Pro_Redirect_Handler::generate_short_code();
				update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, $short_code );
			}
			$full_redirect_url = QRC_MS_Pro_Redirect_Handler::build_redirect_url( $short_code );
		}

		wp_send_json_success( array(
			'message'          => $enable
				? __( 'Dynamic mode enabled.', 'qrc-ms-pro' )
				: __( 'Dynamic mode disabled.', 'qrc-ms-pro' ),
			'is_dynamic'       => $enable,
			'short_code'       => $short_code,
			'full_redirect_url' => $full_redirect_url,
		) );
	}

	/**
	 * Log a redirect URL change to the history.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The QR code post ID.
	 * @param string $old_url The previous destination URL.
	 * @param string $new_url The new destination URL.
	 * @return void
	 */
	private static function log_redirect_change( int $post_id, string $old_url, string $new_url ): void {
		$history = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_HISTORY, true );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = array(
			'old_url'    => $old_url,
			'new_url'    => $new_url,
			'changed_by' => get_current_user_id(),
			'changed_at' => current_time( 'mysql' ),
		);

		update_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_REDIRECT_HISTORY, $history );
	}

	/**
	 * Render an "Expired" badge next to the type column for expired dynamic QR codes.
	 *
	 * Hooked to manage_qrc_ms_code_posts_custom_column at priority 20 (after the free plugin renders).
	 *
	 * @since 1.1.0
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_expired_badge( string $column, int $post_id ): void {
		if ( 'qrc_ms_type' !== $column ) {
			return;
		}

		$is_dynamic = get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, true );
		if ( ! $is_dynamic ) {
			return;
		}

		$expiry_date = get_post_meta( $post_id, '_qrc_ms_pro_expiry_date', true );
		if ( empty( $expiry_date ) ) {
			return;
		}

		if ( strtotime( $expiry_date ) < time() ) {
			printf(
				' <span class="qrc-ms-pro-expired-badge" style="display:inline-block;background:#d63638;color:#fff;font-size:11px;padding:2px 6px;border-radius:3px;margin-left:6px;vertical-align:middle;">%s</span>',
				esc_html__( 'Expired', 'qrc-ms-pro' )
			);
		}
	}
}
