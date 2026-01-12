<?php
/**
 * Fired during plugin activation
 *
 * @package    reMember
 * @subpackage reMember/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    reMember
 * @subpackage reMember/includes
 */
class Remember_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( __FILE__ ) . 'utilities/class-remember-logger.php';
		Remember_Logger::info( 'Starting plugin activation' );

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			Remember_Logger::error( 'WordPress version too old', array( 'version' => get_bloginfo( 'version' ) ) );
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( __( 'reMember requires WordPress 5.0 or higher. Please upgrade WordPress and try again.', 'remember' ) );
		}

		// Create database tables
		Remember_Logger::debug( 'Creating database tables' );
		require_once plugin_dir_path( __FILE__ ) . 'database/class-remember-database.php';
		$database = new Remember_Database();
		$database->create_tables();
		
		// Update database schema if needed
		Remember_Logger::debug( 'Updating database schema if needed' );
		require_once plugin_dir_path( __FILE__ ) . 'database/class-remember-database-updater.php';
		Remember_Database_Updater::update_schema();

		// Seed initial data
		Remember_Logger::debug( 'Seeding initial data' );
		require_once plugin_dir_path( __FILE__ ) . 'database/class-remember-seeder.php';
		$seeder = new Remember_Seeder();
		$seeder->seed();

		// Set up capabilities
		Remember_Logger::debug( 'Setting up capabilities' );
		require_once plugin_dir_path( __FILE__ ) . 'utilities/class-remember-capabilities.php';
		Remember_Capabilities::setup_capabilities();

		// Assign current user as first admin (all capabilities)
		require_once plugin_dir_path( __FILE__ ) . 'utilities/class-remember-capabilities.php';
		require_once plugin_dir_path( __FILE__ ) . 'models/class-member.php';
		require_once plugin_dir_path( __FILE__ ) . 'models/class-role.php';
		
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->ID > 0 ) {
			// Assign all capabilities to WordPress admin
			$all_capabilities = Remember_Capabilities::get_all_capabilities();
			foreach ( array_keys( $all_capabilities ) as $cap ) {
				$current_user->add_cap( $cap );
			}
			
			// Create member record for WordPress admin
			$member_model = new Remember_Member();
			$existing_member = $member_model->get( $current_user->ID );
			if ( ! $existing_member ) {
				// Create member record
				$member_model->create( $current_user->ID, 'vetted' );
				Remember_Logger::debug( 'Member record created for WordPress admin', array( 'user_id' => $current_user->ID ) );
				
				// Create initial profile record with WP user data
				global $wpdb;
				$first_name = get_user_meta( $current_user->ID, 'first_name', true );
				$last_name = get_user_meta( $current_user->ID, 'last_name', true );
				
				// Use display name parts if first/last name aren't set
				if ( empty( $first_name ) && empty( $last_name ) && ! empty( $current_user->display_name ) ) {
					$name_parts = explode( ' ', $current_user->display_name, 2 );
					$first_name = $name_parts[0];
					$last_name = isset( $name_parts[1] ) ? $name_parts[1] : '';
				}
				
				// Set default emergency contact if we have names
				$emergency_first = ! empty( $first_name ) ? $first_name : '';
				$emergency_last = ! empty( $last_name ) ? $last_name : '';
				
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_profiles',
					array(
						'member_id'                    => $current_user->ID,
						'legal_first_name'            => $first_name,
						'legal_last_name'             => $last_name,
						'emergency_contact_first'     => $emergency_first,
						'emergency_contact_last'      => $emergency_last,
						'emergency_contact_phone'     => '',
						'emergency_contact_relationship' => '',
						'created_at'                  => current_time( 'mysql' ),
						'updated_at'                  => current_time( 'mysql' ),
					)
				);
				Remember_Logger::debug( 'Member profile created for WordPress admin with WP user data', array( 'user_id' => $current_user->ID ) );
			}
			
			// Get System Administrator role ID
			$role_model = new Remember_Role();
			global $wpdb;
			$system_admin_role_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT role_id FROM {$wpdb->prefix}remember_roles WHERE role_name = %s",
				'System Administrator'
			) );
			
			if ( $system_admin_role_id ) {
				// Check if role is already assigned
				$existing_role = $wpdb->get_var( $wpdb->prepare(
					"SELECT member_role_id FROM {$wpdb->prefix}remember_member_roles WHERE member_id = %d AND role_id = %d",
					$current_user->ID,
					$system_admin_role_id
				) );
				
				if ( ! $existing_role ) {
					// Assign System Administrator role to member
					$wpdb->insert(
						$wpdb->prefix . 'remember_member_roles',
						array(
							'member_id'  => $current_user->ID,
							'role_id'    => $system_admin_role_id,
							'approved_at' => current_time( 'mysql' ),
							'approved_by' => $current_user->ID,
							'created_at' => current_time( 'mysql' ),
						),
						array( '%d', '%d', '%s', '%d', '%s' )
					);
					Remember_Logger::debug( 'System Administrator role assigned to WordPress admin', array( 'user_id' => $current_user->ID, 'role_id' => $system_admin_role_id ) );
				}
			}
		}

		// Store plugin version
		update_option( 'remember_version', REMEMBER_VERSION );
		Remember_Logger::debug( 'Plugin version stored', array( 'version' => REMEMBER_VERSION ) );

		// Set default options
		$default_options = array(
			'photo_max_size'      => 2097152, // 2MB in bytes
			'photo_max_dimensions' => 800,
			'qb_sync_interval'    => 3600, // 1 hour in seconds
			'vetting_workflow'    => 'on_join', // Default: vet on member join
		);
		update_option( 'remember_options', $default_options );

		// Schedule QuickBooks sync cron job
		if ( ! wp_next_scheduled( 'remember_qb_sync' ) ) {
			wp_schedule_event( time(), 'hourly', 'remember_qb_sync' );
			Remember_Logger::debug( 'Scheduled QuickBooks sync cron job' );
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set flag to show setup wizard
		set_transient( 'remember_show_setup_wizard', true, 3600 ); // Show for 1 hour

		Remember_Logger::info( 'Plugin activation completed successfully' );
	}
}
