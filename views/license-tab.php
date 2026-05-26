<?php
/**
 * License tab content.
 *
 * Rendered inside the free plugin's settings page when the License tab is active.
 * This file is included by QRC_MS_Pro_License_Manager::render_license_tab().
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/views
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_key  = QRC_MS_Pro_License_Manager::get_license_key();
$status       = QRC_MS_Pro_License_Manager::get_status();
$license_data = QRC_MS_Pro_License_Manager::get_license_data();

$status_labels = array(
	'active'   => __( 'Active', 'qrc-ms-pro' ),
	'expired'  => __( 'Expired', 'qrc-ms-pro' ),
	'invalid'  => __( 'Invalid', 'qrc-ms-pro' ),
	'inactive' => __( 'Not Activated', 'qrc-ms-pro' ),
);

$status_colors = array(
	'active'   => '#00a32a',
	'expired'  => '#dba617',
	'invalid'  => '#d63638',
	'inactive' => '#666',
);

$status_label = $status_labels[ $status ] ?? $status_labels['inactive'];
$status_color = $status_colors[ $status ] ?? $status_colors['inactive'];
$nonce        = wp_create_nonce( 'qrc_ms_pro_license' );
?>

<div class="qrc-ms-license-tab">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'qrc-ms-pro' ); ?></th>
			<td>
				<span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: <?php echo esc_attr( $status_color ); ?>; color: #fff; font-weight: 600; font-size: 12px;">
					<?php echo esc_html( $status_label ); ?>
				</span>
				<?php if ( ! empty( $license_data['expires'] ) ) : ?>
					<p class="description">
						<?php printf( esc_html__( 'Expires: %s', 'qrc-ms-pro' ), esc_html( $license_data['expires'] ) ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="qrc-ms-pro-license-key"><?php esc_html_e( 'License Key', 'qrc-ms-pro' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="qrc-ms-pro-license-key"
					class="regular-text"
					value="<?php echo esc_attr( $license_key ); ?>"
					placeholder="<?php esc_attr_e( 'Enter your license key', 'qrc-ms-pro' ); ?>"
					<?php echo $status === 'active' ? 'readonly' : ''; ?>
				/>
				<?php if ( $status === 'active' ) : ?>
					<button type="button" class="button" id="qrc-ms-pro-deactivate" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Deactivate', 'qrc-ms-pro' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="button button-primary" id="qrc-ms-pro-activate" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Activate', 'qrc-ms-pro' ); ?>
					</button>
				<?php endif; ?>
				<p class="description">
					<?php
					printf(
						__( 'Enter your license key to unlock Pro features. <a href="%s" target="_blank">Purchase a license</a> | <a href="%s" target="_blank">Manage your account</a>', 'qrc-ms-pro' ),
						esc_url( 'https://example.com/pricing' ),
						esc_url( 'https://example.com/account' )
					);
					?>
				</p>
				<div id="qrc-ms-pro-license-message" style="margin-top: 10px;"></div>
			</td>
		</tr>
	</table>

	<?php if ( ! empty( $license_data['plan'] ) ) : ?>
		<h4><?php esc_html_e( 'Plan Details', 'qrc-ms-pro' ); ?></h4>
		<table class="widefat striped" style="max-width: 400px;">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Plan', 'qrc-ms-pro' ); ?></strong></td>
					<td><?php echo esc_html( $license_data['plan'] ); ?></td>
				</tr>
				<?php if ( ! empty( $license_data['activations'] ) ) : ?>
				<tr>
					<td><strong><?php esc_html_e( 'Activations', 'qrc-ms-pro' ); ?></strong></td>
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

	var activateBtn = document.getElementById('qrc-ms-pro-activate');
	var deactivateBtn = document.getElementById('qrc-ms-pro-deactivate');
	var messageDiv = document.getElementById('qrc-ms-pro-license-message');
	var keyInput = document.getElementById('qrc-ms-pro-license-key');

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
			data.append('action', 'qrc_ms_pro_activate_license');
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
			data.append('action', 'qrc_ms_pro_deactivate_license');
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
