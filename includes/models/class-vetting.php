<?php
/**
 * Vetting model class
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
 * Vetting model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Vetting extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'vetting';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'vetting_id';

	/**
	 * Create a new vetting record.
	 *
	 * @param int    $member_id         Member ID.
	 * @param int    $primary_vetter_id Primary vetter user ID.
	 * @param string $status            Vetting status.
	 * @return int|false Vetting ID or false on error.
	 */
	public function create( $member_id, $primary_vetter_id, $status = 'pending' ) {
		$data = array(
			'member_id'         => $member_id,
			'primary_vetter_id' => $primary_vetter_id,
			'status'            => $status,
			'decision'          => 'pending',
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);
		return $this->insert( $data );
	}

	/**
	 * Update vetting status.
	 *
	 * @param int    $vetting_id Vetting ID.
	 * @param string $status     New status.
	 * @return int|false
	 */
	public function update_status( $vetting_id, $status ) {
		return $this->update(
			$vetting_id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Complete vetting with decision.
	 *
	 * @param int    $vetting_id Vetting ID.
	 * @param string $decision   Decision (accepted, rejected).
	 * @return int|false
	 */
	public function complete( $vetting_id, $decision ) {
		return $this->update(
			$vetting_id,
			array(
				'status'        => 'completed',
				'decision'      => $decision,
				'completed_at'  => current_time( 'mysql' ),
				'decision_date' => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Schedule vetting.
	 *
	 * @param int      $vetting_id  Vetting ID.
	 * @param datetime $scheduled_at Scheduled date/time.
	 * @return int|false
	 */
	public function schedule( $vetting_id, $scheduled_at ) {
		return $this->update(
			$vetting_id,
			array(
				'status'        => 'scheduled',
				'scheduled_at'  => $scheduled_at,
				'updated_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get vetting by member.
	 *
	 * @param int $member_id Member ID.
	 * @return object|null
	 */
	public function get_by_member( $member_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE member_id = %d",
				$member_id
			)
		);
	}

	/**
	 * Get vetting records by status.
	 *
	 * @param string $status Vetting status.
	 * @return array
	 */
	public function get_by_status( $status ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE status = %s ORDER BY created_at DESC",
				$status
			)
		);
	}

	/**
	 * Get vetting records assigned to a vetter.
	 *
	 * @param int $vetter_id Vetter user ID.
	 * @return array
	 */
	public function get_by_vetter( $vetter_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE primary_vetter_id = %d ORDER BY created_at DESC",
				$vetter_id
			)
		);
	}

	/**
	 * Get pending vetting records.
	 *
	 * @return array
	 */
	public function get_pending() {
		return $this->get_by_status( 'pending' );
	}
}
