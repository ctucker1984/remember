<?php
/**
 * Merchandise model class
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
 * Merchandise model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Merchandise extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'event_merchandise';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'merchandise_id';

	/**
	 * Create a new merchandise item.
	 *
	 * @param array $data Merchandise data.
	 * @return int|false Merchandise ID or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}

	/**
	 * Update a merchandise item.
	 *
	 * @param int   $merchandise_id Merchandise ID.
	 * @param array $data           Merchandise data.
	 * @return int|false
	 */
	public function update( $merchandise_id, $data ) {
		$data['updated_at'] = current_time( 'mysql' );
		return parent::update( $merchandise_id, $data );
	}

	/**
	 * Get merchandise by event.
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public function get_by_event( $event_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_id = %d AND is_available = 1 ORDER BY merchandise_name ASC",
				$event_id
			)
		);
	}

	/**
	 * Get all merchandise for an event (including unavailable).
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public function get_all_by_event( $event_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE event_id = %d ORDER BY merchandise_name ASC",
				$event_id
			)
		);
	}
}
