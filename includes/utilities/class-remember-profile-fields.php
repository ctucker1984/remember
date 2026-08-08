<?php
/**
 * Shared member profile field policy (required vs optional) and collectors.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Profile field requirements and POST collectors.
 */
class Remember_Profile_Fields {

	/**
	 * Profile-table keys that are required (confirmed 1.2.0).
	 *
	 * Nickname and timezone live in WP user meta (see required_meta_keys()).
	 *
	 * @return string[]
	 */
	public static function required_profile_keys() {
		return array(
			'legal_first_name',
			'legal_last_name',
			'address_street',
			'address_city',
			'address_state',
			'address_postal',
			'cell_phone',
			'im_handle',
			'emergency_contact_first',
			'emergency_contact_last',
			'emergency_contact_phone',
			'emergency_contact_relationship',
		);
	}

	/**
	 * Required WP user-meta / identity keys.
	 *
	 * @return string[]
	 */
	public static function required_meta_keys() {
		return array(
			'nickname',
			'timezone_string',
		);
	}

	/**
	 * Human labels for required keys (error messages).
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'nickname'                       => __( 'Nickname', 'remember' ),
			'timezone_string'                => __( 'Time Zone', 'remember' ),
			'legal_first_name'               => __( 'Legal First Name', 'remember' ),
			'legal_last_name'                => __( 'Legal Last Name', 'remember' ),
			'address_street'                 => __( 'Street Address', 'remember' ),
			'address_city'                   => __( 'City', 'remember' ),
			'address_state'                  => __( 'State/Province', 'remember' ),
			'address_postal'                 => __( 'Postal Code', 'remember' ),
			'cell_phone'                     => __( 'Cell Phone', 'remember' ),
			'im_handle'                      => __( 'Instant Messenger', 'remember' ),
			'emergency_contact_first'        => __( 'Emergency Contact First Name', 'remember' ),
			'emergency_contact_last'         => __( 'Emergency Contact Last Name', 'remember' ),
			'emergency_contact_phone'        => __( 'Emergency Contact Phone', 'remember' ),
			'emergency_contact_relationship' => __( 'Emergency Contact Relationship', 'remember' ),
		);
	}

	/**
	 * Whether a field key is required.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public static function is_required( $key ) {
		$key = (string) $key;
		return in_array( $key, self::required_profile_keys(), true )
			|| in_array( $key, self::required_meta_keys(), true );
	}

	/**
	 * Read a text field from POST, trying preferred names in order.
	 *
	 * @param string[] $names Candidate POST keys.
	 * @return string
	 */
	private static function post_text( array $names ) {
		foreach ( $names as $name ) {
			if ( isset( $_POST[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies.
				return sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
			}
		}
		return '';
	}

	/**
	 * Collect member_profiles columns from the current request.
	 *
	 * Accepts standard profile names and/or remember_reg_* registration names.
	 *
	 * @return array<string,mixed>
	 */
	public static function collect_profile_data_from_request() {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-clothing-sizes.php';

		$im_type = self::post_text( array( 'im_type', 'remember_reg_im_type' ) );
		if ( '' === $im_type ) {
			$im_type = 'telegram';
		}

		$country = self::post_text( array( 'address_country', 'remember_reg_address_country' ) );
		if ( '' === $country ) {
			$country = 'US';
		}

		$interests = '';
		if ( isset( $_POST['interests'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$interests = wp_kses_post( wp_unslash( $_POST['interests'] ) );
		} elseif ( isset( $_POST['remember_reg_interests'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$interests = wp_kses_post( wp_unslash( $_POST['remember_reg_interests'] ) );
		}

		return array(
			'legal_first_name'               => self::post_text( array( 'legal_first_name', 'remember_reg_first_name' ) ),
			'legal_last_name'                => self::post_text( array( 'legal_last_name', 'remember_reg_last_name' ) ),
			'address_street'                 => self::post_text( array( 'address_street', 'remember_reg_address_street' ) ),
			'address_city'                   => self::post_text( array( 'address_city', 'remember_reg_address_city' ) ),
			'address_state'                  => self::post_text( array( 'address_state', 'remember_reg_address_state' ) ),
			'address_postal'                 => self::post_text( array( 'address_postal', 'remember_reg_address_postal' ) ),
			'address_country'                => $country,
			'cell_phone'                     => self::post_text( array( 'cell_phone', 'remember_reg_cell_phone' ) ),
			'im_handle'                      => self::post_text( array( 'im_handle', 'remember_reg_im_handle' ) ),
			'im_type'                        => $im_type,
			'interests'                      => $interests,
			'shirt_size'                     => Remember_Clothing_Sizes::sanitize( 'shirt', self::post_text( array( 'shirt_size', 'remember_reg_shirt_size' ) ) ),
			'pants_size'                     => Remember_Clothing_Sizes::sanitize( 'pants', self::post_text( array( 'pants_size', 'remember_reg_pants_size' ) ) ),
			'shoe_size'                      => Remember_Clothing_Sizes::sanitize( 'shoe', self::post_text( array( 'shoe_size', 'remember_reg_shoe_size' ) ) ),
			'emergency_contact_first'        => self::post_text( array( 'emergency_contact_first', 'remember_reg_emergency_contact_first' ) ),
			'emergency_contact_last'         => self::post_text( array( 'emergency_contact_last', 'remember_reg_emergency_contact_last' ) ),
			'emergency_contact_phone'        => self::post_text( array( 'emergency_contact_phone', 'remember_reg_emergency_contact_phone' ) ),
			'emergency_contact_relationship' => self::post_text( array( 'emergency_contact_relationship', 'remember_reg_emergency_contact_relationship' ) ),
			'share_email_with_events'        => ( isset( $_POST['share_email_with_events'] ) || isset( $_POST['remember_reg_share_email_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'share_phone_with_events'        => ( isset( $_POST['share_phone_with_events'] ) || isset( $_POST['remember_reg_share_phone_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'share_location_with_events'     => ( isset( $_POST['share_location_with_events'] ) || isset( $_POST['remember_reg_share_location_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'share_im_with_events'           => ( isset( $_POST['share_im_with_events'] ) || isset( $_POST['remember_reg_share_im_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'share_interests_with_events'    => ( isset( $_POST['share_interests_with_events'] ) || isset( $_POST['remember_reg_share_interests_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'share_photo_with_events'        => ( isset( $_POST['share_photo_with_events'] ) || isset( $_POST['remember_reg_share_photo_with_events'] ) ) ? 1 : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
	}

	/**
	 * Collect nickname + timezone from the request.
	 *
	 * @return array{nickname:string,timezone_string:string}
	 */
	public static function collect_meta_from_request() {
		$nickname = self::post_text( array( 'nickname', 'remember_reg_display_name', 'remember_reg_nickname' ) );
		$timezone = self::post_text( array( 'timezone_string', 'remember_reg_timezone' ) );
		return array(
			'nickname'         => $nickname,
			'timezone_string'  => $timezone,
		);
	}

	/**
	 * First missing required key among profile + meta, or empty string if complete.
	 *
	 * @param array<string,mixed> $profile_data Profile columns.
	 * @param array<string,string> $meta_data    nickname + timezone_string.
	 * @return string
	 */
	public static function first_missing_required( array $profile_data, array $meta_data ) {
		foreach ( self::required_meta_keys() as $key ) {
			if ( ! isset( $meta_data[ $key ] ) || '' === trim( (string) $meta_data[ $key ] ) ) {
				return $key;
			}
		}
		foreach ( self::required_profile_keys() as $key ) {
			if ( ! isset( $profile_data[ $key ] ) || '' === trim( (string) $profile_data[ $key ] ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Replace social / dietary / medical / allergy junctions for a member from POST.
	 *
	 * @param int $member_id Member user ID.
	 * @return void
	 */
	public static function save_junctions_from_request( $member_id ) {
		global $wpdb;
		$member_id = absint( $member_id );
		if ( $member_id < 1 ) {
			return;
		}

		$wpdb->delete( $wpdb->prefix . 'remember_member_social_media', array( 'member_id' => $member_id ), array( '%d' ) );
		if ( isset( $_POST['social_media'] ) && is_array( $_POST['social_media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$social_count = 0;
			foreach ( $_POST['social_media'] as $social_data ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( $social_count >= 3 ) {
					break;
				}
				$platform_id = isset( $social_data['platform_id'] ) ? absint( $social_data['platform_id'] ) : 0;
				$handle      = isset( $social_data['handle'] ) ? sanitize_text_field( wp_unslash( $social_data['handle'] ) ) : '';
				if ( $platform_id > 0 && '' !== $handle ) {
					$wpdb->insert(
						$wpdb->prefix . 'remember_member_social_media',
						array(
							'member_id'   => $member_id,
							'platform_id' => $platform_id,
							'handle'      => $handle,
							'created_at'  => current_time( 'mysql' ),
						),
						array( '%d', '%d', '%s', '%s' )
					);
					$social_count++;
				}
			}
		}

		$wpdb->delete( $wpdb->prefix . 'remember_member_dietary_restrictions', array( 'member_id' => $member_id ), array( '%d' ) );
		if ( isset( $_POST['dietary_restrictions'] ) && is_array( $_POST['dietary_restrictions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( $_POST['dietary_restrictions'] as $restriction_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$restriction_id = absint( $restriction_id );
				if ( $restriction_id > 0 ) {
					$wpdb->insert(
						$wpdb->prefix . 'remember_member_dietary_restrictions',
						array(
							'member_id'      => $member_id,
							'restriction_id' => $restriction_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}

		$wpdb->delete( $wpdb->prefix . 'remember_member_medical_accommodations', array( 'member_id' => $member_id ), array( '%d' ) );
		if ( isset( $_POST['medical_accommodations'] ) && is_array( $_POST['medical_accommodations'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( $_POST['medical_accommodations'] as $accommodation_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$accommodation_id = absint( $accommodation_id );
				if ( $accommodation_id > 0 ) {
					$wpdb->insert(
						$wpdb->prefix . 'remember_member_medical_accommodations',
						array(
							'member_id'        => $member_id,
							'accommodation_id' => $accommodation_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}

		$wpdb->delete( $wpdb->prefix . 'remember_member_allergies', array( 'member_id' => $member_id ), array( '%d' ) );
		if ( isset( $_POST['allergies'] ) && is_array( $_POST['allergies'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( $_POST['allergies'] as $allergy_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$allergy_id = absint( $allergy_id );
				if ( $allergy_id > 0 ) {
					$wpdb->insert(
						$wpdb->prefix . 'remember_member_allergies',
						array(
							'member_id'  => $member_id,
							'allergy_id' => $allergy_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}
	}
}
