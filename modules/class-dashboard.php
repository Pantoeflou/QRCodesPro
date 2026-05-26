<?php
/**
 * Pro Dashboard module.
 *
 * Provides a dashboard landing page for Pro users with stats,
 * recent scans, top performers, and quick actions.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard class.
 *
 * @since 1.4.0
 */
class QRC_MS_Pro_Dashboard {

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Initialize the dashboard module.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the dashboard submenu page.
	 *
	 * Registered at priority 5 so it appears before other items (which register at 20).
	 * This makes "Dashboard" the first submenu item, so clicking "QR Codes" in the
	 * sidebar goes to the dashboard for Pro users.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'QR Codes Dashboard', 'qrc-ms-pro' ),
			__( 'Dashboard', 'qrc-ms-pro' ),
			'edit_posts',
			'qrc-ms-pro-dashboard',
			array( __CLASS__, 'render_admin_page' ),
			0
		);
	}

	/**
	 * Enqueue admin assets for the dashboard page.
	 *
	 * @since 1.4.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( self::$page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'qrc-ms-pro-dashboard',
			QRC_MS_PRO_PLUGIN_URL . 'assets/css/dashboard.css',
			array(),
			QRC_MS_PRO_VERSION
		);
	}

	/**
	 * Render the dashboard admin page.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		// Gather stats.
		$total_qr_codes  = self::get_total_qr_codes();
		$dynamic_codes   = self::get_dynamic_codes_count();
		$total_scans_7d  = QRC_MS_Pro_Analytics::get_scan_count( 0, '7days' );
		$expired_codes   = self::get_expired_codes_count();

		// Recent scans.
		$recent_scans = self::get_recent_scans( 5 );

		// Top performers this week.
		$top_performers = QRC_MS_Pro_Analytics::get_top_qr_codes( '7days', 5 );

		// URLs for quick actions.
		$create_url    = admin_url( 'post-new.php?post_type=qrc_ms_code' );
		$all_codes_url = admin_url( 'edit.php?post_type=qrc_ms_code' );
		$analytics_url = admin_url( 'edit.php?post_type=qrc_ms_code&page=qrc-ms-pro-analytics' );
		$bulk_url      = admin_url( 'edit.php?post_type=qrc_ms_code&page=qrc-ms-pro-bulk' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'QR Codes Dashboard', 'qrc-ms-pro' ); ?></h1>

			<div class="qrc-ms-pro-page-intro">
				<p><?php esc_html_e( 'Welcome to your QR Codes command centre. Here\'s a snapshot of your QR code activity.', 'qrc-ms-pro' ); ?></p>
			</div>

			<!-- Stats Cards -->
			<div class="qrc-ms-pro-dashboard-stats">
				<div class="qrc-ms-pro-stat-card">
					<span class="qrc-ms-pro-stat-icon dashicons dashicons-screenoptions"></span>
					<div class="qrc-ms-pro-stat-content">
						<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( $total_qr_codes ) ); ?></span>
						<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Total QR Codes', 'qrc-ms-pro' ); ?></span>
					</div>
				</div>
				<div class="qrc-ms-pro-stat-card">
					<span class="qrc-ms-pro-stat-icon dashicons dashicons-randomize"></span>
					<div class="qrc-ms-pro-stat-content">
						<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( $dynamic_codes ) ); ?></span>
						<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Dynamic Codes', 'qrc-ms-pro' ); ?></span>
					</div>
				</div>
				<div class="qrc-ms-pro-stat-card">
					<span class="qrc-ms-pro-stat-icon dashicons dashicons-chart-bar"></span>
					<div class="qrc-ms-pro-stat-content">
						<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( $total_scans_7d ) ); ?></span>
						<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Scans (7 Days)', 'qrc-ms-pro' ); ?></span>
					</div>
				</div>
				<div class="qrc-ms-pro-stat-card">
					<span class="qrc-ms-pro-stat-icon dashicons dashicons-warning"></span>
					<div class="qrc-ms-pro-stat-content">
						<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( $expired_codes ) ); ?></span>
						<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Expired Codes', 'qrc-ms-pro' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Two-column layout -->
			<div class="qrc-ms-pro-dashboard-grid">
				<!-- Left: Recent Scans -->
				<div class="qrc-ms-pro-dashboard-card">
					<h2><?php esc_html_e( 'Recent Scans', 'qrc-ms-pro' ); ?></h2>
					<?php if ( ! empty( $recent_scans ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'QR Code', 'qrc-ms-pro' ); ?></th>
									<th><?php esc_html_e( 'Device', 'qrc-ms-pro' ); ?></th>
									<th><?php esc_html_e( 'When', 'qrc-ms-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $recent_scans as $scan ) : ?>
									<tr>
										<td>
											<?php
											$qr_title = self::get_qr_code_title( (int) $scan->qr_code_id );
											echo esc_html( $qr_title );
											?>
										</td>
										<td>
											<span class="qrc-ms-pro-device-badge"><?php echo esc_html( ucfirst( $scan->device_type ?: __( 'Unknown', 'qrc-ms-pro' ) ) ); ?></span>
										</td>
										<td><?php echo esc_html( self::time_ago( $scan->scanned_at ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="qrc-ms-pro-dashboard-card-footer">
							<a href="<?php echo esc_url( $analytics_url ); ?>"><?php esc_html_e( 'View All →', 'qrc-ms-pro' ); ?></a>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No scans recorded yet. Dynamic QR codes will show scan activity here.', 'qrc-ms-pro' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Right: Top Performers -->
				<div class="qrc-ms-pro-dashboard-card">
					<h2><?php esc_html_e( 'Top Performers (This Week)', 'qrc-ms-pro' ); ?></h2>
					<?php if ( ! empty( $top_performers ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'QR Code', 'qrc-ms-pro' ); ?></th>
									<th><?php esc_html_e( 'Scans', 'qrc-ms-pro' ); ?></th>
									<th><?php esc_html_e( 'Action', 'qrc-ms-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_performers as $performer ) : ?>
									<tr>
										<td>
											<?php
											$qr_title = self::get_qr_code_title( (int) $performer['qr_code_id'] );
											echo esc_html( $qr_title );
											?>
										</td>
										<td><strong><?php echo esc_html( number_format_i18n( (int) $performer['scan_count'] ) ); ?></strong></td>
										<td>
											<a href="<?php echo esc_url( get_edit_post_link( (int) $performer['qr_code_id'] ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Edit', 'qrc-ms-pro' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No scan data for this week yet.', 'qrc-ms-pro' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="qrc-ms-pro-dashboard-actions">
				<a href="<?php echo esc_url( $create_url ); ?>" class="button button-primary button-hero">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Create QR Code', 'qrc-ms-pro' ); ?>
				</a>
				<a href="<?php echo esc_url( $bulk_url ); ?>" class="button button-hero">
					<span class="dashicons dashicons-grid-view"></span>
					<?php esc_html_e( 'Bulk Generate', 'qrc-ms-pro' ); ?>
				</a>
				<a href="<?php echo esc_url( $all_codes_url ); ?>" class="button button-hero">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View All QR Codes', 'qrc-ms-pro' ); ?>
				</a>
				<a href="<?php echo esc_url( $analytics_url ); ?>" class="button button-hero">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Analytics', 'qrc-ms-pro' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get total QR codes count.
	 *
	 * @since 1.4.0
	 * @return int
	 */
	private static function get_total_qr_codes(): int {
		$counts = wp_count_posts( 'qrc_ms_code' );
		return (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 );
	}

	/**
	 * Get count of dynamic QR codes.
	 *
	 * @since 1.4.0
	 * @return int
	 */
	private static function get_dynamic_codes_count(): int {
		$query = new \WP_Query( array(
			'post_type'      => 'qrc_ms_code',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_qrc_ms_is_dynamic',
					'value' => '1',
				),
			),
		) );

		return (int) $query->found_posts;
	}

	/**
	 * Get count of expired QR codes.
	 *
	 * Expired codes are dynamic codes whose expiry date has passed.
	 *
	 * @since 1.4.0
	 * @return int
	 */
	private static function get_expired_codes_count(): int {
		$query = new \WP_Query( array(
			'post_type'      => 'qrc_ms_code',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_qrc_ms_expiry_date',
					'value'   => current_time( 'mysql' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
		) );

		return (int) $query->found_posts;
	}

	/**
	 * Get recent scan events.
	 *
	 * @since 1.4.0
	 *
	 * @param int $limit Number of scans to retrieve.
	 * @return array Array of scan row objects.
	 */
	private static function get_recent_scans( int $limit = 5 ): array {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT qr_code_id, device_type, scanned_at FROM {$table_name} ORDER BY scanned_at DESC LIMIT %d",
			$limit
		) );

		return $results ?: array();
	}

	/**
	 * Get the title of a QR code post.
	 *
	 * @since 1.4.0
	 *
	 * @param int $post_id The QR code post ID.
	 * @return string The post title or a fallback.
	 */
	private static function get_qr_code_title( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return __( '(Unknown)', 'qrc-ms-pro' );
		}

		$title = get_the_title( $post_id );
		return ! empty( $title ) ? $title : sprintf(
			/* translators: %d: post ID */
			__( 'QR Code #%d', 'qrc-ms-pro' ),
			$post_id
		);
	}

	/**
	 * Convert a datetime string to a human-readable "time ago" format.
	 *
	 * @since 1.4.0
	 *
	 * @param string $datetime MySQL datetime string.
	 * @return string Human-readable time difference.
	 */
	private static function time_ago( string $datetime ): string {
		$timestamp = strtotime( $datetime );
		if ( ! $timestamp ) {
			return __( 'Unknown', 'qrc-ms-pro' );
		}

		return sprintf(
			/* translators: %s: human-readable time difference */
			__( '%s ago', 'qrc-ms-pro' ),
			human_time_diff( $timestamp, current_time( 'timestamp' ) )
		);
	}
}
