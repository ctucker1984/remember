<?php
/**
 * Cap-aware field catalog for staff reports.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Subjects, fields, and join keys the query compiler may use.
 */
class Remember_Report_Catalog {

	/**
	 * Subjects the current user may open.
	 *
	 * @return array<string,string> subject => label
	 */
	public static function subjects_for_current_user() {
		$all = array(
			'members'      => array(
				'label' => __( 'Members', 'remember' ),
				'cap'   => array( 'remember_read_members', 'remember_read_attendees' ),
			),
			'applications' => array(
				'label' => __( 'Applications', 'remember' ),
				'cap'   => array( 'remember_read_applications' ),
			),
			'payments'     => array(
				'label' => __( 'Payments', 'remember' ),
				'cap'   => array( 'remember_read_billing' ),
			),
			'vetting'      => array(
				'label' => __( 'Vetting', 'remember' ),
				'cap'   => array( 'remember_read_vetting' ),
			),
			'events'       => array(
				'label' => __( 'Events', 'remember' ),
				'cap'   => array( 'remember_read_events' ),
			),
		);
		$out = array();
		foreach ( $all as $id => $meta ) {
			foreach ( $meta['cap'] as $cap ) {
				if ( current_user_can( $cap ) ) {
					$out[ $id ] = $meta['label'];
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Fields visible to the current user for a subject.
	 *
	 * @param string $subject Subject id.
	 * @return array<string,array<string,mixed>> field_id => field
	 */
	public static function fields_for_current_user( $subject ) {
		$subjects = self::subjects_for_current_user();
		if ( ! isset( $subjects[ $subject ] ) ) {
			return array();
		}
		$fields = self::raw_fields( $subject );
		$out    = array();
		foreach ( $fields as $id => $field ) {
			if ( ! empty( $field['sensitive'] ) && 'emergency' === $field['sensitive'] && ! current_user_can( 'remember_access_emergency_contact' ) ) {
				continue;
			}
			if ( ! empty( $field['sensitive'] ) && 'health' === $field['sensitive'] && ! current_user_can( 'remember_access_health' ) ) {
				continue;
			}
			$out[ $id ] = $field;
		}
		if ( in_array( $subject, array( 'members', 'applications' ), true ) ) {
			$out = array_merge( $out, self::custom_question_fields( $subject ) );
		}
		return $out;
	}

	/**
	 * Catalog payload for the builder UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function payload_for_current_user() {
		$subjects = self::subjects_for_current_user();
		$out      = array(
			'subjects' => array(),
		);
		foreach ( $subjects as $id => $label ) {
			$fields = array();
			foreach ( self::fields_for_current_user( $id ) as $fid => $field ) {
				$fields[] = array(
					'id'      => $fid,
					'label'   => $field['label'],
					'group'   => $field['group'],
					'type'    => $field['type'],
					'options' => isset( $field['options'] ) ? $field['options'] : array(),
					'measure' => ! empty( $field['measure'] ),
				);
			}
			$out['subjects'][] = array(
				'id'      => $id,
				'label'   => $label,
				'fields'  => $fields,
				'default' => self::default_columns( $id ),
			);
		}
		$out['operators'] = array(
			array( 'id' => 'eq', 'label' => __( 'is', 'remember' ) ),
			array( 'id' => 'neq', 'label' => __( 'is not', 'remember' ) ),
			array( 'id' => 'contains', 'label' => __( 'contains', 'remember' ) ),
			array( 'id' => 'in', 'label' => __( 'is one of', 'remember' ) ),
			array( 'id' => 'empty', 'label' => __( 'is empty', 'remember' ) ),
			array( 'id' => 'not_empty', 'label' => __( 'is not empty', 'remember' ) ),
			array( 'id' => 'gt', 'label' => __( 'greater than', 'remember' ) ),
			array( 'id' => 'gte', 'label' => __( 'at least', 'remember' ) ),
			array( 'id' => 'lt', 'label' => __( 'less than', 'remember' ) ),
			array( 'id' => 'lte', 'label' => __( 'at most', 'remember' ) ),
			array( 'id' => 'between', 'label' => __( 'between', 'remember' ) ),
		);
		$out['aggregations'] = array(
			array( 'id' => 'count', 'label' => __( 'Count', 'remember' ) ),
			array( 'id' => 'count_distinct', 'label' => __( 'Count distinct', 'remember' ) ),
			array( 'id' => 'sum', 'label' => __( 'Sum', 'remember' ) ),
			array( 'id' => 'min', 'label' => __( 'Min', 'remember' ) ),
			array( 'id' => 'max', 'label' => __( 'Max', 'remember' ) ),
		);
		return $out;
	}

	/**
	 * Default column ids for a subject.
	 *
	 * @param string $subject Subject.
	 * @return string[]
	 */
	public static function default_columns( $subject ) {
		$map = array(
			'members'      => array( 'user.display_name', 'user.user_email', 'member.status', 'profile.member_number' ),
			'applications' => array( 'event.event_name', 'user.display_name', 'role.role_name', 'application.status', 'application.applied_at' ),
			'payments'     => array( 'user.display_name', 'event.event_name', 'payment.total_amount', 'payment.amount_due', 'payment.payment_status' ),
			'vetting'      => array( 'user.display_name', 'vetting.status', 'vetting.decision', 'vetter.display_name', 'vetting.scheduled_at' ),
			'events'       => array( 'event.event_name', 'event.status', 'event.start_date', 'event.end_date', 'location.location_name' ),
		);
		return isset( $map[ $subject ] ) ? $map[ $subject ] : array();
	}

	/**
	 * Whether the viewer is attendees-only (no full member read).
	 *
	 * @return bool
	 */
	public static function is_attendees_only() {
		return current_user_can( 'remember_read_attendees' ) && ! current_user_can( 'remember_read_members' );
	}

	/**
	 * Built-in fields (before cap strip and custom questions).
	 *
	 * @param string $subject Subject.
	 * @return array<string,array<string,mixed>>
	 */
	public static function raw_fields( $subject ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$member_status = array( 'pending_vetting', 'unvetted', 'in_vetting', 'vetted', 'rejected', 'inactive', 'merged' );
		$app_status    = array( 'pending', 'accepted', 'declined', 'cancelled', 'waitlisted' );
		$pay_status    = array( 'pending', 'partial', 'paid', 'refunded', 'cancelled' );
		$vet_status    = array( 'pending', 'scheduled', 'in_progress', 'completed' );
		$vet_decision  = array( 'pending', 'accepted', 'rejected' );
		$event_status  = array( 'draft', 'open', 'closed', 'completed', 'cancelled' );

		$dietary_sql = "(SELECT GROUP_CONCAT(d.restriction_name ORDER BY d.restriction_name SEPARATOR ', ') FROM {$p}remember_member_dietary_restrictions md INNER JOIN {$p}remember_dietary_restrictions d ON d.restriction_id = md.restriction_id WHERE md.member_id = {MEMBER})";
		$allergy_sql = "(SELECT GROUP_CONCAT(al.allergy_name ORDER BY al.allergy_name SEPARATOR ', ') FROM {$p}remember_member_allergies ma INNER JOIN {$p}remember_allergies al ON al.allergy_id = ma.allergy_id WHERE ma.member_id = {MEMBER})";
		$medical_sql = "(SELECT GROUP_CONCAT(mc.accommodation_name ORDER BY mc.accommodation_name SEPARATOR ', ') FROM {$p}remember_member_medical_accommodations mm INNER JOIN {$p}remember_medical_accommodations mc ON mc.accommodation_id = mm.accommodation_id WHERE mm.member_id = {MEMBER})";
		$roles_sql   = "(SELECT GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') FROM {$p}remember_member_roles mr INNER JOIN {$p}remember_roles r ON r.role_id = mr.role_id WHERE mr.member_id = {MEMBER})";
		$social_sql  = "(SELECT GROUP_CONCAT(CONCAT(smp.platform_name, ': ', msm.handle) ORDER BY smp.sort_order SEPARATOR ', ') FROM {$p}remember_member_social_media msm LEFT JOIN {$p}remember_social_media_platforms smp ON smp.platform_id = msm.platform_id WHERE msm.member_id = {MEMBER})";

		if ( 'members' === $subject ) {
			$mid = 'm.member_id';
			return self::replace_member_token(
				array(
					'member.member_id'           => self::f( __( 'Member ID', 'remember' ), 'Member', 'number', $mid, null, true ),
					'user.display_name'          => self::f( __( 'Display name', 'remember' ), 'Member', 'string', 'u.display_name', 'user' ),
					'user.user_email'            => self::f( __( 'Email', 'remember' ), 'Member', 'string', 'u.user_email', 'user' ),
					'member.status'              => self::f( __( 'Status', 'remember' ), 'Member', 'enum', 'm.status', null, false, $member_status ),
					'profile.member_number'      => self::f( __( 'Member number', 'remember' ), 'Profile', 'string', 'p.member_number', 'profile' ),
					'profile.legal_first_name'   => self::f( __( 'Legal first name', 'remember' ), 'Profile', 'string', 'p.legal_first_name', 'profile' ),
					'profile.legal_last_name'    => self::f( __( 'Legal last name', 'remember' ), 'Profile', 'string', 'p.legal_last_name', 'profile' ),
					'user.first_name'            => self::f( __( 'First name', 'remember' ), 'Member', 'string', "(SELECT umfn.meta_value FROM {$wpdb->usermeta} umfn WHERE umfn.user_id = u.ID AND umfn.meta_key = 'first_name' LIMIT 1)", 'user' ),
					'user.last_name'             => self::f( __( 'Last name', 'remember' ), 'Member', 'string', "(SELECT umln.meta_value FROM {$wpdb->usermeta} umln WHERE umln.user_id = u.ID AND umln.meta_key = 'last_name' LIMIT 1)", 'user' ),
					'profile.address_city'       => self::f( __( 'City', 'remember' ), 'Location', 'string', 'p.address_city', 'profile' ),
					'profile.address_state'      => self::f( __( 'State', 'remember' ), 'Location', 'string', 'p.address_state', 'profile' ),
					'profile.address_postal'     => self::f( __( 'Postal code', 'remember' ), 'Location', 'string', 'p.address_postal', 'profile' ),
					'profile.address_country'    => self::f( __( 'Country', 'remember' ), 'Location', 'string', 'p.address_country', 'profile' ),
					'profile.cell_phone'         => self::f( __( 'Cell phone', 'remember' ), 'Profile', 'string', 'p.cell_phone', 'profile' ),
					'profile.timezone'           => self::f( __( 'Time zone', 'remember' ), 'Profile', 'string', 'p.timezone', 'profile' ),
					'profile.im_type'            => self::f( __( 'IM type', 'remember' ), 'Profile', 'string', 'p.im_type', 'profile' ),
					'profile.im_handle'          => self::f( __( 'IM handle', 'remember' ), 'Profile', 'string', 'p.im_handle', 'profile' ),
					'profile.shirt_size'         => self::f( __( 'Shirt size', 'remember' ), 'Clothing', 'string', 'p.shirt_size', 'profile' ),
					'profile.pants_size'         => self::f( __( 'Pants size', 'remember' ), 'Clothing', 'string', 'p.pants_size', 'profile' ),
					'profile.shoe_size'          => self::f( __( 'Shoe size', 'remember' ), 'Clothing', 'string', 'p.shoe_size', 'profile' ),
					'member.roles'               => self::f( __( 'Roles', 'remember' ), 'Member', 'string', $roles_sql ),
					'member.social'              => self::f( __( 'Social handles', 'remember' ), 'Member', 'string', $social_sql ),
					'member.created_at'          => self::f( __( 'Created', 'remember' ), 'Member', 'datetime', 'm.created_at' ),
					'profile.updated_at'         => self::f( __( 'Profile saved', 'remember' ), 'Profile', 'datetime', 'p.updated_at', 'profile' ),
					'emergency.first'            => self::f( __( 'Emergency contact first', 'remember' ), 'Emergency', 'string', 'p.emergency_contact_first', 'profile', false, array(), 'emergency' ),
					'emergency.last'             => self::f( __( 'Emergency contact last', 'remember' ), 'Emergency', 'string', 'p.emergency_contact_last', 'profile', false, array(), 'emergency' ),
					'emergency.phone'            => self::f( __( 'Emergency contact phone', 'remember' ), 'Emergency', 'string', 'p.emergency_contact_phone', 'profile', false, array(), 'emergency' ),
					'emergency.relationship'     => self::f( __( 'Emergency contact relationship', 'remember' ), 'Emergency', 'string', 'p.emergency_contact_relationship', 'profile', false, array(), 'emergency' ),
					'health.dietary'             => self::f( __( 'Dietary restrictions', 'remember' ), 'Health', 'string', $dietary_sql, null, false, array(), 'health' ),
					'health.allergies'           => self::f( __( 'Allergies', 'remember' ), 'Health', 'string', $allergy_sql, null, false, array(), 'health' ),
					'health.medical'             => self::f( __( 'Medical accommodations', 'remember' ), 'Health', 'string', $medical_sql, null, false, array(), 'health' ),
				),
				$mid
			);
		}

		if ( 'applications' === $subject ) {
			$mid = 'a.member_id';
			return self::replace_member_token(
				array(
					'application.application_id' => self::f( __( 'Application ID', 'remember' ), 'Application', 'number', 'a.application_id', null, true ),
					'event.event_name'           => self::f( __( 'Event', 'remember' ), 'Event', 'string', 'e.event_name', 'event' ),
					'event.start_date'           => self::f( __( 'Event start', 'remember' ), 'Event', 'date', 'e.start_date', 'event' ),
					'user.display_name'          => self::f( __( 'Member', 'remember' ), 'Member', 'string', 'u.display_name', 'user' ),
					'user.user_email'            => self::f( __( 'Email', 'remember' ), 'Member', 'string', 'u.user_email', 'user' ),
					'role.role_name'             => self::f( __( 'Event role', 'remember' ), 'Application', 'string', 'r.role_name', 'role' ),
					'application.status'         => self::f( __( 'Status', 'remember' ), 'Application', 'enum', 'a.status', null, false, $app_status ),
					'application.applied_at'     => self::f( __( 'Applied', 'remember' ), 'Application', 'datetime', 'a.applied_at' ),
					'application.waitlisted_at'  => self::f( __( 'Waitlisted', 'remember' ), 'Application', 'datetime', 'a.waitlisted_at' ),
					'application.processed_at'   => self::f( __( 'Processed', 'remember' ), 'Application', 'datetime', 'a.processed_at' ),
					'application.ticket_voided'  => self::f( __( 'Ticket voided', 'remember' ), 'Application', 'enum', 'a.ticket_voided', null, false, array( '0', '1' ) ),
					'application.superseded_at'  => self::f( __( 'Superseded', 'remember' ), 'Application', 'datetime', 'a.superseded_at' ),
					'member.status'              => self::f( __( 'Member status', 'remember' ), 'Member', 'enum', 'm.status', 'member', false, $member_status ),
					'health.dietary'             => self::f( __( 'Dietary restrictions', 'remember' ), 'Health', 'string', $dietary_sql, null, false, array(), 'health' ),
					'health.allergies'           => self::f( __( 'Allergies', 'remember' ), 'Health', 'string', $allergy_sql, null, false, array(), 'health' ),
					'health.medical'             => self::f( __( 'Medical accommodations', 'remember' ), 'Health', 'string', $medical_sql, null, false, array(), 'health' ),
				),
				$mid
			);
		}

		if ( 'payments' === $subject ) {
			return array(
				'payment.payment_id'      => self::f( __( 'Payment ID', 'remember' ), 'Payment', 'number', 'pay.payment_id', null, true ),
				'user.display_name'       => self::f( __( 'Member', 'remember' ), 'Member', 'string', 'u.display_name', 'user' ),
				'user.user_email'         => self::f( __( 'Email', 'remember' ), 'Member', 'string', 'u.user_email', 'user' ),
				'event.event_name'        => self::f( __( 'Event', 'remember' ), 'Event', 'string', 'e.event_name', 'event' ),
				'payment.role_cost'       => self::f( __( 'Role cost', 'remember' ), 'Payment', 'number', 'pay.role_cost', null, true ),
				'payment.merchandise_cost'=> self::f( __( 'Merchandise cost', 'remember' ), 'Payment', 'number', 'pay.merchandise_cost', null, true ),
				'payment.total_amount'    => self::f( __( 'Total', 'remember' ), 'Payment', 'number', 'pay.total_amount', null, true ),
				'payment.amount_paid'     => self::f( __( 'Amount paid', 'remember' ), 'Payment', 'number', 'pay.amount_paid', null, true ),
				'payment.amount_due'      => self::f( __( 'Amount due', 'remember' ), 'Payment', 'number', 'pay.amount_due', null, true ),
				'payment.payment_status'  => self::f( __( 'Status', 'remember' ), 'Payment', 'enum', 'pay.payment_status', null, false, $pay_status ),
				'payment.payment_date'    => self::f( __( 'Payment date', 'remember' ), 'Payment', 'datetime', 'pay.payment_date' ),
				'payment.payment_method'  => self::f( __( 'Method', 'remember' ), 'Payment', 'string', 'pay.payment_method' ),
				'payment.xero_invoice_number' => self::f( __( 'Xero invoice #', 'remember' ), 'Payment', 'string', 'pay.xero_invoice_number' ),
				'payment.quickbooks_invoice_number' => self::f( __( 'QuickBooks invoice #', 'remember' ), 'Payment', 'string', 'pay.quickbooks_invoice_number' ),
			);
		}

		if ( 'vetting' === $subject ) {
			return array(
				'vetting.vetting_id'     => self::f( __( 'Case ID', 'remember' ), 'Vetting', 'number', 'v.vetting_id', null, true ),
				'user.display_name'      => self::f( __( 'Member', 'remember' ), 'Member', 'string', 'u.display_name', 'user' ),
				'user.user_email'        => self::f( __( 'Email', 'remember' ), 'Member', 'string', 'u.user_email', 'user' ),
				'member.status'          => self::f( __( 'Member status', 'remember' ), 'Member', 'enum', 'm.status', 'member', false, $member_status ),
				'vetting.status'         => self::f( __( 'Case status', 'remember' ), 'Vetting', 'enum', 'v.status', null, false, $vet_status ),
				'vetting.decision'       => self::f( __( 'Decision', 'remember' ), 'Vetting', 'enum', 'v.decision', null, false, $vet_decision ),
				'vetter.display_name'    => self::f( __( 'Primary vetter', 'remember' ), 'Vetting', 'string', 'vu.display_name', 'vetter' ),
				'vetting.scheduled_at'   => self::f( __( 'Scheduled', 'remember' ), 'Vetting', 'datetime', 'v.scheduled_at' ),
				'vetting.completed_at'   => self::f( __( 'Completed', 'remember' ), 'Vetting', 'datetime', 'v.completed_at' ),
				'vetting.decision_date'  => self::f( __( 'Decision date', 'remember' ), 'Vetting', 'datetime', 'v.decision_date' ),
				'vetting.created_at'     => self::f( __( 'Opened', 'remember' ), 'Vetting', 'datetime', 'v.created_at' ),
			);
		}

		if ( 'events' === $subject ) {
			return array(
				'event.event_id'         => self::f( __( 'Event ID', 'remember' ), 'Event', 'number', 'e.event_id', null, true ),
				'event.event_name'       => self::f( __( 'Name', 'remember' ), 'Event', 'string', 'e.event_name' ),
				'event.status'           => self::f( __( 'Status', 'remember' ), 'Event', 'enum', 'e.status', null, false, $event_status ),
				'event.start_date'       => self::f( __( 'Start', 'remember' ), 'Event', 'date', 'e.start_date' ),
				'event.end_date'         => self::f( __( 'End', 'remember' ), 'Event', 'date', 'e.end_date' ),
				'event.is_private'       => self::f( __( 'Private', 'remember' ), 'Event', 'enum', 'e.is_private', null, false, array( '0', '1' ) ),
				'location.location_name' => self::f( __( 'Location', 'remember' ), 'Event', 'string', 'loc.location_name', 'location' ),
				'event.created_at'       => self::f( __( 'Created', 'remember' ), 'Event', 'datetime', 'e.created_at' ),
			);
		}

		return array();
	}

	/**
	 * Custom profile questions as report fields.
	 *
	 * @param string $subject members|applications.
	 * @return array<string,array<string,mixed>>
	 */
	public static function custom_question_fields( $subject ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-profile-question.php';
		$model = new Remember_Profile_Question();
		$rows  = $model->get_all_ordered();
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$member_expr = 'members' === $subject ? 'm.member_id' : 'a.member_id';
		$out         = array();
		foreach ( $rows as $q ) {
			$qid = (int) $q->question_id;
			if ( $qid < 1 ) {
				continue;
			}
			$id   = 'pq_' . $qid;
			$join = 'pq_' . $qid;
			$out[ $id ] = self::f(
				sprintf(
					/* translators: %s: custom field label */
					__( 'Custom: %s', 'remember' ),
					(string) $q->label
				),
				__( 'Custom fields', 'remember' ),
				'string',
				$join . '.value_text',
				$join
			);
			$out[ $id ]['question_id'] = $qid;
			$out[ $id ]['member_expr'] = $member_expr;
		}
		return $out;
	}

	/**
	 * Field array helper.
	 *
	 * @param string      $label     Label.
	 * @param string      $group     Group.
	 * @param string      $type      Type.
	 * @param string      $sql       Expression.
	 * @param string|null $join      Join key.
	 * @param bool        $measure   Numeric measure.
	 * @param array       $options   Enum options.
	 * @param string|null $sensitive emergency|health.
	 * @return array<string,mixed>
	 */
	private static function f( $label, $group, $type, $sql, $join = null, $measure = false, $options = array(), $sensitive = null ) {
		$field = array(
			'label'    => $label,
			'group'    => $group,
			'type'     => $type,
			'sql'      => $sql,
			'join'     => $join,
			'measure'  => $measure,
			'options'  => $options,
		);
		if ( $sensitive ) {
			$field['sensitive'] = $sensitive;
		}
		return $field;
	}

	/**
	 * Swap {MEMBER} in subquery SQL for the grain member expression.
	 *
	 * @param array  $fields Fields.
	 * @param string $member Member SQL.
	 * @return array
	 */
	private static function replace_member_token( $fields, $member ) {
		foreach ( $fields as $id => $field ) {
			$fields[ $id ]['sql'] = str_replace( '{MEMBER}', $member, $field['sql'] );
		}
		return $fields;
	}
}
