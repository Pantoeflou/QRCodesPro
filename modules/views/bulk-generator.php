<?php
/**
 * Bulk Generator admin page view.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/views
 * @since      1.2.0
 *
 * @var bool  $woo_active Whether WooCommerce is active.
 * @var array $templates  Available QR code templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Generate QR Codes', 'qrc-ms-pro' ); ?></h1>

	<div class="qrc-ms-pro-page-intro">
		<p>
			<?php esc_html_e( 'Create multiple QR codes at once instead of one at a time. Upload a CSV file with your data, or generate QR codes for all your WooCommerce products in a single click. All generated codes are saved to your QR Codes library and can be downloaded as a ZIP file.', 'qrc-ms-pro' ); ?>
		</p>
	</div>

	<div class="qrc-ms-pro-bulk-tabs">
		<nav class="nav-tab-wrapper">
			<a href="#csv-upload" class="nav-tab nav-tab-active" data-tab="csv-upload">
				<?php esc_html_e( 'CSV Upload', 'qrc-ms-pro' ); ?>
			</a>
			<?php if ( $woo_active ) : ?>
				<a href="#woo-bulk" class="nav-tab" data-tab="woo-bulk">
					<?php esc_html_e( 'WooCommerce Products', 'qrc-ms-pro' ); ?>
				</a>
			<?php endif; ?>
		</nav>

		<!-- CSV Upload Tab -->
		<div id="csv-upload" class="qrc-ms-pro-tab-content active">
			<div class="card">
				<h2><?php esc_html_e( 'Upload CSV File', 'qrc-ms-pro' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Upload a CSV file with columns: title, type, data, template_id (optional).', 'qrc-ms-pro' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Supported types: url, text, email, phone, wifi, vcard.', 'qrc-ms-pro' ); ?>
				</p>

				<form id="qrc-ms-pro-csv-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'qrc_ms_pro_bulk_generate', 'qrc_ms_pro_bulk_nonce' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="csv_file"><?php esc_html_e( 'CSV File', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<input type="file" id="csv_file" name="csv_file" accept=".csv" required>
								<p class="description">
									<?php esc_html_e( 'Maximum file size: 2MB. UTF-8 encoding recommended.', 'qrc-ms-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bulk_template_id"><?php esc_html_e( 'Template', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<select id="bulk_template_id" name="template_id">
									<option value="0"><?php esc_html_e( '— Default —', 'qrc-ms-pro' ); ?></option>
									<?php foreach ( $templates as $template ) : ?>
										<option value="<?php echo esc_attr( $template->ID ); ?>">
											<?php echo esc_html( $template->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Applied to all QR codes unless overridden in CSV.', 'qrc-ms-pro' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bulk_format"><?php esc_html_e( 'Download Format', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<select id="bulk_format" name="format">
									<option value="svg"><?php esc_html_e( 'SVG (Vector)', 'qrc-ms-pro' ); ?></option>
									<option value="png"><?php esc_html_e( 'PNG (Raster)', 'qrc-ms-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bulk_size"><?php esc_html_e( 'Size (px)', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="bulk_size" name="size" value="300" min="100" max="2000" step="50">
								<p class="description">
									<?php esc_html_e( 'Width and height in pixels (100-2000).', 'qrc-ms-pro' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary" id="qrc-ms-pro-csv-submit">
							<?php esc_html_e( 'Generate QR Codes', 'qrc-ms-pro' ); ?>
						</button>
					</p>
				</form>

				<div class="qrc-ms-pro-csv-sample">
					<h3><?php esc_html_e( 'Sample CSV Format', 'qrc-ms-pro' ); ?></h3>
					<pre>title,type,data,template_id
"My Website",url,"https://example.com",0
"Contact Email",email,"hello@example.com",0
"WiFi Network",wifi,"WIFI:T:WPA;S:MyNetwork;P:password123;;",0</pre>
				</div>
			</div>
		</div>

		<?php if ( $woo_active ) : ?>
		<!-- WooCommerce Bulk Tab -->
		<div id="woo-bulk" class="qrc-ms-pro-tab-content">
			<div class="card">
				<h2><?php esc_html_e( 'Generate QR Codes for WooCommerce Products', 'qrc-ms-pro' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Automatically create a QR code for each published product. Products that already have a QR code will be skipped.', 'qrc-ms-pro' ); ?>
				</p>

				<?php
				$product_count = wp_count_posts( 'product' )->publish;
				?>
				<p>
					<?php
					printf(
						/* translators: %d: number of products */
						esc_html__( 'Found %d published product(s).', 'qrc-ms-pro' ),
						(int) $product_count
					);
					?>
				</p>

				<form id="qrc-ms-pro-woo-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="woo_template_id"><?php esc_html_e( 'Template', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<select id="woo_template_id" name="template_id">
									<option value="0"><?php esc_html_e( '— Default —', 'qrc-ms-pro' ); ?></option>
									<?php foreach ( $templates as $template ) : ?>
										<option value="<?php echo esc_attr( $template->ID ); ?>">
											<?php echo esc_html( $template->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woo_format"><?php esc_html_e( 'Download Format', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<select id="woo_format" name="format">
									<option value="svg"><?php esc_html_e( 'SVG (Vector)', 'qrc-ms-pro' ); ?></option>
									<option value="png"><?php esc_html_e( 'PNG (Raster)', 'qrc-ms-pro' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="woo_size"><?php esc_html_e( 'Size (px)', 'qrc-ms-pro' ); ?></label>
							</th>
							<td>
								<input type="number" id="woo_size" name="size" value="300" min="100" max="2000" step="50">
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary" id="qrc-ms-pro-woo-submit">
							<?php esc_html_e( 'Generate for All Products', 'qrc-ms-pro' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<!-- Progress / Results Area -->
	<div id="qrc-ms-pro-bulk-progress" class="card" style="display:none;">
		<h3><?php esc_html_e( 'Progress', 'qrc-ms-pro' ); ?></h3>
		<div class="qrc-ms-pro-progress-bar">
			<div class="qrc-ms-pro-progress-fill" style="width:0%"></div>
		</div>
		<p id="qrc-ms-pro-bulk-status"></p>
		<div id="qrc-ms-pro-bulk-errors" style="display:none;">
			<h4><?php esc_html_e( 'Warnings', 'qrc-ms-pro' ); ?></h4>
			<ul id="qrc-ms-pro-bulk-error-list"></ul>
		</div>
		<p id="qrc-ms-pro-bulk-download" style="display:none;">
			<a href="#" class="button button-secondary" id="qrc-ms-pro-download-zip">
				<?php esc_html_e( 'Download ZIP', 'qrc-ms-pro' ); ?>
			</a>
		</p>
	</div>
</div>

<style>
.qrc-ms-pro-tab-content { display: none; margin-top: 20px; }
.qrc-ms-pro-tab-content.active { display: block; }
.qrc-ms-pro-csv-sample pre { background: #f0f0f0; padding: 12px; overflow-x: auto; }
.qrc-ms-pro-progress-bar { background: #e0e0e0; border-radius: 4px; height: 24px; overflow: hidden; margin: 10px 0; }
.qrc-ms-pro-progress-fill { background: #0073aa; height: 100%; transition: width 0.3s ease; }
</style>

<script>
jQuery(function($) {
	// Tab switching.
	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		$('.nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.qrc-ms-pro-tab-content').removeClass('active');
		$('#' + $(this).data('tab')).addClass('active');
	});
});
</script>
