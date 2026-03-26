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

		Remember_Logger::activation_debug(
			'update_schema: enter',
			array( 'remember_db_version' => get_option( 'remember_db_version', '0.0.0' ) )
		);

		// Migrations compare fresh DB version each time so only the next pending migration runs per request.
		// Update to 1.1.0 (unvetted status)
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.1.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.1.0' ) );
			
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
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.2.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.2.0' ) );
			
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
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.3.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.3.0' ) );
			
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
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.4.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.4.0' ) );
			
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
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.5.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.5.0' ) );
			
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

		// Update to 1.6.0 (multiple reMember line items may map to the same QuickBooks item — drop UNIQUE on quickbooks_product_id).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.6.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.6.0' ) );

			$products_table = $wpdb->prefix . 'remember_products';
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $products_table ) );

			if ( $table_exists === $products_table ) {
				$qb_indexes = $wpdb->get_results( "SHOW INDEX FROM {$products_table} WHERE Key_name = 'quickbooks_product_id'" );
				if ( ! empty( $qb_indexes ) ) {
					$drop = $wpdb->query( "ALTER TABLE {$products_table} DROP INDEX quickbooks_product_id" );
					if ( false !== $drop ) {
						$wpdb->query( "ALTER TABLE {$products_table} ADD KEY idx_quickbooks_product_id (quickbooks_product_id)" );
						Remember_Logger::info( 'remember_products: replaced UNIQUE(quickbooks_product_id) with non-unique index' );
					} else {
						Remember_Logger::error( 'remember_products: failed to drop UNIQUE(quickbooks_product_id)', array( 'error' => $wpdb->last_error ) );
					}
				} else {
					$idx_rows = $wpdb->get_results( "SHOW INDEX FROM {$products_table} WHERE Key_name = 'idx_quickbooks_product_id'" );
					if ( empty( $idx_rows ) ) {
						$wpdb->query( "ALTER TABLE {$products_table} ADD KEY idx_quickbooks_product_id (quickbooks_product_id)" );
					}
				}
			}

			update_option( 'remember_db_version', '1.6.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.6.0' ) );
		}

		// Update to 1.7.0 (track when applications are added to waitlist).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.7.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.7.0' ) );

			$applications_table = $wpdb->prefix . 'remember_event_applications';
			$table_exists       = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $applications_table ) );

			if ( $table_exists === $applications_table ) {
				$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$applications_table}" );
				if ( ! in_array( 'waitlisted_at', $columns, true ) ) {
					$add_column = $wpdb->query( "ALTER TABLE {$applications_table} ADD COLUMN waitlisted_at DATETIME DEFAULT NULL AFTER applied_at" );
					if ( false !== $add_column ) {
						Remember_Logger::info( 'Added waitlisted_at column to remember_event_applications' );
					} else {
						Remember_Logger::error( 'Failed to add waitlisted_at column', array( 'error' => $wpdb->last_error ) );
					}
				}
			}

			update_option( 'remember_db_version', '1.7.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.7.0' ) );
		}

		// Update to 1.8.0 (QuickBooks mappings in remember_qb_item_mappings; remember_products = catalog only).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.8.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.8.0' ) );

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-database.php';
			$db = new Remember_Database();
			Remember_Logger::activation_debug( 'migration 1.8.0: create_qb_item_mappings_table' );
			$db->create_qb_item_mappings_table();

			$products_table = $wpdb->prefix . 'remember_products';
			$roles_table    = $wpdb->prefix . 'remember_roles';
			$map_table      = $wpdb->prefix . 'remember_qb_item_mappings';

			$product_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$products_table}", 0 );
			if ( is_array( $product_cols ) && in_array( 'quickbooks_product_id', $product_cols, true ) ) {
				Remember_Logger::activation_debug( 'migration 1.8.0: INSERT role mappings into qb_item_mappings' );
				// Role mappings: product rows whose name matched an event role.
				$wpdb->query(
					"INSERT INTO {$map_table} (entity_type, entity_id, quickbooks_product_id, quickbooks_product_name, last_sync_at, created_at, updated_at)
					SELECT 'role', r.role_id, p.quickbooks_product_id, p.quickbooks_product_name, p.last_sync_at, p.created_at, p.updated_at
					FROM {$products_table} p
					INNER JOIN {$roles_table} r ON r.role_name = p.product_name AND r.is_event_role = 1
					WHERE p.quickbooks_product_id IS NOT NULL AND p.quickbooks_product_id != ''
					ON DUPLICATE KEY UPDATE
						quickbooks_product_id = VALUES(quickbooks_product_id),
						quickbooks_product_name = VALUES(quickbooks_product_name),
						last_sync_at = VALUES(last_sync_at),
						updated_at = VALUES(updated_at)"
				);

				Remember_Logger::activation_debug( 'migration 1.8.0: INSERT product mappings into qb_item_mappings' );
				// Product (catalog) mappings: remaining product rows with QB ids not consumed as roles.
				$wpdb->query(
					"INSERT INTO {$map_table} (entity_type, entity_id, quickbooks_product_id, quickbooks_product_name, last_sync_at, created_at, updated_at)
					SELECT 'product', p.product_id, p.quickbooks_product_id, p.quickbooks_product_name, p.last_sync_at, p.created_at, p.updated_at
					FROM {$products_table} p
					LEFT JOIN {$roles_table} r ON r.role_name = p.product_name AND r.is_event_role = 1
					WHERE r.role_id IS NULL
					AND p.quickbooks_product_id IS NOT NULL AND p.quickbooks_product_id != ''
					ON DUPLICATE KEY UPDATE
						quickbooks_product_id = VALUES(quickbooks_product_id),
						quickbooks_product_name = VALUES(quickbooks_product_name),
						last_sync_at = VALUES(last_sync_at),
						updated_at = VALUES(updated_at)"
				);

				Remember_Logger::activation_debug( 'migration 1.8.0: DELETE legacy product role mirrors' );
				// Remove legacy rows that were only role-name mirrors in the products table.
				$wpdb->query(
					"DELETE p FROM {$products_table} p
					INNER JOIN {$roles_table} r ON r.role_name = p.product_name AND r.is_event_role = 1"
				);

				Remember_Logger::activation_debug( 'migration 1.8.0: ALTER remember_products DROP QB columns' );
				// Drop QuickBooks columns from remember_products (indexes on those columns drop with the columns).
				$wpdb->query( "ALTER TABLE {$products_table} DROP COLUMN quickbooks_product_id, DROP COLUMN quickbooks_product_name, DROP COLUMN last_sync_at" );
			}

			update_option( 'remember_db_version', '1.8.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.8.0' ) );
			Remember_Logger::activation_debug( 'migration 1.8.0: finished' );
		}

		// Update to 1.9.0 (QuickBooks invoice DocNumber for display; distinct from QBO entity Id in quickbooks_invoice_id).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.9.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.9.0' ) );

			$payments_table = $wpdb->prefix . 'remember_payments';
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $payments_table ) ) === $payments_table;
			if ( $table_exists ) {
				$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$payments_table}", 0 );
				if ( is_array( $cols ) && ! in_array( 'quickbooks_invoice_number', $cols, true ) ) {
					$ok = $wpdb->query(
						"ALTER TABLE {$payments_table} ADD COLUMN quickbooks_invoice_number VARCHAR(50) DEFAULT NULL AFTER quickbooks_invoice_id"
					);
					if ( false === $ok ) {
						Remember_Logger::error(
							'Failed to add quickbooks_invoice_number to remember_payments',
							array( 'error' => $wpdb->last_error )
						);
					} else {
						Remember_Logger::info( 'Added quickbooks_invoice_number to remember_payments' );
					}
				}
			}

			update_option( 'remember_db_version', '1.9.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.9.0' ) );
		}

		// Update to 1.10.0 (catalog default_price for add-on products).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.10.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.10.0' ) );

			$products_table = $wpdb->prefix . 'remember_products';
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $products_table ) ) === $products_table;
			if ( $table_exists ) {
				$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$products_table}", 0 );
				if ( is_array( $cols ) && ! in_array( 'default_price', $cols, true ) ) {
					$ok = $wpdb->query(
						"ALTER TABLE {$products_table} ADD COLUMN default_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER product_type"
					);
					if ( false === $ok ) {
						Remember_Logger::error(
							'Failed to add default_price to remember_products',
							array( 'error' => $wpdb->last_error )
						);
					} else {
						Remember_Logger::info( 'Added default_price to remember_products' );
					}
				}
			}

			update_option( 'remember_db_version', '1.10.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.10.0' ) );
		}

		// Update to 1.11.0 (QuickBooks: one JSON row per Payment applied to an invoice for billing register).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.11.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.11.0' ) );

			$payments_table = $wpdb->prefix . 'remember_payments';
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $payments_table ) ) === $payments_table;
			if ( $table_exists ) {
				$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$payments_table}", 0 );
				if ( is_array( $cols ) && ! in_array( 'quickbooks_payment_lines', $cols, true ) ) {
					$ok = $wpdb->query(
						"ALTER TABLE {$payments_table} ADD COLUMN quickbooks_payment_lines LONGTEXT DEFAULT NULL AFTER quickbooks_invoice_number"
					);
					if ( false === $ok ) {
						Remember_Logger::error(
							'Failed to add quickbooks_payment_lines to remember_payments',
							array( 'error' => $wpdb->last_error )
						);
					} else {
						Remember_Logger::info( 'Added quickbooks_payment_lines to remember_payments' );
					}
				}
			}

			update_option( 'remember_db_version', '1.11.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.11.0' ) );
		}

		// Update to 1.12.0 (QuickBooks invoice sort timestamp for billing register ordering).
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.12.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.12.0' ) );

			$payments_table = $wpdb->prefix . 'remember_payments';
			$table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $payments_table ) ) === $payments_table;
			if ( $table_exists ) {
				$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$payments_table}", 0 );
				if ( is_array( $cols ) && ! in_array( 'quickbooks_invoice_sort_ts', $cols, true ) ) {
					$ok = $wpdb->query(
						"ALTER TABLE {$payments_table} ADD COLUMN quickbooks_invoice_sort_ts BIGINT(20) UNSIGNED DEFAULT NULL AFTER quickbooks_invoice_number"
					);
					if ( false === $ok ) {
						Remember_Logger::error(
							'Failed to add quickbooks_invoice_sort_ts to remember_payments',
							array( 'error' => $wpdb->last_error )
						);
					} else {
						Remember_Logger::info( 'Added quickbooks_invoice_sort_ts to remember_payments' );
					}
				}
			}

			update_option( 'remember_db_version', '1.12.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.12.0' ) );
		}

		// Update to 1.13.0 — ensure member registration page exists and is stored in remember_created_pages.
		if ( version_compare( get_option( 'remember_db_version', '0.0.0' ), '1.13.0', '<' ) ) {
			Remember_Logger::info( 'Updating database schema', array( 'from' => get_option( 'remember_db_version', '0.0.0' ), 'to' => '1.13.0' ) );

			require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-page-creator.php';
			Remember_Logger::activation_debug( 'migration 1.13.0: ensure member_register page' );
			Remember_Page_Creator::create_pages( array( 'member_register' ) );

			update_option( 'remember_db_version', '1.13.0' );
			Remember_Logger::info( 'Database schema updated successfully', array( 'version' => '1.13.0' ) );
		}

		Remember_Logger::activation_debug(
			'update_schema: exit',
			array( 'remember_db_version' => get_option( 'remember_db_version', '0.0.0' ) )
		);
	}
}
