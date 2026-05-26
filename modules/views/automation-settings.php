<?php
/**
 * Automation settings admin page view.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/views
 * @since      1.2.0
 *
 * @var array  $settings          Current automation settings.
 * @var array  $templates         Available QR code templates.
 * @var array  $custom_post_types Available custom post types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Automation Rules', 'qrc-ms-pro' ); ?></h1>

	<div class="qrc-ms-pro-page-intro">
		<p>
			<?php esc_html_e( 'Automation removes the manual step of creating QR codes. When enabled, a QR code is automatically generated every time you publish content — linking directly to that page or product. No extra clicks needed.', 'qrc-ms-pro' ); ?>
		</p>
	</div>

	<?php settings_errors( 'qrc_ms_pro_automation' ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'qrc_ms_pro_automation_save', 'qrc_ms_pro_automation_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Create for Post Types', 'qrc-ms-pro' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="qrc_ms_pro_auto_posts" value="1"
								<?php checked( $settings['enable_posts'] ); ?>>
							<?php esc_html_e( 'Posts', 'qrc-ms-pro' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="qrc_ms_pro_auto_pages" value="1"
								<?php checked( $settings['enable_pages'] ); ?>>
							<?php esc_html_e( 'Pages', 'qrc-ms-pro' ); ?>
						</label>
						<br>
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<label>
								<input type="checkbox" name="qrc_ms_pro_auto_products" value="1"
									<?php checked( $settings['enable_products'] ); ?>>
								<?php esc_html_e( 'WooCommerce Products', 'qrc-ms-pro' ); ?>
							</label>
							<br>
						<?php endif; ?>

						<?php if ( ! empty( $custom_post_types ) ) : ?>
							<br>
							<strong><?php esc_html_e( 'Custom Post Types:', 'qrc-ms-pro' ); ?></strong>
							<br>
							<?php foreach ( $custom_post_types as $cpt ) : ?>
								<label>
									<input type="checkbox" name="qrc_ms_pro_auto_cpt[]"
										value="<?php echo esc_attr( $cpt->name ); ?>"
										<?php checked( in_array( $cpt->name, $settings['enable_cpt'] ?? array(), true ) ); ?>>
									<?php echo esc_html( $cpt->labels->singular_name ); ?>
									<span class="description">(<?php echo esc_html( $cpt->name ); ?>)</span>
								</label>
								<br>
							<?php endforeach; ?>
						<?php endif; ?>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'A QR code will be automatically created when a post of the selected type is published.', 'qrc-ms-pro' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="qrc_ms_pro_auto_template"><?php esc_html_e( 'Default Template', 'qrc-ms-pro' ); ?></label>
				</th>
				<td>
					<select id="qrc_ms_pro_auto_template" name="qrc_ms_pro_auto_template">
						<option value="0"><?php esc_html_e( '— Default Style —', 'qrc-ms-pro' ); ?></option>
						<?php foreach ( $templates as $template ) : ?>
							<option value="<?php echo esc_attr( $template->ID ); ?>"
								<?php selected( $settings['default_template'], $template->ID ); ?>>
								<?php echo esc_html( $template->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Template applied to auto-generated QR codes.', 'qrc-ms-pro' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'URL Change Handling', 'qrc-ms-pro' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="qrc_ms_pro_auto_regenerate" value="1"
							<?php checked( $settings['auto_regenerate'] ); ?>>
						<?php esc_html_e( 'Auto-update QR code data when the source post URL changes (slug change)', 'qrc-ms-pro' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'If enabled, the QR code data will be updated when the linked post permalink changes. Note: this only works for static QR codes. Dynamic QR codes handle this via their redirect URL.', 'qrc-ms-pro' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Automation Settings', 'qrc-ms-pro' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'How It Works', 'qrc-ms-pro' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'Enable automation for the post types you want.', 'qrc-ms-pro' ); ?></li>
		<li><?php esc_html_e( 'When a post of that type is published for the first time, a QR code is automatically created.', 'qrc-ms-pro' ); ?></li>
		<li><?php esc_html_e( 'The QR code encodes the post permalink.', 'qrc-ms-pro' ); ?></li>
		<li><?php esc_html_e( 'If a QR code already exists for a post, it will not be duplicated.', 'qrc-ms-pro' ); ?></li>
	</ol>
</div>
