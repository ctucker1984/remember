<?php
/**
 * Dashboard updates from GitHub Releases.
 *
 * The plugin header sets `Update URI: https://github.com/ctucker1984/remember`, which
 * stops WordPress.org from offering updates but does not supply any on its own. Core
 * then runs the `update_plugins_github.com` filter (WordPress 5.8+) so a plugin can
 * answer for its own hostname. This class is that answer.
 *
 * Only the release asset built by bin/build-plugin-zip.sh is offered as the package.
 * GitHub's generated "Source code" zip unpacks to remember-<tag>/ and would install
 * beside the active copy instead of replacing it.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * GitHub Releases update provider.
 */
class Remember_GitHub_Updater {

	const PLUGIN_BASENAME = 'remember/remember.php';
	const UPDATE_URI      = 'https://github.com/ctucker1984/remember';
	const API_URL         = 'https://api.github.com/repos/ctucker1984/remember/releases/latest';
	const TRANSIENT_KEY   = 'remember_github_latest_release';
	const CACHE_LIFETIME  = 6 * HOUR_IN_SECONDS;

	/**
	 * Register hooks (call from main plugin bootstrap).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_for_update' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_details' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ), 10, 2 );
	}

	/**
	 * Answer core's update query for github.com-hosted plugins.
	 *
	 * @param array|false $update      Update data from an earlier filter, or false.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename being checked.
	 * @return array|false Update payload, or the incoming value when not applicable.
	 */
	public static function check_for_update( $update, $plugin_data, $plugin_file ) {
		unset( $plugin_data );

		if ( self::PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}
		// Another handler already answered for this plugin.
		if ( ! empty( $update ) ) {
			return $update;
		}

		$release = self::get_latest_release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $update;
		}

		if ( ! version_compare( $release['version'], self::installed_version(), '>' ) ) {
			return $update;
		}

		return array(
			'id'      => self::UPDATE_URI,
			'slug'    => 'remember',
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	/**
	 * Populate the "View details" modal, which otherwise 404s against WordPress.org.
	 *
	 * @param false|object|array $result Result from an earlier filter.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || 'remember' !== $args->slug ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( empty( $release['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'reMember',
			'slug'          => 'remember',
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/ctucker1984">ctucker1984</a>',
			'homepage'      => self::UPDATE_URI,
			'download_link' => $release['package'],
			'trunk'         => $release['package'],
			'requires'      => '5.8',
			'sections'      => array(
				'description' => esc_html__( 'Membership communities for WordPress — member profiles, events and locations, applications and vetting, admission tickets, and billing with QuickBooks Online or Xero.', 'remember' ),
				'changelog'   => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Drop the cached release after any plugin update so the next check is fresh.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Update context.
	 * @return void
	 */
	public static function flush_cache( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Installed plugin version.
	 *
	 * @return string
	 */
	private static function installed_version() {
		return defined( 'REMEMBER_VERSION' ) ? REMEMBER_VERSION : '0.0.0';
	}

	/**
	 * Latest published release, cached to stay well inside GitHub's unauthenticated rate limit.
	 *
	 * @return array{version:string,package:string,url:string,notes:string}|array{}
	 */
	private static function get_latest_release() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API_URL,
			array(
				'headers'    => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
				'user-agent' => 'reMember-WordPress/' . self::installed_version(),
				'timeout'    => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache the miss briefly so a GitHub outage cannot slow every admin page load.
			set_transient( self::TRANSIENT_KEY, array(), 15 * MINUTE_IN_SECONDS );
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, array(), 15 * MINUTE_IN_SECONDS );
			return array();
		}

		$version = ltrim( (string) $body['tag_name'], 'vV' );
		$release = array(
			'version' => $version,
			'package' => self::find_release_asset( $body, $version ),
			'url'     => ! empty( $body['html_url'] ) ? esc_url_raw( $body['html_url'] ) : self::UPDATE_URI,
			'notes'   => isset( $body['body'] ) ? (string) $body['body'] : '',
		);

		set_transient( self::TRANSIENT_KEY, $release, self::CACHE_LIFETIME );

		return $release;
	}

	/**
	 * Download URL for the remember-<version>.zip asset attached to a release.
	 *
	 * Returns an empty string when only GitHub's generated source zip is present, since
	 * that archive unpacks to the wrong folder name and must never be offered.
	 *
	 * @param array  $body    Decoded release payload.
	 * @param string $version Release version without the leading v.
	 * @return string
	 */
	private static function find_release_asset( $body, $version ) {
		if ( empty( $body['assets'] ) || ! is_array( $body['assets'] ) ) {
			return '';
		}

		$expected = 'remember-' . $version . '.zip';
		foreach ( $body['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}
			if ( $expected === $asset['name'] ) {
				return esc_url_raw( $asset['browser_download_url'] );
			}
		}

		return '';
	}
}
