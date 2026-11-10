<?php
/**
 * Contact and customer names sent to Xero and QuickBooks.
 *
 * Both require names to be unique. A member whose plain legal name is already
 * taken is saved as "Tom Jones (#123)", using the member ID, which never changes
 * and matches the account number reMember already sends.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Billing contact names.
 */
class Remember_Billing_Contact_Name {

	/**
	 * The name with the member ID added: "Tom Jones (#123)".
	 *
	 * @param string $name      Plain legal name.
	 * @param int    $member_id Member ID.
	 * @return string
	 */
	public static function with_member_number( $name, $member_id ) {
		return trim( (string) $name ) . ' (#' . absint( $member_id ) . ')';
	}

	/**
	 * Name to send on a save.
	 *
	 * A linked contact that already carries this member's number keeps it, so the
	 * name does not change back and forth between syncs. Otherwise the plain name
	 * is sent, and the caller adds the number only if that name is taken.
	 *
	 * @param string $name         Plain legal name.
	 * @param int    $member_id    Member ID.
	 * @param string $current_name Name the linked contact or customer has now, or ''.
	 * @return string
	 */
	public static function preferred( $name, $member_id, $current_name ) {
		$numbered = self::with_member_number( $name, $member_id );
		if ( '' !== trim( (string) $current_name ) && 0 === strcasecmp( trim( (string) $current_name ), $numbered ) ) {
			return $numbered;
		}
		return trim( (string) $name );
	}
}
