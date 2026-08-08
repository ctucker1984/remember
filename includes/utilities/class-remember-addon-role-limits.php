<?php
/**
 * Per event-role max quantity for event add-ons.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * event_merchandise × event_roles → max_qty (0 = hide).
 */
class Remember_Addon_Role_Limits {

	/**
	 * Table name with WP prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_event_merchandise_role_limits';
	}

	/**
	 * Max qty for a merchandise line and event role.
	 *
	 * Returns 0 when hidden. When no role-limit row exists, falls back to
	 * event_merchandise.max_quantity (NULL → treated as 1 for safety).
	 *
	 * @param int $merchandise_id Merchandise ID.
	 * @param int $event_role_id  Event role ID.
	 * @return int
	 */
	public static function get_max_qty( $merchandise_id, $event_role_id ) {
		global $wpdb;
		$merchandise_id = absint( $merchandise_id );
		$event_role_id  = absint( $event_role_id );
		if ( $merchandise_id < 1 || $event_role_id < 1 ) {
			return 0;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT max_qty FROM {$table} WHERE merchandise_id = %d AND event_role_id = %d LIMIT 1",
				$merchandise_id,
				$event_role_id
			)
		);
		if ( $row ) {
			return max( 0, (int) $row->max_qty );
		}

		$merch_table = $wpdb->prefix . 'remember_event_merchandise';
		$max         = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT max_quantity FROM {$merch_table} WHERE merchandise_id = %d LIMIT 1",
				$merchandise_id
			)
		);
		if ( null === $max || '' === $max ) {
			return 1;
		}
		return max( 0, absint( $max ) );
	}

	/**
	 * Limits keyed by event_role_id for one merchandise row.
	 *
	 * @param int $merchandise_id Merchandise ID.
	 * @return array<int,int> event_role_id => max_qty
	 */
	public static function get_for_merchandise( $merchandise_id ) {
		global $wpdb;
		$merchandise_id = absint( $merchandise_id );
		if ( $merchandise_id < 1 ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_role_id, max_qty FROM {$table} WHERE merchandise_id = %d",
				$merchandise_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ absint( $row->event_role_id ) ] = max( 0, (int) $row->max_qty );
		}
		return $out;
	}

	/**
	 * Limits keyed by catalog role_id for admin forms.
	 *
	 * @param int $merchandise_id Merchandise ID.
	 * @param int $event_id       Event ID.
	 * @return array<int,int> role_id => max_qty
	 */
	public static function get_for_merchandise_by_role_id( $merchandise_id, $event_id ) {
		global $wpdb;
		$merchandise_id = absint( $merchandise_id );
		$event_id       = absint( $event_id );
		if ( $merchandise_id < 1 || $event_id < 1 ) {
			return array();
		}
		$limits_table = self::table();
		$roles_table  = $wpdb->prefix . 'remember_event_roles';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT er.role_id, l.max_qty
				FROM {$limits_table} l
				INNER JOIN {$roles_table} er ON er.event_role_id = l.event_role_id
				WHERE l.merchandise_id = %d AND er.event_id = %d",
				$merchandise_id,
				$event_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ absint( $row->role_id ) ] = max( 0, (int) $row->max_qty );
		}
		return $out;
	}

	/**
	 * Available add-ons for an event role (max_qty > 0), with role-aware max_quantity.
	 *
	 * @param int $event_id      Event ID.
	 * @param int $event_role_id Event role ID.
	 * @return object[]
	 */
	public static function get_available_addons_for_role( $event_id, $event_role_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-merchandise.php';
		$merchandise_model = new Remember_Merchandise();
		$addons            = $merchandise_model->get_by_event( absint( $event_id ) );
		$event_role_id     = absint( $event_role_id );
		$filtered          = array();

		foreach ( (array) $addons as $addon ) {
			$max = self::get_max_qty( $addon->merchandise_id, $event_role_id );
			if ( $max < 1 ) {
				continue;
			}
			$clone               = clone $addon;
			$clone->max_quantity = $max;
			$filtered[]          = $clone;
		}

		return $filtered;
	}

	/**
	 * Replace role limits for a merchandise row using catalog role_id keys.
	 *
	 * @param int                $event_id       Event ID.
	 * @param int                $merchandise_id Merchandise ID.
	 * @param array<int,int|string> $role_limits  role_id => max_qty.
	 * @return void
	 */
	public static function sync_for_merchandise( $event_id, $merchandise_id, array $role_limits ) {
		global $wpdb;
		$event_id       = absint( $event_id );
		$merchandise_id = absint( $merchandise_id );
		if ( $event_id < 1 || $merchandise_id < 1 ) {
			return;
		}

		$roles_table = $wpdb->prefix . 'remember_event_roles';
		$event_roles = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_role_id, role_id FROM {$roles_table} WHERE event_id = %d",
				$event_id
			)
		);
		$role_id_to_event_role = array();
		foreach ( (array) $event_roles as $er ) {
			$role_id_to_event_role[ absint( $er->role_id ) ] = absint( $er->event_role_id );
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'merchandise_id' => $merchandise_id ), array( '%d' ) );

		foreach ( $role_limits as $role_id => $max_qty ) {
			$role_id = absint( $role_id );
			if ( $role_id < 1 || ! isset( $role_id_to_event_role[ $role_id ] ) ) {
				continue;
			}
			$max_qty = max( 0, absint( $max_qty ) );
			$wpdb->insert(
				$table,
				array(
					'merchandise_id' => $merchandise_id,
					'event_role_id'  => $role_id_to_event_role[ $role_id ],
					'max_qty'        => $max_qty,
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Delete all limit rows for a merchandise ID.
	 *
	 * @param int $merchandise_id Merchandise ID.
	 * @return void
	 */
	public static function delete_for_merchandise( $merchandise_id ) {
		global $wpdb;
		$merchandise_id = absint( $merchandise_id );
		if ( $merchandise_id < 1 ) {
			return;
		}
		$wpdb->delete( self::table(), array( 'merchandise_id' => $merchandise_id ), array( '%d' ) );
	}

	/**
	 * Clamp a requested quantity for a role (0 if hidden).
	 *
	 * @param int $merchandise_id Merchandise ID.
	 * @param int $event_role_id  Event role ID.
	 * @param int $quantity       Requested qty.
	 * @return int Clamped qty, or 0 if not allowed.
	 */
	public static function clamp_quantity( $merchandise_id, $event_role_id, $quantity ) {
		$max = self::get_max_qty( $merchandise_id, $event_role_id );
		if ( $max < 1 ) {
			return 0;
		}
		$quantity = absint( $quantity );
		if ( $quantity < 1 ) {
			$quantity = 1;
		}
		return min( $quantity, $max );
	}
}
