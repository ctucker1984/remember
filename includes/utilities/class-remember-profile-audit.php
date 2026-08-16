<?php
/**
 * Profile audit timestamps and apply-time currency confirmation (#22).
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Profile audit helpers.
 */
class Remember_Profile_Audit {

	/**
	 * Exact confirmation phrase (lowercase, normalized).
	 *
	 * @var string
	 */
	const CONFIRM_PHRASE = 'my profile is current';

	/**
	 * How recently the profile must have been saved (seconds).
	 *
	 * @var int
	 */
	const FRESHNESS_WINDOW = DAY_IN_SECONDS;

	/**
	 * Normalize a confirmation phrase for comparison.
	 *
	 * Case-insensitive; collapses whitespace; strips trailing punctuation.
	 *
	 * @param mixed $raw Raw input.
	 * @return string
	 */
	public static function normalize_confirm_phrase( $raw ) {
		$value = sanitize_text_field( (string) $raw );
		$value = strtolower( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = trim( (string) $value );
		$value = rtrim( $value, " \t.,!?;:'\"" );
		return trim( $value );
	}

	/**
	 * Whether the posted phrase matches the required confirmation.
	 *
	 * @param mixed $raw Raw input.
	 * @return bool
	 */
	public static function is_confirm_phrase_valid( $raw ) {
		return self::CONFIRM_PHRASE === self::normalize_confirm_phrase( $raw );
	}

	/**
	 * Error message when confirmation phrase fails.
	 *
	 * @return string
	 */
	public static function confirm_phrase_error_message() {
		return sprintf(
			/* translators: %s: required confirmation phrase */
			__( 'Please type “%s” to confirm your profile is current before continuing.', 'remember' ),
			self::CONFIRM_PHRASE
		);
	}

	/**
	 * Error when profile was not saved within the freshness window.
	 *
	 * @return string
	 */
	public static function profile_stale_error_message() {
		return __( 'Please open your profile, confirm it is current and accurate, and click Save before applying. Your last profile save must be within the past 24 hours.', 'remember' );
	}

	/**
	 * Combined server-side validation for apply / dashboard app edit.
	 *
	 * @param mixed $raw_phrase Posted confirmation phrase.
	 * @param int   $member_id  Member ID.
	 * @return string Empty on success, otherwise error message.
	 */
	public static function validate_application_confirmation( $raw_phrase, $member_id ) {
		if ( ! self::is_confirm_phrase_valid( $raw_phrase ) ) {
			return self::confirm_phrase_error_message();
		}
		if ( ! self::is_profile_fresh( $member_id ) ) {
			return self::profile_stale_error_message();
		}
		return '';
	}

	/**
	 * Profile edit URL (opens with ?edit=1), or empty when unknown.
	 *
	 * @return string
	 */
	public static function get_profile_edit_url() {
		$created_pages   = get_option( 'remember_created_pages', array() );
		$profile_page_id = isset( $created_pages['profile'] ) ? absint( $created_pages['profile'] ) : 0;
		if ( $profile_page_id < 1 ) {
			return '';
		}
		$url = get_permalink( $profile_page_id );
		if ( ! $url ) {
			return '';
		}
		return add_query_arg( 'edit', '1', $url );
	}

	/**
	 * Get profile updated_at MySQL datetime for a member, or empty string.
	 *
	 * @param int $member_id Member ID.
	 * @return string
	 */
	public static function get_profile_updated_at( $member_id ) {
		global $wpdb;
		$member_id = absint( $member_id );
		if ( $member_id < 1 ) {
			return '';
		}
		$updated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_at FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d LIMIT 1",
				$member_id
			)
		);
		return $updated ? (string) $updated : '';
	}

	/**
	 * Whether the member profile was saved within the freshness window.
	 *
	 * @param int $member_id Member ID.
	 * @return bool
	 */
	public static function is_profile_fresh( $member_id ) {
		$updated = self::get_profile_updated_at( $member_id );
		if ( '' === $updated ) {
			return false;
		}
		try {
			$tz         = wp_timezone();
			$updated_dt = new DateTimeImmutable( $updated, $tz );
			$now        = new DateTimeImmutable( 'now', $tz );
			$age        = $now->getTimestamp() - $updated_dt->getTimestamp();
			return $age >= 0 && $age <= self::FRESHNESS_WINDOW;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Stamp profile updated_at / updated_by for a member.
	 *
	 * @param int $member_id Member / user ID.
	 * @param int $user_id   Acting user ID (defaults to current user).
	 * @return bool True when a row was updated.
	 */
	public static function touch_updated( $member_id, $user_id = 0 ) {
		global $wpdb;
		$member_id = absint( $member_id );
		$user_id   = absint( $user_id );
		if ( $user_id < 1 ) {
			$user_id = get_current_user_id();
		}
		if ( $member_id < 1 || $user_id < 1 ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'remember_member_profiles',
			array(
				'updated_at' => current_time( 'mysql' ),
				'updated_by' => $user_id,
			),
			array( 'member_id' => $member_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Format a MySQL datetime for display in a user's timezone, or empty when unset.
	 *
	 * @param mixed $mysql_datetime Datetime string.
	 * @param int   $for_user_id    User whose timezone to use (0 = current user).
	 * @return string
	 */
	public static function format_datetime( $mysql_datetime, $for_user_id = 0 ) {
		if ( empty( $mysql_datetime ) ) {
			return '';
		}
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-timezone.php';
		$for_user_id = absint( $for_user_id );
		if ( $for_user_id < 1 ) {
			$for_user_id = get_current_user_id();
		}
		return Remember_Timezone::format_for_user( (string) $mysql_datetime, $for_user_id, true );
	}

	/**
	 * Display name for updated_by, or empty when unset/unknown.
	 *
	 * @param mixed $user_id User ID.
	 * @return string
	 */
	public static function format_updated_by_name( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		$name = trim( (string) $user->display_name );
		return '' !== $name ? $name : (string) $user->user_login;
	}

	/**
	 * Render the profile-currency confirmation field (apply / dashboard edit).
	 *
	 * @param string $input_id HTML id for the input.
	 * @return void
	 */
	public static function render_confirm_field( $input_id = 'remember_profile_currency_confirm' ) {
		$input_id = sanitize_html_class( $input_id );
		if ( '' === $input_id ) {
			$input_id = 'remember_profile_currency_confirm';
		}
		$phrase      = self::CONFIRM_PHRASE;
		$profile_url = self::get_profile_edit_url();
		$member_id   = get_current_user_id();
		$updated_at  = self::get_profile_updated_at( $member_id );
		$is_fresh    = self::is_profile_fresh( $member_id );
		?>
		<div
			class="remember-form-group remember-profile-currency-confirm"
			data-remember-profile-fresh="<?php echo $is_fresh ? '1' : '0'; ?>"
			data-remember-profile-updated-at="<?php echo esc_attr( $updated_at ); ?>"
		>
			<label for="<?php echo esc_attr( $input_id ); ?>" class="remember-form-label">
				<?php esc_html_e( 'Confirm your profile is current', 'remember' ); ?>
				<span class="remember-required">*</span>
			</label>
			<p class="remember-form-help remember-profile-currency-confirm__help">
				<?php if ( $profile_url ) : ?>
					<a href="<?php echo esc_url( $profile_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Check your profile and confirm that it is current and accurate, then click save on it (required).', 'remember' ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'Check your profile and confirm that it is current and accurate, then click save on it (required).', 'remember' ); ?>
				<?php endif; ?>
				<?php
				echo ' ';
				echo wp_kses(
					sprintf(
						/* translators: %s: required confirmation phrase wrapped in <strong> */
						__( 'Type “%s” to unlock submit. Required every time you apply or update an application.', 'remember' ),
						'<strong>' . esc_html( $phrase ) . '</strong>'
					),
					array( 'strong' => array() )
				);
				?>
			</p>
			<p class="remember-form-help remember-profile-currency-confirm__status" hidden></p>
			<input
				type="text"
				id="<?php echo esc_attr( $input_id ); ?>"
				name="remember_profile_currency_confirm"
				class="remember-form-control remember-profile-currency-confirm__input"
				autocomplete="off"
				autocapitalize="none"
				spellcheck="false"
				required
				data-remember-confirm-phrase="<?php echo esc_attr( $phrase ); ?>"
			>
		</div>
		<?php
	}
}
