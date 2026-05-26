<?php
/**
 * Analytics dashboard admin page view.
 *
 * Variables available from the calling context:
 *
 * @var string $period         Current period filter.
 * @var string $orderby        Current sort column.
 * @var string $order          Current sort direction.
 * @var int    $total_scans    Total scans all time.
 * @var int    $today_scans    Scans today.
 * @var int    $week_scans     Scans this week.
 * @var int    $month_scans    Scans this month.
 * @var array  $chart_data     Daily scan data for the chart.
 * @var array  $top_qr_codes   Top QR codes by scan count.
 * @var int    $campaign       Current campaign filter term ID (0 for all).
 * @var array  $campaign_terms All campaign terms for the filter dropdown.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/views
 * @since      1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = admin_url( 'edit.php?post_type=qrc_ms_code&page=qrc-ms-pro-analytics' );
$max_chart_value = 0;
if ( ! empty( $chart_data ) ) {
	$max_chart_value = max( array_column( $chart_data, 'count' ) );
}
?>

<div class="wrap qrc-ms-pro-analytics-wrap">
	<h1><?php esc_html_e( 'Scan Analytics', 'qrc-ms-pro' ); ?></h1>

	<div class="qrc-ms-pro-page-intro">
		<p>
			<?php esc_html_e( 'Track how your QR codes are performing. Every time someone scans a dynamic QR code, the scan is recorded here with device type and timestamp. Use this data to understand which codes get the most engagement and when.', 'qrc-ms-pro' ); ?>
		</p>
	</div>

	<!-- Summary Cards -->
	<div class="qrc-ms-pro-analytics-cards">
		<div class="qrc-ms-pro-analytics-card">
			<span class="qrc-ms-pro-card-value"><?php echo esc_html( number_format_i18n( $total_scans ) ); ?></span>
			<span class="qrc-ms-pro-card-label"><?php esc_html_e( 'Total Scans', 'qrc-ms-pro' ); ?></span>
		</div>
		<div class="qrc-ms-pro-analytics-card">
			<span class="qrc-ms-pro-card-value"><?php echo esc_html( number_format_i18n( $today_scans ) ); ?></span>
			<span class="qrc-ms-pro-card-label"><?php esc_html_e( 'Today', 'qrc-ms-pro' ); ?></span>
		</div>
		<div class="qrc-ms-pro-analytics-card">
			<span class="qrc-ms-pro-card-value"><?php echo esc_html( number_format_i18n( $week_scans ) ); ?></span>
			<span class="qrc-ms-pro-card-label"><?php esc_html_e( 'This Week', 'qrc-ms-pro' ); ?></span>
		</div>
		<div class="qrc-ms-pro-analytics-card">
			<span class="qrc-ms-pro-card-value"><?php echo esc_html( number_format_i18n( $month_scans ) ); ?></span>
			<span class="qrc-ms-pro-card-label"><?php esc_html_e( 'This Month', 'qrc-ms-pro' ); ?></span>
		</div>
	</div>

	<!-- Period Filter -->
	<div class="qrc-ms-pro-analytics-filters">
		<label for="qrc-ms-pro-period-filter"><strong><?php esc_html_e( 'Period:', 'qrc-ms-pro' ); ?></strong></label>
		<?php
		$periods = array(
			'7days'  => __( 'Last 7 Days', 'qrc-ms-pro' ),
			'30days' => __( 'Last 30 Days', 'qrc-ms-pro' ),
			'90days' => __( 'Last 90 Days', 'qrc-ms-pro' ),
			'all'    => __( 'All Time', 'qrc-ms-pro' ),
		);
		foreach ( $periods as $key => $label ) :
			$active_class = ( $period === $key ) ? ' qrc-ms-pro-filter-active' : '';
			$filter_url   = add_query_arg( 'period', $key, $base_url );
			if ( ! empty( $campaign ) ) {
				$filter_url = add_query_arg( 'campaign', $campaign, $filter_url );
			}
			?>
			<a href="<?php echo esc_url( $filter_url ); ?>" class="button<?php echo esc_attr( $active_class ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>

		<?php if ( ! empty( $campaign_terms ) ) : ?>
			<span class="qrc-ms-pro-filter-separator">&nbsp;&nbsp;</span>
			<label for="qrc-ms-pro-campaign-filter"><strong><?php esc_html_e( 'Campaign:', 'qrc-ms-pro' ); ?></strong></label>
			<select id="qrc-ms-pro-campaign-filter" onchange="if(this.value){window.location.href=this.value;}">
				<option value="<?php echo esc_url( add_query_arg( 'period', $period, $base_url ) ); ?>"><?php esc_html_e( 'All Campaigns', 'qrc-ms-pro' ); ?></option>
				<?php foreach ( $campaign_terms as $term ) :
					$campaign_url = add_query_arg( array( 'period' => $period, 'campaign' => $term->term_id ), $base_url );
					$selected = ( $campaign === (int) $term->term_id ) ? ' selected' : '';
					?>
					<option value="<?php echo esc_url( $campaign_url ); ?>"<?php echo esc_attr( $selected ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
	</div>

	<!-- CSS-Only Bar Chart -->
	<?php if ( $max_chart_value > 0 ) : ?>
	<div class="qrc-ms-pro-analytics-chart-section">
		<h2><?php esc_html_e( 'Scans Over Time', 'qrc-ms-pro' ); ?></h2>
		<div class="qrc-ms-pro-chart-container">
			<div class="qrc-ms-pro-chart-bars">
				<?php foreach ( $chart_data as $day ) :
					$percentage = $max_chart_value > 0 ? round( ( $day['count'] / $max_chart_value ) * 100 ) : 0;
					$date_label = wp_date( 'M j', strtotime( $day['date'] ) );
					?>
					<div class="qrc-ms-pro-chart-bar-wrapper" title="<?php echo esc_attr( $date_label . ': ' . $day['count'] . ' ' . __( 'scans', 'qrc-ms-pro' ) ); ?>">
						<div class="qrc-ms-pro-chart-bar" style="height: <?php echo esc_attr( max( $percentage, 2 ) ); ?>%;">
							<?php if ( $day['count'] > 0 ) : ?>
								<span class="qrc-ms-pro-chart-bar-value"><?php echo esc_html( $day['count'] ); ?></span>
							<?php endif; ?>
						</div>
						<span class="qrc-ms-pro-chart-bar-label"><?php echo esc_html( $date_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php elseif ( $total_scans === 0 ) : ?>
	<div class="qrc-ms-pro-analytics-empty">
		<p><?php esc_html_e( 'No scan data yet. Scans will appear here once your dynamic QR codes are scanned.', 'qrc-ms-pro' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- QR Codes Table -->
	<div class="qrc-ms-pro-analytics-table-section">
		<h2><?php esc_html_e( 'QR Code Performance', 'qrc-ms-pro' ); ?></h2>

		<?php if ( ! empty( $top_qr_codes ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-title">
						<?php esc_html_e( 'QR Code', 'qrc-ms-pro' ); ?>
					</th>
					<th scope="col" class="column-scans">
						<?php
						$scans_sort_args = array(
							'period'  => $period,
							'orderby' => 'scans',
							'order'   => ( 'scans' === $orderby && 'DESC' === strtoupper( $order ) ) ? 'ASC' : 'DESC',
						);
						if ( ! empty( $campaign ) ) {
							$scans_sort_args['campaign'] = $campaign;
						}
						$scans_sort_url = add_query_arg( $scans_sort_args, $base_url );
						?>
						<a href="<?php echo esc_url( $scans_sort_url ); ?>">
							<?php esc_html_e( 'Scans', 'qrc-ms-pro' ); ?>
							<?php if ( 'scans' === $orderby ) : ?>
								<span class="sorting-indicators">
									<span class="sorting-indicator <?php echo 'ASC' === strtoupper( $order ) ? 'asc' : 'desc'; ?>"></span>
								</span>
							<?php endif; ?>
						</a>
					</th>
					<th scope="col" class="column-last-scan">
						<?php
						$last_sort_args = array(
							'period'  => $period,
							'orderby' => 'last_scan',
							'order'   => ( 'last_scan' === $orderby && 'DESC' === strtoupper( $order ) ) ? 'ASC' : 'DESC',
						);
						if ( ! empty( $campaign ) ) {
							$last_sort_args['campaign'] = $campaign;
						}
						$last_sort_url = add_query_arg( $last_sort_args, $base_url );
						?>
						<a href="<?php echo esc_url( $last_sort_url ); ?>">
							<?php esc_html_e( 'Last Scan', 'qrc-ms-pro' ); ?>
							<?php if ( 'last_scan' === $orderby ) : ?>
								<span class="sorting-indicators">
									<span class="sorting-indicator <?php echo 'ASC' === strtoupper( $order ) ? 'asc' : 'desc'; ?>"></span>
								</span>
							<?php endif; ?>
						</a>
					</th>
					<th scope="col" class="column-short-code">
						<?php esc_html_e( 'Short Code', 'qrc-ms-pro' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_qr_codes as $qr_data ) :
					$post = get_post( (int) $qr_data['qr_code_id'] );
					if ( ! $post ) {
						continue;
					}
					$short_code = get_post_meta( $post->ID, QRC_MS_Pro_Redirect_Handler::META_SHORT_CODE, true );
					$edit_url   = get_edit_post_link( $post->ID );
					?>
					<tr>
						<td class="column-title">
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html( $post->post_title ?: __( '(no title)', 'qrc-ms-pro' ) ); ?>
								</a>
							</strong>
						</td>
						<td class="column-scans">
							<strong><?php echo esc_html( number_format_i18n( (int) $qr_data['scan_count'] ) ); ?></strong>
						</td>
						<td class="column-last-scan">
							<?php
							if ( ! empty( $qr_data['last_scan'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $qr_data['last_scan'] ) ) );
							} else {
								esc_html_e( 'Never', 'qrc-ms-pro' );
							}
							?>
						</td>
						<td class="column-short-code">
							<code><?php echo esc_html( $short_code ); ?></code>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
		<p class="description"><?php esc_html_e( 'No scan data available for the selected period.', 'qrc-ms-pro' ); ?></p>
		<?php endif; ?>
	</div>

</div>
