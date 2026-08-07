<?php
/**
 * Xero sync orchestration (Phase 1 stub).
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-xero-api.php';

/**
 * Xero sync — mirrors Remember_QuickBooks_Sync public surface.
 *
 * Phase 1: stubs only. Contact sync, invoices, and payment matching land later.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_Xero_Sync {

	/**
	 * Sync a member to a Xero Contact.
	 *
	 * @param int $member_id Member ID.
	 * @return true|WP_Error
	 */
	public static function sync_member_to_contact( $member_id ) {
		return new WP_Error(
			'xero_not_implemented',
			__( 'Xero contact sync is not implemented yet.', 'remember' )
		);
	}

	/**
	 * Create a Xero invoice for an accepted application.
	 *
	 * @param int $application_id Application ID.
	 * @return true|WP_Error
	 */
	public static function create_invoice_for_application( $application_id ) {
		return new WP_Error(
			'xero_not_implemented',
			__( 'Xero invoice creation is not implemented yet.', 'remember' )
		);
	}

	/**
	 * Sync payment/refund status for one payment row from Xero.
	 *
	 * @param int $payment_id Payment ID.
	 * @return true|WP_Error
	 */
	public static function sync_payment_status( $payment_id ) {
		return new WP_Error(
			'xero_not_implemented',
			__( 'Xero payment sync is not implemented yet.', 'remember' )
		);
	}

	/**
	 * Sync all open Xero-linked payments.
	 *
	 * @return array{success:int,error:int}
	 */
	public static function sync_all_payments() {
		return array(
			'success' => 0,
			'error'   => 0,
		);
	}
}
