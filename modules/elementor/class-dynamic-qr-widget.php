<?php
/**
 * Elementor Dynamic QR Widget.
 *
 * Displays a specific dynamic QR code by post ID, allowing users
 * to embed existing QR codes into Elementor layouts.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/elementor
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic QR Widget for Elementor.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Elementor_Dynamic_QR_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @since 1.2.0
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'qrc_ms_pro_dynamic_qr';
	}

	/**
	 * Get widget title.
	 *
	 * @since 1.2.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'Dynamic QR Code', 'qrc-ms-pro' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 1.2.0
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-barcode';
	}

	/**
	 * Get widget categories.
	 *
	 * @since 1.2.0
	 * @return array Widget categories.
	 */
	public function get_categories(): array {
		return array( 'qrc-ms-pro' );
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 1.2.0
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'qr', 'dynamic', 'redirect', 'code', 'scan' );
	}

	/**
	 * Register widget controls.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	protected function register_controls(): void {
		// Content Section.
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Dynamic QR Code', 'qrc-ms-pro' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		// Get available QR codes for the dropdown.
		$qr_codes = self::get_qr_code_options();

		$this->add_control(
			'qr_code_id',
			array(
				'label'       => __( 'Select QR Code', 'qrc-ms-pro' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $qr_codes,
				'default'     => '',
				'label_block' => true,
				'description' => __( 'Choose an existing QR code to display.', 'qrc-ms-pro' ),
			)
		);

		$this->add_control(
			'show_scan_count',
			array(
				'label'        => __( 'Show Scan Count', 'qrc-ms-pro' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'qrc-ms-pro' ),
				'label_off'    => __( 'No', 'qrc-ms-pro' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Show Title', 'qrc-ms-pro' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'qrc-ms-pro' ),
				'label_off'    => __( 'No', 'qrc-ms-pro' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();

		// Style Section.
		$this->start_controls_section(
			'style_section',
			array(
				'label' => __( 'Style', 'qrc-ms-pro' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'qr_size',
			array(
				'label'   => __( 'Size (px)', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'default' => array( 'size' => 200 ),
				'range'   => array(
					'px' => array(
						'min' => 100,
						'max' => 600,
					),
				),
			)
		);

		$this->add_responsive_control(
			'qr_alignment',
			array(
				'label'   => __( 'Alignment', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => array(
					'left'   => array(
						'title' => __( 'Left', 'qrc-ms-pro' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'qrc-ms-pro' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'qrc-ms-pro' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .qrc-ms-pro-elementor-dynamic-qr' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => __( 'Title Color', 'qrc-ms-pro' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#333333',
				'selectors' => array(
					'{{WRAPPER}} .qrc-ms-pro-qr-title' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'show_title' => 'yes',
				),
			)
		);

		$this->add_control(
			'scan_count_color',
			array(
				'label'     => __( 'Scan Count Color', 'qrc-ms-pro' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#666666',
				'selectors' => array(
					'{{WRAPPER}} .qrc-ms-pro-qr-scans' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'show_scan_count' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	protected function render(): void {
		$settings   = $this->get_settings_for_display();
		$qr_code_id = ! empty( $settings['qr_code_id'] ) ? absint( $settings['qr_code_id'] ) : 0;
		$size        = $settings['qr_size']['size'] ?? 200;

		if ( ! $qr_code_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="elementor-alert elementor-alert-info">';
				esc_html_e( 'Please select a QR code in the widget settings.', 'qrc-ms-pro' );
				echo '</p>';
			}
			return;
		}

		$post = get_post( $qr_code_id );
		if ( ! $post || 'qrc_ms_code' !== $post->post_type ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="elementor-alert elementor-alert-warning">';
				esc_html_e( 'Selected QR code not found.', 'qrc-ms-pro' );
				echo '</p>';
			}
			return;
		}

		$qr_data = get_post_meta( $qr_code_id, '_qrc_ms_data', true );

		// For dynamic QR codes, use the redirect URL.
		$is_dynamic = get_post_meta( $qr_code_id, '_qrc_ms_pro_is_dynamic', true );
		$short_code = get_post_meta( $qr_code_id, '_qrc_ms_pro_short_code', true );

		if ( $is_dynamic && ! empty( $short_code ) && class_exists( 'QRC_MS_Pro_Redirect_Handler' ) ) {
			$qr_data = QRC_MS_Pro_Redirect_Handler::build_redirect_url( $short_code );
		}

		echo '<div class="qrc-ms-pro-elementor-dynamic-qr">';

		// Show title.
		if ( 'yes' === ( $settings['show_title'] ?? '' ) ) {
			echo '<h3 class="qrc-ms-pro-qr-title">' . esc_html( $post->post_title ) . '</h3>';
		}

		// Render QR code.
		if ( ! empty( $qr_data ) ) {
			/**
			 * Filter to render a QR code as HTML/SVG.
			 *
			 * @since 1.2.0
			 *
			 * @param string $output The rendered output.
			 * @param string $data   The data to encode.
			 * @param array  $args   Rendering arguments.
			 */
			$output = apply_filters( 'qrc_ms/render_qr_html', '', $qr_data, array(
				'size' => $size,
			) );

			if ( ! empty( $output ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from trusted filter.
				echo $output;
			} else {
				// Fallback shortcode.
				$shortcode = sprintf(
					'[qrc_ms_qr_code data="%s" size="%d"]',
					esc_attr( $qr_data ),
					(int) $size
				);

				$rendered = do_shortcode( $shortcode );
				if ( $rendered !== $shortcode ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $rendered;
				} else {
					printf(
						'<div class="qrc-ms-pro-qr-placeholder" style="width:%1$dpx;height:%1$dpx;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:0 auto;">',
						(int) $size
					);
					echo '<span>' . esc_html__( 'QR Code', 'qrc-ms-pro' ) . '</span>';
					echo '</div>';
				}
			}
		}

		// Show scan count.
		if ( 'yes' === ( $settings['show_scan_count'] ?? '' ) && class_exists( 'QRC_MS_Pro_Analytics' ) ) {
			$scan_count = QRC_MS_Pro_Analytics::get_scan_count( $qr_code_id );
			echo '<p class="qrc-ms-pro-qr-scans">';
			printf(
				/* translators: %s: scan count */
				esc_html__( '%s scans', 'qrc-ms-pro' ),
				esc_html( number_format_i18n( $scan_count ) )
			);
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render widget content template for the editor.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	protected function content_template(): void {
		?>
		<#
		var size = settings.qr_size.size || 200;
		#>
		<div class="qrc-ms-pro-elementor-dynamic-qr">
			<# if ( settings.show_title === 'yes' ) { #>
				<h3 class="qrc-ms-pro-qr-title"><?php esc_html_e( 'QR Code Title', 'qrc-ms-pro' ); ?></h3>
			<# } #>
			<div class="qrc-ms-pro-qr-placeholder" style="width:{{size}}px;height:{{size}}px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:0 auto;">
				<span style="font-size:12px;text-align:center;">
					<?php esc_html_e( 'Dynamic QR Code', 'qrc-ms-pro' ); ?>
				</span>
			</div>
			<# if ( settings.show_scan_count === 'yes' ) { #>
				<p class="qrc-ms-pro-qr-scans"><?php esc_html_e( '0 scans', 'qrc-ms-pro' ); ?></p>
			<# } #>
		</div>
		<?php
	}

	/**
	 * Get QR code posts as options for the select control.
	 *
	 * @since 1.2.0
	 * @return array Associative array of post ID => title.
	 */
	private static function get_qr_code_options(): array {
		$options = array( '' => __( '— Select QR Code —', 'qrc-ms-pro' ) );

		$posts = get_posts( array(
			'post_type'      => 'qrc_ms_code',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		foreach ( $posts as $post ) {
			$options[ $post->ID ] = $post->post_title;
		}

		return $options;
	}
}
