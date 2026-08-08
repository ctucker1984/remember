<?php
/**
 * Billing unwind when an application is declined or cancelled.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Void / refund / leave invoice handling for application exit paths.
 */
class Remember_Billing_Unwind {

	/**
	 * Allowed billing actions.
	 *
	 * @return string[]
	 */
	public static function allowed_actions() {
		return array( 'void', 'refund', 'leave' );
	}

	/**
	 * Sanitize billing action from request.
	 *
	 * @param string $action Raw action.
	 * @return string void|refund|leave
	 */
	public static function sanitize_action( $action ) {
		$action = sanitize_key( (string) $action );
		return in_array( $action, self::allowed_actions(), true ) ? $action : 'leave';
	}

	/**
	 * Whether the application has a provider invoice to act on.
	 *
	 * @param int $application_id Application ID.
	 * @return bool
	 */
	public static function has_invoice( $application_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';
		$payment = ( new Remember_Payment() )->get_by_application( absint( $application_id ) );
		if ( ! $payment ) {
			return false;
		}
		return ! empty( $payment->xero_invoice_id ) || ! empty( $payment->quickbooks_invoice_id );
	}

	/**
	 * Apply billing action after status change.
	 *
	 * @param int    $application_id Application ID.
	 * @param string $billing_action void|refund|leave.
	 * @param string $reason         Short reason for provider reference.
	 * @return true|WP_Error True on success / leave / no invoice; WP_Error on provider failure.
	 */
	public static function apply( $application_id, $billing_action, $reason = '' ) {
		$billing_action = self::sanitize_action( $billing_action );
		$application_id = absint( $application_id );

		require_once plugin_dir_path( __FILE__ ) . 'class-remember-ticket.php';
		Remember_Ticket::set_voided( $application_id, true );

		if ( 'leave' === $billing_action ) {
			return true;
		}

		require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-billing-provider.php';
		$payment = ( new Remember_Payment() )->get_by_application( $application_id );
		if ( ! $payment ) {
			return true;
		}

		if ( Remember_Billing_Provider::is_xero() && ! empty( $payment->xero_invoice_id ) ) {
			return self::apply_xero( $payment, $billing_action, $reason );
		}
		if ( Remember_Billing_Provider::is_quickbooks() && ! empty( $payment->quickbooks_invoice_id ) ) {
			return self::apply_quickbooks( $payment, $billing_action, $reason );
		}

		return true;
	}

	/**
	 * Render radio group for void / refund / leave.
	 *
	 * @param string $field_name Input name.
	 * @param bool   $has_invoice Whether an invoice exists.
	 * @return string HTML.
	 */
	public static function render_action_radios( $field_name = 'billing_action', $has_invoice = true ) {
		ob_start();
		?>
		<fieldset class="remember-billing-unwind" style="margin: 10px 0; padding: 10px; border: 1px solid #ccd0d4; background: #f6f7f7;">
			<legend style="padding: 0 6px;"><strong><?php esc_html_e( 'Invoice / billing', 'remember' ); ?></strong></legend>
			<?php if ( ! $has_invoice ) : ?>
				<p class="description" style="margin: 0 0 8px;">
					<?php esc_html_e( 'No billing-provider invoice is linked. Billing choice will be ignored.', 'remember' ); ?>
				</p>
			<?php endif; ?>
			<label style="display:block; margin-bottom:6px;">
				<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="void" <?php checked( $has_invoice ); ?> <?php disabled( ! $has_invoice ); ?>>
				<?php esc_html_e( 'Void the invoice', 'remember' ); ?>
			</label>
			<label style="display:block; margin-bottom:6px;">
				<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="refund" <?php disabled( ! $has_invoice ); ?>>
				<?php esc_html_e( 'Refund (credit note / unwind payment)', 'remember' ); ?>
			</label>
			<label style="display:block; margin-bottom:0;">
				<input type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="leave" <?php checked( ! $has_invoice ); ?>>
				<?php esc_html_e( 'Leave the invoice unaltered', 'remember' ); ?>
			</label>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param object $payment Payment row.
	 * @param string $billing_action Action.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	private static function apply_xero( $payment, $billing_action, $reason ) {
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-xero-oauth.php';
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-xero-api.php';
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-xero-sync.php';
		require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';

		if ( ! Remember_Xero_OAuth::is_connected() ) {
			return new WP_Error( 'xero_not_connected', __( 'Xero is not connected; could not update the invoice.', 'remember' ) );
		}

		$payment_model = new Remember_Payment();

		if ( 'void' === $billing_action ) {
			$result = Remember_Xero_API::void_invoice( $payment->xero_invoice_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$payment_model->update(
				$payment->payment_id,
				array(
					'payment_status' => 'cancelled',
					'amount_due'     => 0,
				)
			);
			return true;
		}

		// refund
		$result = Remember_Xero_API::create_and_allocate_credit_note_for_invoice(
			$payment->xero_invoice_id,
			$reason
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Remember_Xero_Sync::sync_payment_status( $payment->payment_id );
		return true;
	}

	/**
	 * @param object $payment Payment row.
	 * @param string $billing_action Action.
	 * @param string $reason Reason.
	 * @return true|WP_Error
	 */
	private static function apply_quickbooks( $payment, $billing_action, $reason ) {
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-quickbooks-oauth.php';
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-quickbooks-api.php';
		require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-quickbooks-sync.php';
		require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';

		$qb_settings = Remember_QuickBooks_OAuth::get_settings();
		if ( ! $qb_settings || empty( $qb_settings['access_token'] ) ) {
			return new WP_Error( 'qb_not_connected', __( 'QuickBooks is not connected; could not update the invoice.', 'remember' ) );
		}

		$payment_model = new Remember_Payment();

		if ( 'void' === $billing_action ) {
			$result = Remember_QuickBooks_API::void_invoice( $payment->quickbooks_invoice_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$payment_model->update(
				$payment->payment_id,
				array(
					'payment_status' => 'cancelled',
					'amount_due'     => 0,
				)
			);
			return true;
		}

		// Refund receipts / credit memos are not fully automated yet for QBO.
		// Void unpaid balance when possible; otherwise ask admin to finish refund in QBO.
		$balance = isset( $payment->amount_due ) ? floatval( $payment->amount_due ) : 0;
		$paid    = isset( $payment->amount_paid ) ? floatval( $payment->amount_paid ) : 0;
		if ( $paid <= 0.001 && $balance > 0 ) {
			$result = Remember_QuickBooks_API::void_invoice( $payment->quickbooks_invoice_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$payment_model->update(
				$payment->payment_id,
				array(
					'payment_status' => 'cancelled',
					'amount_due'     => 0,
				)
			);
			return true;
		}

		Remember_QuickBooks_Sync::sync_payment_status( $payment->payment_id );
		return new WP_Error(
			'qb_refund_manual',
			__( 'Application updated, but QuickBooks refunds must be completed in QuickBooks Online (credit memo / refund receipt). Sync afterward to refresh the ledger.', 'remember' )
		);
	}
}
