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

	/**
	 * Get active products only.
	 *
	 * @return array
	 */
	public function get_active() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->get_table()} WHERE is_active = 1 ORDER BY product_name ASC"
		);
	}

	/**
	 * Soft-delete a product (set inactive).
	 *
	 * @param int $product_id Product ID.
	 * @return int|false
	 */
	public function deactivate( $product_id ) {
		return $this->update(
			$product_id,
			array(
				'is_active'  => 0,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Restore a product (set active).
	 *
	 * @param int $product_id Product ID.
	 * @return int|false
	 */
	public function reactivate( $product_id ) {
		return $this->update(
			$product_id,
			array(
				'is_active'  => 1,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}
}
