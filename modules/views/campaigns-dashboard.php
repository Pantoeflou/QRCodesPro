<?php
/**
 * Campaigns dashboard view.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/views
 * @since      1.2.0
 *
 * @var array $campaigns Campaign stats array.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Campaigns', 'qrc-ms-pro' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . QRC_MS_Pro_Campaigns::TAXONOMY . '&post_type=qrc_ms_code' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Create Campaign', 'qrc-ms-pro' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="qrc-ms-pro-page-intro">
		<p>
			<?php esc_html_e( 'Campaigns let you group related QR codes together for easier management and reporting. Use them to organize codes by project, event, location, or marketing initiative.', 'qrc-ms-pro' ); ?>
		</p>
		<details>
			<summary><?php esc_html_e( 'Examples of campaigns', 'qrc-ms-pro' ); ?></summary>
			<ul>
				<li><strong><?php esc_html_e( 'Product Packaging', 'qrc-ms-pro' ); ?></strong> — <?php esc_html_e( 'QR codes printed on product labels linking to manuals, warranty info, or reviews.', 'qrc-ms-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Event Booth', 'qrc-ms-pro' ); ?></strong> — <?php esc_html_e( 'Codes on banners, flyers, and business cards for a trade show or conference.', 'qrc-ms-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Restaurant Tables', 'qrc-ms-pro' ); ?></strong> — <?php esc_html_e( 'Table-specific QR codes linking to menus, ordering, or feedback forms.', 'qrc-ms-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Email Marketing', 'qrc-ms-pro' ); ?></strong> — <?php esc_html_e( 'Track which QR codes in your email campaigns get the most scans.', 'qrc-ms-pro' ); ?></li>
			</ul>
		</details>
	</div>

	<?php if ( empty( $campaigns ) ) : ?>
		<div class="qrc-ms-pro-empty-state">
			<p><?php esc_html_e( 'No campaigns found. Create a campaign to start grouping your QR codes.', 'qrc-ms-pro' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . QRC_MS_Pro_Campaigns::TAXONOMY . '&post_type=qrc_ms_code' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create Campaign', 'qrc-ms-pro' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="qrc-ms-pro-campaigns-summary">
			<div class="qrc-ms-pro-stat-card">
				<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( count( $campaigns ) ) ); ?></span>
				<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Total Campaigns', 'qrc-ms-pro' ); ?></span>
			</div>
			<div class="qrc-ms-pro-stat-card">
				<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( array_sum( array_column( $campaigns, 'qr_count' ) ) ) ); ?></span>
				<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Total QR Codes', 'qrc-ms-pro' ); ?></span>
			</div>
			<div class="qrc-ms-pro-stat-card">
				<span class="qrc-ms-pro-stat-value"><?php echo esc_html( number_format_i18n( array_sum( array_column( $campaigns, 'total_scans' ) ) ) ); ?></span>
				<span class="qrc-ms-pro-stat-label"><?php esc_html_e( 'Total Scans', 'qrc-ms-pro' ); ?></span>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Campaign', 'qrc-ms-pro' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'QR Codes', 'qrc-ms-pro' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'Total Scans', 'qrc-ms-pro' ); ?></th>
					<th scope="col" class="num"><?php esc_html_e( 'This Week', 'qrc-ms-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'qrc-ms-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $campaign ) :
					$analytics_url = admin_url( 'edit.php?post_type=qrc_ms_code&page=qrc-ms-pro-analytics&campaign=' . $campaign['term_id'] );
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( $campaign['edit_url'] ); ?>">
									<?php echo esc_html( $campaign['name'] ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $campaign['description'] ) ) : ?>
								<br><span class="description"><?php echo esc_html( $campaign['description'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( $campaign['qr_count'] ) ); ?></td>
						<td class="num">
							<a href="<?php echo esc_url( $analytics_url ); ?>">
								<?php echo esc_html( number_format_i18n( $campaign['total_scans'] ) ); ?>
							</a>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( $campaign['week_scans'] ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=qrc_ms_code&' . QRC_MS_Pro_Campaigns::TAXONOMY . '=' . $campaign['slug'] ) ); ?>">
								<?php esc_html_e( 'View QR Codes', 'qrc-ms-pro' ); ?>
							</a>
							|
							<a href="<?php echo esc_url( $analytics_url ); ?>">
								<?php esc_html_e( 'View Analytics', 'qrc-ms-pro' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
