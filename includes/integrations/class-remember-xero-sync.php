<?php
/**
 * Xero sync orchestration.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-xero-api.php';
require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * Xero sync — mirrors Remember_QuickBooks_Sync for the active Xero provider.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_Xero_Sync {

	/**
	 * Sync a member to a Xero Contact.
	 *
	 * @param int $member_id Member ID (WordPress user ID).
	 * @return array|WP_Error Contact data or error.
	 */
	public static function sync_member_to_contact( $member_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';

		$member_model = new Remember_Member();
		$member       = $member_model->get( $member_id );
		if ( ! $member ) {
			return new WP_Error( 'member_not_found', __( 'Member not found.', 'remember' ) );
		}

		$user = get_user_by( 'ID', $member_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'WordPress user not found.', 'remember' ) );
		}

		global $wpdb;
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
				$member_id
			)
		);

		$first_name = ! empty( $profile->legal_first_name ) ? $profile->legal_first_name : $user->first_name;
		$last_name  = ! empty( $profile->legal_last_name ) ? $profile->legal_last_name : $user->last_name;
		$full_name  = trim( $first_name . ' ' . $last_name );
		if ( '' === $full_name ) {
			$full_name = $user->display_name;
		}

		$contact_data = array(
			'name'       => $full_name,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $user->user_email,
			'phone'      => ( $profile && ! empty( $profile->cell_phone ) ) ? $profile->cell_phone : '',
		);

		if ( $profile && ( ! empty( $profile->address_street ) || ! empty( $profile->address_city ) ) ) {
			$contact_data['address'] = array(
				'street'  => $profile->address_street ?? '',
				'city'    => $profile->address_city ?? '',
				'state'   => $profile->address_state ?? '',
				'postal'  => $profile->address_postal ?? '',
				'country' => $profile->address_country ?? 'US',
			);
		}

		$xero_contact_id = get_user_meta( $member_id, 'remember_xero_contact_id', true );
		if ( ! $xero_contact_id ) {
			$by_email = Remember_Xero_API::find_contact_by_email( $user->user_email );
			if ( is_array( $by_email ) && ! empty( $by_email['ContactID'] ) ) {
				$xero_contact_id = $by_email['ContactID'];
				update_user_meta( $member_id, 'remember_xero_contact_id', $xero_contact_id );
			}
		}

		if ( $xero_contact_id ) {
			$contact_data['contact_id'] = $xero_contact_id;
		}

		$result = Remember_Xero_API::create_or_update_contact( $contact_data );

		// Stale ContactID: clear and retry once (match-by-email or create).
		if ( is_wp_error( $result ) && $xero_contact_id ) {
			delete_user_meta( $member_id, 'remember_xero_contact_id' );
			unset( $contact_data['contact_id'] );

			$by_email = Remember_Xero_API::find_contact_by_email( $user->user_email );
			if ( is_array( $by_email ) && ! empty( $by_email['ContactID'] ) ) {
				$contact_data['contact_id'] = $by_email['ContactID'];
				update_user_meta( $member_id, 'remember_xero_contact_id', $by_email['ContactID'] );
			}
			$result = Remember_Xero_API::create_or_update_contact( $contact_data );
		}

		if ( is_wp_error( $result ) ) {
			Remember_Logger::error(
				'Failed to sync member to Xero contact',
				array(
					'member_id' => $member_id,
					'error'     => $result->get_error_message(),
				)
			);
			return $result;
		}

		if ( ! empty( $result['ContactID'] ) ) {
			update_user_meta( $member_id, 'remember_xero_contact_id', $result['ContactID'] );
		}

		Remember_Logger::info(
			'Member synced to Xero contact',
			array(
				'member_id'        => $member_id,
				'xero_contact_id'  => isset( $result['ContactID'] ) ? $result['ContactID'] : '',
			)
		);

		return $result;
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
