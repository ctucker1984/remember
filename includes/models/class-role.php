<?php
/**
 * Role model class
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-base-model.php';

/**
 * Role model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Role extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'roles';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'role_id';

	/**
	 * Create a new role.
	 *
	 * @param array $data Role data.
	 * @return int|false Role ID or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}

	/**
	 * Get event roles.
	 *
	 * @return array
	 */
	public function get_event_roles() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->get_table()} WHERE is_event_role = 1 ORDER BY role_name ASC"
		);
	}

	/**
	 * Get system roles.
	 *
	 * @return array
	 */
	public function get_system_roles() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->get_table()} WHERE is_event_role = 0 ORDER BY role_name ASC"
		);
	}

	/**
	 * Get capabilities for a role.
	 *
	 * @param int $role_id Role ID.
	 * @return array Array of capability names.
	 */
	public function get_capabilities( $role_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'remember_role_capabilities';
		$capabilities = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT capability FROM $table_name WHERE role_id = %d ORDER BY capability ASC",
				$role_id
			)
		);
		return $capabilities ? $capabilities : array();
	}

	/**
	 * Set capabilities for a role (replaces existing).
	 *
	 * @param int   $role_id     Role ID.
	 * @param array $capabilities Array of capability names.
	 * @return bool True on success, false on failure.
	 */
	public function set_capabilities( $role_id, $capabilities ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'remember_role_capabilities';
		
		// Delete existing capabilities
		$wpdb->delete( $table_name, array( 'role_id' => $role_id ), array( '%d' ) );
		
		// Insert new capabilities
		if ( ! empty( $capabilities ) ) {
			foreach ( $capabilities as $capability ) {
				$wpdb->insert(
					$table_name,
					array(
						'role_id'    => $role_id,
						'capability' => sanitize_text_field( $capability ),
						'created_at' => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s' )
				);
			}
		}
		
		return true;
	}

	/**
	 * Add a capability to a role.
	 *
	 * @param int    $role_id   Role ID.
	 * @param string $capability Capability name.
	 * @return bool True on success, false on failure.
	 */
	public function add_capability( $role_id, $capability ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'remember_role_capabilities';
		
		// Check if already exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE role_id = %d AND capability = %s",
				$role_id,
				$capability
			)
		);
		
		if ( $exists > 0 ) {
			return true; // Already exists
		}
		
		return $wpdb->insert(
			$table_name,
			array(
				'role_id'    => $role_id,
				'capability' => sanitize_text_field( $capability ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s' )
		) !== false;
	}

	/**
	 * Remove a capability from a role.
	 *
	 * @param int    $role_id   Role ID.
	 * @param string $capability Capability name.
	 * @return bool True on success, false on failure.
	 */
	public function remove_capability( $role_id, $capability ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'remember_role_capabilities';
		
		return $wpdb->delete(
			$table_name,
			array(
				'role_id'    => $role_id,
				'capability' => $capability,
			),
			array( '%d', '%s' )
		) !== false;
	}
}
