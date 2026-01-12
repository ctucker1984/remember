<?php
/**
 * Database schema creation and management
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database schema creation and management class.
 *
 * @package    reMember
 * @subpackage reMember/includes/database
 */
class Remember_Database {

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
	 * Create all database tables.
	 *
	 * @since    1.0.0
	 */
	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$this->create_members_table();
		$this->create_member_profiles_table();
		$this->create_social_media_platforms_table();
		$this->create_member_social_media_table();
		$this->create_dietary_restrictions_table();
		$this->create_member_dietary_restrictions_table();
		$this->create_allergies_table();
		$this->create_member_allergies_table();
		$this->create_medical_accommodations_table();
		$this->create_member_medical_accommodations_table();
		$this->create_roles_table();
		$this->create_member_roles_table();
		$this->create_role_capabilities_table();
		$this->create_locations_table();
		$this->create_events_table();
		$this->create_event_roles_table();
		$this->create_event_merchandise_table();
		$this->create_event_applications_table();
		$this->create_application_merchandise_table();
		$this->create_products_table();
		$this->create_payment_processors_table();
		$this->create_payments_table();
		$this->create_vetting_table();
		$this->create_vetting_collaborators_table();
		$this->create_vetting_notes_table();
		$this->create_notification_settings_table();
		$this->create_plugin_version_table();
	}

	/**
	 * Create members table.
	 */
	private function create_members_table() {
		$table_name = $this->prefix . 'members';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			member_id BIGINT(20) UNSIGNED NOT NULL,
			status ENUM('pending_vetting', 'unvetted', 'in_vetting', 'vetted', 'rejected', 'inactive') DEFAULT 'pending_vetting',
			photo_url VARCHAR(255) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (member_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member profiles table.
	 */
	private function create_member_profiles_table() {
		$table_name = $this->prefix . 'member_profiles';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			profile_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			legal_first_name VARCHAR(100) NOT NULL,
			legal_last_name VARCHAR(100) NOT NULL,
			address_street VARCHAR(255) DEFAULT NULL,
			address_city VARCHAR(100) DEFAULT NULL,
			address_state VARCHAR(50) DEFAULT NULL,
			address_postal VARCHAR(20) DEFAULT NULL,
			address_country VARCHAR(100) DEFAULT NULL,
			cell_phone VARCHAR(50) DEFAULT NULL,
			timezone VARCHAR(50) DEFAULT NULL,
			im_handle VARCHAR(100) DEFAULT NULL,
			im_type VARCHAR(50) DEFAULT 'telegram',
			interests TEXT DEFAULT NULL,
			emergency_contact_first VARCHAR(100) NOT NULL,
			emergency_contact_last VARCHAR(100) NOT NULL,
			emergency_contact_phone VARCHAR(50) NOT NULL,
			emergency_contact_relationship VARCHAR(50) NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (profile_id),
			UNIQUE KEY member_id (member_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create social media platforms table.
	 */
	private function create_social_media_platforms_table() {
		$table_name = $this->prefix . 'social_media_platforms';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			platform_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			platform_name VARCHAR(100) NOT NULL,
			is_active BOOLEAN DEFAULT 1,
			sort_order INT(11) DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (platform_id),
			UNIQUE KEY platform_name (platform_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member social media table.
	 */
	private function create_member_social_media_table() {
		$table_name = $this->prefix . 'member_social_media';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			social_media_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			platform_id BIGINT(20) UNSIGNED NOT NULL,
			handle VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (social_media_id),
			KEY member_id (member_id),
			KEY platform_id (platform_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create dietary restrictions table.
	 */
	private function create_dietary_restrictions_table() {
		$table_name = $this->prefix . 'dietary_restrictions';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			restriction_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			restriction_name VARCHAR(100) NOT NULL,
			is_active BOOLEAN DEFAULT 1,
			sort_order INT(11) DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (restriction_id),
			UNIQUE KEY restriction_name (restriction_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member dietary restrictions table.
	 */
	private function create_member_dietary_restrictions_table() {
		$table_name = $this->prefix . 'member_dietary_restrictions';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			member_id BIGINT(20) UNSIGNED NOT NULL,
			restriction_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (member_id, restriction_id),
			KEY restriction_id (restriction_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create allergies table.
	 */
	private function create_allergies_table() {
		$table_name = $this->prefix . 'allergies';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			allergy_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			allergy_name VARCHAR(100) NOT NULL,
			is_active BOOLEAN DEFAULT 1,
			sort_order INT(11) DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (allergy_id),
			UNIQUE KEY allergy_name (allergy_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member allergies table.
	 */
	private function create_member_allergies_table() {
		$table_name = $this->prefix . 'member_allergies';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			member_id BIGINT(20) UNSIGNED NOT NULL,
			allergy_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (member_id, allergy_id),
			KEY allergy_id (allergy_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create medical accommodations table.
	 */
	private function create_medical_accommodations_table() {
		$table_name = $this->prefix . 'medical_accommodations';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			accommodation_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			accommodation_name VARCHAR(100) NOT NULL,
			description TEXT DEFAULT NULL,
			is_active BOOLEAN DEFAULT 1,
			sort_order INT(11) DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (accommodation_id),
			UNIQUE KEY accommodation_name (accommodation_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member medical accommodations table.
	 */
	private function create_member_medical_accommodations_table() {
		$table_name = $this->prefix . 'member_medical_accommodations';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			member_id BIGINT(20) UNSIGNED NOT NULL,
			accommodation_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY (member_id, accommodation_id),
			KEY accommodation_id (accommodation_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create roles table.
	 */
	private function create_roles_table() {
		$table_name = $this->prefix . 'roles';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			role_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			role_name VARCHAR(100) NOT NULL,
			role_type ENUM('event', 'system') DEFAULT 'event',
			is_event_role BOOLEAN DEFAULT 1,
			description TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (role_id),
			UNIQUE KEY role_name (role_name)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create member roles table.
	 */
	private function create_member_roles_table() {
		$table_name = $this->prefix . 'member_roles';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			member_role_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			role_id BIGINT(20) UNSIGNED NOT NULL,
			approved_at DATETIME DEFAULT NULL,
			approved_by BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (member_role_id),
			UNIQUE KEY member_role (member_id, role_id),
			KEY member_id (member_id),
			KEY role_id (role_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create role capabilities table.
	 */
	private function create_role_capabilities_table() {
		$table_name = $this->prefix . 'role_capabilities';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			role_capability_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			role_id BIGINT(20) UNSIGNED NOT NULL,
			capability VARCHAR(100) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (role_capability_id),
			UNIQUE KEY role_capability (role_id, capability),
			KEY role_id (role_id),
			KEY capability (capability)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create locations table.
	 */
	private function create_locations_table() {
		$table_name = $this->prefix . 'locations';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			location_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			location_name VARCHAR(255) NOT NULL,
			logo_url VARCHAR(255) DEFAULT NULL,
			address_street VARCHAR(255) DEFAULT NULL,
			address_city VARCHAR(100) DEFAULT NULL,
			address_state VARCHAR(50) DEFAULT NULL,
			address_postal VARCHAR(20) DEFAULT NULL,
			address_country VARCHAR(2) DEFAULT 'US',
			details TEXT DEFAULT NULL,
			is_active BOOLEAN DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (location_id),
			KEY address_city (address_city),
			KEY address_state (address_state),
			KEY address_country (address_country)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create events table.
	 */
	private function create_events_table() {
		$table_name = $this->prefix . 'events';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			event_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_name VARCHAR(255) NOT NULL,
			event_description TEXT DEFAULT NULL,
			location_id BIGINT(20) UNSIGNED DEFAULT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			is_private BOOLEAN DEFAULT 0,
			status ENUM('draft', 'open', 'closed', 'completed', 'cancelled') DEFAULT 'draft',
			created_by BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (event_id),
			KEY location_id (location_id),
			KEY status (status),
			KEY start_date (start_date)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create event roles table.
	 */
	private function create_event_roles_table() {
		$table_name = $this->prefix . 'event_roles';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			event_role_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			role_id BIGINT(20) UNSIGNED NOT NULL,
			cost DECIMAL(10,2) DEFAULT 0.00,
			max_participants INT(11) DEFAULT NULL,
			current_count INT(11) DEFAULT 0,
			is_active BOOLEAN DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (event_role_id),
			KEY event_id (event_id),
			KEY role_id (role_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create event merchandise table.
	 */
	private function create_event_merchandise_table() {
		$table_name = $this->prefix . 'event_merchandise';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			merchandise_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			merchandise_name VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			cost DECIMAL(10,2) NOT NULL,
			quickbooks_product_id VARCHAR(100) DEFAULT NULL,
			quickbooks_product_name VARCHAR(255) DEFAULT NULL,
			max_quantity INT(11) DEFAULT NULL,
			is_available BOOLEAN DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (merchandise_id),
			KEY event_id (event_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create event applications table.
	 */
	private function create_event_applications_table() {
		$table_name = $this->prefix . 'event_applications';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			application_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id BIGINT(20) UNSIGNED NOT NULL,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			event_role_id BIGINT(20) UNSIGNED NOT NULL,
			status ENUM('pending', 'accepted', 'declined', 'cancelled', 'waitlisted') DEFAULT 'pending',
			applied_at DATETIME NOT NULL,
			processed_at DATETIME DEFAULT NULL,
			processed_by BIGINT(20) UNSIGNED DEFAULT NULL,
			notes TEXT DEFAULT NULL,
			PRIMARY KEY (application_id),
			UNIQUE KEY event_member_role (event_id, member_id, event_role_id),
			KEY event_id (event_id),
			KEY member_id (member_id),
			KEY event_role_id (event_role_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create application merchandise table.
	 */
	private function create_application_merchandise_table() {
		$table_name = $this->prefix . 'application_merchandise';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			application_merchandise_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_application_id BIGINT(20) UNSIGNED NOT NULL,
			merchandise_id BIGINT(20) UNSIGNED NOT NULL,
			quantity INT(11) DEFAULT 1,
			unit_cost DECIMAL(10,2) NOT NULL,
			total_cost DECIMAL(10,2) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (application_merchandise_id),
			KEY event_application_id (event_application_id),
			KEY merchandise_id (merchandise_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create products table (QuickBooks mapping).
	 */
	private function create_products_table() {
		$table_name = $this->prefix . 'products';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			product_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_name VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			quickbooks_product_id VARCHAR(100) DEFAULT NULL,
			quickbooks_product_name VARCHAR(255) DEFAULT NULL,
			product_type VARCHAR(50) DEFAULT NULL,
			is_active BOOLEAN DEFAULT 1,
			last_sync_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (product_id),
			UNIQUE KEY product_name (product_name),
			UNIQUE KEY quickbooks_product_id (quickbooks_product_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create payment processors table.
	 */
	private function create_payment_processors_table() {
		$table_name = $this->prefix . 'payment_processors';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			processor_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			processor_type ENUM('manual', 'quickbooks') NOT NULL,
			processor_name VARCHAR(100) NOT NULL,
			is_active BOOLEAN DEFAULT 0,
			settings TEXT DEFAULT NULL,
			last_sync_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (processor_id),
			UNIQUE KEY processor_type (processor_type)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create payments table.
	 */
	private function create_payments_table() {
		$table_name = $this->prefix . 'payments';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			payment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_application_id BIGINT(20) UNSIGNED NOT NULL,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			processor_id BIGINT(20) UNSIGNED DEFAULT NULL,
			role_cost DECIMAL(10,2) DEFAULT 0.00,
			merchandise_cost DECIMAL(10,2) DEFAULT 0.00,
			total_amount DECIMAL(10,2) NOT NULL,
			amount_paid DECIMAL(10,2) DEFAULT 0.00,
			amount_due DECIMAL(10,2) NOT NULL,
			payment_status ENUM('pending', 'partial', 'paid', 'refunded', 'cancelled') DEFAULT 'pending',
			payment_date DATETIME DEFAULT NULL,
			payment_method VARCHAR(50) DEFAULT NULL,
			transaction_id VARCHAR(255) DEFAULT NULL,
			quickbooks_invoice_id VARCHAR(100) DEFAULT NULL,
			notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (payment_id),
			UNIQUE KEY event_application_id (event_application_id),
			KEY member_id (member_id),
			KEY processor_id (processor_id),
			KEY payment_status (payment_status)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create vetting table.
	 */
	private function create_vetting_table() {
		$table_name = $this->prefix . 'vetting';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			vetting_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			primary_vetter_id BIGINT(20) UNSIGNED DEFAULT NULL,
			status ENUM('pending', 'scheduled', 'in_progress', 'completed') DEFAULT 'pending',
			scheduled_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			decision ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
			decision_date DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (vetting_id),
			UNIQUE KEY member_id (member_id),
			KEY primary_vetter_id (primary_vetter_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create vetting collaborators table.
	 */
	private function create_vetting_collaborators_table() {
		$table_name = $this->prefix . 'vetting_collaborators';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			collaborator_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vetting_id BIGINT(20) UNSIGNED NOT NULL,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			invited_by BIGINT(20) UNSIGNED NOT NULL,
			invited_at DATETIME NOT NULL,
			status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
			PRIMARY KEY (collaborator_id),
			UNIQUE KEY vetting_collaborator (vetting_id, member_id),
			KEY vetting_id (vetting_id),
			KEY member_id (member_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create vetting notes table.
	 */
	private function create_vetting_notes_table() {
		$table_name = $this->prefix . 'vetting_notes';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			note_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vetting_id BIGINT(20) UNSIGNED NOT NULL,
			member_id BIGINT(20) UNSIGNED NOT NULL,
			note_content TEXT NOT NULL,
			is_admin_only BOOLEAN DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (note_id),
			KEY vetting_id (vetting_id),
			KEY member_id (member_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create notification settings table.
	 */
	private function create_notification_settings_table() {
		$table_name = $this->prefix . 'notification_settings';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			setting_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			notification_type VARCHAR(100) NOT NULL,
			is_enabled BOOLEAN DEFAULT 1,
			subject_template VARCHAR(255) DEFAULT NULL,
			body_template TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (setting_id),
			UNIQUE KEY notification_type (notification_type)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create plugin version table.
	 */
	private function create_plugin_version_table() {
		$table_name = $this->prefix . 'plugin_version';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			version VARCHAR(20) NOT NULL,
			applied_at DATETIME NOT NULL,
			PRIMARY KEY (version)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
