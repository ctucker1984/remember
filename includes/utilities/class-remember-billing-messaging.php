<?php
/**
 * Billing messaging utility.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Billing messaging helper.
 */
class Remember_Billing_Messaging {

	/**
	 * Get the default subtotal disclaimer.
	 *
	 * @return string
	 */
	public static function get_default_subtotal_disclaimer() {
		return __( 'Amounts shown in reMember are subtotal only. A separate billing email from QuickBooks provides final invoice totals, applicable taxes, and payment options.', 'remember' );
	}

	/**
	 * Get the configured subtotal disclaimer.
	 *
	 * @return string
	 */
	public static function get_subtotal_disclaimer() {
		$options = get_option( 'remember_options', array() );
		if ( ! empty( $options['subtotal_disclaimer_text'] ) ) {
			return $options['subtotal_disclaimer_text'];
		}

		return self::get_default_subtotal_disclaimer();
	}
}
