<?php
/**
 * Team / Multi-user module.
 *
 * Provides role-based access control for QR code management with
 * custom capabilities and audit logging.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/modules
 * @since      1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Team class.
 *
 * @since 1.2.0
 */
class QRC_MS_Pro_Team {

	/**
	 * Custom capabilities.
	 *
	 * @since 1.2.0
	 */
	public const CAP_MANAGE  = 'manage_qr_codes';
	public const CAP_EDIT    = 'edit_qr_codes';
	public const CAP_VIEW    = 'view_qr_analytics';

	/**
	 * Audit log meta key.
	 *
	 * @since 1.2.0
	 */
	private const AUDIT_META_KEY = '_qrc_ms_pro_audit_log';

	/**
	 * Option key for capability version tracking.
	 *
	 * @since 1.2.0
	 */
	private const CAPS_VERSION_KEY = 'qrc_ms_pro_caps_version';

	/**
	 * Current capabilities version.
	 *
	 * @since 1.2.0
	 */
	private const CAPS_VERSION = '1.0';

	/**
	 * Initialize the team module.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );

		// Install capabilities if needed.
		self::maybe_install_caps();

		// Map meta capabilities.
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_caps' ), 10, 4 );

		// Audit logging hooks.
		add_action( 'save_post_qrc_ms_code', array( __CLASS__, 'log_post_save' ), 99, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'log_post_delete' ) );
		add_action( 'trashed_post', array( __CLASS__, 'log_post_trash' ) );

		// Admin settings.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
	}

	/**
	 * Register the team feature.
	 *
	 * @since 1.2.0
	 *
	 * @param array $features Existing features.
	 * @return array Modified features.
	 */
	public static function register_feature( array $features ): array {
		$features[] = array(
			'name'        => __( 'Team & Multi-user', 'qrc-ms-pro' ),
			'description' => __( 'Role-based access control and audit logging for QR code management.', 'qrc-ms-pro' ),
			'pro'         => true,
		);

		return $features;
	}

	/**
	 * Install custom capabilities if not already done.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function maybe_install_caps(): void {
		$installed_version = get_option( self::CAPS_VERSION_KEY, '' );

		if ( self::CAPS_VERSION === $installed_version ) {
			return;
		}

		self::install_caps();
		update_option( self::CAPS_VERSION_KEY, self::CAPS_VERSION );
	}

	/**
	 * Install capabilities to roles.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function install_caps(): void {
		// Administrator gets all capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_MANAGE );
			$admin->add_cap( self::CAP_EDIT );
			$admin->add_cap( self::CAP_VIEW );
		}

		// Editor gets edit and view.
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAP_EDIT );
			$editor->add_cap( self::CAP_VIEW );
		}

		// Author gets view only.
		$author = get_role( 'author' );
		if ( $author ) {
			$author->add_cap( self::CAP_VIEW );
		}
	}

	/**
	 * Remove capabilities from all roles (for uninstall).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function remove_caps(): void {
		$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->remove_cap( self::CAP_MANAGE );
				$role->remove_cap( self::CAP_EDIT );
				$role->remove_cap( self::CAP_VIEW );
			}
		}

		delete_option( self::CAPS_VERSION_KEY );
	}

	/**
	 * Map meta capabilities for QR code posts.
	 *
	 * @since 1.2.0
	 *
	 * @param array  $caps    Required capabilities.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments.
	 * @return array Modified capabilities.
	 */
	public static function map_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
		// Map edit_post for qrc_ms_code to our custom capability.
		if ( 'edit_post' === $cap && ! empty( $args[0] ) ) {
			$post = get_post( $args[0] );
			if ( $post && 'qrc_ms_code' === $post->post_type ) {
				// If user has manage_qr_codes, they can do anything.
				if ( user_can( $user_id, self::CAP_MANAGE ) ) {
					return array( self::CAP_MANAGE );
				}
				// If user has edit_qr_codes, they can edit.
				if ( user_can( $user_id, self::CAP_EDIT ) ) {
					return array( self::CAP_EDIT );
				}
			}
		}

