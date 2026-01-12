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
		$target_version = '1.5.0'; // Version that adds show_in_frontend to roles
		
		// Update to 1.1.0 (unvetted status)
		if ( version_compare( $current_version, '1.1.0', '<' ) ) {
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
			update_option( 'remember_db_version', '1.1.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.1.0' ) );
		}
		
		// Update to 1.2.0 (privacy fields)
		if ( version_compare( $current_version, '1.2.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => $current_version, 'to' => '1.2.0' ) );
			
			// Add privacy fields to member_profiles table
			$profiles_table = $wpdb->prefix . 'remember_member_profiles';
			
			// Check if columns already exist
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$profiles_table}" );
			
			$privacy_fields = array(
				'share_email_with_events'      => "TINYINT(1) DEFAULT 0",
				'share_phone_with_events'      => "TINYINT(1) DEFAULT 0",
				'share_location_with_events'   => "TINYINT(1) DEFAULT 0",
				'share_im_with_events'         => "TINYINT(1) DEFAULT 0",
				'share_interests_with_events'  => "TINYINT(1) DEFAULT 0",
			);
			
			$previous_field = 'emergency_contact_relationship';
			foreach ( $privacy_fields as $field_name => $field_type ) {
				if ( ! in_array( $field_name, $columns, true ) ) {
					// Escape field names for SQL
					$field_name_safe = esc_sql( $field_name );
					$previous_field_safe = esc_sql( $previous_field );
					
					$result = $wpdb->query( "ALTER TABLE {$profiles_table} ADD COLUMN `{$field_name_safe}` {$field_type} AFTER `{$previous_field_safe}`" );
					
					if ( $result !== false ) {
						Remember_Logger::info( "Added {$field_name} column to member_profiles table" );
					} else {
						Remember_Logger::error( "Failed to add {$field_name} column", array( 'error' => $wpdb->last_error ) );
					}
				}
				$previous_field = $field_name;
			}
			
			// Update version
			update_option( 'remember_db_version', '1.2.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.2.0' ) );
		}
		
		// Update to 1.3.0 (remove unique constraint on vetting.member_id to allow multiple cases)
		if ( version_compare( $current_version, '1.3.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => $current_version, 'to' => '1.3.0' ) );
			
			$vetting_table = $wpdb->prefix . 'remember_vetting';
			
			// Check if unique key exists
			$indexes = $wpdb->get_results( "SHOW INDEX FROM {$vetting_table} WHERE Key_name = 'member_id' AND Non_unique = 0" );
			
			if ( ! empty( $indexes ) ) {
				// Drop the unique key
				$result = $wpdb->query( "ALTER TABLE {$vetting_table} DROP INDEX member_id" );
				
				if ( $result !== false ) {
					// Add back as a regular index (non-unique)
					$result2 = $wpdb->query( "ALTER TABLE {$vetting_table} ADD INDEX member_id (member_id)" );
					
					if ( $result2 !== false ) {
						Remember_Logger::info( 'Removed unique constraint on vetting.member_id, allowing multiple cases per member' );
					} else {
						Remember_Logger::error( 'Failed to add regular index on member_id', array( 'error' => $wpdb->last_error ) );
					}
				} else {
					Remember_Logger::error( 'Failed to drop unique key on member_id', array( 'error' => $wpdb->last_error ) );
				}
			}
			
			// Update version
			update_option( 'remember_db_version', '1.3.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.3.0' ) );
		}
		
		// Update to 1.4.0 (auto-assign default timezone to existing users)
		if ( version_compare( $current_version, '1.4.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => $current_version, 'to' => '1.4.0' ) );
			
			// Get all users without timezone_string meta
			$users_without_timezone = $wpdb->get_col(
				"SELECT u.ID FROM {$wpdb->users} u
				LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'timezone_string'
				WHERE um.meta_value IS NULL"
			);
			
			$default_timezone = 'America/Los_Angeles';
			$updated_count = 0;
			
			foreach ( $users_without_timezone as $user_id ) {
				update_user_meta( $user_id, 'timezone_string', $default_timezone );
				$updated_count++;
			}
			
			if ( $updated_count > 0 ) {
				Remember_Logger::info( "Assigned default timezone to {$updated_count} users" );
			}
			
			// Update version
			update_option( 'remember_db_version', '1.4.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.4.0' ) );
		}
		
		// Update to 1.5.0 (add show_in_frontend field to roles table)
		if ( version_compare( $current_version, '1.5.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => $current_version, 'to' => '1.5.0' ) );
			
			$roles_table = $wpdb->prefix . 'remember_roles';
			
			// Check if column already exists
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$roles_table}" );
			
			if ( ! in_array( 'show_in_frontend', $columns, true ) ) {
				// Add show_in_frontend column with default value of 1 (true)
				$result = $wpdb->query( "ALTER TABLE {$roles_table} ADD COLUMN show_in_frontend BOOLEAN DEFAULT 1 AFTER is_event_role" );
				
				if ( $result !== false ) {
					Remember_Logger::info( 'Added show_in_frontend column to roles table' );
				} else {
					Remember_Logger::error( 'Failed to add show_in_frontend column', array( 'error' => $wpdb->last_error ) );
				}
			}
			
			// Set Event Administrator role to not show in frontend (always do this, even if column already existed)
			$event_admin_role_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT role_id FROM {$roles_table} WHERE role_name = %s",
				'Event Administrator'
			) );
			
			if ( $event_admin_role_id ) {
				$update_result = $wpdb->update(
					$roles_table,
					array( 'show_in_frontend' => 0 ),
					array( 'role_id' => $event_admin_role_id ),
					array( '%d' ),
					array( '%d' )
				);
				
				if ( $update_result !== false ) {
					Remember_Logger::info( 'Set Event Administrator role to not show in frontend' );
				} else {
					Remember_Logger::error( 'Failed to update Event Administrator role', array( 'error' => $wpdb->last_error ) );
				}
			}
			
			// Update version
			update_option( 'remember_db_version', '1.5.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.5.0' ) );
		}
	}
}
