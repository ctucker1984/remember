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
	 * Whether mapping columns exist (request cache).
	 *
	 * @var bool|null
	 */
	private static $has_mapping_columns = null;

	/**
	 * Whether the write-in stock table exists (request cache).
	 *
	 * @var bool|null
	 */
	private static $has_stock_table = null;

	/**
	 * Allowed categories.
	 *
	 * @return string[]
	 */
	public static function categories() {
		return array( 'shirt', 'pants', 'shoe' );
	}

	/**
	 * Human label for a category.
	 *
	 * @param string $category shirt|pants|shoe.
	 * @return string
	 */
	public static function category_label( $category ) {
		$labels = array(
			'shirt' => __( 'Shirt', 'remember' ),
			'pants' => __( 'Pants', 'remember' ),
			'shoe'  => __( 'Shoes', 'remember' ),
		);
		$category = sanitize_key( $category );
		return isset( $labels[ $category ] ) ? $labels[ $category ] : $category;
	}

	/**
	 * Help text: submit actual size; available is what they receive.
	 *
	 * @return string
	 */
	public static function section_help() {
		return __( 'Enter your actual size. The size listed as available is what you can expect to receive.', 'remember' );
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
	 * Catalog table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'remember_clothing_size_options';
	}

	/**
	 * Write-in stock table name.
	 *
	 * @return string
	 */
	public static function stock_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'remember_clothing_stock';
	}

	/**
	 * Whether the stock table exists.
	 *
	 * @return bool
	 */
	public static function has_stock_table() {
		if ( null !== self::$has_stock_table ) {
			return self::$has_stock_table;
		}
		global $wpdb;
		$table = self::stock_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		self::$has_stock_table = ( $exists === $table );
		return self::$has_stock_table;
	}

	/**
	 * Normalize a staff-entered stock size code.
	 *
	 * @param string $code Raw input.
	 * @return string
	 */
	public static function sanitize_stock_code( $code ) {
		$code = sanitize_text_field( (string) $code );
		$code = trim( $code );
		if ( strlen( $code ) > 20 ) {
			$code = substr( $code, 0, 20 );
		}
		return $code;
	}

	/**
	 * Whether is_stocked / issued_size_code exist.
	 *
	 * @return bool
	 */
	public static function has_mapping_columns() {
		if ( null !== self::$has_mapping_columns ) {
			return self::$has_mapping_columns;
		}
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::$has_mapping_columns = false;
			return false;
		}
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		self::$has_mapping_columns = is_array( $cols ) && in_array( 'issued_size_code', $cols, true ) && in_array( 'is_stocked', $cols, true );
		return self::$has_mapping_columns;
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
		$table = self::table_name();
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
	 * All catalog rows for a category (admin).
	 *
	 * @param string $category shirt|pants|shoe.
	 * @return object[]
	 */
	public static function rows_for( $category ) {
		$category = sanitize_key( $category );
		if ( ! in_array( $category, self::categories(), true ) ) {
			return array();
		}
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE size_category = %s ORDER BY sort_order ASC, size_code ASC",
				$category
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Stock rows for a category (admin).
	 *
	 * @param string $category shirt|pants|shoe.
	 * @return object[]
	 */
	public static function stock_rows( $category ) {
		$category = sanitize_key( $category );
		if ( ! in_array( $category, self::categories(), true ) || ! self::has_stock_table() ) {
			return array();
		}
		global $wpdb;
		$table = self::stock_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE size_category = %s ORDER BY sort_order ASC, size_code ASC",
				$category
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Stock size codes for a category (Available as dropdown).
	 *
	 * @param string $category shirt|pants|shoe.
	 * @return string[]
	 */
	public static function stocked_codes( $category ) {
		$category = sanitize_key( $category );
		if ( self::has_stock_table() ) {
			global $wpdb;
			$table = self::stock_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT size_code FROM {$table} WHERE size_category = %s ORDER BY sort_order ASC, size_code ASC",
					$category
				)
			);
			return empty( $rows ) ? array() : array_map( 'strval', $rows );
		}
		if ( ! self::has_mapping_columns() ) {
			return self::options_for( $category );
		}
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT size_code FROM {$table} WHERE size_category = %s AND is_stocked = 1 ORDER BY sort_order ASC, size_code ASC",
				$category
			)
		);
		return empty( $rows ) ? array() : array_map( 'strval', $rows );
	}

	/**
	 * Add a write-in stock size.
	 *
	 * @param string $category shirt|pants|shoe.
	 * @param string $code     Size code.
	 * @return true|\WP_Error
	 */
	public static function add_stock( $category, $code ) {
		$category = sanitize_key( $category );
		$code     = self::sanitize_stock_code( $code );
		if ( ! in_array( $category, self::categories(), true ) ) {
			return new WP_Error( 'invalid_category', __( 'That clothing category is not valid.', 'remember' ) );
		}
		if ( '' === $code ) {
			return new WP_Error( 'empty_code', __( 'Enter a stock size.', 'remember' ) );
		}
		if ( ! self::has_stock_table() ) {
			return new WP_Error( 'no_table', __( 'Reload this page after the database update finishes.', 'remember' ) );
		}

		global $wpdb;
		$table = self::stock_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT stock_id FROM {$table} WHERE size_category = %s AND size_code = %s",
				$category,
				$code
			)
		);
		if ( $exists ) {
			return new WP_Error(
				'duplicate',
				sprintf(
					/* translators: 1: size code, 2: category label */
					__( '%1$s is already in %2$s stock.', 'remember' ),
					$code,
					self::category_label( $category )
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$table} WHERE size_category = %s",
				$category
			)
		);
		$ok = $wpdb->insert(
			$table,
			array(
				'size_category' => $category,
				'size_code'     => $code,
				'sort_order'    => (int) $max + 1,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'insert_failed', __( 'Could not add that stock size.', 'remember' ) );
		}
		return true;
	}

	/**
	 * Delete a stock size if no body size maps to it.
	 *
	 * @param int $stock_id Row ID.
	 * @return true|\WP_Error
	 */
	public static function delete_stock( $stock_id ) {
		$stock_id = absint( $stock_id );
		if ( $stock_id <= 0 || ! self::has_stock_table() ) {
			return new WP_Error( 'invalid', __( 'That stock size could not be deleted.', 'remember' ) );
		}
		global $wpdb;
		$stock_table = self::stock_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$row         = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$stock_table} WHERE stock_id = %d",
				$stock_id
			)
		);
		if ( ! $row ) {
			return new WP_Error( 'missing', __( 'That stock size was not found.', 'remember' ) );
		}

		$options_table = self::table_name();
		$mapped        = 0;
		if ( self::has_mapping_columns() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
			$mapped = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$options_table} WHERE size_category = %s AND issued_size_code = %s",
					$row->size_category,
					$row->size_code
				)
			);
		}
		if ( $mapped > 0 ) {
			return new WP_Error(
				'in_use',
				sprintf(
					/* translators: %s: stock size code */
					__( 'Cannot delete %s while member sizes still map to it. Remap those rows first.', 'remember' ),
					$row->size_code
				)
			);
		}

		$wpdb->delete( $stock_table, array( 'stock_id' => $stock_id ), array( '%d' ) );
		return true;
	}

	/**
	 * Issued size for an actual body size (live lookup).
	 *
	 * @param string $category  shirt|pants|shoe.
	 * @param string $body_code Actual size code.
	 * @return string Issued code, or the body code when unmapped.
	 */
	public static function issued_for( $category, $body_code ) {
		$category  = sanitize_key( $category );
		$body_code = sanitize_text_field( (string) $body_code );
		if ( '' === $body_code ) {
			return '';
		}
		if ( ! self::has_mapping_columns() ) {
			return $body_code;
		}
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is plugin-controlled.
		$issued = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT issued_size_code FROM {$table} WHERE size_category = %s AND size_code = %s",
				$category,
				$body_code
			)
		);
		$issued = is_string( $issued ) ? $issued : '';
		return '' !== $issued ? $issued : $body_code;
	}

	/**
	 * Display "XL (available L)" for dropdowns, views, print, and staff detail.
	 *
	 * @param string $category  shirt|pants|shoe.
	 * @param string $body_code Actual size.
	 * @return string
	 */
	public static function format_pair( $category, $body_code ) {
		$body_code = sanitize_text_field( (string) $body_code );
		if ( '' === $body_code ) {
			return '';
		}
		$issued = self::issued_for( $category, $body_code );
		if ( '' === $issued ) {
			$issued = $body_code;
		}
		return sprintf(
			/* translators: 1: actual body size, 2: available inventory size */
			__( '%1$s (available %2$s)', 'remember' ),
			$body_code,
			$issued
		);
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
	 * Option values are actual body sizes. Labels include the available size.
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
				esc_html( self::format_pair( $category, $code ) )
			);
		}
		$html .= '</select>';

		return $html;
	}

	/**
	 * Save catalog rows and stock sort from Settings → Clothing.
	 *
	 * @param array $posted       clothing_sizes[category][option_id] => fields.
	 * @param array $stock_posted clothing_stock[stock_id] => sort_order.
	 * @return true|\WP_Error
	 */
	public static function save_catalog( $posted, $stock_posted = array() ) {
		if ( ! is_array( $posted ) ) {
			$posted = array();
		}

		global $wpdb;

		if ( self::has_stock_table() && is_array( $stock_posted ) ) {
			$stock_table = self::stock_table_name();
			foreach ( $stock_posted as $stock_id => $fields ) {
				$stock_id = absint( $stock_id );
				if ( $stock_id <= 0 ) {
					continue;
				}
				$sort = is_array( $fields ) && isset( $fields['sort_order'] ) ? absint( $fields['sort_order'] ) : absint( $fields );
				$wpdb->update(
					$stock_table,
					array( 'sort_order' => $sort ),
					array( 'stock_id' => $stock_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		$table = self::table_name();

		foreach ( self::categories() as $category ) {
			if ( ! isset( $posted[ $category ] ) || ! is_array( $posted[ $category ] ) ) {
				continue;
			}

			$existing_rows = self::rows_for( $category );
			$by_id         = array();
			foreach ( $existing_rows as $row ) {
				$by_id[ (int) $row->option_id ] = $row;
			}

			$stocked = self::stocked_codes( $category );

			$parsed = array();
			foreach ( $posted[ $category ] as $option_id => $fields ) {
				$option_id = absint( $option_id );
				if ( $option_id <= 0 || ! isset( $by_id[ $option_id ] ) || ! is_array( $fields ) ) {
					continue;
				}
				$code = (string) $by_id[ $option_id ]->size_code;
				$parsed[ $option_id ] = array(
					'code'             => $code,
					'is_active'        => ! empty( $fields['is_active'] ) ? 1 : 0,
					'issued_size_code' => isset( $fields['issued_size_code'] ) ? self::sanitize_stock_code( wp_unslash( $fields['issued_size_code'] ) ) : '',
					'sort_order'       => isset( $fields['sort_order'] ) ? absint( $fields['sort_order'] ) : 0,
				);
			}

			foreach ( $parsed as $row ) {
				if ( $row['is_active'] && '' === $row['issued_size_code'] ) {
					return new WP_Error(
						'empty_map',
						sprintf(
							/* translators: %s: category label */
							__( 'Every active %s size must map to a size in Stock. Add stock sizes first.', 'remember' ),
							self::category_label( $category )
						)
					);
				}
				if ( '' === $row['issued_size_code'] ) {
					continue;
				}
				if ( ! in_array( $row['issued_size_code'], $stocked, true ) ) {
					return new WP_Error(
						'unstocked_target',
						sprintf(
							/* translators: 1: body size, 2: available size, 3: category label */
							__( '%1$s cannot use %2$s as available because that size is not in %3$s stock. Add it to Stock or pick another.', 'remember' ),
							$row['code'],
							$row['issued_size_code'],
							self::category_label( $category )
						)
					);
				}
			}

			foreach ( $parsed as $option_id => $row ) {
				$update = array(
					'is_active'        => $row['is_active'],
					'issued_size_code' => '' !== $row['issued_size_code'] ? $row['issued_size_code'] : $row['code'],
					'sort_order'       => $row['sort_order'],
				);
				$formats = array( '%d', '%s', '%d' );
				$wpdb->update(
					$table,
					$update,
					array( 'option_id' => $option_id ),
					$formats,
					array( '%d' )
				);
			}
		}

		self::$has_mapping_columns = true;
		return true;
	}
}
