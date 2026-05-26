<?php
/**
 * Automation Rules module.
 *
 * Auto-generates QR codes when content is published based on
 * configurable rules per post type.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automation class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Automation {

	/**
	 * Option name for automation settings.
	 *
	 * @since 1.2.0
	 */
	public const OPTION_KEY = 'qrc_ms_pro_automation_settings';

	/**
	 * Meta key stored on source posts linking to their auto-generated QR code.
	 *
	 * @since 1.2.0
	 */
	public const META_QR_ID = '_qrc_ms_auto_qr_id';

	/**
	 * Meta key stored on QR code posts linking back to the source post.
	 *
	 * @since 1.2.0
	 */
	public const META_SOURCE_ID = '_qrc_ms_source_post_id';

	/**
	 * Nonce action for settings form.
	 *
	 * @since 1.2.0
	 */
	private const NONCE_ACTION = 'qrc_ms_pro_automation_save';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Initialize the automation module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status_change' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
	}

	/**
	 * Register the automation feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Automation Rules', 'qrc-ms-pro' ),
			'description' => __( 'Automatically generate QR codes when content is published.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register the automation settings admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'Automation Rules', 'qrc-ms-pro' ),
			__( 'Automation', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-automation',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Render the automation settings page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['qrc_ms_pro_automation_nonce'] ) ) {
			self::save_settings();
		}

		$settings = self::get_settings();

		// Get available templates.
		$templates = get_posts( array(
			'post_type'      => 'qrc_ms_template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		// Get custom post types (exclude built-in and QR code types).
		$custom_post_types = get_post_types( array(
			'public'   => true,
			'_builtin' => false,
		), 'objects' );

		// Remove our own post types.
		unset( $custom_post_types['qrc_ms_code'], $custom_post_types['qrc_ms_template'] );

		// Remove product if WooCommerce is active (handled separately).
		if ( class_exists( 'WooCommerce' ) ) {
			unset( $custom_post_types['product'] );
		}

		include QRC_MS_PRO_PLUGIN_DIR . 'modules/views/automation-settings.php';
	}

	/**
	 * Save automation settings from form submission.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function save_settings(): void {
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_automation_nonce'] ?? '' ) ),
			self::NONCE_ACTION
		) ) {
			add_settings_error( 'qrc_ms_pro_automation', 'nonce_failed', __( 'Security check failed.', 'qrc-ms-pro' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = array(
			'enable_posts'       => ! empty( $_POST['qrc_ms_pro_auto_posts'] ),
			'enable_pages'       => ! empty( $_POST['qrc_ms_pro_auto_pages'] ),
			'enable_products'    => ! empty( $_POST['qrc_ms_pro_auto_products'] ),
			'enable_cpt'         => isset( $_POST['qrc_ms_pro_auto_cpt'] ) && is_array( $_POST['qrc_ms_pro_auto_cpt'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['qrc_ms_pro_auto_cpt'] ) )
				: array(),
			'default_template'   => isset( $_POST['qrc_ms_pro_auto_template'] ) ? absint( $_POST['qrc_ms_pro_auto_template'] ) : 0,
			'auto_regenerate'    => ! empty( $_POST['qrc_ms_pro_auto_regenerate'] ),
		);

		update_option( self::OPTION_KEY, $settings );

		add_settings_error(
			'qrc_ms_pro_automation',
			'settings_saved',
			__( 'Automation settings saved.', 'qrc-ms-pro' ),
			'success'
		);
	}

	/**
	 * Get automation settings with defaults.
	 *
	 * @since 1.2.0
	 * @return array Settings array.
	 */
	public static function get_settings(): array {
		$defaults = array(
			'enable_posts'     => false,
			'enable_pages'     => false,
			'enable_products'  => false,
			'enable_cpt'       => array(),
			'default_template' => 0,
			'auto_regenerate'  => false,
		);

		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			return $defaults;
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Handle post status transitions to auto-create QR codes.
	 *
	 * @since 1.2.0
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public static function on_post_status_change( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only trigger when transitioning to 'publish'.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Don't trigger for QR code posts themselves.
		if ( in_array( $post->post_type, array( 'qrc_ms_code', 'qrc_ms_template' ), true ) ) {
			return;
		}

		// Check if automation is enabled for this post type.
		if ( ! self::is_enabled_for_post_type( $post->post_type ) ) {
			return;
		}

		// Check if a QR code already exists for this post.
		$existing_qr_id = get_post_meta( $post->ID, self::META_QR_ID, true );
		if ( $existing_qr_id && get_post_status( $existing_qr_id ) ) {
			return;
		}

		// Create the QR code.
		self::create_qr_for_post( $post );
	}

	/**
	 * Handle post updates to regenerate QR code data when URL changes.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after update.
	 * @param \WP_Post $post_before Post object before update.
	 * @return void
	 */
	public static function on_post_updated( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		$settings = self::get_settings();

		if ( empty( $settings['auto_regenerate'] ) ) {
			return;
		}

		// Only for published posts.
		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		// Check if slug changed (which changes the URL).
		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		// Check if this post has an auto-generated QR code.
		$qr_id = get_post_meta( $post_id, self::META_QR_ID, true );
		if ( ! $qr_id || ! get_post_status( $qr_id ) ) {
			return;
		}

		// Update the QR code data with the new URL.
		$new_url = get_permalink( $post_id );
		if ( $new_url ) {
			update_post_meta( $qr_id, '_qrc_ms_data', $new_url );
		}
	}

	/**
	 * Check if automation is enabled for a given post type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $post_type The post type to check.
	 * @return bool Whether automation is enabled.
	 */
	private static function is_enabled_for_post_type( string $post_type ): bool {
		$settings = self::get_settings();

		return match ( $post_type ) {
			'post'    => ! empty( $settings['enable_posts'] ),
			'page'    => ! empty( $settings['enable_pages'] ),
			'product' => ! empty( $settings['enable_products'] ),
			default   => in_array( $post_type, $settings['enable_cpt'] ?? array(), true ),
		};
	}

	/**
	 * Create a QR code post for a given source post.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Post $post The source post.
	 * @return int|false The created QR code post ID or false on failure.
	 */
	private static function create_qr_for_post( \WP_Post $post ): int|false {
		$settings = self::get_settings();
		$url      = get_permalink( $post->ID );

		if ( ! $url ) {
			return false;
		}

		$qr_post_id = wp_insert_post( array(
			'post_type'   => 'qrc_ms_code',
			'post_title'  => sprintf(
				/* translators: %s: source post title */
				__( 'QR: %s', 'qrc-ms-pro' ),
				$post->post_title
			),
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $qr_post_id ) ) {
			return false;
		}

		// Store QR code meta.
		update_post_meta( $qr_post_id, '_qrc_ms_type', 'url' );
		update_post_meta( $qr_post_id, '_qrc_ms_data', $url );
		update_post_meta( $qr_post_id, self::META_SOURCE_ID, $post->ID );

		if ( ! empty( $settings['default_template'] ) ) {
			update_post_meta( $qr_post_id, '_qrc_ms_template_id', $settings['default_template'] );
		}

		// Link source post to QR code.
		update_post_meta( $post->ID, self::META_QR_ID, $qr_post_id );

		/**
		 * Fires after a QR code is auto-created for a post.
		 *
		 * @since 1.2.0
		 *
		 * @param int      $qr_post_id The created QR code post ID.
		 * @param \WP_Post $post       The source post.
		 */
		do_action( 'qrc_ms_pro/automation/qr_created', $qr_post_id, $post );

		return $qr_post_id;
	}
}
