<?php
/**
 * Database updater class
 *
 * Handles database schema updates and migrations.
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database updater class.
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */
class Remember_Database_Updater {

	/**
	 * Update database schema if needed.
	 */
	public static function update_schema() {
		global $wpdb;
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';
		
		// Check current schema version
		$current_version = get_option( 'remember_db_version', '0.0.0' );
		$target_version = '1.1.0'; // Version that adds 'unvetted' status
		
		if ( version_compare( $current_version, $target_version, '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => $current_version, 'to' => $target_version ) );
			
			// Update members table to include 'unvetted' status
			$table_name = $wpdb->prefix . 'remember_members';
			$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'status'" );
			
			if ( ! empty( $column_exists ) ) {
				// Check current ENUM values
				$column_info = $wpdb->get_row( "SHOW COLUMNS FROM {$table_name} WHERE Field = 'status'" );
				if ( $column_info && strpos( $column_info->Type, 'unvetted' ) === false ) {
					// Add 'unvetted' to the ENUM
					$result = $wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN status ENUM('pending_vetting', 'unvetted', 'in_vetting', 'vetted', 'rejected', 'inactive') DEFAULT 'pending_vetting'" );
					
					if ( $result !== false ) {
						Remember_Logger::info( 'Members table updated with unvetted status' );
					} else {
						Remember_Logger::error( 'Failed to update members table', array( 'error' => $wpdb->last_error ) );
					}
				}
			}
			
			// Update vetting table to allow nullable primary_vetter_id
			$vetting_table = $wpdb->prefix . 'remember_vetting';
			$vetting_column = $wpdb->get_row( "SHOW COLUMNS FROM {$vetting_table} WHERE Field = 'primary_vetter_id'" );
			
			if ( $vetting_column && strpos( $vetting_column->Null, 'YES' ) === false ) {
				$result = $wpdb->query( "ALTER TABLE {$vetting_table} MODIFY COLUMN primary_vetter_id BIGINT(20) UNSIGNED DEFAULT NULL" );
				
				if ( $result !== false ) {
					Remember_Logger::info( 'Vetting table updated to allow nullable primary_vetter_id' );
				} else {
					Remember_Logger::error( 'Failed to update vetting table', array( 'error' => $wpdb->last_error ) );
				}
			}
			
			// Update version
			update_option( 'remember_db_version', $target_version );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => $target_version ) );
		}
	}
}
