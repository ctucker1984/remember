<?php
/**
 * Remove reMember data when the plugin is deleted. Does not delete WordPress users.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Uninstall wipe (Plugins → Delete). Deactivate does not call this.
 */
class Remember_Uninstall {

	/**
	 * Drop plugin tables, options, uploads, and capabilities. Keep wp_users.
	 *
	 * @return void
	 */
	public static function wipe() {
		global $wpdb;

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}

		self::unschedule_cron();

		$photos = self::member_photo_urls();
		$pages  = get_option( 'remember_created_pages', array() );
		$logdir = self::log_directory();

		self::drop_tables();
		self::delete_options_and_transients();
		self::delete_user_meta();
		self::remove_capabilities();
		self::delete_created_pages( $pages );
		self::delete_upload_urls( $photos );
		self::delete_directory( $logdir ? dirname( $logdir ) : '' );
		self::delete_directory( self::uploads_remember_dir() );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Clear plugin cron events.
	 *
	 * @return void
	 */
	private static function unschedule_cron() {
		foreach ( array( 'remember_qb_sync', 'remember_duplicate_scan' ) as $hook ) {
			if ( function_exists( 'wp_unschedule_hook' ) ) {
				wp_unschedule_hook( $hook );
				continue;
			}
			$timestamp = wp_next_scheduled( $hook );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Profile photo URLs stored on members, if the table still exists.
	 *
	 * @return string[]
	 */
	private static function member_photo_urls() {
		global $wpdb;
		$table = $wpdb->prefix . 'remember_members';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $found ) {
			return array();
		}
		$urls = $wpdb->get_col( "SELECT photo_url FROM `{$table}` WHERE photo_url IS NOT NULL AND photo_url <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $urls ) ? $urls : array();
	}

	/**
	 * Current log directory, if the option is set.
	 *
	 * @return string
	 */
	private static function log_directory() {
		$key = get_option( 'remember_log_dir_key', '' );
		if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $key ) ) {
			return '';
		}
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		$base    = ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) )
			? $uploads['basedir']
			: WP_CONTENT_DIR . '/uploads';
		return trailingslashit( $base ) . 'remember-logs/' . $key;
	}

	/**
	 * Optional member-photo folder under uploads.
	 *
	 * @return string
	 */
	private static function uploads_remember_dir() {
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		$base    = ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) )
			? $uploads['basedir']
			: '';
		return $base ? trailingslashit( $base ) . 'remember' : '';
	}

	/**
	 * DROP every wp_remember_* table.
	 *
	 * @return void
	 */
	private static function drop_tables() {
		global $wpdb;
		$like   = $wpdb->esc_like( $wpdb->prefix . 'remember_' ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		if ( ! is_array( $tables ) || empty( $tables ) ) {
			return;
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		foreach ( $tables as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- names from SHOW TABLES.
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
	}

	/**
	 * Delete remember_* options and transients.
	 *
	 * @return void
	 */
	private static function delete_options_and_transients() {
		global $wpdb;
		$patterns = array(
			$wpdb->esc_like( 'remember_' ) . '%',
			$wpdb->esc_like( '_transient_remember_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_remember_' ) . '%',
			$wpdb->esc_like( '_site_transient_remember_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_remember_' ) . '%',
		);
		foreach ( $patterns as $like ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}
	}

	/**
	 * Delete remember_* user meta. Leaves WordPress users and core profile fields.
	 *
	 * @return void
	 */
	private static function delete_user_meta() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'remember_' ) . '%'
			)
		);
	}

	/**
	 * Strip remember_* caps from roles and per-user grants.
	 *
	 * @return void
	 */
	private static function remove_capabilities() {
		global $wpdb;

		$wp_roles = wp_roles();
		if ( $wp_roles && is_array( $wp_roles->roles ) ) {
			foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
				$role = get_role( $role_name );
				if ( ! $role || empty( $role->capabilities ) ) {
					continue;
				}
				foreach ( array_keys( $role->capabilities ) as $cap ) {
					if ( 0 === strpos( $cap, 'remember_' ) ) {
						$role->remove_cap( $cap );
					}
				}
			}
		}

		$cap_key = $wpdb->prefix . 'capabilities';
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $cap_key ) );
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			$caps = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $caps ) ) {
				continue;
			}
			$changed = false;
			foreach ( array_keys( $caps ) as $cap ) {
				if ( 0 === strpos( (string) $cap, 'remember_' ) ) {
					unset( $caps[ $cap ] );
					$changed = true;
				}
			}
			if ( $changed ) {
				update_user_meta( (int) $row->user_id, $cap_key, $caps );
			}
		}
	}

	/**
	 * Delete setup-wizard pages we created.
	 *
	 * @param mixed $pages Option value.
	 * @return void
	 */
	private static function delete_created_pages( $pages ) {
		if ( ! is_array( $pages ) ) {
			return;
		}
		foreach ( $pages as $page_id ) {
			$page_id = absint( $page_id );
			if ( $page_id > 0 ) {
				wp_delete_post( $page_id, true );
			}
		}
	}

	/**
	 * Unlink local upload files for stored photo URLs.
	 *
	 * @param string[] $urls Photo URLs.
	 * @return void
	 */
	private static function delete_upload_urls( $urls ) {
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return;
		}
		$basedir = wp_normalize_path( $uploads['basedir'] );
		$baseurl = $uploads['baseurl'];
		foreach ( $urls as $url ) {
			$url = (string) $url;
			if ( 0 !== strpos( $url, $baseurl ) ) {
				continue;
			}
			$path = wp_normalize_path( str_replace( $baseurl, $basedir, $url ) );
			if ( 0 !== strpos( $path, $basedir ) || ! is_file( $path ) ) {
				continue;
			}
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Recursively delete a directory under uploads.
	 *
	 * @param string $dir Absolute path.
	 * @return void
	 */
	private static function delete_directory( $dir ) {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();
		$base    = ( is_array( $uploads ) && ! empty( $uploads['basedir'] ) ) ? wp_normalize_path( $uploads['basedir'] ) : '';
		$dir     = wp_normalize_path( $dir );
		if ( '' === $base || 0 !== strpos( $dir, $base ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::delete_directory( $path );
			} elseif ( is_file( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$parent = dirname( $dir );
		if ( $parent !== $base && 0 === strpos( $parent, $base ) && is_dir( $parent ) ) {
			$left = array_diff( scandir( $parent ), array( '.', '..' ) );
			if ( empty( $left ) ) {
				@rmdir( $parent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}
}
