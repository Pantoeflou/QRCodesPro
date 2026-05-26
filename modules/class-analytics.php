<?php
/**
 * Scan Analytics module.
 *
 * Records and reports QR code scan events. Hooks into the redirect
 * action to capture scans, and provides query methods for the dashboard.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics class.
 *
 * @since 1.1.0
 */
class QRC_MS_Pro_Analytics {

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Initialize the analytics module.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function init(): void {
		// Record scans on redirect.
		add_action( 'qrc_ms_pro/redirect', array( __CLASS__, 'record_scan' ), 10, 2 );

		// Register feature.
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Meta box on QR code edit screen.
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );

		// List table column.
		add_filter( 'manage_qrc_ms_code_posts_columns', array( __CLASS__, 'add_scans_column' ) );
		add_action( 'manage_qrc_ms_code_posts_custom_column', array( __CLASS__, 'render_scans_column' ), 10, 2 );
		add_filter( 'manage_edit-qrc_ms_code_sortable_columns', array( __CLASS__, 'make_scans_column_sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_scans' ) );
	}

	/**
	 * Register the scan_analytics feature.
	 *
	 * @since 1.1.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Scan Analytics', 'qrc-ms-pro' ),
			'description' => __( 'Track QR code scans with device and time-based analytics.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Record a scan event.
	 *
	 * Called during the redirect action. Must be fast — simple INSERT only.
	 *
	 * @since 1.1.0
	 *
	 * @param string $short_code  The short code being resolved.
	 * @param string $redirect_url The destination URL.
	 * @return void
	 */
	public static function record_scan( string $short_code, string $redirect_url ): void {
		global $wpdb;

		// Resolve QR code post ID from short code.
		$post = QRC_MS_Pro_Redirect_Handler::get_qr_post_by_code( $short_code );
		$qr_code_id = $post ? $post->ID : 0;

		// Gather request data.
		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$referer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$ip_address  = self::get_client_ip();
		$ip_hash     = hash( 'sha256', $ip_address . wp_salt( 'auth' ) );
		$device_type = self::detect_device_type( $user_agent );

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_name,
			array(
				'qr_code_id'  => $qr_code_id,
				'short_code'  => $short_code,
				'scanned_at'  => current_time( 'mysql' ),
				'ip_hash'     => $ip_hash,
				'country'     => '',
				'device_type' => $device_type,
				'user_agent'  => $user_agent,
				'referer'     => mb_substr( $referer, 0, 500 ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get the client IP address.
	 *
	 * @since 1.1.0
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Detect device type from user agent string.
	 *
	 * @since 1.1.0
	 *
	 * @param string $user_agent The user agent string.
	 * @return string 'mobile', 'tablet', or 'desktop'.
	 */
	private static function detect_device_type( string $user_agent ): string {
		if ( preg_match( '/Mobile|Android.*Mobile|iPhone|iPod/i', $user_agent ) ) {
			return 'mobile';
		}

		if ( preg_match( '/iPad|Android(?!.*Mobile)|Tablet/i', $user_agent ) ) {
			return 'tablet';
		}

		return 'desktop';
	}

	/**
	 * Get total scan count for a QR code.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $qr_code_id The QR code post ID.
	 * @param string $period     'all', 'today', 'week', 'month', '7days', '30days', '90days'.
	 * @return int Scan count.
	 */
	public static function get_scan_count( int $qr_code_id = 0, string $period = 'all' ): int {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();
		$where      = array();
		$values     = array();

		if ( $qr_code_id > 0 ) {
			$where[]  = 'qr_code_id = %d';
			$values[] = $qr_code_id;
		}

		$date_clause = self::get_period_clause( $period );
		if ( $date_clause ) {
			$where[] = $date_clause;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} {$where_sql}",
				...$values
			) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where_sql}" );
		}

		return $count;
	}

	/**
	 * Get scans over time for charting.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $qr_code_id The QR code post ID (0 for all).
	 * @param string $period     '7days', '30days', '90days'.
	 * @return array Array of ['date' => 'Y-m-d', 'count' => int].
	 */
	public static function get_scans_over_time( int $qr_code_id = 0, string $period = '30days' ): array {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();
		$days       = self::period_to_days( $period );
		$where      = array( 'scanned_at >= DATE_SUB(NOW(), INTERVAL %d DAY)' );
		$values     = array( $days );

		if ( $qr_code_id > 0 ) {
			$where[]  = 'qr_code_id = %d';
			$values[] = $qr_code_id;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(scanned_at) as scan_date, COUNT(*) as scan_count
			FROM {$table_name}
			{$where_sql}
			GROUP BY DATE(scanned_at)
			ORDER BY scan_date ASC",
			...$values
		), ARRAY_A );

		// Fill in missing dates with zero counts.
		$filled = array();
		$start  = new \DateTime( "-{$days} days" );
		$end    = new \DateTime( 'now' );

		$indexed = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$indexed[ $row['scan_date'] ] = (int) $row['scan_count'];
			}
		}

		$interval = new \DateInterval( 'P1D' );
		$range    = new \DatePeriod( $start, $interval, $end->modify( '+1 day' ) );

		foreach ( $range as $date ) {
			$key      = $date->format( 'Y-m-d' );
			$filled[] = array(
				'date'  => $key,
				'count' => $indexed[ $key ] ?? 0,
			);
		}

		return $filled;
	}

	/**
	 * Get scans grouped by device type.
	 *
	 * @since 1.1.0
	 *
	 * @param int $qr_code_id The QR code post ID (0 for all).
	 * @return array Array of ['device_type' => string, 'count' => int].
	 */
	public static function get_scans_by_device( int $qr_code_id = 0 ): array {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();

		if ( $qr_code_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT device_type, COUNT(*) as scan_count
				FROM {$table_name}
				WHERE qr_code_id = %d AND device_type != ''
				GROUP BY device_type
				ORDER BY scan_count DESC",
				$qr_code_id
			), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				"SELECT device_type, COUNT(*) as scan_count
				FROM {$table_name}
				WHERE device_type != ''
				GROUP BY device_type
				ORDER BY scan_count DESC",
				ARRAY_A
			);
		}

		return $results ?: array();
	}

	/**
	 * Get the last scan date for a QR code.
	 *
	 * @since 1.1.0
	 *
	 * @param int $qr_code_id The QR code post ID.
	 * @return string|null The last scan datetime or null.
	 */
	public static function get_last_scan( int $qr_code_id ): ?string {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT scanned_at FROM {$table_name} WHERE qr_code_id = %d ORDER BY scanned_at DESC LIMIT 1",
			$qr_code_id
		) );

		return $result ?: null;
	}

	/**
	 * Get top QR codes by scan count.
	 *
	 * @since 1.1.0
	 *
	 * @param string $period  Time period filter.
	 * @param int    $limit   Number of results.
	 * @param string $orderby Column to order by ('scans' or 'last_scan').
	 * @param string $order   'ASC' or 'DESC'.
	 * @return array Array of QR code data with scan counts.
	 */
	public static function get_top_qr_codes( string $period = 'all', int $limit = 50, string $orderby = 'scans', string $order = 'DESC' ): array {
		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();
		$date_clause = self::get_period_clause( $period );
		$where = $date_clause ? "WHERE {$date_clause}" : '';

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$order_col = match ( $orderby ) {
			'last_scan' => 'last_scan',
			default     => 'scan_count',
		};

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT qr_code_id, COUNT(*) as scan_count, MAX(scanned_at) as last_scan
			FROM {$table_name}
			{$where}
			GROUP BY qr_code_id
			ORDER BY {$order_col} {$order}
			LIMIT %d",
			$limit
		), ARRAY_A );

		return $results ?: array();
	}

	/**
	 * Register the admin submenu page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'Scan Analytics', 'qrc-ms-pro' ),
			__( 'Analytics', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-analytics',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets for the analytics page.
	 *
	 * @since 1.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( self::$page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'qrc-ms-pro-analytics',
			QRC_MS_PRO_PLUGIN_URL . 'assets/css/analytics.css',
			array(),
			QRC_MS_PRO_VERSION
		);
	}

	/**
	 * Render the analytics admin page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		// Get current period filter.
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30days'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$valid_periods = array( '7days', '30days', '90days', 'all' );
		if ( ! in_array( $period, $valid_periods, true ) ) {
			$period = '30days';
		}

		// Sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'scans'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Campaign filter.
		$campaign = isset( $_GET['campaign'] ) ? absint( $_GET['campaign'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$campaign_qr_ids = array();

		if ( $campaign > 0 && taxonomy_exists( QRC_MS_Pro_Campaigns::TAXONOMY ) ) {
			$campaign_qr_ids = get_posts( array(
				'post_type'      => 'qrc_ms_code',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => QRC_MS_Pro_Campaigns::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $campaign,
					),
				),
			) );

			// If campaign has no QR codes, set to empty array to show zero results.
			if ( empty( $campaign_qr_ids ) ) {
				$campaign_qr_ids = array( 0 );
			}
		}

		// Get all campaign terms for the filter dropdown.
		$campaign_terms = array();
		if ( taxonomy_exists( QRC_MS_Pro_Campaigns::TAXONOMY ) ) {
			$terms = get_terms( array(
				'taxonomy'   => QRC_MS_Pro_Campaigns::TAXONOMY,
				'hide_empty' => false,
			) );
			if ( ! is_wp_error( $terms ) ) {
				$campaign_terms = $terms;
			}
		}

		// Gather data — filtered by campaign if selected.
		if ( ! empty( $campaign_qr_ids ) && $campaign > 0 ) {
			$total_scans = self::get_scan_count_for_ids( $campaign_qr_ids, 'all' );
			$today_scans = self::get_scan_count_for_ids( $campaign_qr_ids, 'today' );
			$week_scans  = self::get_scan_count_for_ids( $campaign_qr_ids, 'week' );
			$month_scans = self::get_scan_count_for_ids( $campaign_qr_ids, 'month' );
			$chart_data  = self::get_scans_over_time_for_ids( $campaign_qr_ids, $period );
			$top_qr_codes = self::get_top_qr_codes_for_ids( $campaign_qr_ids, $period, 50, $orderby, $order );
		} else {
			$total_scans  = self::get_scan_count( 0, 'all' );
			$today_scans  = self::get_scan_count( 0, 'today' );
			$week_scans   = self::get_scan_count( 0, 'week' );
			$month_scans  = self::get_scan_count( 0, 'month' );
			$chart_data   = self::get_scans_over_time( 0, $period );
			$top_qr_codes = self::get_top_qr_codes( $period, 50, $orderby, $order );
		}

		include QRC_MS_PRO_PLUGIN_DIR . 'modules/views/analytics-dashboard.php';
	}

	/**
	 * Register the analytics meta box on QR code edit screen.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'qrc_ms_pro_analytics',
			__( 'Scan Analytics', 'qrc-ms-pro' ),
			array( __CLASS__, 'render_meta_box' ),
			'qrc_ms_code',
			'side',
			'default'
		);
	}

	/**
	 * Render the analytics meta box.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Post $post The current post.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$is_dynamic = (bool) get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, true );

		if ( ! $is_dynamic ) {
			echo '<p class="description">' . esc_html__( 'Enable Dynamic Mode to track scans.', 'qrc-ms-pro' ) . '</p>';
			return;
		}

		$total_scans = self::get_scan_count( $post->ID );
		$week_scans  = self::get_scan_count( $post->ID, 'week' );
		$last_scan   = self::get_last_scan( $post->ID );
		$devices     = self::get_scans_by_device( $post->ID );

		$device_total = array_sum( array_column( $devices, 'scan_count' ) );
		?>
		<div class="qrc-ms-pro-analytics-meta">
			<div class="qrc-ms-pro-analytics-stat">
				<span class="qrc-ms-pro-analytics-label"><?php esc_html_e( 'Total Scans', 'qrc-ms-pro' ); ?></span>
				<span class="qrc-ms-pro-analytics-value"><?php echo esc_html( number_format_i18n( $total_scans ) ); ?></span>
			</div>
			<div class="qrc-ms-pro-analytics-stat">
				<span class="qrc-ms-pro-analytics-label"><?php esc_html_e( 'This Week', 'qrc-ms-pro' ); ?></span>
				<span class="qrc-ms-pro-analytics-value"><?php echo esc_html( number_format_i18n( $week_scans ) ); ?></span>
			</div>
			<div class="qrc-ms-pro-analytics-stat">
				<span class="qrc-ms-pro-analytics-label"><?php esc_html_e( 'Last Scan', 'qrc-ms-pro' ); ?></span>
				<span class="qrc-ms-pro-analytics-value">
					<?php
					if ( $last_scan ) {
						echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_scan ) ) );
					} else {
						esc_html_e( 'Never', 'qrc-ms-pro' );
					}
					?>
				</span>
			</div>

			<?php if ( $device_total > 0 ) : ?>
			<div class="qrc-ms-pro-analytics-stat">
				<span class="qrc-ms-pro-analytics-label"><?php esc_html_e( 'Devices', 'qrc-ms-pro' ); ?></span>
				<div class="qrc-ms-pro-analytics-devices">
					<?php foreach ( $devices as $device ) : ?>
						<span class="qrc-ms-pro-device-badge">
							<?php echo esc_html( ucfirst( $device['device_type'] ) ); ?>:
							<?php echo esc_html( round( ( (int) $device['scan_count'] / $device_total ) * 100 ) ); ?>%
						</span>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add "Scans" column to the QR code list table.
	 *
	 * @since 1.1.0
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_scans_column( array $columns ): array {
		$columns['qrc_ms_pro_scans'] = __( 'Scans', 'qrc-ms-pro' );
		return $columns;
	}

	/**
	 * Render the "Scans" column content.
	 *
	 * @since 1.1.0
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_scans_column( string $column, int $post_id ): void {
		if ( 'qrc_ms_pro_scans' !== $column ) {
			return;
		}

		$is_dynamic = (bool) get_post_meta( $post_id, QRC_MS_Pro_Redirect_Handler::META_IS_DYNAMIC, true );

		if ( ! $is_dynamic ) {
			echo '<span class="qrc-ms-pro-scans-na">&mdash;</span>';
			return;
		}

		$count = self::get_scan_count( $post_id );
		echo '<strong>' . esc_html( number_format_i18n( $count ) ) . '</strong>';
	}

	/**
	 * Make the scans column sortable.
	 *
	 * @since 1.1.0
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function make_scans_column_sortable( array $columns ): array {
		$columns['qrc_ms_pro_scans'] = 'qrc_ms_pro_scans';
		return $columns;
	}

	/**
	 * Handle sorting by scan count in the list table.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public static function sort_by_scans( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'qrc_ms_pro_scans' !== $query->get( 'orderby' ) ) {
			return;
		}

		global $wpdb;
		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();

		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'meta_key', '_qrc_ms_pro_scan_count_cache' );
		$query->set( 'meta_type', 'NUMERIC' );
	}

	/**
	 * Get scan count for a set of QR code IDs.
	 *
	 * @since 1.3.0
	 *
	 * @param array  $qr_code_ids Array of QR code post IDs.
	 * @param string $period      Time period filter.
	 * @return int Scan count.
	 */
	public static function get_scan_count_for_ids( array $qr_code_ids, string $period = 'all' ): int {
		global $wpdb;

		if ( empty( $qr_code_ids ) ) {
			return 0;
		}

		$table_name  = QRC_MS_Pro_Analytics_DB::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $qr_code_ids ), '%d' ) );
		$where = "qr_code_id IN ({$placeholders})";

		$date_clause = self::get_period_clause( $period );
		if ( $date_clause ) {
			$where .= " AND {$date_clause}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE {$where}",
			...$qr_code_ids
		) );

		return $count;
	}

	/**
	 * Get scans over time for a set of QR code IDs.
	 *
	 * @since 1.3.0
	 *
	 * @param array  $qr_code_ids Array of QR code post IDs.
	 * @param string $period      '7days', '30days', '90days'.
	 * @return array Array of ['date' => 'Y-m-d', 'count' => int].
	 */
	public static function get_scans_over_time_for_ids( array $qr_code_ids, string $period = '30days' ): array {
		global $wpdb;

		if ( empty( $qr_code_ids ) ) {
			return array();
		}

		$table_name   = QRC_MS_Pro_Analytics_DB::get_table_name();
		$days         = self::period_to_days( $period );
		$placeholders = implode( ',', array_fill( 0, count( $qr_code_ids ), '%d' ) );

		$values = array( $days );
		$values = array_merge( $values, $qr_code_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(scanned_at) as scan_date, COUNT(*) as scan_count
			FROM {$table_name}
			WHERE scanned_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND qr_code_id IN ({$placeholders})
			GROUP BY DATE(scanned_at)
			ORDER BY scan_date ASC",
			...$values
		), ARRAY_A );

		// Fill in missing dates with zero counts.
		$filled = array();
		$start  = new \DateTime( "-{$days} days" );
		$end    = new \DateTime( 'now' );

		$indexed = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$indexed[ $row['scan_date'] ] = (int) $row['scan_count'];
			}
		}

		$interval = new \DateInterval( 'P1D' );
		$range    = new \DatePeriod( $start, $interval, $end->modify( '+1 day' ) );

		foreach ( $range as $date ) {
			$key      = $date->format( 'Y-m-d' );
			$filled[] = array(
				'date'  => $key,
				'count' => $indexed[ $key ] ?? 0,
			);
		}

		return $filled;
	}

	/**
	 * Get top QR codes by scan count for a set of QR code IDs.
	 *
	 * @since 1.3.0
	 *
	 * @param array  $qr_code_ids Array of QR code post IDs.
	 * @param string $period      Time period filter.
	 * @param int    $limit       Number of results.
	 * @param string $orderby     Column to order by ('scans' or 'last_scan').
	 * @param string $order       'ASC' or 'DESC'.
	 * @return array Array of QR code data with scan counts.
	 */
	public static function get_top_qr_codes_for_ids( array $qr_code_ids, string $period = 'all', int $limit = 50, string $orderby = 'scans', string $order = 'DESC' ): array {
		global $wpdb;

		if ( empty( $qr_code_ids ) ) {
			return array();
		}

		$table_name   = QRC_MS_Pro_Analytics_DB::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $qr_code_ids ), '%d' ) );
		$date_clause  = self::get_period_clause( $period );

		$where = "qr_code_id IN ({$placeholders})";
		if ( $date_clause ) {
			$where .= " AND {$date_clause}";
		}

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$order_col = match ( $orderby ) {
			'last_scan' => 'last_scan',
			default     => 'scan_count',
		};

		$values = array_merge( $qr_code_ids, array( $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT qr_code_id, COUNT(*) as scan_count, MAX(scanned_at) as last_scan
			FROM {$table_name}
			WHERE {$where}
			GROUP BY qr_code_id
			ORDER BY {$order_col} {$order}
			LIMIT %d",
			...$values
		), ARRAY_A );

		return $results ?: array();
	}

	/**
	 * Convert a period string to a SQL date clause.
	 *
	 * @since 1.1.0
	 *
	 * @param string $period The period identifier.
	 * @return string SQL clause or empty string.
	 */
	private static function get_period_clause( string $period ): string {
		return match ( $period ) {
			'today' => "DATE(scanned_at) = CURDATE()",
			'week'  => "scanned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			'month' => "scanned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			'7days' => "scanned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
			'30days' => "scanned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
			'90days' => "scanned_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
			default  => '',
		};
	}

	/**
	 * Convert a period string to number of days.
	 *
	 * @since 1.1.0
	 *
	 * @param string $period The period identifier.
	 * @return int Number of days.
	 */
	private static function period_to_days( string $period ): int {
		return match ( $period ) {
			'7days'  => 7,
			'30days' => 30,
			'90days' => 90,
			default  => 30,
		};
	}
}
