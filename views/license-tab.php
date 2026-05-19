<?php
/**
 * License tab content.
 *
 * Rendered inside the free plugin's settings page when the License tab is active.
 * This file is included by WP_Forever_Pro_License_Manager::render_license_tab().
 *
 * @package    WP_Forever_Pro
 * @subpackage WP_Forever_Pro/views
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_key  = WP_Forever_Pro_License_Manager::get_license_key();
$status       = WP_Forever_Pro_License_Manager::get_status();
$license_data = WP_Forever_Pro_License_Manager::get_license_data();

$status_labels = array(
	'active'   => __( 'Active', 'wp-forever-pro' ),
	'expired'  => __( 'Expired', 'wp-forever-pro' ),
	'invalid'  => __( 'Invalid', 'wp-forever-pro' ),
	'inactive' => __( 'Not Activated', 'wp-forever-pro' ),
);

$status_colors = array(
	'active'   => '#00a32a',
	'expired'  => '#dba617',
	'invalid'  => '#d63638',
	'inactive' => '#666',
);

$status_label = $status_labels[ $status ] ?? $status_labels['inactive'];
$status_color = $status_colors[ $status ] ?? $status_colors['inactive'];
$nonce        = wp_create_nonce( 'wp_forever_pro_license' );
?>

<div class="wp-forever-license-tab">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'wp-forever-pro' ); ?></th>
			<td>
				<span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: <?php echo esc_attr( $status_color ); ?>; color: #fff; font-weight: 600; font-size: 12px;">
					<?php echo esc_html( $status_label ); ?>
				</span>
				<?php if ( ! empty( $license_data['expires'] ) ) : ?>
					<p class="description">
						<?php printf( esc_html__( 'Expires: %s', 'wp-forever-pro' ), esc_html( $license_data['expires'] ) ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp-forever-pro-license-key"><?php esc_html_e( 'License Key', 'wp-forever-pro' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="wp-forever-pro-license-key"
					class="regular-text"
					value="<?php echo esc_attr( $license_key ); ?>"
					placeholder="<?php esc_attr_e( 'Enter your license key', 'wp-forever-pro' ); ?>"
					<?php echo $status === 'active' ? 'readonly' : ''; ?>
				/>
				<?php if ( $status === 'active' ) : ?>
					<button type="button" class="button" id="wp-forever-pro-deactivate" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Deactivate', 'wp-forever-pro' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button button-primary" id="wp-forever-pro-activate" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Activate', 'wp-forever-pro' ); ?>
					</button>
				<?php endif; ?>
				<p class="description">
					<?php
					printf(
						__( 'Enter your license key to unlock Pro features. <a href="%s" target="_blank">Purchase a license</a> | <a href="%s" target="_blank">Manage your account</a>', 'wp-forever-pro' ),
						esc_url( 'https://your-site.com/pricing' ),
						esc_url( 'https://your-site.com/account' )
					);
					?>
				</p>
				<div id="wp-forever-pro-license-message" style="margin-top: 10px;"></div>
			</td>
		</tr>
	</table>

	<?php if ( ! empty( $license_data['plan'] ) ) : ?>
		<h4><?php esc_html_e( 'Plan Details', 'wp-forever-pro' ); ?></h4>
		<table class="widefat striped" style="max-width: 400px;">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Plan', 'wp-forever-pro' ); ?></strong></td>
					<td><?php echo esc_html( $license_data['plan'] ); ?></td>
				</tr>
				<?php if ( ! empty( $license_data['activations'] ) ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Activations', 'wp-forever-pro' ); ?></strong></td>
					<td><?php echo esc_html( $license_data['activations'] ); ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<script>
(function() {
	'use strict';
	var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

	var activateBtn = document.getElementById('wp-forever-pro-activate');
	var deactivateBtn = document.getElementById('wp-forever-pro-deactivate');
	var messageDiv = document.getElementById('wp-forever-pro-license-message');
	var keyInput = document.getElementById('wp-forever-pro-license-key');

	function showMessage(msg, type) {
		var color = type === 'success' ? '#00a32a' : '#d63638';
		messageDiv.innerHTML = '<span style="color:' + color + ';">' + msg + '</span>';
	}

	if (activateBtn) {
		activateBtn.addEventListener('click', function() {
			var key = keyInput.value.trim();
			if (!key) { showMessage('Please enter a license key.', 'error'); return; }

			activateBtn.disabled = true;
			activateBtn.textContent = 'Activating...';

			var data = new FormData();
			data.append('action', 'wp_forever_pro_activate_license');
			data.append('nonce', activateBtn.dataset.nonce);
			data.append('license_key', key);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res.success) { showMessage(res.data.message, 'success'); location.reload(); }
					else { showMessage(res.data.message || 'Activation failed.', 'error'); }
				})
				.catch(function() { showMessage('Network error.', 'error'); })
				.finally(function() { activateBtn.disabled = false; activateBtn.textContent = 'Activate'; });
		});
	}

	if (deactivateBtn) {
		deactivateBtn.addEventListener('click', function() {
			if (!confirm('Deactivate your license on this site?')) return;

			deactivateBtn.disabled = true;
			var data = new FormData();
			data.append('action', 'wp_forever_pro_deactivate_license');
			data.append('nonce', deactivateBtn.dataset.nonce);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (res.success) { location.reload(); }
					else { showMessage(res.data.message || 'Deactivation failed.', 'error'); }
				})
				.catch(function() { showMessage('Network error.', 'error'); })
				.finally(function() { deactivateBtn.disabled = false; });
		});
	}
})();
</script>
