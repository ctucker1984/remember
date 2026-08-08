<?php
/**
 * Clothing size option lists (shirt / pants / shoes).
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * US men's clothing size helpers.
 */
class Remember_Clothing_Sizes {

	/**
	 * Allowed categories.
	 *
	 * @return string[]
	 */
	public static function categories() {
		return array( 'shirt', 'pants', 'shoe' );
	}

	/**
	 * Canonical default sizes (also seeded into remember_clothing_size_options).
	 *
	 * @return array<string,string[]> category => size codes
	 */
	public static function defaults() {
		$letter = array( 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL' );
		$shoes  = array();
		for ( $n = 6; $n <= 15; $n++ ) {
			$shoes[] = (string) $n;
		}

		return array(
			'shirt' => $letter,
			'pants' => $letter,
			'shoe'  => $shoes,
		);
	}

	/**
	 * Size codes for a category (DB when available, else defaults).
	 *
	 * @param string $category shirt|pants|shoe.
	 * @return string[]
	 */
	public static function options_for( $category ) {
		$category = sanitize_key( $category );
		$defaults = self::defaults();
		if ( ! isset( $defaults[ $category ] ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'remember_clothing_size_options';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return $defaults[ $category ];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT size_code FROM {$table} WHERE size_category = %s AND is_active = 1 ORDER BY sort_order ASC, size_code ASC",
				$category
			)
		);

		if ( empty( $rows ) ) {
			return $defaults[ $category ];
		}

		return array_map( 'strval', $rows );
	}

	/**
	 * Sanitize a submitted size for a category (empty allowed).
	 *
	 * @param string $category shirt|pants|shoe.
	 * @param string $value    Submitted value.
	 * @return string
	 */
	public static function sanitize( $category, $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$allowed = self::options_for( $category );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Render a select dropdown for a size category.
	 *
	 * @param string $category shirt|pants|shoe.
	 * @param string $selected Selected size code.
	 * @param string $name     Input name.
	 * @param string $id       Input id.
	 * @param bool   $required Required attribute.
	 * @param string $class    Optional CSS class.
	 * @return string
	 */
	public static function dropdown( $category, $selected = '', $name = '', $id = '', $required = false, $class = '' ) {
		$category = sanitize_key( $category );
		if ( '' === $name ) {
			$name = $category . '_size';
		}
		if ( '' === $id ) {
			$id = $name;
		}

		$labels = array(
			'shirt' => __( 'Select shirt size', 'remember' ),
			'pants' => __( 'Select pants size', 'remember' ),
			'shoe'  => __( 'Select shoe size', 'remember' ),
		);
		$placeholder = isset( $labels[ $category ] ) ? $labels[ $category ] : __( 'Select size', 'remember' );

		$html  = sprintf(
			'<select name="%s" id="%s"%s%s>',
			esc_attr( $name ),
			esc_attr( $id ),
			$class ? ' class="' . esc_attr( $class ) . '"' : '',
			$required ? ' required' : ''
		);
		$html .= '<option value="">' . esc_html( $placeholder ) . '</option>';
		foreach ( self::options_for( $category ) as $code ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $code ),
				selected( (string) $selected, (string) $code, false ),
				esc_html( $code )
			);
		}
		$html .= '</select>';

		return $html;
	}
}
