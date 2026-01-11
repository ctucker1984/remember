<?php
/**
 * Countries helper class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Countries helper class.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Countries {

	/**
	 * Get list of countries (ISO 3166-1 alpha-2 codes).
	 *
	 * @return array Array of country codes => country names.
	 */
	public static function get_countries() {
		return array(
			'US' => __( 'United States', 'remember' ),
			'CA' => __( 'Canada', 'remember' ),
			'MX' => __( 'Mexico', 'remember' ),
			'GB' => __( 'United Kingdom', 'remember' ),
			'AU' => __( 'Australia', 'remember' ),
			'NZ' => __( 'New Zealand', 'remember' ),
			'DE' => __( 'Germany', 'remember' ),
			'FR' => __( 'France', 'remember' ),
			'IT' => __( 'Italy', 'remember' ),
			'ES' => __( 'Spain', 'remember' ),
			'NL' => __( 'Netherlands', 'remember' ),
			'BE' => __( 'Belgium', 'remember' ),
			'CH' => __( 'Switzerland', 'remember' ),
			'AT' => __( 'Austria', 'remember' ),
			'SE' => __( 'Sweden', 'remember' ),
			'NO' => __( 'Norway', 'remember' ),
			'DK' => __( 'Denmark', 'remember' ),
			'FI' => __( 'Finland', 'remember' ),
			'IE' => __( 'Ireland', 'remember' ),
			'PT' => __( 'Portugal', 'remember' ),
			'GR' => __( 'Greece', 'remember' ),
			'PL' => __( 'Poland', 'remember' ),
			'CZ' => __( 'Czech Republic', 'remember' ),
			'HU' => __( 'Hungary', 'remember' ),
			'RO' => __( 'Romania', 'remember' ),
			'BG' => __( 'Bulgaria', 'remember' ),
			'HR' => __( 'Croatia', 'remember' ),
			'RS' => __( 'Serbia', 'remember' ),
			'SK' => __( 'Slovakia', 'remember' ),
			'SI' => __( 'Slovenia', 'remember' ),
			'EE' => __( 'Estonia', 'remember' ),
			'LV' => __( 'Latvia', 'remember' ),
			'LT' => __( 'Lithuania', 'remember' ),
			'JP' => __( 'Japan', 'remember' ),
			'CN' => __( 'China', 'remember' ),
			'KR' => __( 'South Korea', 'remember' ),
			'IN' => __( 'India', 'remember' ),
			'BR' => __( 'Brazil', 'remember' ),
			'AR' => __( 'Argentina', 'remember' ),
			'CL' => __( 'Chile', 'remember' ),
			'CO' => __( 'Colombia', 'remember' ),
			'PE' => __( 'Peru', 'remember' ),
			'VE' => __( 'Venezuela', 'remember' ),
			'ZA' => __( 'South Africa', 'remember' ),
			'EG' => __( 'Egypt', 'remember' ),
			'IL' => __( 'Israel', 'remember' ),
			'TR' => __( 'Turkey', 'remember' ),
			'RU' => __( 'Russia', 'remember' ),
			'UA' => __( 'Ukraine', 'remember' ),
			'SG' => __( 'Singapore', 'remember' ),
			'MY' => __( 'Malaysia', 'remember' ),
			'TH' => __( 'Thailand', 'remember' ),
			'VN' => __( 'Vietnam', 'remember' ),
			'PH' => __( 'Philippines', 'remember' ),
			'ID' => __( 'Indonesia', 'remember' ),
			'AE' => __( 'United Arab Emirates', 'remember' ),
			'SA' => __( 'Saudi Arabia', 'remember' ),
			'IS' => __( 'Iceland', 'remember' ),
			'LU' => __( 'Luxembourg', 'remember' ),
			'MT' => __( 'Malta', 'remember' ),
		);
	}

	/**
	 * Get country name by code.
	 *
	 * @param string $code Country code (ISO 3166-1 alpha-2).
	 * @return string Country name or empty string if not found.
	 */
	public static function get_country_name( $code ) {
		$countries = self::get_countries();
		return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}

	/**
	 * Generate country dropdown HTML.
	 *
	 * @param string $name     Field name.
	 * @param string $selected Selected country code.
	 * @param array  $args     Additional arguments (id, class, required).
	 * @return string HTML for dropdown.
	 */
	public static function dropdown( $name, $selected = 'US', $args = array() ) {
		$defaults = array(
			'id'       => $name,
			'class'    => 'regular-text',
			'required' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$html = sprintf(
			'<select name="%s" id="%s" class="%s"%s>',
			esc_attr( $name ),
			esc_attr( $args['id'] ),
			esc_attr( $args['class'] ),
			$args['required'] ? ' required' : ''
		);

		$countries = self::get_countries();
		asort( $countries ); // Sort alphabetically by name

		foreach ( $countries as $code => $name ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $name )
			);
		}

		$html .= '</select>';
		return $html;
	}
}
