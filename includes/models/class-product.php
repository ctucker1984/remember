<?php
/**
 * Product model class
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-base-model.php';

/**
 * Product model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Product extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'products';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'product_id';

	/**
	 * Get product by QuickBooks product ID.
	 *
	 * @param string $qb_product_id QuickBooks product ID.
	 * @return object|null
	 */
	public function get_by_qb_id( $qb_product_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE quickbooks_product_id = %s",
				$qb_product_id
			)
		);
	}

	/**
	 * Get product by name.
	 *
	 * @param string $product_name Product name.
	 * @return object|null
	 */
	public function get_by_name( $product_name ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE product_name = %s",
				$product_name
			)
		);
	}
}
