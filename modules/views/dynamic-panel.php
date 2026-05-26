<?php
/**
 * Dynamic QR Code admin panel view.
 *
 * Renders the dynamic QR code controls inside the meta box.
 * Variables available from the calling context:
 *
 * @var \WP_Post $post              The current QR code post.
 * @var bool     $is_dynamic        Whether dynamic mode is enabled.
 * @var string   $short_code        The unique short code (may be empty).
 * @var string   $redirect_url      The current destination URL.
 * @var string   $full_redirect_url The full redirect URL to encode in the QR.
 * @var array    $history           Array of redirect history entries.
 * @var string   $expiry_date       The expiry date in Y-m-d format (may be empty).
 * @var string   $fallback_url      The fallback URL for expired codes (may be empty).
 * @var string   $expiry_message    The message shown when the QR code has expired.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/views
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="qrc-ms-pro-dynamic-panel" id="qrc-ms-pro-dynamic-panel" data-post-id="<?php echo esc_attr( $post->ID ); ?>">

	<?php wp_nonce_field( 'qrc_ms_pro_save_dynamic', 'qrc_ms_pro_dynamic_nonce' ); ?>

	<!-- Dynamic Mode Toggle -->
	<div class="qrc-ms-pro-dynamic-toggle">
		<label for="qrc_ms_pro_is_dynamic">
			<input
				type="checkbox"
				id="qrc_ms_pro_is_dynamic"
				name="qrc_ms_pro_is_dynamic"
				value="1"
				<?php checked( $is_dynamic ); ?>
			/>
			<strong><?php esc_html_e( 'Enable Dynamic Mode', 'qrc-ms-pro' ); ?></strong>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the QR code encodes a redirect URL instead of the final destination. This allows you to change where the QR code points after it has been printed.', 'qrc-ms-pro' ); ?>
		</p>
	</div>

	<!-- Dynamic Details (shown when enabled) -->
	<div class="qrc-ms-pro-dynamic-details" id="qrc-ms-pro-dynamic-details" style="<?php echo $is_dynamic ? '' : 'display:none;'; ?>">

		<?php if ( ! empty( $short_code ) ) : ?>
			<!-- Scan Count Summary -->
			<?php if ( class_exists( 'QRC_MS_Pro_Analytics' ) ) : ?>
			<div class="qrc-ms-pro-field qrc-ms-pro-scan-summary">
				<label><strong><?php esc_html_e( 'Scan Activity', 'qrc-ms-pro' ); ?></strong></label>
				<div class="qrc-ms-pro-scan-stats">
					<?php
					$scan_count = QRC_MS_Pro_Analytics::get_scan_count( $post->ID );
					$last_scan  = QRC_MS_Pro_Analytics::get_last_scan( $post->ID );
					?>
					<span class="qrc-ms-pro-scan-stat">
						<?php
						printf(
							/* translators: %s: number of scans */
							esc_html__( '%s total scans', 'qrc-ms-pro' ),
							'<strong>' . esc_html( number_format_i18n( $scan_count ) ) . '</strong>'
						);
						?>
					</span>
					<?php if ( $last_scan ) : ?>
					<span class="qrc-ms-pro-scan-stat">
						<?php
						printf(
							/* translators: %s: date of last scan */
							esc_html__( 'Last scan: %s', 'qrc-ms-pro' ),
							esc_html( wp_date( get_option( 'date_format' ), strtotime( $last_scan ) ) )
						);
						?>
					</span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Short Code Display -->
			<div class="qrc-ms-pro-field">
				<label><strong><?php esc_html_e( 'Short Code', 'qrc-ms-pro' ); ?></strong></label>
				<code class="qrc-ms-pro-short-code"><?php echo esc_html( $short_code ); ?></code>
			</div>

			<!-- Redirect URL (what gets encoded in the QR) -->
			<div class="qrc-ms-pro-field qrc-ms-pro-redirect-url-display">
				<label><strong><?php esc_html_e( 'QR Code Encodes This URL', 'qrc-ms-pro' ); ?></strong></label>
				<div class="qrc-ms-pro-url-box">
					<input
						type="text"
						readonly
						value="<?php echo esc_attr( $full_redirect_url ); ?>"
						class="widefat qrc-ms-pro-redirect-url-readonly"
						id="qrc_ms_pro_full_redirect_url"
					/>
					<button type="button" class="button button-small qrc-ms-pro-copy-url" data-target="qrc_ms_pro_full_redirect_url">
						<?php esc_html_e( 'Copy', 'qrc-ms-pro' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'This is the URL encoded in the QR code image. When scanned, it redirects to the destination below.', 'qrc-ms-pro' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<!-- Current Destination URL -->
		<div class="qrc-ms-pro-field">
			<label for="qrc_ms_pro_redirect_url"><strong><?php esc_html_e( 'Destination URL', 'qrc-ms-pro' ); ?></strong></label>
			<div class="qrc-ms-pro-destination-row">
				<input
					type="url"
					id="qrc_ms_pro_redirect_url"
					name="qrc_ms_pro_redirect_url"
					value="<?php echo esc_attr( $redirect_url ); ?>"
					placeholder="https://example.com/landing-page"
					class="widefat"
				/>
				<?php if ( ! empty( $short_code ) ) : ?>
					<button type="button" class="button button-primary qrc-ms-pro-update-destination" id="qrc-ms-pro-update-destination-btn">
						<?php esc_html_e( 'Update Destination', 'qrc-ms-pro' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<p class="description">
				<?php esc_html_e( 'The URL that users will be redirected to when they scan the QR code. You can change this at any time without reprinting the QR code.', 'qrc-ms-pro' ); ?>
			</p>
			<div class="qrc-ms-pro-ajax-message" id="qrc-ms-pro-ajax-message" style="display:none;"></div>
		</div>

		<!-- Expiry Settings -->
		<div class="qrc-ms-pro-field qrc-ms-pro-expiry-settings">
			<h4><?php esc_html_e( 'Expiry Settings', 'qrc-ms-pro' ); ?></h4>

			<div class="qrc-ms-pro-expiry-row">
				<label for="qrc_ms_pro_expiry_date"><strong><?php esc_html_e( 'Expiry Date', 'qrc-ms-pro' ); ?></strong></label>
				<input
					type="date"
					id="qrc_ms_pro_expiry_date"
					name="qrc_ms_pro_expiry_date"
					value="<?php echo esc_attr( $expiry_date ); ?>"
					class="regular-text"
				/>
				<p class="description">
					<?php esc_html_e( 'After this date, the QR code will no longer redirect to the destination. Leave empty for no expiry.', 'qrc-ms-pro' ); ?>
				</p>
			</div>

			<div class="qrc-ms-pro-expiry-row" style="margin-top: 12px;">
				<label for="qrc_ms_pro_fallback_url"><strong><?php esc_html_e( 'Fallback URL', 'qrc-ms-pro' ); ?></strong></label>
				<input
					type="url"
					id="qrc_ms_pro_fallback_url"
					name="qrc_ms_pro_fallback_url"
					value="<?php echo esc_attr( $fallback_url ); ?>"
					placeholder="https://example.com/expired"
					class="widefat"
				/>
				<p class="description">
					<?php esc_html_e( 'Redirect to this URL after expiry. If empty, an expiry message will be shown instead.', 'qrc-ms-pro' ); ?>
				</p>
			</div>

			<div class="qrc-ms-pro-expiry-row" style="margin-top: 12px;">
				<label for="qrc_ms_pro_expiry_message"><strong><?php esc_html_e( 'Expiry Message', 'qrc-ms-pro' ); ?></strong></label>
				<input
					type="text"
					id="qrc_ms_pro_expiry_message"
					name="qrc_ms_pro_expiry_message"
					value="<?php echo esc_attr( $expiry_message ); ?>"
					class="widefat"
				/>
				<p class="description">
					<?php esc_html_e( 'Message shown when the QR code has expired and no fallback URL is set.', 'qrc-ms-pro' ); ?>
				</p>
			</div>

			<?php
			// Show expired badge if applicable.
			if ( ! empty( $expiry_date ) && strtotime( $expiry_date ) < time() ) :
				?>
				<div class="notice notice-warning inline" style="margin-top: 12px;">
					<p>
						<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
						<strong><?php esc_html_e( 'This QR code has expired.', 'qrc-ms-pro' ); ?></strong>
						<?php
						printf(
							/* translators: %s: expiry date */
							esc_html__( 'Expired on %s.', 'qrc-ms-pro' ),
							esc_html( wp_date( get_option( 'date_format' ), strtotime( $expiry_date ) ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $history ) ) : ?>
			<!-- Redirect History -->
			<div class="qrc-ms-pro-field qrc-ms-pro-history">
				<label><strong><?php esc_html_e( 'Redirect History', 'qrc-ms-pro' ); ?></strong></label>
				<table class="widefat striped qrc-ms-pro-history-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'qrc-ms-pro' ); ?></th>
							<th><?php esc_html_e( 'Previous URL', 'qrc-ms-pro' ); ?></th>
							<th><?php esc_html_e( 'New URL', 'qrc-ms-pro' ); ?></th>
							<th><?php esc_html_e( 'Changed By', 'qrc-ms-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Show most recent first.
						$reversed_history = array_reverse( $history );
						foreach ( $reversed_history as $entry ) :
							$user = get_userdata( $entry['changed_by'] ?? 0 );
							$username = $user ? $user->display_name : __( 'Unknown', 'qrc-ms-pro' );
							?>
							<tr>
								<td><?php echo esc_html( $entry['changed_at'] ?? '' ); ?></td>
								<td class="qrc-ms-pro-url-cell" title="<?php echo esc_attr( $entry['old_url'] ?? '' ); ?>">
									<?php echo esc_html( $entry['old_url'] ?? '' ); ?>
								</td>
								<td class="qrc-ms-pro-url-cell" title="<?php echo esc_attr( $entry['new_url'] ?? '' ); ?>">
									<?php echo esc_html( $entry['new_url'] ?? '' ); ?>
								</td>
								<td><?php echo esc_html( $username ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</div>

</div>
