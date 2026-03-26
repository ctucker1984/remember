<?php
/**
 * Capabilities management for the plugin
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Capabilities management class.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Capabilities {

	/**
	 * Get all available plugin capabilities.
	 *
	 * @return array Array of capability => label pairs.
	 */
	public static function get_all_capabilities() {
		$capabilities = array();

		// CRUD capabilities for each module
		$modules = array(
			'members'     => __( 'Members', 'remember' ),
			'attendees'   => __( 'Attendees', 'remember' ),
			'events'      => __( 'Events', 'remember' ),
			'applications' => __( 'Applications', 'remember' ),
			'vetting'     => __( 'Vetting', 'remember' ),
			'billing'     => __( 'Billing', 'remember' ),
			'locations'   => __( 'Locations', 'remember' ),
			'roles'       => __( 'Roles', 'remember' ),
		);

		foreach ( $modules as $module => $label ) {
			$capabilities[ "remember_create_{$module}" ] = sprintf( __( 'Create %s', 'remember' ), $label );
			$capabilities[ "remember_read_{$module}" ]   = sprintf( __( 'Read %s', 'remember' ), $label );
			$capabilities[ "remember_update_{$module}" ] = sprintf( __( 'Update %s', 'remember' ), $label );
			$capabilities[ "remember_delete_{$module}" ] = sprintf( __( 'Delete %s', 'remember' ), $label );
		}

		// Non-CRUD capabilities (system admin only)
		$capabilities['remember_access_settings'] = __( 'Access Settings', 'remember' );
		$capabilities['remember_view_reports']    = __( 'View Reports', 'remember' );

		return $capabilities;
	}

	/**
	 * Sync role capabilities to WordPress user capabilities.
	 * Grants all capabilities from assigned reMember roles to the WordPress user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function sync_user_capabilities_from_roles( $user_id ) {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		
		// Get all reMember capabilities
		$all_capabilities = self::get_all_capabilities();
		$all_cap_keys = array_keys( $all_capabilities );
		
		// Remove all reMember capabilities first
		foreach ( $all_cap_keys as $cap ) {
			$user->remove_cap( $cap );
		}
		
		// Get all roles assigned to this member
		$role_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT role_id FROM {$wpdb->prefix}remember_member_roles WHERE member_id = %d",
			$user_id
		) );
		
		if ( empty( $role_ids ) ) {
			return true; // No roles assigned, capabilities already removed
		}
		
		// Get all capabilities from assigned roles
		$placeholders = implode( ',', array_fill( 0, count( $role_ids ), '%d' ) );
		$capabilities = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT capability FROM {$wpdb->prefix}remember_role_capabilities WHERE role_id IN ($placeholders)",
			$role_ids
		) );
		
		// Grant capabilities to user
		foreach ( $capabilities as $capability ) {
			if ( in_array( $capability, $all_cap_keys, true ) ) {
				$user->add_cap( $capability );
			}
		}
		
		return true;
	}

	/**
	 * Get capability label.
	 *
	 * @param string $capability Capability name.
	 * @return string Capability label.
	 */
	public static function get_capability_label( $capability ) {
		$capabilities = self::get_all_capabilities();
		return isset( $capabilities[ $capability ] ) ? $capabilities[ $capability ] : $capability;
	}

	/**
	 * Set up all plugin capabilities.
	 *
	 * @since    1.0.0
	 */
	public static function setup_capabilities() {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		Remember_Logger::activation_debug( 'setup_capabilities: start' );

		// Get administrator role
		$admin_role = get_role( 'administrator' );
		
		if ( $admin_role ) {
			$capabilities = self::get_all_capabilities();
			foreach ( array_keys( $capabilities ) as $cap ) {
				$admin_role->add_cap( $cap );
			}
			Remember_Logger::activation_debug( 'setup_capabilities: caps added to administrator', array( 'count' => count( $capabilities ) ) );
		} else {
			Remember_Logger::activation_debug( 'setup_capabilities: administrator role missing' );
		}

		Remember_Logger::activation_debug( 'setup_capabilities: end' );
	}

	/**
	 * Remove all plugin capabilities.
	 *
	 * @since    1.0.0
	 */
	public static function remove_capabilities() {
		$admin_role = get_role( 'administrator' );
		
		if ( $admin_role ) {
			$capabilities = self::get_all_capabilities();
			foreach ( array_keys( $capabilities ) as $cap ) {
				$admin_role->remove_cap( $cap );
			}
		}
	}
}
