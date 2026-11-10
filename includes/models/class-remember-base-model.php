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
	 * Cached column names for this table.
	 *
	 * @var string[]|null
	 */
	protected $columns_cache = null;

	/**
	 * Database error from the last failed write.
	 *
	 * @var string
	 */
	protected $last_error = '';

	/**
	 * Query from the last failed insert.
	 *
	 * @var string
	 */
	protected $last_query = '';

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
	 * Real column names for this table, cached per instance.
	 *
	 * @return string[]
	 */
	protected function get_columns() {
		if ( null === $this->columns_cache ) {
			$columns = $this->wpdb->get_col( 'SHOW COLUMNS FROM `' . esc_sql( $this->get_table() ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is internal.
			$this->columns_cache = is_array( $columns ) ? $columns : array();
		}
		return $this->columns_cache;
	}

	/**
	 * Resolve an ORDER BY column to a real column of this table.
	 *
	 * Protects ORDER BY against injection: any value that is not an actual
	 * column name falls back to the primary key. Callers no longer have to
	 * whitelist before passing request input.
	 *
	 * @param string $orderby Requested column.
	 * @return string Safe column name.
	 */
	protected function sanitize_orderby( $orderby ) {
		$orderby = (string) $orderby;
		return in_array( $orderby, $this->get_columns(), true ) ? $orderby : $this->primary_key;
	}

	/**
	 * Coerce a sort direction to ASC or DESC.
	 *
	 * @param string $order Requested direction.
	 * @return string 'ASC' or 'DESC'.
	 */
	protected function sanitize_order( $order ) {
		return ( 'DESC' === strtoupper( (string) $order ) ) ? 'DESC' : 'ASC';
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
			$orderby = $this->sanitize_orderby( $args['orderby'] );
			$order   = $this->sanitize_order( $args['order'] );
			$query  .= " ORDER BY `{$orderby}` {$order}";
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
	 * @return int|false The ID of the inserted record, or false on error.
	 */
	public function insert( $data ) {
		$result = $this->wpdb->insert( $this->get_table(), $data );
		if ( $result ) {
			// If primary key was provided in data, return it; otherwise return insert_id
			if ( isset( $data[ $this->primary_key ] ) ) {
				return $data[ $this->primary_key ];
			}
			return $this->wpdb->insert_id;
		}
		// Store error for retrieval (capture immediately before any other DB operations)
		$this->last_error = $this->wpdb->last_error;
		$this->last_query = $this->wpdb->last_query;
		$this->log_write_failure( 'insert', null, $data );
		return false;
	}

	/**
	 * Log a failed write with the ids needed to find the record. Column values are not logged.
	 *
	 * @param string   $operation insert|update|delete.
	 * @param int|null $id        Primary key, when known.
	 * @param array    $data      Data that was written.
	 * @return void
	 */
	protected function log_write_failure( $operation, $id, $data = array() ) {
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';
		$context = array(
			'table' => $this->table_name,
		);
		if ( null !== $id ) {
			$context[ $this->primary_key ] = is_numeric( $id ) ? (int) $id : (string) $id;
		}
		if ( ! empty( $data ) ) {
			$context['columns'] = array_keys( $data );
			foreach ( $data as $key => $value ) {
				if ( '_id' === substr( (string) $key, -3 ) && is_numeric( $value ) && ! isset( $context[ $key ] ) ) {
					$context[ $key ] = (int) $value;
				}
			}
		}
		$context['error'] = $this->last_error;
		Remember_Logger::error( 'Database ' . $operation . ' failed', $context );
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
		$result = $this->wpdb->update(
			$this->get_table(),
			$data,
			array( $this->primary_key => $id )
		);
		if ( false === $result ) {
			$this->last_error = $this->wpdb->last_error;
			$this->log_write_failure( 'update', $id, $data );
		}
		return $result;
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete(
			$this->get_table(),
			array( $this->primary_key => $id )
		);
		if ( false === $result ) {
			$this->last_error = $this->wpdb->last_error;
			$this->log_write_failure( 'delete', $id );
		}
		return $result;
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

	/**
	 * Build the shared "find a person" search conditions for the admin lists.
	 *
	 * A partial term matches the account (login, email, display name) and the
	 * profile (legal first name, legal last name, and the full legal name).
	 * When the term holds at least 3 digits it also matches the stored cell
	 * phone with punctuation stripped, so "555-1019" finds "+18055551019".
	 * Lists that do not show a phone skip that match. Callers OR in any extra
	 * columns of their own (member number, event name) and join the profiles
	 * table as `p` and the users table as `u`.
	 *
	 * @param string $term          Raw search term.
	 * @param bool   $include_phone Whether to match the cell phone.
	 * @return array {
	 *     @type string[] $conditions SQL LIKE fragments.
	 *     @type string[] $params     One %s value per fragment.
	 * }
	 */
	protected function person_search_clauses( $term, $include_phone = true ) {
		$term = trim( (string) $term );
		$like = '%' . $this->wpdb->esc_like( $term ) . '%';

		$conditions = array(
			'u.display_name LIKE %s',
			'u.user_email LIKE %s',
			'u.user_login LIKE %s',
			'p.legal_first_name LIKE %s',
			'p.legal_last_name LIKE %s',
			"CONCAT_WS(' ', p.legal_first_name, p.legal_last_name) LIKE %s",
		);
		$params     = array_fill( 0, count( $conditions ), $like );

		$digits = preg_replace( '/\D+/', '', $term );
		if ( $include_phone && strlen( $digits ) >= 3 ) {
			$conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.cell_phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') LIKE %s";
			$params[]     = '%' . $this->wpdb->esc_like( $digits ) . '%';
		}

		return array(
			'conditions' => $conditions,
			'params'     => $params,
		);
	}
}
