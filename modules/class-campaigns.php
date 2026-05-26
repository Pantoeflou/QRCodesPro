<?php
/**
 * Campaigns module.
 *
 * Registers a custom taxonomy for grouping QR codes into campaigns
 * and provides a dashboard showing campaign-level statistics.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campaigns class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Campaigns {

	/**
	 * Taxonomy name.
	 *
	 * @since 1.2.0
	 */
	public const TAXONOMY = 'qrc_ms_campaign';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Initialize the campaigns module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Bulk actions for assigning QR codes to campaigns.
		add_filter( 'bulk_actions-edit-qrc_ms_code', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-qrc_ms_code', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_admin_notice' ) );
	}

	/**
	 * Register the campaign feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Campaigns', 'qrc-ms-pro' ),
			'description' => __( 'Group QR codes into campaigns and view aggregated statistics.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register the qrc_ms_campaign taxonomy.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Campaigns', 'taxonomy general name', 'qrc-ms-pro' ),
			'singular_name'              => _x( 'Campaign', 'taxonomy singular name', 'qrc-ms-pro' ),
			'search_items'               => __( 'Search Campaigns', 'qrc-ms-pro' ),
			'popular_items'              => __( 'Popular Campaigns', 'qrc-ms-pro' ),
			'all_items'                  => __( 'All Campaigns', 'qrc-ms-pro' ),
			'parent_item'                => __( 'Parent Campaign', 'qrc-ms-pro' ),
			'parent_item_colon'          => __( 'Parent Campaign:', 'qrc-ms-pro' ),
			'edit_item'                  => __( 'Edit Campaign', 'qrc-ms-pro' ),
			'update_item'                => __( 'Update Campaign', 'qrc-ms-pro' ),
			'add_new_item'               => __( 'Add New Campaign', 'qrc-ms-pro' ),
			'new_item_name'              => __( 'New Campaign Name', 'qrc-ms-pro' ),
			'separate_items_with_commas' => __( 'Separate campaigns with commas', 'qrc-ms-pro' ),
			'add_or_remove_items'        => __( 'Add or remove campaigns', 'qrc-ms-pro' ),
			'choose_from_most_used'      => __( 'Choose from the most used campaigns', 'qrc-ms-pro' ),
			'not_found'                  => __( 'No campaigns found.', 'qrc-ms-pro' ),
			'menu_name'                  => __( 'Campaigns', 'qrc-ms-pro' ),
			'back_to_items'              => __( '&larr; Back to Campaigns', 'qrc-ms-pro' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
			'show_in_menu'      => false,
			'rewrite'           => false,
		);

		register_taxonomy( self::TAXONOMY, 'qrc_ms_code', $args );
	}

	/**
	 * Register the campaigns dashboard admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'Campaign Dashboard', 'qrc-ms-pro' ),
			__( 'Campaigns', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-campaigns',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets for the campaigns page.
	 *
	 * @since 1.2.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( self::$page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'qrc-ms-pro-campaigns',
			QRC_MS_PRO_PLUGIN_URL . 'assets/css/campaigns.css',
			array(),
			QRC_MS_PRO_VERSION
		);
	}

	/**
	 * Render the campaigns dashboard page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		$campaigns = self::get_campaign_stats();

		include QRC_MS_PRO_PLUGIN_DIR . 'modules/views/campaigns-dashboard.php';
	}

	/**
	 * Register bulk actions for assigning QR codes to campaigns.
	 *
	 * Adds one bulk action per campaign (up to 10 most recent).
	 *
	 * @since 1.2.0
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public static function register_bulk_actions( array $bulk_actions ): array {
		$terms = get_terms( array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'number'     => 10,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $bulk_actions;
		}

		foreach ( $terms as $term ) {
			$bulk_actions[ 'qrc_ms_pro_assign_campaign_' . $term->term_id ] = sprintf(
				/* translators: %s: campaign name */
				__( 'Assign to: %s', 'qrc-ms-pro' ),
				$term->name
			);
		}

		return $bulk_actions;
	}

	/**
	 * Handle bulk action for assigning QR codes to a campaign.
	 *
	 * @since 1.2.0
	 *
	 * @param string $redirect_to The redirect URL.
	 * @param string $doaction    The action being taken.
	 * @param array  $post_ids    The array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ): string {
		// Check if this is one of our campaign assignment actions.
		if ( ! str_starts_with( $doaction, 'qrc_ms_pro_assign_campaign_' ) ) {
			return $redirect_to;
		}

		$term_id = (int) str_replace( 'qrc_ms_pro_assign_campaign_', '', $doaction );

		if ( $term_id <= 0 ) {
			return $redirect_to;
		}

		// Verify the term exists.
		$term = get_term( $term_id, self::TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			return $redirect_to;
		}

		$assigned_count = 0;

		foreach ( $post_ids as $post_id ) {
			$result = wp_set_object_terms( $post_id, $term_id, self::TAXONOMY, true );
			if ( ! is_wp_error( $result ) ) {
				$assigned_count++;
			}
		}

		$redirect_to = add_query_arg( array(
			'qrc_ms_pro_campaign_assigned' => $assigned_count,
			'qrc_ms_pro_campaign_name'     => urlencode( $term->name ),
		), $redirect_to );

		return $redirect_to;
	}

	/**
	 * Display admin notice after bulk campaign assignment.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function bulk_action_admin_notice(): void {
		if ( ! isset( $_GET['qrc_ms_pro_campaign_assigned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count = absint( $_GET['qrc_ms_pro_campaign_assigned'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$name  = isset( $_GET['qrc_ms_pro_campaign_name'] ) ? sanitize_text_field( urldecode( wp_unslash( $_GET['qrc_ms_pro_campaign_name'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $count > 0 && ! empty( $name ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: 1: number of QR codes, 2: campaign name */
					_n(
						'%1$d QR code assigned to campaign "%2$s".',
						'%1$d QR codes assigned to campaign "%2$s".',
						$count,
						'qrc-ms-pro'
					),
					$count,
					$name
				) )
			);
		}
	}

	/**
	 * Get statistics for all campaigns.
	 *
	 * @since 1.2.0
	 * @return array Array of campaign data with stats.
	 */
	public static function get_campaign_stats(): array {
		$terms = get_terms( array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$stats = array();

		foreach ( $terms as $term ) {
			$qr_code_ids = get_posts( array(
				'post_type'      => 'qrc_ms_code',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			) );

			$total_scans = 0;
			$week_scans  = 0;
			if ( class_exists( 'QRC_MS_Pro_Analytics' ) && ! empty( $qr_code_ids ) ) {
				foreach ( $qr_code_ids as $qr_id ) {
					$total_scans += QRC_MS_Pro_Analytics::get_scan_count( $qr_id );
					$week_scans  += QRC_MS_Pro_Analytics::get_scan_count( $qr_id, 'week' );
				}
			}

			$stats[] = array(
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'qr_count'    => count( $qr_code_ids ),
				'total_scans' => $total_scans,
				'week_scans'  => $week_scans,
				'edit_url'    => get_edit_term_link( $term->term_id, self::TAXONOMY, 'qrc_ms_code' ),
			);
		}

		return $stats;
	}
}
