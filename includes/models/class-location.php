<?php
/**
 * Location model class
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
 * Location model class.
 *
 * @package    reMember
 * @subpackage reMember/includes/models
 */
class Remember_Location extends Remember_Base_Model {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	protected $table_name = 'locations';

	/**
	 * Primary key column name.
	 *
	 * @var string
	 */
	protected $primary_key = 'location_id';

	/**
	 * Create a new location.
	 *
	 * @param array $data Location data.
	 * @return int|false Location ID or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}

	/**
	 * Update a location.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $data        Location data.
	 * @return int|false
	 */
	public function update( $location_id, $data ) {
		$data['updated_at'] = current_time( 'mysql' );
		return parent::update( $location_id, $data );
	}

	/**
	 * Get active locations.
	 *
	 * @return array
	 */
	public function get_active() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->get_table()} WHERE is_active = 1 ORDER BY location_name ASC"
		);
	}

	/**
	 * Get locations by city.
	 *
	 * @param string $city City name.
	 * @return array
	 */
	public function get_by_city( $city ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE address_city = %s ORDER BY location_name ASC",
				$city
			)
		);
	}

	/**
	 * Get locations by state.
	 *
	 * @param string $state State/province code.
	 * @return array
	 */
	public function get_by_state( $state ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE address_state = %s ORDER BY location_name ASC",
				$state
			)
		);
	}

	/**
	 * Get unique cities.
	 *
	 * @return array Array of city names.
	 */
	public function get_cities() {
		global $wpdb;
		$cities = $wpdb->get_col(
			"SELECT DISTINCT address_city FROM {$this->get_table()} WHERE address_city IS NOT NULL AND address_city != '' ORDER BY address_city ASC"
		);
		return $cities ? $cities : array();
	}

	/**
	 * Get unique states.
	 *
	 * @return array Array of state codes.
	 */
	public function get_states() {
		global $wpdb;
		$states = $wpdb->get_col(
			"SELECT DISTINCT address_state FROM {$this->get_table()} WHERE address_state IS NOT NULL AND address_state != '' ORDER BY address_state ASC"
		);
		return $states ? $states : array();
	}

	/**
	 * Get location by name.
	 *
	 * @param string $name Location name.
	 * @return object|null Location object or null if not found.
	 */
	public function get_by_name( $name ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table()} WHERE location_name = %s LIMIT 1",
				$name
			)
		);
	}
}
