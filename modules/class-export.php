<?php
/**
 * Export & Reporting module.
 *
 * Provides CSV export of QR codes and analytics data, plus a printable
 * QR sheet generator for physical distribution.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Export {

	/**
	 * Nonce action for export requests.
	 *
	 * @since 1.2.0
	 */
	private const NONCE_ACTION = 'qrc_ms_pro_export';

	/**
	 * Initialize the export module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );

		// Add bulk action to QR codes list table.
		add_filter( 'bulk_actions-edit-qrc_ms_code', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-qrc_ms_code', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );

		// Export handlers (admin_init for CSV download before headers).
		add_action( 'admin_init', array( __CLASS__, 'handle_export_request' ) );

		// Add export buttons to analytics dashboard.
		add_action( 'qrc_ms_pro/analytics_dashboard_actions', array( __CLASS__, 'render_analytics_export_buttons' ) );

		// Admin page for print sheet.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
	}

	/**
	 * Register the export feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Export & Reporting', 'qrc-ms-pro' ),
			'description' => __( 'Export QR codes and analytics as CSV, and generate printable QR sheets.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register bulk actions on the QR codes list table.
	 *
	 * @since 1.2.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public static function register_bulk_actions( array $actions ): array {
		$actions['qrc_ms_pro_export_csv']   = __( 'Export as CSV', 'qrc-ms-pro' );
		$actions['qrc_ms_pro_print_sheet']  = __( 'Print QR Sheet', 'qrc-ms-pro' );

		return $actions;
	}

	/**
	 * Handle bulk actions from the list table.
	 *
	 * @since 1.2.0
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The action being taken.
	 * @param array  $post_ids     The selected post IDs.
	 * @return string Modified redirect URL.
	 */
	public static function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ): string {
		if ( 'qrc_ms_pro_export_csv' === $action ) {
			// Store IDs in transient and redirect to export handler.
			$key = 'qrc_ms_pro_export_' . wp_generate_password( 12, false );
			set_transient( $key, $post_ids, 5 * MINUTE_IN_SECONDS );

			$redirect_url = add_query_arg( array(
				'qrc_ms_pro_export' => 'csv',
				'export_key'        => $key,
				'_wpnonce'          => wp_create_nonce( self::NONCE_ACTION ),
			), admin_url( 'admin.php' ) );
		}

		if ( 'qrc_ms_pro_print_sheet' === $action ) {
			$key = 'qrc_ms_pro_print_' . wp_generate_password( 12, false );
			set_transient( $key, $post_ids, 5 * MINUTE_IN_SECONDS );

			$redirect_url = add_query_arg( array(
				'page'      => 'qrc-ms-pro-print-sheet',
				'print_key' => $key,
			), admin_url( 'admin.php' ) );
		}

		return $redirect_url;
	}

	/**
	 * Handle export requests (runs on admin_init to send headers before output).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function handle_export_request(): void {
		if ( ! isset( $_GET['qrc_ms_pro_export'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$export_type = sanitize_text_field( wp_unslash( $_GET['qrc_ms_pro_export'] ) );

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
			self::NONCE_ACTION
		) ) {
			wp_die( esc_html__( 'Security check failed.', 'qrc-ms-pro' ) );
		}

		switch ( $export_type ) {
			case 'csv':
				self::export_qr_codes_csv();
				break;
			case 'analytics':
				self::export_analytics_csv();
				break;
		}
	}

	/**
	 * Export QR codes as CSV.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function export_qr_codes_csv(): void {
		$export_key = isset( $_GET['export_key'] ) ? sanitize_text_field( wp_unslash( $_GET['export_key'] ) ) : '';
		$post_ids   = array();

		if ( ! empty( $export_key ) ) {
			$post_ids = get_transient( $export_key );
			delete_transient( $export_key );
		}

		// If no specific IDs, export all.
		if ( empty( $post_ids ) ) {
			$post_ids = get_posts( array(
				'post_type'      => 'qrc_ms_code',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) );
		}

		$filename = 'qr-codes-export-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		// Header row.
		fputcsv( $output, array(
			'ID',
			'Title',
			'Type',
			'Data',
			'Short Code',
			'Is Dynamic',
			'Scan Count',
			'Created Date',
			'Status',
		) );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$type       = get_post_meta( $post_id, '_qrc_ms_type', true );
			$data       = get_post_meta( $post_id, '_qrc_ms_data', true );
			$short_code = get_post_meta( $post_id, '_qrc_ms_pro_short_code', true );
			$is_dynamic = get_post_meta( $post_id, '_qrc_ms_pro_is_dynamic', true );

			$scan_count = 0;
			if ( class_exists( 'QRC_MS_Pro_Analytics' ) ) {
				$scan_count = QRC_MS_Pro_Analytics::get_scan_count( $post_id );
			}

			fputcsv( $output, array(
				$post_id,
				$post->post_title,
				$type ?: 'url',
				$data,
				$short_code,
				$is_dynamic ? 'Yes' : 'No',
				$scan_count,
				$post->post_date,
				$post->post_status,
			) );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Export analytics data as CSV.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function export_analytics_csv(): void {
		if ( ! class_exists( 'QRC_MS_Pro_Analytics_DB' ) ) {
			wp_die( esc_html__( 'Analytics module is not available.', 'qrc-ms-pro' ) );
		}

		global $wpdb;

		$table_name = QRC_MS_Pro_Analytics_DB::get_table_name();
		$period     = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30days';
		$qr_code_id = isset( $_GET['qr_code_id'] ) ? absint( $_GET['qr_code_id'] ) : 0;

		$where  = array();
		$values = array();

		// Period filter.
		$days = match ( $period ) {
			'7days'  => 7,
			'30days' => 30,
			'90days' => 90,
			default  => 0,
		};

		if ( $days > 0 ) {
			$where[]  = 'scanned_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
			$values[] = $days;
		}

		if ( $qr_code_id > 0 ) {
			$where[]  = 'qr_code_id = %d';
			$values[] = $qr_code_id;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT qr_code_id, scanned_at, device_type, referer FROM {$table_name} {$where_sql} ORDER BY scanned_at DESC LIMIT 10000",
				...$values
			), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				"SELECT qr_code_id, scanned_at, device_type, referer FROM {$table_name} {$where_sql} ORDER BY scanned_at DESC LIMIT 10000",
				ARRAY_A
			);
		}

		$filename = 'qr-analytics-export-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array(
			'QR Code ID',
			'QR Code Title',
			'Scanned At',
			'Device Type',
			'Referer',
		) );

		// Cache post titles.
		$title_cache = array();

		foreach ( $results as $row ) {
			$qr_id = (int) $row['qr_code_id'];

			if ( ! isset( $title_cache[ $qr_id ] ) ) {
				$post = get_post( $qr_id );
				$title_cache[ $qr_id ] = $post ? $post->post_title : __( '(Deleted)', 'qrc-ms-pro' );
			}

			fputcsv( $output, array(
				$qr_id,
				$title_cache[ $qr_id ],
				$row['scanned_at'],
				$row['device_type'],
				$row['referer'],
			) );
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Render export buttons on the analytics dashboard.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_analytics_export_buttons(): void {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30days'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$export_url = wp_nonce_url(
			add_query_arg( array(
				'qrc_ms_pro_export' => 'analytics',
				'period'            => $period,
			), admin_url( 'admin.php' ) ),
			self::NONCE_ACTION
		);

		$csv_export_url = wp_nonce_url(
			add_query_arg( array(
				'qrc_ms_pro_export' => 'csv',
			), admin_url( 'admin.php' ) ),
			self::NONCE_ACTION
		);

		?>
		<div class="qrc-ms-pro-export-buttons">
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Export Analytics CSV', 'qrc-ms-pro' ); ?>
			</a>
			<a href="<?php echo esc_url( $csv_export_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Export All QR Codes CSV', 'qrc-ms-pro' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=qrc-ms-pro-print-sheet' ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Print QR Sheet', 'qrc-ms-pro' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Register the print sheet admin page (hidden from menu).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		add_submenu_page(
			'', // Hidden from menu.
			__( 'Print QR Sheet', 'qrc-ms-pro' ),
			__( 'Print QR Sheet', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-print-sheet',
			array( __CLASS__, 'render_print_sheet' )
		);
	}

	/**
	 * Render the printable QR sheet page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_print_sheet(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		// Get QR codes to print.
		$print_key = isset( $_GET['print_key'] ) ? sanitize_text_field( wp_unslash( $_GET['print_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_ids  = array();

		if ( ! empty( $print_key ) ) {
			$post_ids = get_transient( $print_key );
			delete_transient( $print_key );
		}

		// If no specific IDs, get all published QR codes.
		if ( empty( $post_ids ) ) {
			$post_ids = get_posts( array(
				'post_type'      => 'qrc_ms_code',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) );
		}

		if ( empty( $post_ids ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'No QR codes found to print.', 'qrc-ms-pro' ) . '</p></div>';
			return;
		}

		// Render the print-friendly page.
		self::render_print_page( $post_ids );
	}

	/**
	 * Render a print-friendly HTML page with QR codes in a grid.
	 *
	 * @since 1.2.0
	 *
	 * @param array $post_ids Array of QR code post IDs.
	 * @return void
	 */
	private static function render_print_page( array $post_ids ): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Printable QR Sheet', 'qrc-ms-pro' ); ?></h1>
			<p>
				<button type="button" class="button button-primary" onclick="window.print();">
					<span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
					<?php esc_html_e( 'Print This Page', 'qrc-ms-pro' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=qrc_ms_code' ) ); ?>" class="button">
					<?php esc_html_e( '&larr; Back to QR Codes', 'qrc-ms-pro' ); ?>
				</a>
			</p>

			<style>
				.qrc-ms-pro-print-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
					gap: 24px;
					padding: 20px 0;
				}
				.qrc-ms-pro-print-item {
					text-align: center;
					padding: 16px;
					border: 1px solid #ddd;
					border-radius: 4px;
					page-break-inside: avoid;
				}
				.qrc-ms-pro-print-item h3 {
					font-size: 12px;
					margin: 8px 0 4px;
					word-break: break-word;
				}
				.qrc-ms-pro-print-item .qrc-ms-pro-print-data {
					font-size: 10px;
					color: #666;
					word-break: break-all;
				}
				.qrc-ms-pro-print-item svg {
					max-width: 150px;
					height: auto;
				}
				@media print {
					.wrap > h1,
					.wrap > p,
					#wpadminbar,
					#adminmenumain,
					#wpfooter { display: none !important; }
					.wrap { margin: 0; padding: 0; }
					.qrc-ms-pro-print-grid { gap: 16px; }
					.qrc-ms-pro-print-item { border: 1px solid #ccc; }
				}
			</style>

			<div class="qrc-ms-pro-print-grid">
				<?php foreach ( $post_ids as $post_id ) : ?>
					<?php
					$post = get_post( $post_id );
					if ( ! $post ) {
						continue;
					}

					$data = get_post_meta( $post_id, '_qrc_ms_data', true );
					if ( empty( $data ) ) {
						continue;
					}

					// Check if dynamic — use redirect URL.
					$is_dynamic = get_post_meta( $post_id, '_qrc_ms_pro_is_dynamic', true );
					$short_code = get_post_meta( $post_id, '_qrc_ms_pro_short_code', true );

					if ( $is_dynamic && ! empty( $short_code ) && class_exists( 'QRC_MS_Pro_Redirect_Handler' ) ) {
						$data = QRC_MS_Pro_Redirect_Handler::build_redirect_url( $short_code );
					}

					/**
					 * Filter to render a QR code as inline SVG for printing.
					 *
					 * @since 1.2.0
					 *
					 * @param string $svg  The SVG content.
					 * @param string $data The data to encode.
					 * @param int    $size The desired size.
					 */
					$svg = apply_filters( 'qrc_ms/generate_svg', '', $data, 150 );
					?>
					<div class="qrc-ms-pro-print-item">
						<?php if ( ! empty( $svg ) ) : ?>
							<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from trusted filter. ?>
						<?php else : ?>
							<div style="width:150px;height:150px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:0 auto;">
								<span style="font-size:10px;"><?php esc_html_e( 'QR', 'qrc-ms-pro' ); ?></span>
							</div>
						<?php endif; ?>
						<h3><?php echo esc_html( $post->post_title ); ?></h3>
						<div class="qrc-ms-pro-print-data"><?php echo esc_html( mb_substr( $data, 0, 60 ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
