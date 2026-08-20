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
			'address_country',
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
			'display_name'                   => __( 'Display name', 'remember' ),
			'timezone_string'                => __( 'Time Zone', 'remember' ),
			'legal_first_name'               => __( 'Legal First Name', 'remember' ),
			'legal_last_name'                => __( 'Legal Last Name', 'remember' ),
			'address_street'                 => __( 'Street Address', 'remember' ),
			'address_city'                   => __( 'City', 'remember' ),
			'address_state'                  => __( 'State/Province', 'remember' ),
			'address_postal'                 => __( 'Postal Code', 'remember' ),
			'address_country'                => __( 'Country', 'remember' ),
			'cell_phone'                     => __( 'Cell Phone', 'remember' ),
			'im_handle'                      => __( 'Instant Messenger', 'remember' ),
			'emergency_contact_first'        => __( 'Emergency Contact First Name', 'remember' ),
			'emergency_contact_last'         => __( 'Emergency Contact Last Name', 'remember' ),
			'emergency_contact_phone'        => __( 'Emergency Contact Phone', 'remember' ),
			'emergency_contact_relationship' => __( 'Emergency Contact Relationship', 'remember' ),
			'dietary_restrictions'           => __( 'Dietary Restrictions', 'remember' ),
			'medical_accommodations'         => __( 'Medical Accommodations', 'remember' ),
			'allergies'                      => __( 'Known Allergies', 'remember' ),
		);
	}

	/**
	 * Required profile keys for a form context.
	 *
	 * Admin member edit stays lean so staff can save incomplete records.
	 *
	 * @param string $context front|register|admin.
	 * @return string[]
	 */
	public static function required_profile_keys_for_context( $context = 'front' ) {
		if ( 'admin' === $context ) {
			return array(
				'legal_first_name',
				'legal_last_name',
				'cell_phone',
			);
		}
		return self::required_profile_keys();
	}

	/**
	 * Required meta keys for a form context.
	 *
	 * @param string $context front|register|admin.
	 * @return string[]
	 */
	public static function required_meta_keys_for_context( $context = 'front' ) {
		if ( 'admin' === $context ) {
			return array(
				'nickname',
				'display_name',
			);
		}
		return self::required_meta_keys();
	}

	/**
	 * Whether a field key is required.
	 *
	 * @param string $key     Field key.
	 * @param string $context front|register|admin.
	 * @return bool
	 */
	public static function is_required( $key, $context = 'front' ) {
		$key = (string) $key;
		return in_array( $key, self::required_profile_keys_for_context( $context ), true )
			|| in_array( $key, self::required_meta_keys_for_context( $context ), true );
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
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-im-platforms.php';
		$im_type = Remember_Im_Platforms::sanitize_key_value( $im_type );

		$country = self::post_text( array( 'address_country', 'remember_reg_address_country' ) );
		if ( '' === $country ) {
			$country = 'US';
		}

		$interests = '';
		if ( isset( $_POST['interests'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$interests = self::sanitize_interests( wp_unslash( $_POST['interests'] ) );
		} elseif ( isset( $_POST['remember_reg_interests'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$interests = self::sanitize_interests( wp_unslash( $_POST['remember_reg_interests'] ) );
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
	 * Collect nickname + timezone (+ display_name when posted) from the request.
	 *
	 * @return array<string,string>
	 */
	public static function collect_meta_from_request() {
		$nickname = self::post_text( array( 'nickname', 'remember_reg_display_name', 'remember_reg_nickname' ) );
		$timezone = self::post_text( array( 'timezone_string', 'remember_reg_timezone' ) );
		return array(
			'nickname'        => $nickname,
			'timezone_string' => $timezone,
			'display_name'    => self::post_text( array( 'display_name' ) ),
		);
	}

	/**
	 * First missing required key among profile + meta, or empty string if complete.
	 *
	 * @param array<string,mixed>  $profile_data Profile columns.
	 * @param array<string,string> $meta_data    Meta keys from collect_meta_from_request().
	 * @param string               $context      front|register|admin.
	 * @return string
	 */
	public static function first_missing_required( array $profile_data, array $meta_data, $context = 'front' ) {
		foreach ( self::required_meta_keys_for_context( $context ) as $key ) {
			if ( ! isset( $meta_data[ $key ] ) || '' === trim( (string) $meta_data[ $key ] ) ) {
				return $key;
			}
		}
		foreach ( self::required_profile_keys_for_context( $context ) as $key ) {
			if ( ! isset( $profile_data[ $key ] ) || '' === trim( (string) $profile_data[ $key ] ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Whether at least one ID was posted for a checkbox catalog field.
	 *
	 * @param string $post_key POST array key (e.g. dietary_restrictions).
	 * @return bool
	 */
	private static function request_has_catalog_selection( $post_key ) {
		if ( ! isset( $_POST[ $post_key ] ) || ! is_array( $_POST[ $post_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		foreach ( $_POST[ $post_key ] as $raw_id ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( absint( $raw_id ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * First missing required health catalog (dietary / medical / allergies), or empty string.
	 *
	 * Skips a catalog when it has no active options (nothing to choose).
	 *
	 * @return string Field key: dietary_restrictions|medical_accommodations|allergies|''.
	 */
	public static function first_missing_required_health_catalog() {
		global $wpdb;

		$checks = array(
			'dietary_restrictions'    => $wpdb->prefix . 'remember_dietary_restrictions',
			'medical_accommodations'  => $wpdb->prefix . 'remember_medical_accommodations',
			'allergies'               => $wpdb->prefix . 'remember_allergies',
		);

		foreach ( $checks as $post_key => $table ) {
			$active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from fixed map.
			if ( $active < 1 ) {
				continue;
			}
			if ( ! self::request_has_catalog_selection( $post_key ) ) {
				return $post_key;
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

	/**
	 * Maximum plain-text length for Interests (HTML tags do not count).
	 *
	 * @return int
	 */
	public static function interests_max_length() {
		$max = (int) apply_filters( 'remember_interests_max_length', 2000 );
		return $max > 0 ? $max : 2000;
	}

	/**
	 * Visible text of an Interests HTML value.
	 *
	 * @param string $html Rich text.
	 * @return string
	 */
	public static function interests_plain_text( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Character count of Interests visible text.
	 *
	 * @param string $html Rich text.
	 * @return int
	 */
	public static function interests_char_count( $html ) {
		$text = self::interests_plain_text( $html );
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}
		return strlen( $text );
	}

	/**
	 * Whether Interests exceeds the plain-text limit.
	 *
	 * @param string $html Rich text.
	 * @return bool
	 */
	public static function interests_is_over_limit( $html ) {
		return self::interests_char_count( $html ) > self::interests_max_length();
	}

	/**
	 * Error copy when Interests is too long.
	 *
	 * @return string
	 */
	public static function interests_too_long_message() {
		return sprintf(
			/* translators: %s: maximum character count */
			__( 'Interests must be %s characters or fewer.', 'remember' ),
			number_format_i18n( self::interests_max_length() )
		);
	}

	/**
	 * Sanitize Interests HTML (does not truncate).
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function sanitize_interests( $html ) {
		return wp_kses_post( (string) $html );
	}

	/**
	 * Sanitize Interests and clamp visible text to the limit (CSV import).
	 *
	 * @param string $html Raw HTML or plain text.
	 * @return string
	 */
	public static function clamp_interests( $html ) {
		$html = self::sanitize_interests( $html );
		$max  = self::interests_max_length();
		if ( self::interests_char_count( $html ) <= $max ) {
			return $html;
		}
		$text = self::interests_plain_text( $html );
		$cut  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
		return wp_kses_post( wpautop( $cut ) );
	}

	/**
	 * Attach live Interests counting to this TinyMCE instance (called from editor setup).
	 *
	 * @param array  $init      TinyMCE init array.
	 * @param string $editor_id Editor id.
	 * @return array
	 */
	public static function tinymce_interests_setup( $init, $editor_id ) {
		if ( 'interests' !== $editor_id && 'remember_reg_interests' !== $editor_id ) {
			return $init;
		}

		$call = 'if(window.rememberInitInterestsLimitEditor){window.rememberInitInterestsLimitEditor(editor);}';
		$prev = isset( $init['setup'] ) ? trim( (string) $init['setup'] ) : '';
		if ( $prev && preg_match( '/^\(?function\s*\(/', $prev ) ) {
			$init['setup'] = 'function(editor){(' . rtrim( $prev, ';' ) . ')(editor);' . $call . '}';
		} else {
			$init['setup'] = 'function(editor){' . $call . '}';
		}

		return $init;
	}

	/**
	 * Render the Interests wp_editor plus a live character counter.
	 *
	 * @param string $content        Current HTML.
	 * @param string $editor_id      TinyMCE / textarea id.
	 * @param string $textarea_name  POST name.
	 * @param string $editor_class   Optional editor_class.
	 * @return void
	 */
	public static function render_interests_editor( $content, $editor_id, $textarea_name, $editor_class = '' ) {
		if ( ! wp_script_is( 'editor', 'enqueued' ) && ! wp_script_is( 'editor', 'done' ) ) {
			wp_enqueue_editor();
		}

		if ( ! has_filter( 'tiny_mce_before_init', array( __CLASS__, 'tinymce_interests_setup' ) ) ) {
			add_filter( 'tiny_mce_before_init', array( __CLASS__, 'tinymce_interests_setup' ), 20, 2 );
		}

		$settings = array(
			'textarea_name' => $textarea_name,
			'textarea_rows' => 6,
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => false,
		);
		if ( '' !== $editor_class ) {
			$settings['editor_class'] = $editor_class;
		}

		wp_editor( $content, $editor_id, $settings );

		$max   = self::interests_max_length();
		$count = self::interests_char_count( $content );
		/* translators: 1: current character count, 2: maximum */
		$count_template = __( '%1$s / %2$s characters', 'remember' );
		printf(
			'<p class="description remember-interests-count" data-remember-interests-editor="%1$s" data-remember-interests-max="%2$s" data-remember-interests-template="%3$s">%4$s</p>',
			esc_attr( $editor_id ),
			esc_attr( (string) $max ),
			esc_attr( $count_template ),
			esc_html( sprintf( $count_template, number_format_i18n( $count ), number_format_i18n( $max ) ) )
		);
	}

	/**
	 * Sanitize a member number: empty string, alphanumeric string, or null if invalid.
	 *
	 * @param mixed $raw Raw input.
	 * @return string|null Empty string when cleared, alphanumeric value, or null when invalid.
	 */
	public static function sanitize_member_number( $raw ) {
		$value = sanitize_text_field( (string) $raw );
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9]+$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Whether another member already has this member number.
	 *
	 * @param string $member_number      Candidate number (non-empty).
	 * @param int    $exclude_member_id  Member ID to ignore (current member).
	 * @return bool
	 */
	public static function is_member_number_taken( $member_number, $exclude_member_id = 0 ) {
		global $wpdb;
		$member_number = (string) $member_number;
		if ( '' === $member_number ) {
			return false;
		}
		$exclude_member_id = absint( $exclude_member_id );
		$sql               = "SELECT member_id FROM {$wpdb->prefix}remember_member_profiles WHERE member_number = %s";
		$params            = array( $member_number );
		if ( $exclude_member_id > 0 ) {
			$sql     .= ' AND member_id <> %d';
			$params[] = $exclude_member_id;
		}
		$sql .= ' LIMIT 1';
		$found = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		return ! empty( $found );
	}
}
