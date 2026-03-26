<?php
/**
 * Member model class
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
 * Member model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Member extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'members';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'member_id';

	/**
	 * Create a new member.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $status  Member status.
	 * @return int|false Member ID or false on error.
	 */
	public function create( $user_id, $status = 'pending_vetting' ) {
		$data = array(
			'member_id' => $user_id,
			'status'    => $status,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		return $this->insert( $data );
	}

	/**
	 * Update member status.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $status    New status.
	 * @return int|false
	 */
	public function update_status( $member_id, $status ) {
		return $this->update(
			$member_id,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Update member photo.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $photo_url Photo URL.
	 * @return int|false
	 */
	public function update_photo( $member_id, $photo_url ) {
		return $this->update(
			$member_id,
			array(
				'photo_url'  => $photo_url,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get member by WordPress user ID.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public function get_by_user_id( $user_id ) {
		return $this->get( $user_id );
	}

	/**
	 * Whether the member has vetted status (may apply to events and use member-only features).
	 *
	 * @param object|null $member Row from get().
	 * @return bool
	 */
	public static function is_vetted_member( $member ) {
		$is_vetted = $member && isset( $member->status ) && 'vetted' === $member->status;
		return (bool) apply_filters( 'remember_member_is_vetted', $is_vetted, $member );
	}

	/**
	 * Get members by status.
	 *
	 * @param string $status Member status.
	 * @param array  $args   Query arguments.
	 * @return array
	 */
	public function get_by_status( $status, $args = array() ) {
		$args['where'] = array( 'status' => $status );
		return $this->get_all_with_where( $args );
	}

	/**
	 * Get members by role ID.
	 *
	 * @param int   $role_id Role ID.
	 * @param array $args    Query arguments.
	 * @return array
	 */
	public function get_by_role( $role_id, $args = array() ) {
		global $wpdb;
		$role_id = absint( $role_id );
		
		$query = "SELECT m.* FROM {$this->get_table()} m 
			INNER JOIN {$wpdb->prefix}remember_member_roles mr ON m.member_id = mr.member_id 
			WHERE mr.role_id = %d 
			ORDER BY m.member_id ASC";
		
		return $wpdb->get_results( $wpdb->prepare( $query, $role_id ) );
	}

	/**
	 * Get attendees (members with accepted applications) for events where the current user is working.
	 * A user is "working" on an event if they have an accepted application for that event.
	 *
	 * @param int $guard_member_id The member ID of the guard/user viewing attendees.
	 * @return array Array of member objects.
	 */
	public function get_attendees_for_guard( $guard_member_id ) {
		global $wpdb;
		
		// Get all events where this guard/user has an accepted application (they're working on these events)
		$guard_event_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT event_id 
			FROM {$wpdb->prefix}remember_event_applications 
			WHERE member_id = %d AND status = 'accepted'",
			$guard_member_id
		) );
		
		if ( empty( $guard_event_ids ) ) {
			return array();
		}
		
		// Get all member IDs who have accepted applications for those events (these are the attendees)
		$attendee_member_ids = $wpdb->get_col(
			"SELECT DISTINCT member_id 
			FROM {$wpdb->prefix}remember_event_applications 
			WHERE event_id IN (" . implode( ',', array_map( 'absint', $guard_event_ids ) ) . ") 
			AND status = 'accepted' 
			AND member_id != " . absint( $guard_member_id )
		);
		
		if ( empty( $attendee_member_ids ) ) {
			return array();
		}
		
		// Get the member records
		$placeholders = implode( ',', array_fill( 0, count( $attendee_member_ids ), '%d' ) );
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table()} 
			WHERE member_id IN ($placeholders) 
			ORDER BY member_id ASC",
			$attendee_member_ids
		);
		
		return $wpdb->get_results( $query );
	}

	/**
	 * Get event roles assigned to this member.
	 *
	 * @param int $member_id Member ID.
	 * @return array Array of role IDs.
	 */
	public function get_member_event_role_ids( $member_id ) {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT mr.role_id 
				FROM {$wpdb->prefix}remember_member_roles mr 
				INNER JOIN {$wpdb->prefix}remember_roles r ON mr.role_id = r.role_id 
				WHERE mr.member_id = %d 
				AND r.role_type = 'event'",
				$member_id
			)
		);
	}

	/**
	 * Get all valid members (those with existing WordPress users).
	 * Filters out orphaned records where the WordPress user no longer exists.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_valid( $args = array() ) {
		global $wpdb;
		
		$defaults = array(
			'limit'   => -1,
			'offset'  => 0,
			'orderby' => $this->primary_key,
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT m.* FROM {$this->get_table()} m 
			INNER JOIN {$wpdb->prefix}users u ON m.member_id = u.ID";

		if ( ! empty( $args['orderby'] ) ) {
			$query .= " ORDER BY m.{$args['orderby']} {$args['order']}";
		}

		if ( $args['limit'] > 0 ) {
			$query .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get all records with WHERE conditions.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	private function get_all_with_where( $args = array() ) {
		$defaults = array(
			'where'   => array(),
			'limit'   => -1,
			'offset'  => 0,
			'orderby' => $this->primary_key,
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT * FROM {$this->get_table()}";

		if ( ! empty( $args['where'] ) ) {
			$conditions = array();
			foreach ( $args['where'] as $column => $value ) {
				if ( is_numeric( $value ) ) {
					$conditions[] = $this->wpdb->prepare( "{$column} = %d", $value );
				} else {
					$conditions[] = $this->wpdb->prepare( "{$column} = %s", $value );
				}
			}
			$query .= ' WHERE ' . implode( ' AND ', $conditions );
		}

		if ( ! empty( $args['orderby'] ) ) {
			$query .= " ORDER BY {$args['orderby']} {$args['order']}";
		}

		if ( $args['limit'] > 0 ) {
			$query .= $this->wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
		}

		return $this->wpdb->get_results( $query );
	}
}
