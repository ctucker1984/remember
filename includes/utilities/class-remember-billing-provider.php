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
}
