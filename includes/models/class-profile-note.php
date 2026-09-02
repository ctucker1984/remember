<?php
/**
 * Profile-level notes (not vetting case notes).
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
 * Notes attached to a member profile.
 */
class Remember_Profile_Note extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'profile_notes';

	/**
	 * Primary key.
	 *
	 * @var string
	 */
	protected $primary_key = 'note_id';

	/**
	 * Notes for a member, newest first.
	 *
	 * @param int  $member_id         Profile owner (WP user ID).
	 * @param bool $include_private   Include private-to-admin notes.
	 * @return object[]
	 */
	public function get_for_member( $member_id, $include_private = true ) {
		$member_id = absint( $member_id );
		if ( $member_id <= 0 ) {
			return array();
		}

		$sql = "SELECT * FROM {$this->get_table()} WHERE member_id = %d";
		if ( ! $include_private ) {
			$sql .= ' AND is_admin_only = 0';
		}
		$sql .= ' ORDER BY created_at DESC, note_id DESC';

		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $member_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is plugin-controlled; values are prepared.
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Append a note to a profile.
	 *
	 * @param int    $member_id    Profile owner (WP user ID).
	 * @param int    $author_id    Staff user ID.
	 * @param string $note_content Note body.
	 * @param bool   $is_admin_only Private to admin when true.
	 * @return int|false Note ID or false.
	 */
	public function add( $member_id, $author_id, $note_content, $is_admin_only = false ) {
		$member_id    = absint( $member_id );
		$author_id    = absint( $author_id );
		$note_content = sanitize_textarea_field( (string) $note_content );
		if ( $member_id <= 0 || $author_id <= 0 || '' === $note_content ) {
			return false;
		}

		$ok = $this->wpdb->insert(
			$this->get_table(),
			array(
				'member_id'     => $member_id,
				'author_id'     => $author_id,
				'note_content'  => $note_content,
				'is_admin_only' => $is_admin_only ? 1 : 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		return $ok ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Point notes at the surviving member after a profile merge.
	 *
	 * @param int $from_member_id Locked-out profile.
	 * @param int $to_member_id   Surviving profile.
	 * @return int Rows updated.
	 */
	public function reassign_member( $from_member_id, $to_member_id ) {
		$from_member_id = absint( $from_member_id );
		$to_member_id   = absint( $to_member_id );
		if ( $from_member_id <= 0 || $to_member_id <= 0 || $from_member_id === $to_member_id ) {
			return 0;
		}

		$updated = $this->wpdb->update(
			$this->get_table(),
			array( 'member_id' => $to_member_id ),
			array( 'member_id' => $from_member_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false === $updated ? 0 : (int) $updated;
	}
}
