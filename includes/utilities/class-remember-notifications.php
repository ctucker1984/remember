<?php
/**
 * Member notification email sender.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sends templated emails using notification_settings rows.
 */
class Remember_Notifications {

	/**
	 * Send a notification by type with placeholder context.
	 *
	 * @param string $type    Notification type key.
	 * @param array  $context Placeholder values (without braces).
	 * @param string $to      Recipient email.
	 * @return bool|WP_Error True on success, false if disabled, WP_Error on failure.
	 */
	public static function send( $type, $context, $to ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-notification-setting.php';
		$model  = new Remember_Notification_Setting();
		$setting = $model->get_by_type( $type );

		$enabled = true;
		$subject = Remember_Notification_Setting::get_default_subject( $type );
		$body    = Remember_Notification_Setting::get_default_body( $type );

		if ( $setting ) {
			$enabled = ! empty( $setting->is_enabled );
			if ( ! empty( $setting->subject_template ) ) {
				$subject = $setting->subject_template;
			}
			if ( ! empty( $setting->body_template ) ) {
				$body = $setting->body_template;
			}
		}

		if ( ! $enabled ) {
			return false;
		}

		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'remember_notify_bad_email', __( 'Invalid recipient email.', 'remember' ) );
		}

		$context = self::normalize_context( $context );
		$subject = self::replace_placeholders( $subject, $context );
		$body    = self::replace_placeholders( $body, $context );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'remember_notify_send_failed', __( 'Failed to send notification email.', 'remember' ) );
		}

		return true;
	}

	/**
	 * Email ticket-ready / accepted notice after accept.
	 *
	 * @param int $application_id Application ID.
	 * @return bool|WP_Error
	 */
	public static function send_ticket_ready( $application_id ) {
		$context = self::context_for_application( $application_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$result = self::send( 'event_application_accepted', $context['placeholders'], $context['email'] );
		if ( true === $result ) {
			self::mark_ticket_ready_emailed( $application_id );
		}
		return $result;
	}

	/**
	 * Email paid ticket notice.
	 *
	 * @param int $application_id Application ID.
	 * @return bool|WP_Error
	 */
	public static function send_ticket_paid( $application_id ) {
		$context = self::context_for_application( $application_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$result = self::send( 'event_ticket_paid', $context['placeholders'], $context['email'] );
		if ( true === $result ) {
			self::mark_ticket_paid_emailed( $application_id );
		}
		return $result;
	}

	/**
	 * Email the current ticket (admin one-click).
	 *
	 * Uses paid template when payment_status is paid; otherwise ticket-ready / accept template.
	 *
	 * @param int $application_id Application ID.
	 * @return bool|WP_Error
	 */
	public static function send_current_ticket( $application_id ) {
		$context = self::context_for_application( $application_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$payment_status = isset( $context['placeholders']['payment_status'] ) ? $context['placeholders']['payment_status'] : '';
		if ( 'paid' === $payment_status ) {
			return self::send_ticket_paid( $application_id );
		}

		return self::send_ticket_ready( $application_id );
	}

	/**
	 * Balance-due / payment reminder for one application.
	 *
	 * @param int $application_id Application ID.
	 * @return bool|WP_Error
	 */
	public static function send_balance_due( $application_id ) {
		$context = self::context_for_application( $application_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$payment_status = $context['placeholders']['payment_status'] ?? '';
		if ( 'paid' === $payment_status ) {
			return new WP_Error( 'remember_notify_already_paid', __( 'Application is already paid in full.', 'remember' ) );
		}

		return self::send( 'payment_due_reminder', $context['placeholders'], $context['email'] );
	}

	/**
	 * Blast balance-due emails to accepted applicants who still owe.
	 *
	 * @param array $args {
	 *     @type int $event_id Optional event filter.
	 * }
	 * @return array{sent:int,skipped:int,errors:int}
	 */
	public static function blast_balance_due( $args = array() ) {
		global $wpdb;
		$args     = wp_parse_args( $args, array( 'event_id' => 0 ) );
		$event_id = absint( $args['event_id'] );

		$sql = "SELECT a.application_id
			FROM {$wpdb->prefix}remember_event_applications a
			LEFT JOIN {$wpdb->prefix}remember_payments p ON p.event_application_id = a.application_id
			WHERE a.status = 'accepted'
			AND (a.ticket_voided IS NULL OR a.ticket_voided = 0)
			AND (
				p.payment_id IS NULL
				OR p.payment_status IN ('pending', 'partial')
			)";
		$query_args = array();
		if ( $event_id > 0 ) {
			$sql         .= ' AND a.event_id = %d';
			$query_args[] = $event_id;
		}

		if ( ! empty( $query_args ) ) {
			$rows = $wpdb->get_col( $wpdb->prepare( $sql, $query_args ) );
		} else {
			$rows = $wpdb->get_col( $sql );
		}

		$stats = array(
			'sent'    => 0,
			'skipped' => 0,
			'errors'  => 0,
		);

		foreach ( (array) $rows as $application_id ) {
			$result = self::send_balance_due( absint( $application_id ) );
			if ( true === $result ) {
				$stats['sent']++;
			} elseif ( false === $result ) {
				$stats['skipped']++;
			} else {
				$stats['errors']++;
			}
		}

		return $stats;
	}

	/**
	 * Handle payment status transitions for paid ticket email.
	 *
	 * @param int    $payment_id  Payment ID.
	 * @param string $old_status  Previous status.
	 * @param string $new_status  New status.
	 * @param object $payment     Payment row after update.
	 * @return void
	 */
	public static function maybe_send_on_payment_status_change( $payment_id, $old_status, $new_status, $payment ) {
		if ( 'paid' !== $new_status || 'paid' === $old_status ) {
			return;
		}
		if ( empty( $payment->event_application_id ) ) {
			return;
		}
		$result = self::send_ticket_paid( (int) $payment->event_application_id );
		if ( is_wp_error( $result ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
			Remember_Logger::warning(
				'Ticket paid email failed',
				array(
					'application_id' => (int) $payment->event_application_id,
					'error'          => $result->get_error_message(),
				)
			);
		}
	}

	/**
	 * Build placeholder context for an application.
	 *
	 * @param int $application_id Application ID.
	 * @return array|WP_Error
	 */
	private static function context_for_application( $application_id ) {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-ticket.php';
		$data = Remember_Ticket::get_data( $application_id );
		if ( is_wp_error( $data ) ) {
			// Accepted-or-voided required for ticket data; fall back for edge cases.
			require_once plugin_dir_path( __FILE__ ) . '../models/class-application.php';
			require_once plugin_dir_path( __FILE__ ) . '../models/class-event.php';
			require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';
			$application_model = new Remember_Application();
			$application       = $application_model->get( $application_id );
			if ( ! $application ) {
				return $data;
			}
			$user  = get_user_by( 'ID', $application->member_id );
			$event = ( new Remember_Event() )->get( $application->event_id );
			$payment = ( new Remember_Payment() )->get_by_application( $application_id );
			$placeholders = array(
				'member_name'     => $user ? $user->display_name : '',
				'event_name'      => $event ? $event->event_name : '',
				'application_id'  => (string) $application_id,
				'ticket_id'       => 'RM-' . (int) $application_id,
				'ticket_url'      => Remember_Ticket::get_ticket_url( $application_id ),
				'amount'          => $payment ? number_format( (float) $payment->amount_paid, 2 ) : '0.00',
				'amount_due'      => $payment ? number_format( (float) $payment->amount_due, 2 ) : '0.00',
				'payment_status'  => $payment ? (string) $payment->payment_status : '',
				'event_location'  => '',
				'event_dates'     => '',
				'date'            => date_i18n( get_option( 'date_format' ) ),
			);
			$email = $user ? $user->user_email : '';
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'remember_notify_no_email', __( 'Member has no valid email.', 'remember' ) );
			}
			return array(
				'email'        => $email,
				'placeholders' => $placeholders,
			);
		}

		$email = $data['member_email'];
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'remember_notify_no_email', __( 'Member has no valid email.', 'remember' ) );
		}

		$placeholders = array(
			'member_name'    => $data['member_name'],
			'event_name'     => $data['event_name'],
			'application_id' => (string) $data['application_id'],
			'ticket_id'      => $data['ticket_id'],
			'ticket_url'     => Remember_Ticket::get_ticket_url( $data['application_id'] ),
			'amount'         => number_format( (float) $data['amount_paid'], 2 ),
			'amount_due'     => number_format( (float) $data['amount_due'], 2 ),
			'payment_status' => $data['payment_status'],
			'event_location' => ! empty( $data['location_lines'] ) ? implode( ', ', $data['location_lines'] ) : '',
			'event_dates'    => $data['event_dates'],
			'date'           => date_i18n( get_option( 'date_format' ) ),
		);

		return array(
			'email'        => $email,
			'placeholders' => $placeholders,
		);
	}

	/**
	 * @param array $context Context.
	 * @return array
	 */
	private static function normalize_context( $context ) {
		$defaults = array(
			'member_name'    => '',
			'event_name'     => '',
			'application_id' => '',
			'ticket_id'      => '',
			'ticket_url'     => '',
			'amount'         => '',
			'amount_due'     => '',
			'payment_status' => '',
			'event_location' => '',
			'event_dates'    => '',
			'date'           => date_i18n( get_option( 'date_format' ) ),
			'vetting_id'     => '',
		);
		return wp_parse_args( $context, $defaults );
	}

	/**
	 * @param string $text    Template text.
	 * @param array  $context Placeholders.
	 * @return string
	 */
	private static function replace_placeholders( $text, $context ) {
		foreach ( $context as $key => $value ) {
			$text = str_replace( '{' . $key . '}', (string) $value, $text );
		}
		return $text;
	}

	/**
	 * @param int $application_id Application ID.
	 * @return void
	 */
	private static function mark_ticket_ready_emailed( $application_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'remember_event_applications',
			array( 'ticket_ready_emailed_at' => current_time( 'mysql' ) ),
			array( 'application_id' => absint( $application_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $application_id Application ID.
	 * @return void
	 */
	private static function mark_ticket_paid_emailed( $application_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'remember_payments',
			array( 'ticket_paid_emailed_at' => current_time( 'mysql' ) ),
			array( 'event_application_id' => absint( $application_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
