<?php
/**
 * Active billing / accounting provider helpers.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Site-wide billing provider: none | quickbooks | xero.
 */
class Remember_Billing_Provider {

	/**
	 * Get the configured billing provider.
	 *
	 * @return string none|quickbooks|xero
	 */
	public static function get() {
		$options  = get_option( 'remember_options', array() );
		$provider = isset( $options['billing_provider'] ) ? (string) $options['billing_provider'] : 'none';
		if ( ! in_array( $provider, array( 'none', 'quickbooks', 'xero' ), true ) ) {
			return 'none';
		}
		return $provider;
	}

	/**
	 * Whether QuickBooks is the active provider.
	 *
	 * @return bool
	 */
	public static function is_quickbooks() {
		return 'quickbooks' === self::get();
	}

	/**
	 * Whether Xero is the active provider.
	 *
	 * @return bool
	 */
	public static function is_xero() {
		return 'xero' === self::get();
	}

	/**
	 * Sync provider invoice/payment status for one member's payment rows.
	 *
	 * Used when a member opens their dashboard so amounts stay current.
	 *
	 * @param int $member_id WordPress user / member ID.
	 * @param int $throttle_seconds Skip if synced this recently (0 = always). Default 60.
	 * @return array{success:int,error:int,skipped:bool}
	 */
	public static function sync_member_payments( $member_id, $throttle_seconds = 60 ) {
		$member_id = absint( $member_id );
		$empty     = array(
			'success' => 0,
			'error'   => 0,
			'skipped' => true,
		);
		if ( $member_id <= 0 ) {
			return $empty;
		}

		$throttle_seconds = absint( $throttle_seconds );
		if ( $throttle_seconds > 0 ) {
			$transient_key = 'remember_member_billing_sync_' . $member_id;
			if ( false !== get_transient( $transient_key ) ) {
				return $empty;
			}
		}

		$results = array(
			'success' => 0,
			'error'   => 0,
			'skipped' => false,
		);

		if ( self::is_xero() ) {
			require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-xero-oauth.php';
			require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-xero-sync.php';
			if ( ! Remember_Xero_OAuth::is_connected() ) {
				return $empty;
			}
			$results = Remember_Xero_Sync::sync_member_payments( $member_id );
		} elseif ( self::is_quickbooks() ) {
			require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-quickbooks-oauth.php';
			require_once plugin_dir_path( __FILE__ ) . '../integrations/class-remember-quickbooks-sync.php';
			$qb = Remember_QuickBooks_OAuth::get_settings();
			if ( empty( $qb['access_token'] ) || empty( $qb['realm_id'] ) ) {
				return $empty;
			}
			$results = Remember_QuickBooks_Sync::sync_member_payments( $member_id );
		} else {
			return $empty;
		}

		if ( $throttle_seconds > 0 ) {
			set_transient( 'remember_member_billing_sync_' . $member_id, 1, $throttle_seconds );
		}

		$results['skipped'] = false;
		return $results;
	}
}
