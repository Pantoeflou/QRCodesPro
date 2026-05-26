<?php
/**
 * Redirect handler for dynamic QR codes.
 *
 * Intercepts requests with the `qrc_ms_redirect` query parameter,
 * looks up the short code, and performs a 302 redirect to the stored
 * destination URL. This handler runs on every front-end request so
 * it must bail out as early as possible when not relevant.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect handler class.
 *
 * @since 1.0.0
 */
class QRC_MS_Pro_Redirect_Handler {

	/**
	 * Query parameter name used in redirect URLs.
	 *
	 * @since 1.0.0
	 */
	public const QUERY_PARAM = 'qrc_ms_redirect';

	/**
	 * Post meta key for the short code.
	 *
	 * @since 1.0.0
	 */
	public const META_SHORT_CODE = '_qrc_ms_short_code';

	/**
	 * Post meta key for the dynamic flag.
	 *
	 * @since 1.0.0
	 */
	public const META_IS_DYNAMIC = '_qrc_ms_is_dynamic';

	/**
	 * Post meta key for the current redirect destination.
	 *
	 * @since 1.0.0
	 */
	public const META_REDIRECT_URL = '_qrc_ms_redirect_url';

	/**
	 * Post meta key for redirect history.
	 *
	 * @since 1.0.0
	 */
	public const META_REDIRECT_HISTORY = '_qrc_ms_redirect_history';

	/**
	 * Short code length.
	 *
	 * @since 1.0.0
	 */
	private const SHORT_CODE_LENGTH = 8;

	/**
	 * Initialize the redirect handler.
	 *
	 * Hooks into template_redirect at priority 1 for early interception.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ), 1 );
	}

	/**
	 * Handle incoming redirect requests.
	 *
	 * Checks for the redirect query parameter and performs the redirect
	 * if a valid short code is found.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function handle_redirect(): void {
		// Early bail-out: no query param present.
		if ( ! isset( $_GET[ self::QUERY_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$short_code = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Validate short code format (alphanumeric, exact length).
		if ( ! preg_match( '/^[a-zA-Z0-9]{' . self::SHORT_CODE_LENGTH . '}$/', $short_code ) ) {
			self::handle_not_found();
			return;
		}

		$redirect_url = self::get_redirect_url( $short_code );

		if ( false === $redirect_url ) {
			self::handle_not_found();
			return;
		}

		// Check expiry BEFORE recording analytics.
		$post = self::get_qr_post_by_code( $short_code );
		if ( null !== $post ) {
			$expiry_date = get_post_meta( $post->ID, '_qrc_ms_pro_expiry_date', true );

			if ( ! empty( $expiry_date ) && strtotime( $expiry_date ) < time() ) {
				self::handle_expired( $post->ID );
				return;
			}
		}

		/**
		 * Fires before a dynamic QR redirect is performed.
		 *
		 * Allows other modules (e.g., analytics) to hook in and record the scan.
		 *
		 * @since 1.0.0
		 *
		 * @param string $short_code  The short code being resolved.
		 * @param string $redirect_url The destination URL.
		 */
		do_action( 'qrc_ms_pro/redirect', $short_code, $redirect_url );

		wp_redirect( $redirect_url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Generate a unique short code.
	 *
	 * Creates an 8-character alphanumeric code and verifies uniqueness
	 * against existing codes in the database.
	 *
	 * @since 1.0.0
	 * @return string Unique short code.
	 */
	public static function generate_short_code(): string {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$max_index  = strlen( $characters ) - 1;
		$attempts   = 0;
		$max_attempts = 10;

		do {
			$code = '';
			for ( $i = 0; $i < self::SHORT_CODE_LENGTH; $i++ ) {
				$code .= $characters[ random_int( 0, $max_index ) ];
			}

			$attempts++;

			// Check uniqueness.
			$existing = self::get_qr_post_by_code( $code );

		} while ( null !== $existing && $attempts < $max_attempts );

		return $code;
	}

	/**
	 * Get the redirect URL for a given short code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $short_code The short code to look up.
	 * @return string|false The destination URL, or false if not found.
	 */
	public static function get_redirect_url( string $short_code ): string|false {
		$post = self::get_qr_post_by_code( $short_code );

		if ( null === $post ) {
			return false;
		}

		// Verify the post is published and dynamic mode is enabled.
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		$is_dynamic = get_post_meta( $post->ID, self::META_IS_DYNAMIC, true );
		if ( ! $is_dynamic ) {
			return false;
		}

		$url = get_post_meta( $post->ID, self::META_REDIRECT_URL, true );

		if ( empty( $url ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * Get the QR code post associated with a short code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $short_code The short code to look up.
	 * @return \WP_Post|null The post object, or null if not found.
	 */
	public static function get_qr_post_by_code( string $short_code ): ?\WP_Post {
		$posts = get_posts( array(
			'post_type'      => 'qrc_ms_code',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'meta_key'       => self::META_SHORT_CODE,
			'meta_value'     => $short_code,
			'no_found_rows'  => true,
		) );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Build the full redirect URL for a given short code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $short_code The short code.
	 * @return string The full redirect URL to encode in the QR code.
	 */
	public static function build_redirect_url( string $short_code ): string {
		return add_query_arg( self::QUERY_PARAM, $short_code, site_url( '/' ) );
	}

	/**
	 * Handle a not-found short code by showing a 404.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function handle_not_found(): void {
		global $wp_query;

		if ( isset( $wp_query ) ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}

		/**
		 * Fires when a dynamic QR redirect short code is not found.
		 *
		 * @since 1.0.0
		 *
		 * @param string $short_code The short code that was not found.
		 */
		do_action( 'qrc_ms_pro/redirect_not_found', sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Let WordPress handle the 404 template.
	}

	/**
	 * Handle an expired QR code redirect.
	 *
	 * If a fallback URL is set, redirect to it. Otherwise, display
	 * the expiry message as a simple HTML page.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The QR code post ID.
	 * @return void
	 */
	private static function handle_expired( int $post_id ): void {
		/**
		 * Fires when an expired QR code is scanned.
		 *
		 * @since 1.1.0
		 *
		 * @param int $post_id The expired QR code post ID.
		 */
		do_action( 'qrc_ms_pro/redirect_expired', $post_id );

		$fallback_url = get_post_meta( $post_id, '_qrc_ms_pro_fallback_url', true );

		if ( ! empty( $fallback_url ) ) {
			wp_redirect( $fallback_url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}

		$expiry_message = get_post_meta( $post_id, '_qrc_ms_pro_expiry_message', true );
		if ( empty( $expiry_message ) ) {
			$expiry_message = __( 'This QR code has expired.', 'qrc-ms-pro' );
		}

		status_header( 410 );
		nocache_headers();

		// Output a simple HTML page with the expiry message.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $expiry_message ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 100vh;
					margin: 0;
					background: #f0f0f1;
					color: #1d2327;
				}
				.expired-container {
					text-align: center;
					padding: 40px;
					background: #fff;
					border-radius: 8px;
					box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
					max-width: 500px;
				}
				.expired-icon {
					font-size: 48px;
					margin-bottom: 16px;
				}
				.expired-message {
					font-size: 18px;
					line-height: 1.5;
				}
			</style>
		</head>
		<body>
			<div class="expired-container">
				<div class="expired-icon">⏰</div>
				<p class="expired-message"><?php echo esc_html( $expiry_message ); ?></p>
			</div>
		</body>
		</html>
		<?php
		die();
	}
}
