<?php
/**
 * Advanced Branding module.
 *
 * Extends QR code styling with pro-only options including center logos,
 * gradient foregrounds, eye styles, and frame/border text labels.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Branding class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Branding {

	/**
	 * Meta key for branding options.
	 *
	 * @since 1.2.0
	 */
	public const META_KEY = '_qrc_ms_pro_branding';

	/**
	 * Nonce action for saving branding meta.
	 *
	 * @since 1.2.0
	 */
	private const NONCE_ACTION = 'qrc_ms_pro_save_branding';

	/**
	 * Initialize the branding module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_qrc_ms_code', array( __CLASS__, 'save_branding_meta' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'qrc_ms/render_options', array( __CLASS__, 'inject_branding_options' ), 10, 2 );
	}

	/**
	 * Inject branding options (logo, gradient, etc.) into QR render options.
	 *
	 * Hooks into the free plugin's render_options filter to pass branding
	 * settings from the current post's meta into the renderer.
	 *
	 * @since 1.2.0
	 *
	 * @param array  $opts The current render options.
	 * @param string $data The data being encoded.
	 * @return array Modified render options with branding applied.
	 */
	public static function inject_branding_options( array $opts, string $data ): array {
		// Only inject branding when rendering a specific QR code post.
		$post_id = self::get_current_qr_post_id( $data );
		if ( 0 === $post_id ) {
			return $opts;
		}

		$branding = self::get_branding_options( $post_id );

		// Inject logo if set.
		if ( ! empty( $branding['logo_id'] ) ) {
			$logo_url = wp_get_attachment_url( (int) $branding['logo_id'] );
			if ( $logo_url ) {
				$opts['logo_url']  = $logo_url;
				$opts['logo_size'] = (int) $branding['logo_size'];
			}
		}

		// Inject gradient if enabled (renderer will handle SVG gradient defs).
		if ( ! empty( $branding['gradient_enabled'] ) ) {
			$opts['gradient'] = array(
				'start'     => $branding['gradient_start'],
				'end'       => $branding['gradient_end'],
				'direction' => $branding['gradient_direction'],
			);
		}

		return $opts;
	}

	/**
	 * Attempt to determine the current QR code post ID from context.
	 *
	 * Checks the global post (admin edit screen) and falls back to
	 * querying by the encoded data URL if it matches a QR code post's content.
	 *
	 * @since 1.2.0
	 *
	 * @param string $data The data being encoded into the QR code.
	 * @return int Post ID or 0 if not determinable.
	 */
	private static function get_current_qr_post_id( string $data ): int {
		// On admin edit screens, the global $post is the QR code being edited.
		if ( is_admin() ) {
			global $post;
			if ( $post instanceof \WP_Post && 'qrc_ms_code' === $post->post_type ) {
				return $post->ID;
			}
		}

		// Try to find a QR code post that has this data stored as its content meta.
		$found = get_posts( array(
			'post_type'  => 'qrc_ms_code',
			'meta_key'   => '_qrc_ms_data',
			'meta_value' => $data,
			'fields'     => 'ids',
			'numberposts' => 1,
		) );

		if ( ! empty( $found ) ) {
			return (int) $found[0];
		}

		return 0;
	}

	/**
	 * Register the branding feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Advanced Branding', 'qrc-ms-pro' ),
			'description' => __( 'Add logos, gradients, custom eye styles, and frames to your QR codes.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Register the Pro Branding meta box.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_meta_box(): void {
		add_meta_box(
			'qrc_ms_pro_branding',
			__( 'Branding', 'qrc-ms-pro' ),
			array( __CLASS__, 'render_meta_box' ),
			'qrc_ms_code',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Pro Branding meta box.
	 *
	 * @since 1.2.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		$branding = self::get_branding_options( $post->ID );

		// Check if a template is applied and show context.
		$template_id = get_post_meta( $post->ID, '_qrc_ms_template', true );
		if ( ! empty( $template_id ) ) {
			$template_post = get_post( (int) $template_id );
			if ( $template_post ) {
				printf(
					'<div class="qrc-ms-pro-template-notice" style="margin-bottom:12px;padding:8px 12px;background:#f0f6fc;border-left:3px solid #2271b1;border-radius:0 3px 3px 0;font-size:13px;">%s <strong>%s</strong>. %s</div>',
					esc_html__( 'Template applied:', 'qrc-ms-pro' ),
					esc_html( $template_post->post_title ),
					esc_html__( 'Settings below override the template defaults for this QR code only.', 'qrc-ms-pro' )
				);
			}
		}

		wp_nonce_field( self::NONCE_ACTION, 'qrc_ms_pro_branding_nonce' );
		?>
		<div class="qrc-ms-pro-branding-fields">

			<!-- Center Logo -->
			<fieldset class="qrc-ms-pro-fieldset">
				<legend><?php esc_html_e( 'Center Logo', 'qrc-ms-pro' ); ?></legend>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_logo_id"><?php esc_html_e( 'Logo Image', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="hidden" id="qrc_ms_pro_logo_id" name="qrc_ms_pro_branding[logo_id]"
								value="<?php echo esc_attr( $branding['logo_id'] ); ?>">
							<div id="qrc-ms-pro-logo-preview">
								<?php if ( ! empty( $branding['logo_id'] ) ) : ?>
									<?php echo wp_get_attachment_image( (int) $branding['logo_id'], 'thumbnail' ); ?>
								<?php endif; ?>
							</div>
							<button type="button" class="button" id="qrc-ms-pro-logo-upload">
								<?php esc_html_e( 'Select Logo', 'qrc-ms-pro' ); ?>
							</button>
							<button type="button" class="button" id="qrc-ms-pro-logo-remove"
								<?php echo empty( $branding['logo_id'] ) ? 'style="display:none;"' : ''; ?>>
								<?php esc_html_e( 'Remove', 'qrc-ms-pro' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Recommended: square image, PNG with transparent background.', 'qrc-ms-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_logo_size"><?php esc_html_e( 'Logo Size', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="number" id="qrc_ms_pro_logo_size" name="qrc_ms_pro_branding[logo_size]"
								value="<?php echo esc_attr( $branding['logo_size'] ); ?>"
								min="5" max="40" step="1">
							<span class="description">%</span>
							<p class="description">
								<?php esc_html_e( 'Percentage of QR code size (5-40%). Larger logos may affect scannability.', 'qrc-ms-pro' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</fieldset>

			<!-- Eye Style -->
			<fieldset class="qrc-ms-pro-fieldset">
				<legend><?php esc_html_e( 'Eye Style (Finder Patterns)', 'qrc-ms-pro' ); ?></legend>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_eye_style"><?php esc_html_e( 'Style', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<select id="qrc_ms_pro_eye_style" name="qrc_ms_pro_branding[eye_style]">
								<option value="square" <?php selected( $branding['eye_style'], 'square' ); ?>>
									<?php esc_html_e( 'Square (Default)', 'qrc-ms-pro' ); ?>
								</option>
								<option value="rounded" <?php selected( $branding['eye_style'], 'rounded' ); ?>>
									<?php esc_html_e( 'Rounded', 'qrc-ms-pro' ); ?>
								</option>
								<option value="circle" <?php selected( $branding['eye_style'], 'circle' ); ?>>
									<?php esc_html_e( 'Circle', 'qrc-ms-pro' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Affects the three finder pattern corners of the QR code.', 'qrc-ms-pro' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</fieldset>

			<!-- Gradient Foreground -->
			<fieldset class="qrc-ms-pro-fieldset">
				<legend><?php esc_html_e( 'Gradient Foreground', 'qrc-ms-pro' ); ?></legend>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Enable Gradient', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="qrc_ms_pro_branding[gradient_enabled]" value="1"
									<?php checked( $branding['gradient_enabled'], '1' ); ?>>
								<?php esc_html_e( 'Use gradient instead of solid foreground color', 'qrc-ms-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_gradient_start"><?php esc_html_e( 'Start Color', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="color" id="qrc_ms_pro_gradient_start" name="qrc_ms_pro_branding[gradient_start]"
								value="<?php echo esc_attr( $branding['gradient_start'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_gradient_end"><?php esc_html_e( 'End Color', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="color" id="qrc_ms_pro_gradient_end" name="qrc_ms_pro_branding[gradient_end]"
								value="<?php echo esc_attr( $branding['gradient_end'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_gradient_direction"><?php esc_html_e( 'Direction', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<select id="qrc_ms_pro_gradient_direction" name="qrc_ms_pro_branding[gradient_direction]">
								<option value="to-bottom" <?php selected( $branding['gradient_direction'], 'to-bottom' ); ?>>
									<?php esc_html_e( 'Top to Bottom', 'qrc-ms-pro' ); ?>
								</option>
								<option value="to-right" <?php selected( $branding['gradient_direction'], 'to-right' ); ?>>
									<?php esc_html_e( 'Left to Right', 'qrc-ms-pro' ); ?>
								</option>
								<option value="to-bottom-right" <?php selected( $branding['gradient_direction'], 'to-bottom-right' ); ?>>
									<?php esc_html_e( 'Diagonal', 'qrc-ms-pro' ); ?>
								</option>
								<option value="radial" <?php selected( $branding['gradient_direction'], 'radial' ); ?>>
									<?php esc_html_e( 'Radial (Center Out)', 'qrc-ms-pro' ); ?>
								</option>
							</select>
						</td>
					</tr>
				</table>
			</fieldset>

			<!-- Frame / Border -->
			<fieldset class="qrc-ms-pro-fieldset">
				<legend><?php esc_html_e( 'Frame & Label', 'qrc-ms-pro' ); ?></legend>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Enable Frame', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="qrc_ms_pro_branding[frame_enabled]" value="1"
									<?php checked( $branding['frame_enabled'], '1' ); ?>>
								<?php esc_html_e( 'Add a frame/border around the QR code', 'qrc-ms-pro' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_frame_color"><?php esc_html_e( 'Frame Color', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="color" id="qrc_ms_pro_frame_color" name="qrc_ms_pro_branding[frame_color]"
								value="<?php echo esc_attr( $branding['frame_color'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_frame_text"><?php esc_html_e( 'Label Text', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="text" id="qrc_ms_pro_frame_text" name="qrc_ms_pro_branding[frame_text]"
								value="<?php echo esc_attr( $branding['frame_text'] ); ?>"
								class="regular-text" maxlength="50"
								placeholder="<?php esc_attr_e( 'e.g., Scan Me!', 'qrc-ms-pro' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Text displayed below the QR code within the frame (max 50 characters).', 'qrc-ms-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qrc_ms_pro_frame_text_color"><?php esc_html_e( 'Text Color', 'qrc-ms-pro' ); ?></label>
						</th>
						<td>
							<input type="color" id="qrc_ms_pro_frame_text_color" name="qrc_ms_pro_branding[frame_text_color]"
								value="<?php echo esc_attr( $branding['frame_text_color'] ); ?>">
						</td>
					</tr>
				</table>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets for the branding meta box.
	 *
	 * @since 1.2.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		global $post_type;

		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( 'qrc_ms_code' !== $post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'qrc-ms-pro-branding',
			QRC_MS_PRO_PLUGIN_URL . 'assets/js/branding.js',
			array( 'jquery' ),
			QRC_MS_PRO_VERSION,
			true
		);

		wp_enqueue_style(
			'qrc-ms-pro-branding',
			QRC_MS_PRO_PLUGIN_URL . 'assets/css/branding.css',
			array(),
			QRC_MS_PRO_VERSION
		);
	}

	/**
	 * Save branding meta data on post save.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_branding_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['qrc_ms_pro_branding_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_branding_nonce'] ) ),
			self::NONCE_ACTION
		) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['qrc_ms_pro_branding'] ) || ! is_array( $_POST['qrc_ms_pro_branding'] ) ) {
			return;
		}

		$raw = wp_unslash( $_POST['qrc_ms_pro_branding'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$branding = array(
			'logo_id'            => isset( $raw['logo_id'] ) ? absint( $raw['logo_id'] ) : 0,
			'logo_size'          => isset( $raw['logo_size'] ) ? max( 5, min( 40, absint( $raw['logo_size'] ) ) ) : 20,
			'eye_style'          => isset( $raw['eye_style'] ) && in_array( $raw['eye_style'], array( 'square', 'rounded', 'circle' ), true )
				? sanitize_text_field( $raw['eye_style'] )
				: 'square',
			'gradient_enabled'   => ! empty( $raw['gradient_enabled'] ) ? '1' : '',
			'gradient_start'     => isset( $raw['gradient_start'] ) ? sanitize_hex_color( $raw['gradient_start'] ) : '#000000',
			'gradient_end'       => isset( $raw['gradient_end'] ) ? sanitize_hex_color( $raw['gradient_end'] ) : '#333333',
			'gradient_direction' => isset( $raw['gradient_direction'] ) && in_array( $raw['gradient_direction'], array( 'to-bottom', 'to-right', 'to-bottom-right', 'radial' ), true )
				? sanitize_text_field( $raw['gradient_direction'] )
				: 'to-bottom',
			'frame_enabled'      => ! empty( $raw['frame_enabled'] ) ? '1' : '',
			'frame_color'        => isset( $raw['frame_color'] ) ? sanitize_hex_color( $raw['frame_color'] ) : '#000000',
			'frame_text'         => isset( $raw['frame_text'] ) ? sanitize_text_field( mb_substr( $raw['frame_text'], 0, 50 ) ) : '',
			'frame_text_color'   => isset( $raw['frame_text_color'] ) ? sanitize_hex_color( $raw['frame_text_color'] ) : '#ffffff',
		);

		update_post_meta( $post_id, self::META_KEY, $branding );
	}

	/**
	 * Get branding options for a QR code post.
	 *
	 * Falls back to the applied template's branding settings if no
	 * per-code branding has been saved.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id The post ID.
	 * @return array Branding options with defaults.
	 */
	public static function get_branding_options( int $post_id ): array {
		$defaults = array(
			'logo_id'            => 0,
			'logo_size'          => 20,
			'eye_style'          => 'square',
			'gradient_enabled'   => '',
			'gradient_start'     => '#000000',
			'gradient_end'       => '#333333',
			'gradient_direction' => 'to-bottom',
			'frame_enabled'      => '',
			'frame_color'        => '#000000',
			'frame_text'         => '',
			'frame_text_color'   => '#ffffff',
		);

		// Check for per-code branding first.
		$saved = get_post_meta( $post_id, self::META_KEY, true );

		if ( is_array( $saved ) ) {
			return wp_parse_args( $saved, $defaults );
		}

		// Fall back to template branding if a template is applied.
		$template_id = get_post_meta( $post_id, '_qrc_ms_template', true );
		if ( ! empty( $template_id ) && class_exists( 'QRC_MS_Template_Meta_Box' ) ) {
			$template_options = QRC_MS_Template_Meta_Box::get_template_options( (int) $template_id );
			// Extract branding keys from template options.
			$template_branding = array_intersect_key( $template_options, $defaults );
			if ( ! empty( $template_branding ) ) {
				return wp_parse_args( $template_branding, $defaults );
			}
		}

		return $defaults;
	}
}
