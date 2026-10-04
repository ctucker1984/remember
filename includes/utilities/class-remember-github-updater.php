<?php
/**
 * Dashboard updates from GitHub Releases.
 *
 * The plugin header sets `Update URI: https://github.com/ctucker1984/remember`, which
 * stops WordPress.org from offering updates but does not supply any on its own. Core
 * then runs the `update_plugins_github.com` filter (WordPress 5.8+) so a plugin can
 * answer for its own hostname. This class is that answer.
 *
 * WordPress itself only rebuilds the plugin update list about every 12 hours.
 * Check again deletes that list; we also drop our GitHub snapshot then, and we
 * overlay the latest GitHub release whenever the list is read so a new version
 * shows up without waiting for the next core check.
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
	const CACHE_LIFETIME  = 15 * MINUTE_IN_SECONDS;

	/**
	 * Register hooks (call from main plugin bootstrap).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_for_update' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_details' ), 10, 3 );
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_after_upgrade' ), 10, 2 );
		add_action( 'delete_site_transient_update_plugins', array( __CLASS__, 'clear_release_cache' ) );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_flush_on_force_check' ) );
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

		$payload = self::update_payload();
		if ( empty( $payload ) ) {
			return $update;
		}

		if ( ! version_compare( $payload['version'], self::installed_version(), '>' ) ) {
			return $update;
		}

		return $payload;
	}

	/**
	 * Overlay GitHub's latest release onto WordPress's cached plugin update list.
	 *
	 * Core only writes that list about twice a day. Without this, a release shipped
	 * an hour ago stays invisible until the next write or a Check again click.
	 *
	 * @param mixed $transient Site transient value (object, false, or unexpected).
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$payload = self::update_payload();
		if ( empty( $payload ) ) {
			return $transient;
		}

		$item = (object) array(
			'id'          => $payload['id'],
			'slug'        => $payload['slug'],
			'plugin'      => self::PLUGIN_BASENAME,
			'version'     => $payload['version'],
			'new_version' => $payload['version'],
			'url'         => $payload['url'],
			'package'     => $payload['package'],
		);

		if ( version_compare( $payload['version'], self::installed_version(), '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ self::PLUGIN_BASENAME ] = $item;
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				unset( $transient->no_update[ self::PLUGIN_BASENAME ] );
			}
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ self::PLUGIN_BASENAME ] = $item;
			if ( isset( $transient->response ) && is_array( $transient->response ) ) {
				unset( $transient->response[ self::PLUGIN_BASENAME ] );
			}
		}

		return $transient;
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
	 * Dashboard → Updates → Check again. Core deletes update_plugins; this is extra
	 * in case a host load-order skips that action.
	 *
	 * @return void
	 */
	public static function maybe_flush_on_force_check() {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::clear_release_cache();
		}
	}

	/**
	 * Drop the cached GitHub release.
	 *
	 * @return void
	 */
	public static function clear_release_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Drop the cached release after any plugin update so the next check is fresh.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Update context.
	 * @return void
	 */
	public static function flush_after_upgrade( $upgrader, $hook_extra ) {
		unset( $upgrader );

		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}
		self::clear_release_cache();
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
	 * Array payload for update_plugins_github.com (empty when GitHub has nothing usable).
	 *
	 * @return array{id:string,slug:string,version:string,url:string,package:string}|array{}
	 */
	private static function update_payload() {
		$release = self::get_latest_release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
			return array();
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
	 * Latest published release, cached to stay well inside GitHub's unauthenticated rate limit.
	 *
	 * Dashboard Check again (`force-check`) skips the snapshot and talks to GitHub now.
	 *
	 * @return array{version:string,package:string,url:string,notes:string}|array{}
	 */
	private static function get_latest_release() {
		$force = isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $force ) {
			self::clear_release_cache();
		} else {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
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
