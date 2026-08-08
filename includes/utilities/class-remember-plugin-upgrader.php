<?php
/**
 * Safe zip overwrite / upgrade for reMember.
 *
 * WordPress Plugin_Upgrader::upgrade() (dashboard updates) silently deactivates
 * before replacing files. Upload → “Replace current with uploaded” uses
 * install( overwrite_package ) and does NOT deactivate — which can fatal while
 * files are deleted/replaced under an active plugin. This class mirrors the
 * core deactivate → replace → silent reactivate flow for remember/remember.php.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin upgrader safety hooks.
 */
class Remember_Plugin_Upgrader {

	const PLUGIN_BASENAME = 'remember/remember.php';
	const TRANSIENT_KEY   = 'remember_reactivate_after_upgrade';

	/**
	 * Register hooks (call from main plugin bootstrap).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'upgrader_clear_destination', array( __CLASS__, 'deactivate_before_clear_destination' ), 1, 4 );
		add_action( 'upgrader_overwrote_package', array( __CLASS__, 'maybe_reactivate_after_overwrite' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'maybe_reactivate_after_process' ), 10, 2 );
	}

	/**
	 * Whether a filesystem destination path is the remember plugin folder.
	 *
	 * @param string $remote_destination Destination path (WP_Filesystem style).
	 * @return bool
	 */
	private static function is_remember_destination( $remote_destination ) {
		$remote_destination = untrailingslashit( str_replace( '\\', '/', (string) $remote_destination ) );
		return ( 'remember' === basename( $remote_destination ) );
	}

	/**
	 * Whether plugin header data belongs to reMember.
	 *
	 * @param array $plugin_data Plugin headers.
	 * @return bool
	 */
	private static function is_remember_plugin_data( $plugin_data ) {
		if ( ! is_array( $plugin_data ) ) {
			return false;
		}
		if ( ! empty( $plugin_data['TextDomain'] ) && 'remember' === $plugin_data['TextDomain'] ) {
			return true;
		}
		if ( ! empty( $plugin_data['Name'] ) && 'reMember' === $plugin_data['Name'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Silently deactivate reMember before its directory is wiped during overwrite/upgrade.
	 *
	 * Hooked early on {@see 'upgrader_clear_destination'} so upload-overwrite installs
	 * (which skip Plugin_Upgrader::deactivate_plugin_before_upgrade) are covered.
	 *
	 * @param bool|WP_Error $removed            Clear result.
	 * @param string        $local_destination  Local destination.
	 * @param string        $remote_destination Remote destination being cleared.
	 * @param array         $hook_extra         Upgrader hook extras.
	 * @return bool|WP_Error
	 */
	public static function deactivate_before_clear_destination( $removed, $local_destination, $remote_destination, $hook_extra ) {
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}
		if ( wp_doing_cron() ) {
			return $removed;
		}
		if ( ! self::is_remember_destination( $remote_destination ) ) {
			return $removed;
		}
		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'deactivate_plugins' ) ) {
			return $removed;
		}

		$plugin = self::PLUGIN_BASENAME;
		if ( ! is_plugin_active( $plugin ) && ! is_plugin_active_for_network( $plugin ) ) {
			return $removed;
		}

		$network_wide = is_multisite() && is_plugin_active_for_network( $plugin );
		// Silent: do not run deactivation hooks mid-upgrade.
		deactivate_plugins( $plugin, true, $network_wide );

		set_transient(
			self::TRANSIENT_KEY,
			array(
				'plugin'       => $plugin,
				'network_wide' => $network_wide ? 1 : 0,
			),
			HOUR_IN_SECONDS
		);

		return $removed;
	}

	/**
	 * Reactivate after a successful upload overwrite of reMember.
	 *
	 * @param string $package      Package file path.
	 * @param array  $plugin_data  New plugin headers.
	 * @param string $package_type 'plugin' or 'theme'.
	 * @return void
	 */
	public static function maybe_reactivate_after_overwrite( $package, $plugin_data, $package_type ) {
		unset( $package );
		if ( 'plugin' !== $package_type || ! self::is_remember_plugin_data( $plugin_data ) ) {
			return;
		}
		self::reactivate_if_flagged();
	}

	/**
	 * Reactivate after a successful update process that targeted reMember.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Process extras.
	 * @return void
	 */
	public static function maybe_reactivate_after_process( $upgrader, $hook_extra ) {
		unset( $upgrader );
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}
		if ( ! empty( $hook_extra['plugin'] ) && self::PLUGIN_BASENAME === $hook_extra['plugin'] ) {
			self::reactivate_if_flagged();
			return;
		}
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && in_array( self::PLUGIN_BASENAME, $hook_extra['plugins'], true ) ) {
			self::reactivate_if_flagged();
		}
	}

	/**
	 * Silently reactivate if we deactivated for an upgrade in this request cycle.
	 *
	 * @return void
	 */
	private static function reactivate_if_flagged() {
		$flag = get_transient( self::TRANSIENT_KEY );
		delete_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $flag ) || empty( $flag['plugin'] ) ) {
			return;
		}
		if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active' ) ) {
			return;
		}

		$plugin       = (string) $flag['plugin'];
		$network_wide = ! empty( $flag['network_wide'] );

		if ( $network_wide ) {
			if ( is_plugin_active_for_network( $plugin ) ) {
				return;
			}
		} elseif ( is_plugin_active( $plugin ) ) {
			return;
		}

		// Silent activate (same as core update iframe reactivation).
		$result = activate_plugin( $plugin, '', $network_wide, true );
		if ( is_wp_error( $result ) && class_exists( 'Remember_Logger', false ) ) {
			Remember_Logger::warning(
				'reMember could not reactivate after upgrade',
				array(
					'error' => $result->get_error_message(),
				)
			);
		}
	}
}
