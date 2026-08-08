<?php
/**
 * Notification setting model class
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
 * Notification setting model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Notification_Setting extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'notification_settings';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'setting_id';

	/**
	 * Get notification setting by type.
	 *
	 * @param string $notification_type Notification type.
	 * @return object|null
	 */
	public function get_by_type( $notification_type ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE notification_type = %s",
				$notification_type
			)
		);
	}

	/**
	 * Get all notification settings.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'orderby' => 'notification_type',
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );
		
		global $wpdb;
		$query = "SELECT * FROM {$this->get_table()}";
		
		if ( ! empty( $args['orderby'] ) ) {
			$order = ! empty( $args['order'] ) ? strtoupper( $args['order'] ) : 'ASC';
			$query .= " ORDER BY {$args['orderby']} {$order}";
		}
		
		return $wpdb->get_results( $query );
	}

	/**
	 * Update notification setting.
	 *
	 * @param string $notification_type Notification type.
	 * @param array  $data              Data to update.
	 * @return int|false
	 */
	public function update_by_type( $notification_type, $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return $wpdb->update(
			$this->get_table(),
			$data,
			array( 'notification_type' => $notification_type ),
			array( '%d', '%s', '%s', '%s' ), // Format for is_enabled, subject_template, body_template, updated_at
			array( '%s' ) // Format for notification_type in WHERE clause
		);
	}

	/**
	 * Get notification type label.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$labels = array(
			'application_received'          => __( 'Application Received', 'remember' ),
			'vetting_assigned'              => __( 'Vetting Assigned', 'remember' ),
			'vetting_scheduled'             => __( 'Vetting Scheduled', 'remember' ),
			'vetting_completed'             => __( 'Vetting Completed', 'remember' ),
			'member_vetted'                 => __( 'Member Vetted', 'remember' ),
			'event_application_submitted'   => __( 'Event Application Submitted', 'remember' ),
			'event_application_accepted'     => __( 'Event Application Accepted', 'remember' ),
			'event_application_declined'    => __( 'Event Application Declined', 'remember' ),
			'event_application_waitlisted'  => __( 'Event Application Waitlisted', 'remember' ),
			'event_ticket_paid'             => __( 'Event Ticket Paid', 'remember' ),
			'payment_recorded'              => __( 'Payment Recorded', 'remember' ),
			'payment_due_reminder'          => __( 'Payment Due Reminder', 'remember' ),
			'vetting_collaborator_invited'  => __( 'Vetting Collaborator Invited', 'remember' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Get notification type description.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	public static function get_type_description( $type ) {
		$descriptions = array(
			'application_received'          => __( 'Sent when a new application is received.', 'remember' ),
			'vetting_assigned'              => __( 'Sent when a vetter is assigned to a vetting case.', 'remember' ),
			'vetting_scheduled'             => __( 'Sent when a vetting case is scheduled.', 'remember' ),
			'vetting_completed'             => __( 'Sent when a vetting case is completed.', 'remember' ),
			'member_vetted'                 => __( 'Sent when a member is vetted (accepted).', 'remember' ),
			'event_application_submitted'   => __( 'Sent to member when they submit an event application.', 'remember' ),
			'event_application_accepted'     => __( 'Sent to member when their event application is accepted (includes ticket link).', 'remember' ),
			'event_application_declined'    => __( 'Sent to member when their event application is declined.', 'remember' ),
			'event_application_waitlisted'  => __( 'Sent to member when their event application is waitlisted.', 'remember' ),
			'event_ticket_paid'             => __( 'Sent to member when their event ticket is paid in full (includes ticket link).', 'remember' ),
			'payment_recorded'              => __( 'Sent when a payment is recorded.', 'remember' ),
			'payment_due_reminder'          => __( 'Sent as a reminder when payment is due (also used for balance-due blasts).', 'remember' ),
			'vetting_collaborator_invited'  => __( 'Sent when a collaborator is invited to a vetting case.', 'remember' ),
		);
		return isset( $descriptions[ $type ] ) ? $descriptions[ $type ] : '';
	}

	/**
	 * Get notification type category.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	public static function get_type_category( $type ) {
		if ( strpos( $type, 'vetting' ) !== false ) {
			return 'vetting';
		} elseif ( strpos( $type, 'application' ) !== false || strpos( $type, 'event' ) !== false ) {
			return 'applications';
		} elseif ( strpos( $type, 'payment' ) !== false ) {
			return 'billing';
		} else {
			return 'general';
		}
	}

	/**
	 * Get default subject template for notification type.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	public static function get_default_subject( $type ) {
		$templates = array(
			'application_received'          => __( 'New Application Received - {member_name}', 'remember' ),
			'vetting_assigned'              => __( 'Vetting Case Assigned - {member_name}', 'remember' ),
			'vetting_scheduled'             => __( 'Vetting Case Scheduled - {member_name}', 'remember' ),
			'vetting_completed'             => __( 'Vetting Case Completed - {member_name}', 'remember' ),
			'member_vetted'                 => __( 'Welcome! You\'ve Been Vetted - {member_name}', 'remember' ),
			'event_application_submitted'   => __( 'Application Submitted for {event_name}', 'remember' ),
			'event_application_accepted'     => __( 'Application Accepted for {event_name}', 'remember' ),
			'event_application_declined'    => __( 'Application Update for {event_name}', 'remember' ),
			'event_application_waitlisted'  => __( 'Application Waitlisted for {event_name}', 'remember' ),
			'event_ticket_paid'             => __( 'Your paid ticket for {event_name}', 'remember' ),
			'payment_recorded'              => __( 'Payment Recorded - \${amount}', 'remember' ),
			'payment_due_reminder'          => __( 'Payment Reminder - \${amount_due} Due for {event_name}', 'remember' ),
			'vetting_collaborator_invited'  => __( 'Invitation to Collaborate on Vetting Case', 'remember' ),
		);
		return isset( $templates[ $type ] ) ? $templates[ $type ] : '';
	}

	/**
	 * Get default body template for notification type.
	 *
	 * @param string $type Notification type.
	 * @return string
	 */
	public static function get_default_body( $type ) {
		$templates = array(
			'application_received' => __( "Hello,\n\nA new application has been received from {member_name}.\n\nApplication ID: {application_id}\nEvent: {event_name}\nDate: {date}\n\nPlease review the application in the admin panel.", 'remember' ),
			
			'vetting_assigned' => __( "Hello,\n\nYou have been assigned as the primary vetter for a vetting case.\n\nMember: {member_name}\nVetting Case ID: {vetting_id}\nDate: {date}\n\nPlease review the case and begin the vetting process.", 'remember' ),
			
			'vetting_scheduled' => __( "Hello,\n\nThe vetting case for {member_name} has been scheduled.\n\nVetting Case ID: {vetting_id}\nScheduled Date: {date}\n\nPlease prepare for the scheduled vetting session.", 'remember' ),
			
			'vetting_completed' => __( "Hello,\n\nThe vetting case for {member_name} has been completed.\n\nVetting Case ID: {vetting_id}\nCompletion Date: {date}\n\nPlease review the decision in the admin panel.", 'remember' ),
			
			'member_vetted' => __( "Hello {member_name},\n\nCongratulations! Your vetting process has been completed and you have been accepted as a member.\n\nYou can now apply for events and participate in our community.\n\nWelcome aboard!\n\nThe Team", 'remember' ),
			
			'event_application_submitted' => __( "Hello {member_name},\n\nThank you for submitting your application for {event_name}.\n\nApplication ID: {application_id}\nDate: {date}\n\nWe have received your application and will review it shortly. You will be notified once a decision has been made.\n\nThank you,\nThe Team", 'remember' ),
			
			'event_application_accepted' => __( "Hello {member_name},\n\nGreat news! Your application for {event_name} has been accepted.\n\nApplication ID: {application_id}\nTicket ID: {ticket_id}\nEvent: {event_name}\nDates: {event_dates}\nLocation: {event_location}\nPayment status: {payment_status}\nAmount due: \${amount_due}\n\nView or print your admission ticket (also serves as a receipt):\n{ticket_url}\n\nIf a balance remains, please complete payment. You will receive another email with your paid ticket once payment is recorded.\n\nBest regards,\nThe Team", 'remember' ),
			
			'event_application_declined' => __( "Hello {member_name},\n\nThank you for your interest in {event_name}.\n\nApplication ID: {application_id}\nEvent: {event_name}\nDate: {date}\n\nUnfortunately, we are unable to accept your application at this time. We appreciate your interest and encourage you to apply for future events.\n\nBest regards,\nThe Team", 'remember' ),
			
			'event_application_waitlisted' => __( "Hello {member_name},\n\nYour application for {event_name} has been placed on our waitlist.\n\nApplication ID: {application_id}\nEvent: {event_name}\nDate: {date}\n\nWe will notify you if a spot becomes available.\n\nThank you for your patience,\nThe Team", 'remember' ),

			'event_ticket_paid' => __( "Hello {member_name},\n\nYour payment for {event_name} has been recorded as paid in full.\n\nTicket ID: {ticket_id}\nApplication ID: {application_id}\nEvent: {event_name}\nDates: {event_dates}\nLocation: {event_location}\n\nView or print your paid admission ticket / receipt:\n{ticket_url}\n\nWe look forward to seeing you at the event.\n\nBest regards,\nThe Team", 'remember' ),
			
			'payment_recorded' => __( "Hello {member_name},\n\nThis email confirms that a payment has been recorded.\n\nAmount: \${amount}\nDate: {date}\nApplication ID: {application_id}\n\nThank you for your payment.\n\nBest regards,\nThe Team", 'remember' ),
			
			'payment_due_reminder' => __( "Hello {member_name},\n\nThis is a reminder that payment is due for your accepted application.\n\nAmount Due: \${amount_due}\nApplication ID: {application_id}\nTicket ID: {ticket_id}\nEvent: {event_name}\nDates: {event_dates}\nLocation: {event_location}\nPayment status: {payment_status}\n\nView your ticket (PAYMENT REQUIRED until paid):\n{ticket_url}\n\nPlease submit your payment at your earliest convenience.\n\nThank you,\nThe Team", 'remember' ),
			
			'vetting_collaborator_invited' => __( "Hello,\n\nYou have been invited to collaborate on a vetting case.\n\nMember: {member_name}\nVetting Case ID: {vetting_id}\nDate: {date}\n\nPlease review the case and provide your input.\n\nThank you,\nThe Team", 'remember' ),
		);
		return isset( $templates[ $type ] ) ? $templates[ $type ] : '';
	}
}
