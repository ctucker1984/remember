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
		// Get administrator role
		$admin_role = get_role( 'administrator' );
		
		if ( $admin_role ) {
			$capabilities = self::get_all_capabilities();
			foreach ( array_keys( $capabilities ) as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
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
