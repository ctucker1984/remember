<?php
/**
 * Per-user saved report definitions.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * Saved reports table access.
 */
class Remember_Saved_Report {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'remember_saved_reports';
	}

	/**
	 * Reports owned by a user.
	 *
	 * @param int $owner_id User ID.
	 * @return object[]
	 */
	public static function list_for_owner( $owner_id ) {
		global $wpdb;
		$owner_id = absint( $owner_id );
		$table    = self::table_name();
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT report_id, owner_id, name, subject, definition, created_at, updated_at
				FROM {$table}
				WHERE owner_id = %d
				ORDER BY name ASC, report_id ASC",
				$owner_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Load one saved report.
	 *
	 * @param int $report_id Report ID.
	 * @return object|null
	 */
	public static function get( $report_id ) {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE report_id = %d",
				absint( $report_id )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Load one report if owned by the user.
	 *
	 * @param int $report_id Report ID.
	 * @param int $owner_id  User ID.
	 * @return object|null
	 */
	public static function get_owned( $report_id, $owner_id ) {
		global $wpdb;
		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE report_id = %d AND owner_id = %d",
				absint( $report_id ),
				absint( $owner_id )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Insert or update.
	 *
	 * @param int    $owner_id   User.
	 * @param string $name       Name.
	 * @param string $subject    Subject.
	 * @param array  $definition Definition.
	 * @param int    $report_id  Existing ID or 0.
	 * @return int|\WP_Error
	 */
	public static function save( $owner_id, $name, $subject, $definition, $report_id = 0 ) {
		global $wpdb;
		$owner_id  = absint( $owner_id );
		$report_id = absint( $report_id );
		$name      = sanitize_text_field( $name );
		$subject   = sanitize_key( $subject );
		if ( '' === $name ) {
			Remember_Logger::debug( 'Saved report refused: no name', array( 'owner_id' => $owner_id, 'report_id' => $report_id ) );
			return new WP_Error( 'name', __( 'Name this report.', 'remember' ) );
		}
		$json = wp_json_encode( $definition );
		if ( ! is_string( $json ) ) {
			Remember_Logger::error(
				'Saved report not saved: its definition could not be encoded',
				array(
					'owner_id'  => $owner_id,
					'report_id' => $report_id,
					'subject'   => $subject,
				)
			);
			return new WP_Error( 'definition', __( 'Could not save that report.', 'remember' ) );
		}
		$now = current_time( 'mysql' );
		if ( $report_id > 0 ) {
			$existing = self::get_owned( $report_id, $owner_id );
			if ( ! $existing ) {
				Remember_Logger::warning( 'Saved report not updated: not found for this owner', array( 'owner_id' => $owner_id, 'report_id' => $report_id ) );
				return new WP_Error( 'missing', __( 'That report was not found.', 'remember' ) );
			}
			$updated = $wpdb->update(
				self::table_name(),
				array(
					'name'       => $name,
					'subject'    => $subject,
					'definition' => $json,
					'updated_at' => $now,
				),
				array(
					'report_id' => $report_id,
					'owner_id'  => $owner_id,
				),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d', '%d' )
			);
			if ( false === $updated ) {
				Remember_Logger::error(
					'Saved report could not be updated',
					array(
						'owner_id'  => $owner_id,
						'report_id' => $report_id,
						'error'     => $wpdb->last_error,
					)
				);
				return new WP_Error( 'update', __( 'Could not save that report.', 'remember' ) );
			}
			Remember_Logger::info(
				'Saved report updated',
				array(
					'owner_id'  => $owner_id,
					'report_id' => $report_id,
					'subject'   => $subject,
				)
			);
			return $report_id;
		}
		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'owner_id'    => $owner_id,
				'name'        => $name,
				'subject'     => $subject,
				'definition'  => $json,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$id = false === $inserted ? 0 : (int) $wpdb->insert_id;
		if ( $id < 1 ) {
			Remember_Logger::error(
				'Saved report could not be created',
				array(
					'owner_id' => $owner_id,
					'subject'  => $subject,
					'error'    => $wpdb->last_error,
				)
			);
			return new WP_Error( 'insert', __( 'Could not save that report.', 'remember' ) );
		}
		Remember_Logger::info(
			'Saved report created',
			array(
				'owner_id'  => $owner_id,
				'report_id' => $id,
				'subject'   => $subject,
			)
		);
		return $id;
	}

	/**
	 * Copy an owned report into another user's library.
	 *
	 * @param int $report_id     Source report.
	 * @param int $from_owner_id Current owner.
	 * @param int $to_owner_id   Recipient.
	 * @return int|\WP_Error New report ID.
	 */
	public static function copy_to_owner( $report_id, $from_owner_id, $to_owner_id ) {
		$row = self::get_owned( $report_id, $from_owner_id );
		if ( ! $row ) {
			Remember_Logger::warning( 'Saved report not copied: not found for this owner', array( 'owner_id' => absint( $from_owner_id ), 'report_id' => absint( $report_id ) ) );
			return new WP_Error( 'missing', __( 'That report was not found.', 'remember' ) );
		}
		$to_owner_id = absint( $to_owner_id );
		if ( $to_owner_id < 1 || $to_owner_id === absint( $from_owner_id ) ) {
			Remember_Logger::debug( 'Saved report not copied: no other recipient chosen', array( 'report_id' => absint( $report_id ), 'to_owner_id' => $to_owner_id ) );
			return new WP_Error( 'recipient', __( 'Choose someone else to copy this report to.', 'remember' ) );
		}
		$definition = json_decode( (string) $row->definition, true );
		if ( ! is_array( $definition ) ) {
			$definition = array();
		}
		unset( $definition['event_id'] );
		return self::save( $to_owner_id, $row->name, $row->subject, $definition, 0 );
	}

	/**
	 * Delete owned report.
	 *
	 * @param int $report_id Report.
	 * @param int $owner_id  User.
	 * @return bool
	 */
	public static function delete_owned( $report_id, $owner_id ) {
		global $wpdb;
		$context = array(
			'report_id' => absint( $report_id ),
			'owner_id'  => absint( $owner_id ),
		);
		$deleted = $wpdb->delete(
			self::table_name(),
			$context,
			array( '%d', '%d' )
		);
		if ( false === $deleted ) {
			$context['error'] = $wpdb->last_error;
			Remember_Logger::error( 'Saved report could not be deleted', $context );
			return false;
		}
		if ( $deleted < 1 ) {
			Remember_Logger::warning( 'Saved report not deleted: not found for this owner', $context );
			return false;
		}
		Remember_Logger::info( 'Saved report deleted', $context );
		return true;
	}
}
