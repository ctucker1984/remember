<?php
/**
 * Database seeder for initial data
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database seeder class.
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */
class Remember_Seeder {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'remember_';
	}

	/**
	 * Seed initial data.
	 *
	 * @since    1.0.0
	 */
	public function seed() {
		$this->seed_default_location();
		$this->seed_default_roles();
		$this->seed_social_media_platforms();
		$this->seed_dietary_restrictions();
		$this->seed_allergies();
		$this->seed_medical_accommodations();
		$this->seed_notification_settings();
		$this->seed_payment_processors();
	}

	/**
	 * Seed default location.
	 */
	private function seed_default_location() {
		$table_name = $this->prefix . 'locations';
		
		// Check if any locations exist
		$existing = $this->wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		if ( $existing > 0 ) {
			return;
		}

		$this->wpdb->insert(
			$table_name,
			array(
				'location_name'  => __( 'Default Location', 'remember' ),
				'logo_url'       => null,
				'address_street' => '',
				'address_city'   => '',
				'address_state'  => '',
				'address_postal' => '',
				'address_country' => 'US',
				'details'        => __( 'Default location created during plugin activation.', 'remember' ),
				'is_active'      => 1,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Seed default roles.
	 */
	private function seed_default_roles() {
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-capabilities.php';
		require_once plugin_dir_path( __FILE__ ) . '../models/class-role.php';
		
		$table_name = $this->prefix . 'roles';
		$role_model = new Remember_Role();
		
		// Check if Event Administrator role exists
		$event_admin_role_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT role_id FROM $table_name WHERE role_name = %s", 'Event Administrator' ) );
		if ( ! $event_admin_role_id ) {
			$this->wpdb->insert(
				$table_name,
				array(
					'role_name'    => 'Event Administrator',
					'role_type'    => 'event',
					'is_event_role' => 1,
					'description'  => __( 'Event Administrator role - assigned to members for specific events', 'remember' ),
					'created_at'   => current_time( 'mysql' ),
				)
			);
			$event_admin_role_id = $this->wpdb->insert_id;
			
			// Assign default capabilities to Event Administrator
			$event_admin_capabilities = array(
				// Applications
				'remember_create_applications',
				'remember_read_applications',
				'remember_update_applications',
				// Billing
				'remember_create_billing',
				'remember_read_billing',
				'remember_update_billing',
				'remember_delete_billing',
				// Events
				'remember_create_events',
				'remember_read_events',
				'remember_update_events',
				'remember_delete_events',
				// Locations (read only)
				'remember_read_locations',
				// Members (read only)
				'remember_read_members',
				'remember_update_members',
				// Vetting (read only)
				'remember_read_vetting',
			);
			$role_model->set_capabilities( $event_admin_role_id, $event_admin_capabilities );
		}

		// Check if Vetting role exists
		$vetting_role_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT role_id FROM $table_name WHERE role_name = %s", 'Vetting' ) );
		if ( ! $vetting_role_id ) {
			$this->wpdb->insert(
				$table_name,
				array(
					'role_name'     => 'Vetting',
					'role_type'     => 'system',
					'is_event_role' => 0,
					'description'   => __( 'Vetting team member role', 'remember' ),
					'created_at'    => current_time( 'mysql' ),
				)
			);
			$vetting_role_id = $this->wpdb->insert_id;
			
			// Assign default capabilities to Vetting
			$vetting_capabilities = array(
				// Vetting
				'remember_create_vetting',
				'remember_read_vetting',
				'remember_update_vetting',
				// Applications (read only)
				'remember_read_applications',
				// Events (read only)
				'remember_read_events',
				// Locations (read only)
				'remember_read_locations',
				// Members (read and update)
				'remember_read_members',
				'remember_update_members',
			);
			$role_model->set_capabilities( $vetting_role_id, $vetting_capabilities );
		}

		// Check if System Administrator role exists
		$system_admin_role_id = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT role_id FROM $table_name WHERE role_name = %s", 'System Administrator' ) );
		if ( ! $system_admin_role_id ) {
			$this->wpdb->insert(
				$table_name,
				array(
					'role_name'     => 'System Administrator',
					'role_type'     => 'system',
					'is_event_role' => 0,
					'description'   => __( 'System Administrator role - full access to all plugin features', 'remember' ),
					'created_at'    => current_time( 'mysql' ),
				)
			);
			$system_admin_role_id = $this->wpdb->insert_id;
			
			// Assign all capabilities to System Administrator
			$all_capabilities = Remember_Capabilities::get_all_capabilities();
			$role_model->set_capabilities( $system_admin_role_id, array_keys( $all_capabilities ) );
		}
	}

	/**
	 * Seed social media platforms.
	 */
	private function seed_social_media_platforms() {
		$table_name = $this->prefix . 'social_media_platforms';
		
		$platforms = array(
			'Facebook',
			'Instagram',
			'X (Twitter)',
			'LinkedIn',
			'YouTube',
			'TikTok',
			'Snapchat',
			'Discord',
			'Reddit',
			'Threads',
		);

		foreach ( $platforms as $index => $platform_name ) {
			$existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT platform_id FROM $table_name WHERE platform_name = %s", $platform_name ) );
			if ( ! $existing ) {
				$this->wpdb->insert(
					$table_name,
					array(
						'platform_name' => $platform_name,
						'is_active'     => 1,
						'sort_order'    => $index,
						'created_at'    => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Seed dietary restrictions.
	 */
	private function seed_dietary_restrictions() {
		$table_name = $this->prefix . 'dietary_restrictions';
		
		$restrictions = array(
			__( 'Vegetarian', 'remember' ),
			__( 'Vegan', 'remember' ),
			__( 'Gluten-Free', 'remember' ),
			__( 'Dairy-Free', 'remember' ),
			__( 'Kosher', 'remember' ),
			__( 'Halal', 'remember' ),
			__( 'No Nuts', 'remember' ),
			__( 'No Shellfish', 'remember' ),
			__( 'Low Sodium', 'remember' ),
			__( 'Diabetic', 'remember' ),
		);

		foreach ( $restrictions as $index => $restriction ) {
			$existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT restriction_id FROM $table_name WHERE restriction_name = %s", $restriction ) );
			if ( ! $existing ) {
				$this->wpdb->insert(
					$table_name,
					array(
						'restriction_name' => $restriction,
						'is_active'        => 1,
						'sort_order'       => $index,
						'created_at'       => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Seed allergies.
	 */
	private function seed_allergies() {
		$table_name = $this->prefix . 'allergies';
		
		$allergies = array(
			__( 'Peanuts', 'remember' ),
			__( 'Tree Nuts', 'remember' ),
			__( 'Shellfish', 'remember' ),
			__( 'Fish', 'remember' ),
			__( 'Eggs', 'remember' ),
			__( 'Dairy', 'remember' ),
			__( 'Soy', 'remember' ),
			__( 'Wheat', 'remember' ),
			__( 'Sesame', 'remember' ),
			__( 'Latex', 'remember' ),
		);

		foreach ( $allergies as $index => $allergy ) {
			$existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT allergy_id FROM $table_name WHERE allergy_name = %s", $allergy ) );
			if ( ! $existing ) {
				$this->wpdb->insert(
					$table_name,
					array(
						'allergy_name' => $allergy,
						'is_active'    => 1,
						'sort_order'   => $index,
						'created_at'   => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Seed medical accommodations.
	 */
	private function seed_medical_accommodations() {
		$table_name = $this->prefix . 'medical_accommodations';
		
		$accommodations = array(
			array(
				'name'        => __( 'CPAP Machine', 'remember' ),
				'description' => __( 'Requires CPAP machine for sleep', 'remember' ),
			),
			array(
				'name'        => __( 'Wheelchair Accessible', 'remember' ),
				'description' => __( 'Requires wheelchair accessible facilities', 'remember' ),
			),
			array(
				'name'        => __( 'Hearing Assistance', 'remember' ),
				'description' => __( 'Requires hearing assistance devices', 'remember' ),
			),
			array(
				'name'        => __( 'Visual Assistance', 'remember' ),
				'description' => __( 'Requires visual assistance accommodations', 'remember' ),
			),
			array(
				'name'        => __( 'Mobility Assistance', 'remember' ),
				'description' => __( 'Requires mobility assistance', 'remember' ),
			),
			array(
				'name'        => __( 'Medical Equipment Storage', 'remember' ),
				'description' => __( 'Requires secure storage for medical equipment', 'remember' ),
			),
		);

		foreach ( $accommodations as $index => $accommodation ) {
			$existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT accommodation_id FROM $table_name WHERE accommodation_name = %s", $accommodation['name'] ) );
			if ( ! $existing ) {
				$this->wpdb->insert(
					$table_name,
					array(
						'accommodation_name' => $accommodation['name'],
						'description'        => $accommodation['description'],
						'is_active'          => 1,
						'sort_order'         => $index,
						'created_at'         => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Seed notification settings.
	 */
	private function seed_notification_settings() {
		$table_name = $this->prefix . 'notification_settings';
		
		$notification_types = array(
			'application_received',
			'vetting_assigned',
			'vetting_scheduled',
			'vetting_completed',
			'member_vetted',
			'event_application_submitted',
			'event_application_accepted',
			'event_application_declined',
			'event_application_waitlisted',
			'payment_recorded',
			'payment_due_reminder',
			'vetting_collaborator_invited',
		);

		foreach ( $notification_types as $type ) {
			$existing = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT setting_id FROM $table_name WHERE notification_type = %s", $type ) );
			if ( ! $existing ) {
				$this->wpdb->insert(
					$table_name,
					array(
						'notification_type' => $type,
						'is_enabled'        => 1,
						'subject_template'  => '',
						'body_template'     => '',
						'created_at'        => current_time( 'mysql' ),
						'updated_at'        => current_time( 'mysql' ),
					)
				);
			}
		}
	}

	/**
	 * Seed payment processors.
	 */
	private function seed_payment_processors() {
		$table_name = $this->prefix . 'payment_processors';
		
		// Manual processor
		$manual = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT processor_id FROM $table_name WHERE processor_type = %s", 'manual' ) );
		if ( ! $manual ) {
			$this->wpdb->insert(
				$table_name,
				array(
					'processor_type' => 'manual',
					'processor_name' => __( 'Manual Entry', 'remember' ),
					'is_active'      => 1,
					'settings'       => '',
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				)
			);
		}

		// QuickBooks processor (inactive by default)
		$quickbooks = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT processor_id FROM $table_name WHERE processor_type = %s", 'quickbooks' ) );
		if ( ! $quickbooks ) {
			$this->wpdb->insert(
				$table_name,
				array(
					'processor_type' => 'quickbooks',
					'processor_name' => __( 'QuickBooks Online', 'remember' ),
					'is_active'      => 0,
					'settings'       => '',
					'created_at'     => current_time( 'mysql' ),
					'updated_at'     => current_time( 'mysql' ),
				)
			);
		}
	}
}
