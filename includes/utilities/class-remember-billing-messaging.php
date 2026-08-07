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
	 * Display name for the active billing provider (for disclaimer copy).
	 *
	 * @return string
	 */
	public static function get_provider_display_name() {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-billing-provider.php';

		if ( Remember_Billing_Provider::is_xero() ) {
			return __( 'Xero', 'remember' );
		}
		if ( Remember_Billing_Provider::is_quickbooks() ) {
			return __( 'QuickBooks', 'remember' );
		}
		return __( 'your billing provider', 'remember' );
	}

	/**
	 * Build the default subtotal disclaimer for a provider label.
	 *
	 * @param string|null $provider_name Optional override (QuickBooks / Xero / …).
	 * @return string
	 */
	public static function get_default_subtotal_disclaimer( $provider_name = null ) {
		if ( null === $provider_name || '' === $provider_name ) {
			$provider_name = self::get_provider_display_name();
		}

		return sprintf(
			/* translators: %s: billing provider name (QuickBooks, Xero, etc.) */
			__( 'Amounts shown in reMember are subtotal only. A separate billing email from %s provides final invoice totals, applicable taxes, and payment options.', 'remember' ),
			$provider_name
		);
	}

	/**
	 * Whether stored disclaimer text is an auto-generated default (any provider).
	 *
	 * @param string $text Stored option value.
	 * @return bool
	 */
	private static function is_auto_default_disclaimer( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return true;
		}

		$candidates = array(
			self::get_default_subtotal_disclaimer( __( 'QuickBooks', 'remember' ) ),
			self::get_default_subtotal_disclaimer( __( 'Xero', 'remember' ) ),
			self::get_default_subtotal_disclaimer( __( 'your billing provider', 'remember' ) ),
			// Pre-1.1.0 hardcoded English string (no sprintf).
			'Amounts shown in reMember are subtotal only. A separate billing email from QuickBooks provides final invoice totals, applicable taxes, and payment options.',
		);

		foreach ( $candidates as $candidate ) {
			if ( $text === $candidate ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the configured subtotal disclaimer.
	 *
	 * Custom Settings text is used when it is not one of the auto defaults.
	 * Auto defaults always follow the active billing provider.
	 *
	 * @return string
	 */
	public static function get_subtotal_disclaimer() {
		$options = get_option( 'remember_options', array() );
		if ( ! empty( $options['subtotal_disclaimer_text'] ) && ! self::is_auto_default_disclaimer( $options['subtotal_disclaimer_text'] ) ) {
			return $options['subtotal_disclaimer_text'];
		}

		return self::get_default_subtotal_disclaimer();
	}
}
