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

	/**
	 * Ensure remember_products has a row for each event role and each distinct merchandise name.
	 * The mapping UI lists this table; without rows, roles never appear until sync or invoice creation.
	 *
	 * @return void
	 */
	public function ensure_mapping_rows_for_line_items() {
		require_once plugin_dir_path( __FILE__ ) . 'class-role.php';
		$role_model = new Remember_Role();
		foreach ( $role_model->get_event_roles() as $role ) {
			if ( $this->get_by_name( $role->role_name ) ) {
				continue;
			}
			$now = current_time( 'mysql' );
			$this->insert(
				array(
					'product_name' => $role->role_name,
					'description'  => sprintf( __( 'Event role: %s', 'remember' ), $role->role_name ),
					'product_type' => 'Service',
					'is_active'    => 1,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
		}

		$merch_table = $this->wpdb->prefix . 'remember_event_merchandise';
		$names       = $this->wpdb->get_col(
			"SELECT DISTINCT merchandise_name FROM {$merch_table}
			WHERE merchandise_name IS NOT NULL AND merchandise_name != ''
			ORDER BY merchandise_name ASC"
		);
		foreach ( $names as $merch_name ) {
			if ( $this->get_by_name( $merch_name ) ) {
				continue;
			}
			$now = current_time( 'mysql' );
			$this->insert(
				array(
					'product_name' => $merch_name,
					'description'  => sprintf( __( 'Merchandise: %s', 'remember' ), $merch_name ),
					'product_type' => 'Inventory',
					'is_active'    => 1,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
		}
	}
}
