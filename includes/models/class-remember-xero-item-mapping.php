<?php
/**
 * Xero item mapping model (roles + catalog products).
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
 * Maps reMember entities to Xero Item IDs.
 *
 * entity_type = 'role'     → entity_id = remember_roles.role_id
 * entity_type = 'product'  → entity_id = remember_products.product_id
 */
class Remember_Xero_Item_Mapping extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'xero_item_mappings';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'mapping_id';

	/**
	 * Get mapping for an entity.
	 *
	 * @param string $entity_type 'role' or 'product'.
	 * @param int    $entity_id   Role ID or product ID.
	 * @return object|null
	 */
	public function get_by_entity( $entity_type, $entity_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE entity_type = %s AND entity_id = %d",
				$entity_type,
				$entity_id
			)
		);
	}

	/**
	 * Upsert Xero mapping for an entity.
	 *
	 * @param string      $entity_type  'role' or 'product'.
	 * @param int         $entity_id    Role ID or product ID.
	 * @param string|null $xero_item_id Xero Item Id, or empty to remove mapping.
	 * @param string|null $xero_item_name Cached Xero name.
	 * @return bool
	 */
	public function upsert( $entity_type, $entity_id, $xero_item_id, $xero_item_name = null ) {
		global $wpdb;
		$entity_id = absint( $entity_id );
		if ( $entity_id <= 0 || ! in_array( $entity_type, array( 'role', 'product' ), true ) ) {
			return false;
		}

		if ( null === $xero_item_id || '' === $xero_item_id ) {
			return (bool) $wpdb->delete(
				$this->get_table(),
				array(
					'entity_type' => $entity_type,
					'entity_id'   => $entity_id,
				),
				array( '%s', '%d' )
			);
		}

		$now  = current_time( 'mysql' );
		$data = array(
			'entity_type'    => $entity_type,
			'entity_id'      => $entity_id,
			'xero_item_id'   => sanitize_text_field( $xero_item_id ),
			'xero_item_name' => $xero_item_name ? sanitize_text_field( $xero_item_name ) : null,
			'last_sync_at'   => $now,
			'updated_at'     => $now,
		);

		$existing = $this->get_by_entity( $entity_type, $entity_id );
		if ( $existing ) {
			return false !== $this->wpdb->update(
				$this->get_table(),
				array(
					'xero_item_id'   => $data['xero_item_id'],
					'xero_item_name' => $data['xero_item_name'],
					'last_sync_at'   => $data['last_sync_at'],
					'updated_at'     => $data['updated_at'],
				),
				array(
					'entity_type' => $entity_type,
					'entity_id'   => $entity_id,
				),
				array( '%s', '%s', '%s', '%s' ),
				array( '%s', '%d' )
			);
		}

		$data['created_at'] = $now;
		return false !== $this->insert( $data );
	}
}
