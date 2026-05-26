<?php
/**
 * Self-hosted plugin updater.
 *
 * Checks your server for new versions of the pro add-on and delivers
 * updates to licensed users. Hooks into WordPress update system.
 *
 * @package    QRC_MS_Pro
 * @subpackage QRC_MS_Pro/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Updater class.
 *
 * @since 1.0.0
 */
class QRC_MS_Pro_Updater {

	/**
	 * Your update server endpoint.
	 */
	private const UPDATE_URL = 'https://example.com/wp-json/updates/v1/check';

	/**
	 * Initialize the updater.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_action( 'in_plugin_update_message-' . QRC_MS_PRO_PLUGIN_BASENAME, array( __CLASS__, 'update_message' ), 10, 2 );
	}

	/**
	 * Check for updates.
	 *
	 * @since 1.0.0
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public static function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$license_key = QRC_MS_Pro_License_Manager::get_license_key();
		if ( empty( $license_key ) ) {
			return $transient;
		}

		$remote = self::get_remote_info( $license_key );
		if ( ! $remote ) {
			return $transient;
		}

		if ( version_compare( QRC_MS_PRO_VERSION, $remote['version'], '<' ) ) {
			$transient->response[ QRC_MS_PRO_PLUGIN_BASENAME ] = (object) array(
				'slug'        => dirname( QRC_MS_PRO_PLUGIN_BASENAME ),
				'plugin'      => QRC_MS_PRO_PLUGIN_BASENAME,
				'new_version' => $remote['version'],
				'url'         => $remote['homepage'] ?? '',
				'package'     => $remote['download_url'] ?? '',
				'tested'      => $remote['tested'] ?? '',
				'requires'    => $remote['requires'] ?? '',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the update details modal.
	 *
	 * @since 1.0.0
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Arguments.
	 * @return false|object
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		$slug = dirname( QRC_MS_PRO_PLUGIN_BASENAME );
		if ( ! isset( $args->slug ) || $args->slug !== $slug ) {
			return $result;
		}

		$license_key = QRC_MS_Pro_License_Manager::get_license_key();
		$remote      = self::get_remote_info( $license_key );

		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote['name'] ?? 'QR Codes - Made Simple Pro',
			'slug'          => $slug,
			'version'       => $remote['version'] ?? QRC_MS_PRO_VERSION,
			'author'        => $remote['author'] ?? '',
			'homepage'      => $remote['homepage'] ?? '',
			'requires'      => $remote['requires'] ?? '6.0',
			'tested'        => $remote['tested'] ?? '',
			'requires_php'  => $remote['requires_php'] ?? '8.0',
			'download_link' => $remote['download_url'] ?? '',
			'sections'      => array(
				'description' => $remote['description'] ?? '',
				'changelog'   => $remote['changelog'] ?? '',
			),
		);
	}

	/**
	 * Show message in plugin update row when no license.
	 *
	 * @since 1.0.0
	 * @param array  $plugin_data Plugin data.
	 * @param object $response    Response.
	 * @return void
	 */
	public static function update_message( $plugin_data, $response ): void {
		if ( empty( $response->package ) ) {
			printf(
				' <strong>%s</strong>',
				esc_html__( 'A valid license key is required to receive updates.', 'qrc-ms-pro' )
			);
		}
	}

	/**
	 * Get remote plugin info from update server.
	 *
	 * @since 1.0.0
	 * @param string $license_key License key.
	 * @return array|false
	 */
	private static function get_remote_info( string $license_key ): array|false {
		$response = wp_remote_get(
			add_query_arg( array(
				'license_key' => $license_key,
				'site_url'    => home_url(),
				'plugin'      => 'qrc-ms-pro',
				'version'     => QRC_MS_PRO_VERSION,
			), self::UPDATE_URL ),
			array( 'timeout' => 10 )
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			return false;
		}

		return $data;
	}
}
