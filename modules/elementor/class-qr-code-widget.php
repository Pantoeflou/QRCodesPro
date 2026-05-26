<?php
/**
 * Elementor QR Code Widget.
 *
 * Displays a QR code with configurable data and styling options.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules/elementor
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QR Code Widget for Elementor.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Elementor_QR_Code_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @since 1.2.0
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'qrc_ms_pro_qr_code';
	}

	/**
	 * Get widget title.
	 *
	 * @since 1.2.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'QR Code', 'qrc-ms-pro' );
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
		return array( 'qr', 'code', 'barcode', 'scan', 'url' );
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
				'label' => __( 'QR Code Content', 'qrc-ms-pro' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'qr_type',
			array(
				'label'   => __( 'Type', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'url',
				'options' => array(
					'url'         => __( 'URL', 'qrc-ms-pro' ),
					'text'        => __( 'Text', 'qrc-ms-pro' ),
					'email'       => __( 'Email', 'qrc-ms-pro' ),
					'phone'       => __( 'Phone', 'qrc-ms-pro' ),
					'current_url' => __( 'Current Page URL', 'qrc-ms-pro' ),
				),
			)
		);

		$this->add_control(
			'qr_data',
			array(
				'label'       => __( 'Data', 'qrc-ms-pro' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => home_url(),
				'placeholder' => __( 'https://example.com', 'qrc-ms-pro' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
				'condition'   => array(
					'qr_type!' => 'current_url',
				),
			)
		);

		$this->end_controls_section();

		// Style Section.
		$this->start_controls_section(
			'style_section',
			array(
				'label' => __( 'QR Code Style', 'qrc-ms-pro' ),
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

		$this->add_control(
			'qr_foreground',
			array(
				'label'   => __( 'Foreground Color', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#000000',
			)
		);

		$this->add_control(
			'qr_background',
			array(
				'label'   => __( 'Background Color', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
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
					'{{WRAPPER}} .qrc-ms-pro-elementor-qr' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'qr_error_correction',
			array(
				'label'   => __( 'Error Correction', 'qrc-ms-pro' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'M',
				'options' => array(
					'L' => __( 'Low (7%)', 'qrc-ms-pro' ),
					'M' => __( 'Medium (15%)', 'qrc-ms-pro' ),
					'Q' => __( 'Quartile (25%)', 'qrc-ms-pro' ),
					'H' => __( 'High (30%)', 'qrc-ms-pro' ),
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
		$settings = $this->get_settings_for_display();

		$qr_type = $settings['qr_type'] ?? 'url';
		$qr_data = $settings['qr_data'] ?? '';
		$size    = $settings['qr_size']['size'] ?? 200;
		$fg      = $settings['qr_foreground'] ?? '#000000';
		$bg      = $settings['qr_background'] ?? '#ffffff';
		$ec      = $settings['qr_error_correction'] ?? 'M';

		// For current page URL type, use a placeholder in editor.
		if ( 'current_url' === $qr_type ) {
			$qr_data = get_permalink() ?: home_url();
		}

		if ( empty( $qr_data ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="elementor-alert elementor-alert-info">';
				esc_html_e( 'Please enter QR code data in the widget settings.', 'qrc-ms-pro' );
				echo '</p>';
			}
			return;
		}

		/**
		 * Filter to render a QR code as HTML/SVG.
		 *
		 * @since 1.2.0
		 *
		 * @param string $output The rendered output (empty by default).
		 * @param string $data   The data to encode.
		 * @param array  $args   Rendering arguments.
		 */
		$output = apply_filters( 'qrc_ms/render_qr_html', '', $qr_data, array(
			'size'             => $size,
			'foreground'       => $fg,
			'background'       => $bg,
			'error_correction' => $ec,
		) );

		echo '<div class="qrc-ms-pro-elementor-qr">';

		if ( ! empty( $output ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG output from trusted filter.
			echo $output;
		} else {
			// Fallback: render using shortcode if available.
			$shortcode = sprintf(
				'[qrc_ms_qr_code data="%s" size="%d" foreground="%s" background="%s"]',
				esc_attr( $qr_data ),
				(int) $size,
				esc_attr( $fg ),
				esc_attr( $bg )
			);

			$rendered = do_shortcode( $shortcode );

			if ( $rendered !== $shortcode ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output.
				echo $rendered;
			} else {
				// Ultimate fallback: display data as text.
				printf(
					'<div class="qrc-ms-pro-qr-placeholder" style="width:%1$dpx;height:%1$dpx;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:0 auto;">',
					(int) $size
				);
				echo '<span>' . esc_html__( 'QR Code', 'qrc-ms-pro' ) . ': ' . esc_html( mb_substr( $qr_data, 0, 40 ) ) . '</span>';
				echo '</div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render widget output in the editor (content template).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	protected function content_template(): void {
		?>
		<#
		var data = settings.qr_type === 'current_url' ? '<?php echo esc_js( home_url() ); ?>' : settings.qr_data;
		var size = settings.qr_size.size || 200;
		#>
		<div class="qrc-ms-pro-elementor-qr">
			<div class="qrc-ms-pro-qr-placeholder" style="width:{{size}}px;height:{{size}}px;border:2px dashed #ccc;display:flex;align-items:center;justify-content:center;margin:0 auto;background:{{settings.qr_background}};">
				<span style="color:{{settings.qr_foreground}};font-size:12px;text-align:center;padding:10px;">
					<?php esc_html_e( 'QR Code', 'qrc-ms-pro' ); ?><br>
					<small>{{{data}}}</small>
				</span>
			</div>
		</div>
		<?php
	}
}
