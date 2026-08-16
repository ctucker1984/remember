<?php
/**
 * Profile custom question model.
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
 * Admin-defined profile question (custom field).
 */
class Remember_Profile_Question extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'profile_questions';

	/**
	 * Primary key.
	 *
	 * @var string
	 */
	protected $primary_key = 'question_id';

	/**
	 * Active questions ordered for forms.
	 *
	 * @return array<int, object>
	 */
	public function get_active() {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->get_table()}
			WHERE is_active = 1
			ORDER BY sort_order ASC, question_id ASC"
		);
	}

	/**
	 * All questions ordered for admin list / export headers.
	 *
	 * @return array<int, object>
	 */
	public function get_all_ordered() {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->get_table()}
			ORDER BY sort_order ASC, question_id ASC"
		);
	}

	/**
	 * Find by export field key.
	 *
	 * @param string $field_key Export key.
	 * @return object|null
	 */
	public function get_by_field_key( $field_key ) {
		$field_key = sanitize_key( $field_key );
		if ( '' === $field_key ) {
			return null;
		}
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE field_key = %s",
				$field_key
			)
		);
	}

	/**
	 * Whether field_key is already used (optionally excluding a question).
	 *
	 * @param string   $field_key   Key.
	 * @param int|null $exclude_id  Question ID to exclude.
	 * @return bool
	 */
	public function field_key_exists( $field_key, $exclude_id = null ) {
		$field_key = sanitize_key( $field_key );
		if ( '' === $field_key ) {
			return false;
		}
		$exclude_id = absint( $exclude_id );
		if ( $exclude_id > 0 ) {
			$row = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT question_id FROM {$this->get_table()} WHERE field_key = %s AND question_id != %d LIMIT 1",
					$field_key,
					$exclude_id
				)
			);
		} else {
			$row = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT question_id FROM {$this->get_table()} WHERE field_key = %s LIMIT 1",
					$field_key
				)
			);
		}
		return ! empty( $row );
	}
}
