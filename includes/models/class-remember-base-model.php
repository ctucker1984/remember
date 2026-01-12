<?php
/**
 * Base model class for all reMember models
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Base model class.
 *
 * Provides common database operations for all models.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
abstract class Remember_Base_Model {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'id';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'remember_';
	}

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	protected function get_table() {
		return $this->prefix . $this->table_name;
	}

	/**
	 * Get a record by ID.
	 *
	 * @param int $id Record ID.
	 * @return object|null
	 */
	public function get( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE {$this->primary_key} = %d",
				$id
			)
		);
	}

	/**
	 * Get all records.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		$defaults = array(
			'limit'  => -1,
			'offset' => 0,
			'orderby' => $this->primary_key,
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$query = "SELECT * FROM {$this->get_table()}";

		if ( ! empty( $args['orderby'] ) ) {
			$query .= " ORDER BY {$args['orderby']} {$args['order']}";
		}

		if ( $args['limit'] > 0 ) {
			$query .= $this->wpdb->prepare( " LIMIT %d OFFSET %d", $args['limit'], $args['offset'] );
		}

		return $this->wpdb->get_results( $query );
	}

	/**
	 * Insert a new record.
	 *
	 * @param array $data Data to insert.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public function insert( $data ) {
		$result = $this->wpdb->insert( $this->get_table(), $data );
		if ( $result ) {
			return $this->wpdb->insert_id;
		}
		// Store error for retrieval (capture immediately before any other DB operations)
		$this->last_error = $this->wpdb->last_error;
		$this->last_query = $this->wpdb->last_query;
		return false;
	}

	/**
	 * Get last database error.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return isset( $this->last_error ) ? $this->last_error : '';
	}

	/**
	 * Get last database query.
	 *
	 * @return string
	 */
	public function get_last_query() {
		return isset( $this->last_query ) ? $this->last_query : '';
	}

	/**
	 * Update a record.
	 *
	 * @param int   $id   Record ID.
	 * @param array $data Data to update.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update( $id, $data ) {
		return $this->wpdb->update(
			$this->get_table(),
			$data,
			array( $this->primary_key => $id )
		);
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete( $id ) {
		return $this->wpdb->delete(
			$this->get_table(),
			array( $this->primary_key => $id )
		);
	}

	/**
	 * Count records.
	 *
	 * @param array $where WHERE conditions.
	 * @return int
	 */
	public function count( $where = array() ) {
		$query = "SELECT COUNT(*) FROM {$this->get_table()}";
		
		if ( ! empty( $where ) ) {
			$conditions = array();
			foreach ( $where as $column => $value ) {
				if ( is_numeric( $value ) ) {
					$conditions[] = $this->wpdb->prepare( "{$column} = %d", $value );
				} else {
					$conditions[] = $this->wpdb->prepare( "{$column} = %s", $value );
				}
			}
			$query .= ' WHERE ' . implode( ' AND ', $conditions );
		}

		return (int) $this->wpdb->get_var( $query );
	}
}
