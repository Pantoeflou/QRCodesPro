<?php
/**
 * Bulk Generator module.
 *
 * Allows generating multiple QR codes at once from CSV upload or
 * WooCommerce product bulk generation.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Generator class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Bulk_Generator {

	/**
	 * Nonce action for bulk generation.
	 *
	 * @since 1.2.0
	 */
	private const NONCE_ACTION = 'qrc_ms_pro_bulk_generate';

	/**
	 * AJAX nonce action.
	 *
	 * @since 1.2.0
	 */
	private const AJAX_NONCE_ACTION = 'qrc_ms_pro_bulk_ajax';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Initialize the bulk generator module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_qrc_ms_pro_bulk_process_csv', array( __CLASS__, 'ajax_process_csv' ) );
		add_action( 'wp_ajax_qrc_ms_pro_bulk_woo', array( __CLASS__, 'ajax_process_woo' ) );
		add_action( 'wp_ajax_qrc_ms_pro_bulk_download_zip', array( __CLASS__, 'ajax_download_zip' ) );
	}

	/**
	 * Register the bulk generator feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Bulk Generation', 'qrc-ms-pro' ),
			'description' => __( 'Generate multiple QR codes at once from CSV upload or WooCommerce products.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register the bulk generator admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'Bulk Generate QR Codes', 'qrc-ms-pro' ),
			__( 'Bulk Generate', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-bulk-generate',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
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

		wp_enqueue_script(
			'qrc-ms-pro-bulk-generator',
			QRC_MS_PRO_PLUGIN_URL . 'assets/js/bulk-generator.js',
			array( 'jquery' ),
			QRC_MS_PRO_VERSION,
			true
		);

		wp_localize_script( 'qrc-ms-pro-bulk-generator', 'qrcMsProBulk', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
			'i18n'    => array(
				'processing'  => __( 'Processing...', 'qrc-ms-pro' ),
				'complete'    => __( 'Bulk generation complete!', 'qrc-ms-pro' ),
				'error'       => __( 'An error occurred during processing.', 'qrc-ms-pro' ),
				'noFile'      => __( 'Please select a CSV file.', 'qrc-ms-pro' ),
				'downloading' => __( 'Preparing download...', 'qrc-ms-pro' ),
			),
		) );
	}

	/**
	 * Render the bulk generator admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		$woo_active = class_exists( 'WooCommerce' );

		// Get available templates.
		$templates = get_posts( array(
			'post_type'      => 'qrc_ms_template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		include QRC_MS_PRO_PLUGIN_DIR . 'modules/views/bulk-generator.php';
	}

	/**
	 * AJAX handler: Process CSV upload and create QR codes.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function ajax_process_csv(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'qrc-ms-pro' ) ), 403 );
		}

		if ( empty( $_FILES['csv_file'] ) || UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
			wp_send_json_error( array( 'message' => __( 'No valid CSV file uploaded.', 'qrc-ms-pro' ) ), 400 );
		}

		$file_path = sanitize_text_field( $_FILES['csv_file']['tmp_name'] );
		$file_type = wp_check_filetype( sanitize_file_name( $_FILES['csv_file']['name'] ) );

		if ( 'csv' !== $file_type['ext'] && 'text/csv' !== $file_type['type'] ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a valid CSV file.', 'qrc-ms-pro' ) ), 400 );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$format      = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'png';
		$size        = isset( $_POST['size'] ) ? absint( $_POST['size'] ) : 300;

		if ( ! in_array( $format, array( 'png', 'svg' ), true ) ) {
			$format = 'png';
		}

		$size = max( 100, min( 2000, $size ) );

		$created_ids = array();
		$errors      = array();
		$row_number  = 0;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not read the CSV file.', 'qrc-ms-pro' ) ), 500 );
		}

		// Read header row.
		$header = fgetcsv( $handle );
		if ( false === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array( 'message' => __( 'CSV file is empty or malformed.', 'qrc-ms-pro' ) ), 400 );
		}

		// Normalize header columns.
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );

		// Validate required columns.
		$required = array( 'title', 'data' );
		$missing  = array_diff( $required, $header );
		if ( ! empty( $missing ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: comma-separated list of missing columns */
					__( 'CSV is missing required columns: %s', 'qrc-ms-pro' ),
					implode( ', ', $missing )
				),
			), 400 );
		}

		$col_map = array_flip( $header );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			// Skip empty rows.
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			$title       = isset( $col_map['title'], $row[ $col_map['title'] ] ) ? sanitize_text_field( $row[ $col_map['title'] ] ) : '';
			$type        = isset( $col_map['type'], $row[ $col_map['type'] ] ) ? sanitize_text_field( $row[ $col_map['type'] ] ) : 'url';
			$data        = isset( $col_map['data'], $row[ $col_map['data'] ] ) ? sanitize_text_field( $row[ $col_map['data'] ] ) : '';
			$row_tpl_id  = isset( $col_map['template_id'], $row[ $col_map['template_id'] ] ) ? absint( $row[ $col_map['template_id'] ] ) : $template_id;

			if ( empty( $title ) || empty( $data ) ) {
				$errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: Missing title or data, skipped.', 'qrc-ms-pro' ),
					$row_number
				);
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'   => 'qrc_ms_code',
				'post_title'  => $title,
				'post_status' => 'publish',
			), true );

			if ( is_wp_error( $post_id ) ) {
				$errors[] = sprintf(
					/* translators: %1$d: row number, %2$s: error message */
					__( 'Row %1$d: %2$s', 'qrc-ms-pro' ),
					$row_number,
					$post_id->get_error_message()
				);
				continue;
			}

			// Store QR code meta.
			update_post_meta( $post_id, '_qrc_ms_type', $type );
			update_post_meta( $post_id, '_qrc_ms_data', $data );

			if ( $row_tpl_id > 0 ) {
				update_post_meta( $post_id, '_qrc_ms_template_id', $row_tpl_id );
			}

			$created_ids[] = $post_id;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Store created IDs in a transient for ZIP download.
		if ( ! empty( $created_ids ) ) {
			$batch_key = 'qrc_ms_pro_bulk_' . wp_generate_password( 12, false );
			set_transient( $batch_key, array(
				'ids'    => $created_ids,
				'format' => $format,
				'size'   => $size,
			), HOUR_IN_SECONDS );
		}

		wp_send_json_success( array(
			'message'     => sprintf(
				/* translators: %d: number of QR codes created */
				__( 'Successfully created %d QR code(s).', 'qrc-ms-pro' ),
				count( $created_ids )
			),
			'created'     => count( $created_ids ),
			'errors'      => $errors,
			'batch_key'   => $batch_key ?? '',
		) );
	}

	/**
	 * AJAX handler: Generate QR codes for all WooCommerce products.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function ajax_process_woo(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'qrc-ms-pro' ) ), 403 );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'qrc-ms-pro' ) ), 400 );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$format      = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'png';
		$size        = isset( $_POST['size'] ) ? absint( $_POST['size'] ) : 300;
		$offset      = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size  = 20;

		if ( ! in_array( $format, array( 'png', 'svg' ), true ) ) {
			$format = 'png';
		}

		$size = max( 100, min( 2000, $size ) );

		// Get products in batches.
		$products = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );

		$total_products = wp_count_posts( 'product' )->publish;
		$created_ids    = array();

		foreach ( $products as $product_id ) {
			// Skip if QR code already exists for this product.
			$existing = get_post_meta( $product_id, '_qrc_ms_auto_qr_id', true );
			if ( $existing && get_post_status( $existing ) ) {
				continue;
			}

			$product_url = get_permalink( $product_id );
			$product     = get_post( $product_id );

			if ( ! $product_url || ! $product ) {
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_type'   => 'qrc_ms_code',
				'post_title'  => sprintf(
					/* translators: %s: product name */
					__( 'QR: %s', 'qrc-ms-pro' ),
					$product->post_title
				),
				'post_status' => 'publish',
			), true );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_qrc_ms_type', 'url' );
			update_post_meta( $post_id, '_qrc_ms_data', $product_url );

			if ( $template_id > 0 ) {
				update_post_meta( $post_id, '_qrc_ms_template_id', $template_id );
			}

			// Link product to QR code.
			update_post_meta( $product_id, '_qrc_ms_auto_qr_id', $post_id );
			update_post_meta( $post_id, '_qrc_ms_source_post_id', $product_id );

			$created_ids[] = $post_id;
		}

		$has_more = ( $offset + $batch_size ) < $total_products;

		// Store batch for download.
		$batch_key = isset( $_POST['batch_key'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_key'] ) ) : '';
		if ( empty( $batch_key ) ) {
			$batch_key = 'qrc_ms_pro_bulk_' . wp_generate_password( 12, false );
		}

		$existing_batch = get_transient( $batch_key );
		$all_ids        = is_array( $existing_batch ) ? $existing_batch['ids'] : array();
		$all_ids        = array_merge( $all_ids, $created_ids );

		set_transient( $batch_key, array(
			'ids'    => $all_ids,
			'format' => $format,
			'size'   => $size,
		), HOUR_IN_SECONDS );

		wp_send_json_success( array(
			'message'     => sprintf(
				/* translators: %1$d: created count, %2$d: total processed */
				__( 'Processed %1$d of %2$d products.', 'qrc-ms-pro' ),
				$offset + count( $products ),
				$total_products
			),
			'created'     => count( $created_ids ),
			'has_more'    => $has_more,
			'next_offset' => $offset + $batch_size,
			'batch_key'   => $batch_key,
			'total'       => $total_products,
		) );
	}

	/**
	 * AJAX handler: Download ZIP of generated QR codes.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function ajax_download_zip(): void {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'qrc-ms-pro' ) ), 403 );
		}

		$batch_key = isset( $_POST['batch_key'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_key'] ) ) : '';
		if ( empty( $batch_key ) ) {
			wp_send_json_error( array( 'message' => __( 'No batch data found.', 'qrc-ms-pro' ) ), 400 );
		}

		$batch = get_transient( $batch_key );
		if ( ! $batch || empty( $batch['ids'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch data expired or not found.', 'qrc-ms-pro' ) ), 400 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array( 'message' => __( 'ZipArchive extension is not available on this server.', 'qrc-ms-pro' ) ), 500 );
		}

		$upload_dir = wp_upload_dir();
		$zip_dir    = $upload_dir['basedir'] . '/qrc-ms-pro-exports/';
		$zip_url    = $upload_dir['baseurl'] . '/qrc-ms-pro-exports/';

		if ( ! file_exists( $zip_dir ) ) {
			wp_mkdir_p( $zip_dir );
		}

		$zip_filename = 'qr-codes-' . gmdate( 'Y-m-d-His' ) . '.zip';
		$zip_path     = $zip_dir . $zip_filename;

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create ZIP file.', 'qrc-ms-pro' ) ), 500 );
		}

		$format = $batch['format'] ?? 'svg';
		$size   = $batch['size'] ?? 300;

		foreach ( $batch['ids'] as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$data = get_post_meta( $post_id, '_qrc_ms_data', true );
			if ( empty( $data ) ) {
				continue;
			}

			$safe_title = sanitize_file_name( $post->post_title );

			if ( 'svg' === $format ) {
				// Generate a simple SVG QR placeholder.
				// In production, this would call the QR renderer.
				$svg_content = self::generate_qr_svg( $data, $size );
				$zip->addFromString( $safe_title . '.svg', $svg_content );
			} else {
				// For PNG, generate SVG and note that full PNG rendering
				// requires GD/Imagick integration with the QR renderer.
				$svg_content = self::generate_qr_svg( $data, $size );
				$zip->addFromString( $safe_title . '.svg', $svg_content );
			}
		}

		$zip->close();

		// Clean up transient.
		delete_transient( $batch_key );

		wp_send_json_success( array(
			'message'      => __( 'ZIP file ready for download.', 'qrc-ms-pro' ),
			'download_url' => $zip_url . $zip_filename,
		) );
	}

	/**
	 * Generate a simple QR code SVG.
	 *
	 * Uses the free plugin's renderer if available, otherwise generates
	 * a placeholder SVG indicating the data.
	 *
	 * @since 1.2.0
	 *
	 * @param string $data The data to encode.
	 * @param int    $size The size in pixels.
	 * @return string SVG content.
	 */
	private static function generate_qr_svg( string $data, int $size = 300 ): string {
		/**
		 * Filter to generate QR code SVG content.
		 *
		 * The free plugin should hook into this to provide actual QR rendering.
		 *
		 * @since 1.2.0
		 *
		 * @param string $svg  The SVG content (empty by default).
		 * @param string $data The data to encode.
		 * @param int    $size The desired size.
		 */
		$svg = apply_filters( 'qrc_ms/generate_svg', '', $data, $size );

		if ( ! empty( $svg ) ) {
			return $svg;
		}

		// Fallback: simple placeholder SVG.
		$escaped_data = esc_html( mb_substr( $data, 0, 50 ) );
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d">'
			. '<rect width="%1$d" height="%1$d" fill="#ffffff" stroke="#cccccc"/>'
			. '<text x="50%%" y="50%%" text-anchor="middle" dy=".3em" font-size="12" fill="#333333">QR: %2$s</text>'
			. '</svg>',
			$size,
			$escaped_data
		);
	}
}