		return $caps;
	}

	/**
	 * Log when a QR code post is saved.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function log_post_save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$action = 'revision' === $post->post_status ? 'revised' : 'updated';

		// Check if this is a new post (no revisions yet).
		$revisions = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 1 ) );
		if ( empty( $revisions ) ) {
			$action = 'created';
		}

		self::add_audit_entry( $post_id, $action );
	}

	/**
	 * Log when a QR code post is permanently deleted.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function log_post_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'qrc_ms_code' !== $post->post_type ) {
			return;
		}

		self::add_audit_entry( $post_id, 'deleted' );
	}

	/**
	 * Log when a QR code post is trashed.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function log_post_trash( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'qrc_ms_code' !== $post->post_type ) {
			return;
		}

		self::add_audit_entry( $post_id, 'trashed' );
	}

	/**
	 * Add an audit log entry for a QR code post.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $post_id The QR code post ID.
	 * @param string $action  The action performed.
	 * @return void
	 */
	private static function add_audit_entry( int $post_id, string $action ): void {
		$log = get_post_meta( $post_id, self::AUDIT_META_KEY, true );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$current_user = wp_get_current_user();

		$entry = array(
			'action'    => $action,
			'user_id'   => get_current_user_id(),
			'user_name' => $current_user->display_name ?: $current_user->user_login,
			'timestamp' => current_time( 'mysql' ),
			'ip'        => self::get_client_ip(),
		);

		// Keep only the last 50 entries.
		$log[] = $entry;
		$log   = array_slice( $log, -50 );

		update_post_meta( $post_id, self::AUDIT_META_KEY, $log );
	}

	/**
	 * Get the audit log for a QR code post.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id The QR code post ID.
	 * @return array Audit log entries.
	 */
	public static function get_audit_log( int $post_id ): array {
		$log = get_post_meta( $post_id, self::AUDIT_META_KEY, true );

		if ( ! is_array( $log ) ) {
			return array();
		}

		// Return in reverse chronological order.
		return array_reverse( $log );
	}

	/**
	 * Register the team settings admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=qrc_ms_code',
			__( 'Team & Permissions', 'qrc-ms-pro' ),
			__( 'Team', 'qrc-ms-pro' ),
			'manage_options',
			'qrc-ms-pro-team',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Render the team settings admin page.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'qrc-ms-pro' ) );
		}

		// Handle role capability updates.
		if ( isset( $_POST['qrc_ms_pro_team_nonce'] ) && wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['qrc_ms_pro_team_nonce'] ) ),
			'qrc_ms_pro_team_save'
		) ) {
			self::save_role_settings();
		}

		$roles = wp_roles()->roles;
		$caps  = array(
			self::CAP_MANAGE => __( 'Manage QR Codes (full access)', 'qrc-ms-pro' ),
			self::CAP_EDIT   => __( 'Edit QR Codes', 'qrc-ms-pro' ),
			self::CAP_VIEW   => __( 'View QR Analytics', 'qrc-ms-pro' ),
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Team & Permissions', 'qrc-ms-pro' ); ?></h1>

			<div class="qrc-ms-pro-page-intro">
				<p>
					<?php esc_html_e( 'Control who on your team can create, edit, and view QR codes. Assign granular permissions to WordPress roles so editors can manage codes without full admin access, and authors can view analytics without editing anything.', 'qrc-ms-pro' ); ?>
				</p>
			</div>

			<form method="post" action="">
				<?php wp_nonce_field( 'qrc_ms_pro_team_save', 'qrc_ms_pro_team_nonce' ); ?>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Role', 'qrc-ms-pro' ); ?></th>
							<?php foreach ( $caps as $cap_key => $cap_label ) : ?>
								<th scope="col"><?php echo esc_html( $cap_label ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $roles as $role_slug => $role_data ) : ?>
							<?php
							// Skip subscriber.
							if ( 'subscriber' === $role_slug ) {
								continue;
							}
							$role = get_role( $role_slug );
							?>
							<tr>
								<td><strong><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></strong></td>
								<?php foreach ( $caps as $cap_key => $cap_label ) : ?>
									<td>
										<label>
											<input type="checkbox"
												name="qrc_ms_pro_role_caps[<?php echo esc_attr( $role_slug ); ?>][<?php echo esc_attr( $cap_key ); ?>]"
												value="1"
												<?php checked( $role && $role->has_cap( $cap_key ) ); ?>
												<?php disabled( 'administrator' === $role_slug ); ?>>
										</label>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description">
					<?php esc_html_e( 'Administrator always has full access and cannot be modified.', 'qrc-ms-pro' ); ?>
				</p>

				<?php submit_button( __( 'Save Permissions', 'qrc-ms-pro' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Capability Descriptions', 'qrc-ms-pro' ); ?></h2>
			<dl>
				<dt><strong><?php esc_html_e( 'Manage QR Codes', 'qrc-ms-pro' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Full access: create, edit, delete QR codes, manage settings, and view all analytics.', 'qrc-ms-pro' ); ?></dd>

				<dt><strong><?php esc_html_e( 'Edit QR Codes', 'qrc-ms-pro' ); ?></strong></dt>
				<dd><?php esc_html_e( 'Create and edit QR codes, but cannot delete or manage plugin settings.', 'qrc-ms-pro' ); ?></dd>

				<dt><strong><?php esc_html_e( 'View QR Analytics', 'qrc-ms-pro' ); ?></strong></dt>
				<dd><?php esc_html_e( 'View scan analytics and reports, but cannot create or modify QR codes.', 'qrc-ms-pro' ); ?></dd>
			</dl>
		</div>
		<?php
	}

	/**
	 * Save role capability settings.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private static function save_role_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$role_caps = isset( $_POST['qrc_ms_pro_role_caps'] ) && is_array( $_POST['qrc_ms_pro_role_caps'] )
			? wp_unslash( $_POST['qrc_ms_pro_role_caps'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		$all_caps = array( self::CAP_MANAGE, self::CAP_EDIT, self::CAP_VIEW );
		$roles    = wp_roles()->roles;

		foreach ( $roles as $role_slug => $role_data ) {
			// Never modify administrator.
			if ( 'administrator' === $role_slug ) {
				continue;
			}

			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}

			foreach ( $all_caps as $cap ) {
				$has_cap = ! empty( $role_caps[ $role_slug ][ $cap ] );

				if ( $has_cap ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Get the client IP address for audit logging.
	 *
	 * @since 1.2.0
	 * @return string Hashed IP address.
	 */
	private static function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Store hashed IP for privacy.
		return $ip ? hash( 'sha256', $ip . wp_salt( 'auth' ) ) : '';
	}
}
