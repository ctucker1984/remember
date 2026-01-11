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
}
