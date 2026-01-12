<?php
/**
 * Event Application model class
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
 * Event Application model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Application extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'event_applications';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'application_id';

	/**
	 * Create a new application.
	 *
	 * @param array $data Application data.
	 * @return int|false Application ID or false on error.
	 */
	public function create( $data ) {
		// Validate event_role_id exists before creating
		if ( isset( $data['event_role_id'] ) && ! empty( $data['event_role_id'] ) ) {
			global $wpdb;
			$event_role_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}remember_event_roles WHERE event_role_id = %d",
				$data['event_role_id']
			) );
			
			if ( ! $event_role_exists ) {
				$this->last_error = sprintf( 
					__( 'Invalid event_role_id: %d. The event role does not exist.', 'remember' ),
					$data['event_role_id']
				);
				return false;
			}
			
			// Also validate that the event_role belongs to the specified event
			if ( isset( $data['event_id'] ) && ! empty( $data['event_id'] ) ) {
				$event_role_belongs_to_event = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}remember_event_roles 
					WHERE event_role_id = %d AND event_id = %d",
					$data['event_role_id'],
					$data['event_id']
				) );
				
				if ( ! $event_role_belongs_to_event ) {
					$this->last_error = sprintf( 
						__( 'Event role %d does not belong to event %d.', 'remember' ),
						$data['event_role_id'],
						$data['event_id']
					);
					return false;
				}
			}
		}
		
		$data['applied_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}

	/**
	 * Update application status.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $status        New status.
	 * @param int    $processed_by  User ID who processed it.
	 * @return int|false
	 */
	public function update_status( $application_id, $status, $processed_by = null ) {
		$data = array(
			'status'      => $status,
			'processed_at' => current_time( 'mysql' ),
		);
		if ( $processed_by ) {
			$data['processed_by'] = $processed_by;
		}
		return $this->update( $application_id, $data );
	}

	/**
	 * Get applications by event.
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public function get_by_event( $event_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_id = %d ORDER BY applied_at DESC",
				$event_id
			)
		);
	}

	/**
	 * Get applications by member.
	 *
	 * @param int $member_id Member ID.
	 * @return array
	 */
	public function get_by_member( $member_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE member_id = %d ORDER BY applied_at DESC",
				$member_id
			)
		);
	}

	/**
	 * Get applications by status.
	 *
	 * @param string $status Application status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE status = %s ORDER BY applied_at DESC",
				$status
			)
		);
	}

	/**
	 * Check if member already applied to event role.
	 *
	 * @param int $event_id     Event ID.
	 * @param int $member_id    Member ID.
	 * @param int $event_role_id Event role ID.
	 * @return object|null
	 */
	public function get_existing_application( $event_id, $member_id, $event_role_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_id = %d AND member_id = %d AND event_role_id = %d",
				$event_id,
				$member_id,
				$event_role_id
			)
		);
	}

	/**
	 * Get application by event and member (any role).
	 *
	 * @param int $event_id  Event ID.
	 * @param int $member_id Member ID.
	 * @return object|null
	 */
	public function get_by_event_and_member( $event_id, $member_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_id = %d AND member_id = %d ORDER BY applied_at DESC LIMIT 1",
				$event_id,
				$member_id
			)
		);
	}

	/**
	 * Find and return applications with orphaned event_role_id values.
	 *
	 * @return array Array of application objects with orphaned event_role_id.
	 */
	public function get_orphaned_applications() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT a.* 
			FROM {$this->get_table()} a
			LEFT JOIN {$wpdb->prefix}remember_event_roles er ON a.event_role_id = er.event_role_id
			WHERE er.event_role_id IS NULL"
		);
	}

	/**
	 * Clean up orphaned applications (delete applications with invalid event_role_id).
	 *
	 * @return int Number of applications deleted.
	 */
	public function cleanup_orphaned_applications() {
		global $wpdb;
		$deleted = $wpdb->query(
			"DELETE a FROM {$this->get_table()} a
			LEFT JOIN {$wpdb->prefix}remember_event_roles er ON a.event_role_id = er.event_role_id
			WHERE er.event_role_id IS NULL"
		);
		return $deleted;
	}
}
