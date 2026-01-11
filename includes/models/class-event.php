<?php
/**
 * Event model class
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
 * Event model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Event extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'events';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'event_id';

	/**
	 * Create a new event.
	 *
	 * @param array $data Event data.
	 * @return int|false Event ID or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}

	/**
	 * Update an event.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $data     Event data.
	 * @return int|false
	 */
	public function update( $event_id, $data ) {
		$data['updated_at'] = current_time( 'mysql' );
		return parent::update( $event_id, $data );
	}

	/**
	 * Get events by status.
	 *
	 * @param string $status Event status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE status = %s ORDER BY start_date ASC",
				$status
			)
		);
	}

	/**
	 * Get open events.
	 *
	 * @return array
	 */
	public function get_open() {
		return $this->get_by_status( 'open' );
	}

	/**
	 * Get upcoming events.
	 *
	 * @return array
	 */
	public function get_upcoming() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->get_table()} WHERE start_date >= CURDATE() AND status IN ('open', 'draft') ORDER BY start_date ASC"
		);
	}

	/**
	 * Get historical events by location (past events, sorted reverse chronological).
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_historical_by_location( $location_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} 
				WHERE location_id = %d 
				AND end_date < CURDATE() 
				AND status IN ('completed', 'closed', 'cancelled')
				ORDER BY end_date DESC, start_date DESC",
				$location_id
			)
		);
	}

	/**
	 * Get event roles for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return array Array of event role objects with role information.
	 */
	public function get_event_roles( $event_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT er.*, r.role_name 
				FROM {$wpdb->prefix}remember_event_roles er 
				JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
				WHERE er.event_id = %d 
				ORDER BY r.role_name ASC",
				$event_id
			)
		);
	}

	/**
	 * Set event roles for an event (replaces existing).
	 *
	 * @param int   $event_id Event ID.
	 * @param array $role_ids Array of role IDs to assign to the event.
	 * @return bool True on success, false on failure.
	 */
	public function set_event_roles( $event_id, $role_ids ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'remember_event_roles';
		
		// Delete existing event roles
		$wpdb->delete( $table_name, array( 'event_id' => $event_id ), array( '%d' ) );
		
		// Insert new event roles
		if ( ! empty( $role_ids ) ) {
			foreach ( $role_ids as $role_id ) {
				$role_id = absint( $role_id );
				if ( $role_id > 0 ) {
					$wpdb->insert(
						$table_name,
						array(
							'event_id'    => $event_id,
							'role_id'     => $role_id,
							'cost'        => 0.00,
							'is_active'   => 1,
							'created_at'  => current_time( 'mysql' ),
						),
						array( '%d', '%d', '%f', '%d', '%s' )
					);
				}
			}
		}
		
		return true;
	}
}
