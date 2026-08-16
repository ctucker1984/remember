<?php
/**
 * Event agreements library — revisions, event pins, apply acceptances.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Agreements helpers.
 */
class Remember_Agreements {

	/**
	 * @return string
	 */
	public static function agreements_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_agreements';
	}

	/**
	 * @return string
	 */
	public static function revisions_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_agreement_revisions';
	}

	/**
	 * @return string
	 */
	public static function event_agreements_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_event_agreements';
	}

	/**
	 * @return string
	 */
	public static function acceptances_table() {
		global $wpdb;
		return $wpdb->prefix . 'remember_agreement_acceptances';
	}

	/**
	 * All agreements for admin list.
	 *
	 * @return array<int, object>
	 */
	public static function get_all_agreements() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT a.*,
				(SELECT MAX(r.revision_number) FROM ' . self::revisions_table() . ' r WHERE r.agreement_id = a.agreement_id) AS latest_revision,
				(SELECT r2.revision_id FROM ' . self::revisions_table() . ' r2 WHERE r2.agreement_id = a.agreement_id ORDER BY r2.revision_number DESC LIMIT 1) AS latest_revision_id
			FROM ' . self::agreements_table() . ' a
			ORDER BY a.sort_order ASC, a.title ASC'
		);
	}

	/**
	 * Active agreements (for event attach UI).
	 *
	 * @return array<int, object>
	 */
	public static function get_active_agreements() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT * FROM ' . self::agreements_table() . ' WHERE is_active = 1 ORDER BY sort_order ASC, title ASC'
		);
	}

	/**
	 * @param int $agreement_id Agreement ID.
	 * @return object|null
	 */
	public static function get_agreement( $agreement_id ) {
		global $wpdb;
		$agreement_id = absint( $agreement_id );
		if ( $agreement_id <= 0 ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::agreements_table() . ' WHERE agreement_id = %d',
				$agreement_id
			)
		);
	}

	/**
	 * @param int $revision_id Revision ID.
	 * @return object|null
	 */
	public static function get_revision( $revision_id ) {
		global $wpdb;
		$revision_id = absint( $revision_id );
		if ( $revision_id <= 0 ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.*, a.title AS agreement_title
				FROM ' . self::revisions_table() . ' r
				INNER JOIN ' . self::agreements_table() . ' a ON a.agreement_id = r.agreement_id
				WHERE r.revision_id = %d',
				$revision_id
			)
		);
	}

	/**
	 * Revisions for one agreement, newest first.
	 *
	 * @param int $agreement_id Agreement ID.
	 * @return array<int, object>
	 */
	public static function get_revisions_for_agreement( $agreement_id ) {
		global $wpdb;
		$agreement_id = absint( $agreement_id );
		if ( $agreement_id <= 0 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::revisions_table() . ' WHERE agreement_id = %d ORDER BY revision_number DESC',
				$agreement_id
			)
		);
	}

	/**
	 * Create agreement with first revision.
	 *
	 * @param string $title Title.
	 * @param string $body  Rich HTML.
	 * @param int    $sort  Sort order.
	 * @param int    $active Active flag.
	 * @return int|false Agreement ID.
	 */
	public static function create_agreement( $title, $body, $sort = 0, $active = 1 ) {
		global $wpdb;
		$title = sanitize_text_field( $title );
		$body  = wp_kses_post( $body );
		if ( '' === $title || '' === trim( wp_strip_all_tags( $body ) ) ) {
			return false;
		}
		$now = current_time( 'mysql' );
		$ok  = $wpdb->insert(
			self::agreements_table(),
			array(
				'title'      => $title,
				'is_active'  => $active ? 1 : 0,
				'sort_order' => absint( $sort ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		$agreement_id = (int) $wpdb->insert_id;
		$rev_id       = self::add_revision( $agreement_id, $body );
		if ( ! $rev_id ) {
			$wpdb->delete( self::agreements_table(), array( 'agreement_id' => $agreement_id ), array( '%d' ) );
			return false;
		}
		return $agreement_id;
	}

	/**
	 * Update agreement metadata (not body — body is always a new revision).
	 *
	 * @param int    $agreement_id ID.
	 * @param string $title        Title.
	 * @param int    $sort         Sort.
	 * @param int    $active       Active.
	 * @return bool
	 */
	public static function update_agreement_meta( $agreement_id, $title, $sort = 0, $active = 1 ) {
		global $wpdb;
		$agreement_id = absint( $agreement_id );
		$title        = sanitize_text_field( $title );
		if ( $agreement_id <= 0 || '' === $title ) {
			return false;
		}
		return false !== $wpdb->update(
			self::agreements_table(),
			array(
				'title'      => $title,
				'is_active'  => $active ? 1 : 0,
				'sort_order' => absint( $sort ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'agreement_id' => $agreement_id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Publish a new immutable revision.
	 *
	 * @param int    $agreement_id Agreement ID.
	 * @param string $body         Rich HTML.
	 * @return int|false Revision ID.
	 */
	public static function add_revision( $agreement_id, $body ) {
		global $wpdb;
		$agreement_id = absint( $agreement_id );
		$body         = wp_kses_post( $body );
		if ( $agreement_id <= 0 || '' === trim( wp_strip_all_tags( $body ) ) ) {
			return false;
		}
		$next = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(MAX(revision_number), 0) + 1 FROM ' . self::revisions_table() . ' WHERE agreement_id = %d',
				$agreement_id
			)
		);
		$ok = $wpdb->insert(
			self::revisions_table(),
			array(
				'agreement_id'    => $agreement_id,
				'revision_number' => $next,
				'body'            => $body,
				'created_by'      => get_current_user_id() ? get_current_user_id() : null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		$wpdb->update(
			self::agreements_table(),
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'agreement_id' => $agreement_id ),
			array( '%s' ),
			array( '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete agreement and its revisions if unused by events/acceptances.
	 *
	 * @param int $agreement_id Agreement ID.
	 * @return true|string True or error message.
	 */
	public static function delete_agreement( $agreement_id ) {
		global $wpdb;
		$agreement_id = absint( $agreement_id );
		if ( $agreement_id <= 0 ) {
			return __( 'Invalid agreement.', 'remember' );
		}
		$in_use = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::event_agreements_table() . ' WHERE agreement_id = %d',
				$agreement_id
			)
		);
		if ( $in_use > 0 ) {
			return __( 'This agreement is attached to one or more events. Detach it first.', 'remember' );
		}
		$rev_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT revision_id FROM ' . self::revisions_table() . ' WHERE agreement_id = %d',
				$agreement_id
			)
		);
		if ( ! empty( $rev_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $rev_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$accepted = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . self::acceptances_table() . " WHERE revision_id IN ($placeholders)",
					$rev_ids
				)
			);
			if ( $accepted > 0 ) {
				return __( 'This agreement has acceptance records. Deactivate it instead of deleting.', 'remember' );
			}
		}
		$wpdb->delete( self::revisions_table(), array( 'agreement_id' => $agreement_id ), array( '%d' ) );
		$wpdb->delete( self::agreements_table(), array( 'agreement_id' => $agreement_id ), array( '%d' ) );
		return true;
	}

	/**
	 * Pinned revisions for an event (apply + admin).
	 *
	 * @param int $event_id Event ID.
	 * @return array<int, object>
	 */
	public static function get_event_pinned_revisions( $event_id ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( $event_id <= 0 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ea.event_agreement_id, ea.event_id, ea.agreement_id, ea.revision_id, ea.sort_order,
					a.title AS agreement_title, r.revision_number, r.body, r.created_at AS revision_created_at
				FROM ' . self::event_agreements_table() . ' ea
				INNER JOIN ' . self::agreements_table() . ' a ON a.agreement_id = ea.agreement_id
				INNER JOIN ' . self::revisions_table() . ' r ON r.revision_id = ea.revision_id
				WHERE ea.event_id = %d
				ORDER BY ea.sort_order ASC, a.title ASC',
				$event_id
			)
		);
	}

	/**
	 * Map agreement_id => revision_id currently pinned on event.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int, int>
	 */
	public static function get_event_pin_map( $event_id ) {
		$map = array();
		foreach ( self::get_event_pinned_revisions( $event_id ) as $row ) {
			$map[ (int) $row->agreement_id ] = (int) $row->revision_id;
		}
		return $map;
	}

	/**
	 * Replace event agreement pins from POST-shaped map agreement_id => revision_id.
	 *
	 * @param int                $event_id Event ID.
	 * @param array<int, int>    $pins     agreement_id => revision_id.
	 * @return void
	 */
	public static function sync_event_pins( $event_id, $pins ) {
		global $wpdb;
		$event_id = absint( $event_id );
		if ( $event_id <= 0 ) {
			return;
		}
		$wpdb->delete( self::event_agreements_table(), array( 'event_id' => $event_id ), array( '%d' ) );
		if ( empty( $pins ) || ! is_array( $pins ) ) {
			return;
		}
		$sort = 0;
		foreach ( $pins as $agreement_id => $revision_id ) {
			$agreement_id = absint( $agreement_id );
			$revision_id  = absint( $revision_id );
			if ( $agreement_id <= 0 || $revision_id <= 0 ) {
				continue;
			}
			$rev = self::get_revision( $revision_id );
			if ( ! $rev || (int) $rev->agreement_id !== $agreement_id ) {
				continue;
			}
			$wpdb->insert(
				self::event_agreements_table(),
				array(
					'event_id'     => $event_id,
					'agreement_id' => $agreement_id,
					'revision_id'  => $revision_id,
					'sort_order'   => $sort,
				),
				array( '%d', '%d', '%d', '%d' )
			);
			++$sort;
		}
	}

	/**
	 * Parse pins from event edit POST.
	 *
	 * @return array<int, int>
	 */
	public static function pins_from_request() {
		$pins = array();
		if ( empty( $_POST['event_agreement_ids'] ) || ! is_array( $_POST['event_agreement_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $pins;
		}
		$ids  = array_map( 'absint', wp_unslash( $_POST['event_agreement_ids'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$revs = isset( $_POST['event_agreement_revisions'] ) && is_array( $_POST['event_agreement_revisions'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['event_agreement_revisions'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		foreach ( $ids as $agreement_id ) {
			$agreement_id = absint( $agreement_id );
			if ( $agreement_id <= 0 ) {
				continue;
			}
			$revision_id = isset( $revs[ $agreement_id ] ) ? absint( $revs[ $agreement_id ] ) : 0;
			if ( $revision_id > 0 ) {
				$pins[ $agreement_id ] = $revision_id;
			}
		}
		return $pins;
	}

	/**
	 * Validate apply-form acknowledgments for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return string|null Error message or null if OK.
	 */
	public static function validate_apply_acceptances( $event_id ) {
		$pinned = self::get_event_pinned_revisions( $event_id );
		if ( empty( $pinned ) ) {
			return null;
		}
		$acks = isset( $_POST['remember_agreement'] ) && is_array( $_POST['remember_agreement'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['remember_agreement'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		foreach ( $pinned as $row ) {
			$rid = (int) $row->revision_id;
			$ack = isset( $acks[ $rid ] ) && is_array( $acks[ $rid ] ) ? $acks[ $rid ] : array();
			$ok  = ! empty( $ack['agree'] );
			$name = isset( $ack['legal_name'] ) ? trim( sanitize_text_field( (string) $ack['legal_name'] ) ) : '';
			if ( ! $ok || '' === $name ) {
				return sprintf(
					/* translators: %s: agreement title */
					__( 'Please acknowledge “%s” and type your legal name.', 'remember' ),
					$row->agreement_title
				);
			}
		}
		return null;
	}

	/**
	 * Persist acceptances after application create.
	 *
	 * @param int $application_id Application ID.
	 * @param int $event_id       Event ID.
	 * @return void
	 */
	public static function save_apply_acceptances( $application_id, $event_id ) {
		global $wpdb;
		$application_id = absint( $application_id );
		$event_id       = absint( $event_id );
		if ( $application_id <= 0 || $event_id <= 0 ) {
			return;
		}
		$pinned = self::get_event_pinned_revisions( $event_id );
		if ( empty( $pinned ) ) {
			return;
		}
		$acks = isset( $_POST['remember_agreement'] ) && is_array( $_POST['remember_agreement'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['remember_agreement'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '';
		$now = current_time( 'mysql' );

		foreach ( $pinned as $row ) {
			$rid  = (int) $row->revision_id;
			$ack  = isset( $acks[ $rid ] ) && is_array( $acks[ $rid ] ) ? $acks[ $rid ] : array();
			$name = isset( $ack['legal_name'] ) ? trim( sanitize_text_field( (string) $ack['legal_name'] ) ) : '';
			if ( '' === $name || empty( $ack['agree'] ) ) {
				continue;
			}
			$wpdb->replace(
				self::acceptances_table(),
				array(
					'application_id'   => $application_id,
					'revision_id'      => $rid,
					'typed_legal_name' => $name,
					'ip_address'       => $ip,
					'user_agent'       => $ua,
					'accepted_at'      => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Acceptances for an application (admin detail).
	 *
	 * @param int $application_id Application ID.
	 * @return array<int, object>
	 */
	public static function get_acceptances_for_application( $application_id ) {
		global $wpdb;
		$application_id = absint( $application_id );
		if ( $application_id <= 0 ) {
			return array();
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT acc.*, a.title AS agreement_title, r.revision_number, r.body
				FROM ' . self::acceptances_table() . ' acc
				INNER JOIN ' . self::revisions_table() . ' r ON r.revision_id = acc.revision_id
				INNER JOIN ' . self::agreements_table() . ' a ON a.agreement_id = r.agreement_id
				WHERE acc.application_id = %d
				ORDER BY a.title ASC',
				$application_id
			)
		);
	}

	/**
	 * Render apply-form agreement blocks for an event.
	 *
	 * @param int $event_id Event ID.
	 * @return string HTML.
	 */
	public static function render_apply_html( $event_id ) {
		$pinned = self::get_event_pinned_revisions( $event_id );
		if ( empty( $pinned ) ) {
			return '<p class="remember-form-help">' . esc_html__( 'No agreements are required for this event.', 'remember' ) . '</p>';
		}
		ob_start();
		echo '<div class="remember-agreements-apply">';
		foreach ( $pinned as $row ) {
			$rid = (int) $row->revision_id;
			echo '<div class="remember-agreement-block">';
			echo '<h4 class="remember-agreement-title">' . esc_html( $row->agreement_title );
			echo ' <span class="remember-agreement-revision">(' . esc_html(
				sprintf(
					/* translators: %d: revision number */
					__( 'Revision %d', 'remember' ),
					(int) $row->revision_number
				)
			) . ')</span></h4>';
			echo '<div class="remember-agreement-body remember-richtext">' . wp_kses_post( wpautop( $row->body ) ) . '</div>';
			echo '<label class="remember-checkbox-label">';
			echo '<input type="checkbox" name="remember_agreement[' . esc_attr( (string) $rid ) . '][agree]" value="1" required> ';
			echo '<span>' . esc_html__( 'I have read and agree to this', 'remember' ) . '</span>';
			echo '</label>';
			echo '<p class="remember-form-group" style="margin-top:0.75em;">';
			echo '<label class="remember-form-label" for="remember_agreement_name_' . esc_attr( (string) $rid ) . '">';
			echo esc_html__( 'Type your legal name', 'remember' ) . ' <span class="remember-required">*</span>';
			echo '</label>';
			echo '<input type="text" class="remember-form-control" id="remember_agreement_name_' . esc_attr( (string) $rid ) . '" name="remember_agreement[' . esc_attr( (string) $rid ) . '][legal_name]" required autocomplete="name">';
			echo '</p>';
			echo '</div>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
