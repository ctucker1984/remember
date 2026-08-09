<?php
/**
 * Timezone utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Timezone utility class.
 *
 * Handles timezone conversions and formatting.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Timezone {

	/**
	 * Get organization timezone (from WordPress settings).
	 *
	 * @return DateTimeZone
	 */
	public static function get_organization_timezone() {
		return wp_timezone();
	}

	/**
	 * Get user's timezone.
	 *
	 * @param int $user_id User ID.
	 * @return DateTimeZone
	 */
	public static function get_user_timezone( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$timezone_string = get_user_meta( $user_id, 'timezone_string', true );
		
		// Default to America/Los_Angeles if not set
		if ( empty( $timezone_string ) ) {
			$timezone_string = 'America/Los_Angeles';
			// Auto-assign default timezone
			update_user_meta( $user_id, 'timezone_string', $timezone_string );
		}

		try {
			return new DateTimeZone( $timezone_string );
		} catch ( Exception $e ) {
			// Fallback to organization timezone if invalid
			return self::get_organization_timezone();
		}
	}

	/**
	 * Convert datetime from organization timezone to user's timezone.
	 *
	 * @param string $datetime Datetime string in organization timezone (MySQL format).
	 * @param int    $user_id  User ID (defaults to current user).
	 * @return DateTime
	 */
	public static function convert_to_user_timezone( $datetime, $user_id = null ) {
		$org_tz = self::get_organization_timezone();
		$user_tz = self::get_user_timezone( $user_id );
		
		$dt = new DateTime( $datetime, $org_tz );
		$dt->setTimezone( $user_tz );
		
		return $dt;
	}

	/**
	 * Format datetime for user display with timezone conversion.
	 *
	 * @param string $datetime Datetime string in organization timezone (MySQL format).
	 * @param int    $user_id  User ID (defaults to current user).
	 * @param bool   $show_date Whether to show date (true) or just time if today (false).
	 * @return string Formatted string with user's local time.
	 */
	public static function format_for_user( $datetime, $user_id = null, $show_date = true ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$user_tz = self::get_user_timezone( $user_id );
		$dt = self::convert_to_user_timezone( $datetime, $user_id );
		
		// Check if it's today in user's timezone
		$now = new DateTime( 'now', $user_tz );
		$is_today = $dt->format( 'Y-m-d' ) === $now->format( 'Y-m-d' );
		
		if ( ! $show_date && $is_today ) {
			// Just show time if it's today
			return $dt->format( get_option( 'time_format' ) );
		} else {
			// Show date and time
			return $dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		}
	}

	/**
	 * Format datetime with "your time" notation.
	 *
	 * @param string $datetime Datetime string in organization timezone (MySQL format).
	 * @param int    $user_id  User ID (defaults to current user).
	 * @param bool   $show_date Whether to show date (true) or just time if today (false).
	 * @return string Formatted string with "X:XX AM your time" notation.
	 */
	public static function format_with_your_time( $datetime, $user_id = null, $show_date = true ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		$formatted = self::format_for_user( $datetime, $user_id, $show_date );
		
		if ( empty( $formatted ) ) {
			return '';
		}

		// Check if it's today in user's timezone
		$user_tz = self::get_user_timezone( $user_id );
		$dt = self::convert_to_user_timezone( $datetime, $user_id );
		$now = new DateTime( 'now', $user_tz );
		$is_today = $dt->format( 'Y-m-d' ) === $now->format( 'Y-m-d' );
		
		if ( ! $show_date && $is_today ) {
			// Just show time with "your time"
			return sprintf( __( '%s your time', 'remember' ), $formatted );
		} else {
			// Show date and time with "your time"
			return sprintf( __( '%s your time', 'remember' ), $formatted );
		}
	}

	/**
	 * Get hierarchical timezone dropdown HTML (using WordPress function).
	 *
	 * @param string $selected Selected timezone.
	 * @param string $name     Field name.
	 * @param string $id       Field ID.
	 * @param bool   $required Whether field is required.
	 * @param string $class    CSS class for the select element.
	 * @return string HTML for dropdown.
	 */
	public static function dropdown( $selected = '', $name = 'timezone_string', $id = 'timezone_string', $required = false, $class = 'regular-text' ) {
		$selected = is_string( $selected ) ? trim( $selected ) : '';

		$classes = trim( $class . ' remember-timezone-select' );

		// Single combobox control (JS): type in the field to match options — no separate filter box.
		$html  = '<div class="remember-timezone-picker">';
		$html .= sprintf(
			'<select name="%s" id="%s" class="%s"%s data-remember-timezone="1">',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $classes ),
			$required ? ' required' : ''
		);

		// Empty choice so registration is not pre-filtered to a default city.
		if ( '' === $selected ) {
			$html .= sprintf(
				'<option value="" selected="selected">%s</option>',
				esc_html__( 'Select your time zone…', 'remember' )
			);
		}

		// WordPress hierarchical list (continent/region optgroups) — source data for the combobox.
		$html .= wp_timezone_choice( $selected, get_user_locale() );

		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Whether a timezone string is usable (IANA id or WP UTC± offset choice).
	 *
	 * @param string $timezone Timezone string.
	 * @return bool
	 */
	public static function is_valid_timezone( $timezone ) {
		$timezone = is_string( $timezone ) ? trim( $timezone ) : '';
		if ( '' === $timezone ) {
			return false;
		}

		try {
			new DateTimeZone( $timezone );
			return true;
		} catch ( Exception $e ) {
			return (bool) preg_match( '/^UTC[+-]/', $timezone );
		}
	}

	/**
	 * Get timezone offset in hours between organization and user timezone.
	 *
	 * @param int $user_id User ID (defaults to current user).
	 * @return float Offset in hours (positive means user is ahead, negative means behind).
	 */
	public static function get_timezone_offset_hours( $user_id = null ) {
		$org_tz = self::get_organization_timezone();
		$user_tz = self::get_user_timezone( $user_id );
		
		$now = new DateTime( 'now', $org_tz );
		$user_now = clone $now;
		$user_now->setTimezone( $user_tz );
		
		$org_offset = $now->getOffset();
		$user_offset = $user_now->getOffset();
		
		// Calculate difference in hours
		$offset_seconds = $user_offset - $org_offset;
		return $offset_seconds / 3600;
	}

	/**
	 * Get example time conversion message.
	 *
	 * @param int $user_id User ID (defaults to current user).
	 * @return string Example message like "Scheduling at 11:00 AM will show 8:00 AM, your time".
	 */
	public static function get_example_conversion( $user_id = null ) {
		$offset_hours = self::get_timezone_offset_hours( $user_id );
		
		if ( abs( $offset_hours ) < 0.01 ) {
			// Same timezone
			return __( 'Your timezone matches the organization timezone.', 'remember' );
		}
		
		// Use 11:00 AM as example
		$example_org_time = '11:00 AM';
		$example_hour = 11;
		$example_minute = 0;
		
		// Calculate user time
		$user_hour = $example_hour + $offset_hours;
		$user_minute = $example_minute;
		
		// Handle hour overflow/underflow
		if ( $user_hour < 1 ) {
			$user_hour += 12;
			$user_ampm = 'PM';
		} elseif ( $user_hour > 12 ) {
			$user_hour -= 12;
			$user_ampm = 'PM';
		} elseif ( $user_hour === 12 ) {
			$user_ampm = 'PM';
		} else {
			$user_ampm = 'AM';
		}
		
		$user_time = sprintf( '%d:%02d %s', $user_hour, $user_minute, $user_ampm );
		
		return sprintf(
			__( 'Scheduling at %s will show %s, your time', 'remember' ),
			$example_org_time,
			$user_time
		);
	}

	/**
	 * Get organization timezone name for display.
	 *
	 * @return string Timezone name.
	 */
	public static function get_organization_timezone_name() {
		$tz = self::get_organization_timezone();
		return $tz->getName();
	}
}
