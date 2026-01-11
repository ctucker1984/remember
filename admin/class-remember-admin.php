<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    reMember
 * @subpackage reMember/admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    reMember
 * @subpackage reMember/admin
 */
class Remember_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'remember' ) !== false ) {
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/css/admin.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'remember' ) !== false ) {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/js/admin.js', array( 'jquery' ), $this->version, false );
		}
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-logger.php';
		Remember_Logger::debug( 'Building admin menu' );

		// Main menu item (Dashboard)
		add_menu_page(
			__( 'reMember Dashboard', 'remember' ),
			__( 'reMember', 'remember' ),
			'remember_read_events',
			'remember',
			array( $this, 'display_dashboard_page' ),
			'dashicons-groups',
			30
		);

		// Dashboard submenu (duplicate of main menu)
		add_submenu_page(
			'remember',
			__( 'Dashboard', 'remember' ),
			__( 'Dashboard', 'remember' ),
			'remember_read_events',
			'remember',
			array( $this, 'display_dashboard_page' )
		);

		// Members
		add_submenu_page(
			'remember',
			__( 'Members', 'remember' ),
			__( 'Members', 'remember' ),
			'remember_read_members',
			'remember-members',
			array( $this, 'display_members_page' )
		);

		// Events
		add_submenu_page(
			'remember',
			__( 'Events', 'remember' ),
			__( 'Events', 'remember' ),
			'remember_read_events',
			'remember-events',
			array( $this, 'display_events_page' )
		);

		// Applications
		add_submenu_page(
			'remember',
			__( 'Applications', 'remember' ),
			__( 'Applications', 'remember' ),
			'remember_read_applications',
			'remember-applications',
			array( $this, 'display_applications_page' )
		);

		// Vetting
		add_submenu_page(
			'remember',
			__( 'Vetting', 'remember' ),
			__( 'Vetting', 'remember' ),
			'remember_read_vetting',
			'remember-vetting',
			array( $this, 'display_vetting_page' )
		);

		// Billing
		add_submenu_page(
			'remember',
			__( 'Billing', 'remember' ),
			__( 'Billing', 'remember' ),
			'remember_read_billing',
			'remember-billing',
			array( $this, 'display_billing_page' )
		);

		// Locations
		add_submenu_page(
			'remember',
			__( 'Locations', 'remember' ),
			__( 'Locations', 'remember' ),
			'remember_read_locations',
			'remember-locations',
			array( $this, 'display_locations_page' )
		);

		// Roles
		add_submenu_page(
			'remember',
			__( 'Roles', 'remember' ),
			__( 'Roles', 'remember' ),
			'remember_read_roles',
			'remember-roles',
			array( $this, 'display_roles_page' )
		);

		// Settings
		add_submenu_page(
			'remember',
			__( 'Settings', 'remember' ),
			__( 'Settings', 'remember' ),
			'remember_access_settings',
			'remember-settings',
			array( $this, 'display_settings_page' )
		);

		Remember_Logger::debug( 'Admin menu registered' );
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function display_dashboard_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-event.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-application.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-vetting.php';
		include_once 'views/dashboard.php';
	}

	/**
	 * Render the members page.
	 *
	 * @since    1.0.0
	 */
	public function display_members_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		include_once 'views/members.php';
	}

	/**
	 * Render the events page.
	 *
	 * @since    1.0.0
	 */
	public function display_events_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-event.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-location.php';
		include_once 'views/events.php';
	}

	/**
	 * Render the applications page.
	 *
	 * @since    1.0.0
	 */
	public function display_applications_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-application.php';
		include_once 'views/applications.php';
	}

	/**
	 * Render the vetting page.
	 *
	 * @since    1.0.0
	 */
	public function display_vetting_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-vetting.php';
		include_once 'views/vetting.php';
	}

	/**
	 * Render the billing page.
	 *
	 * @since    1.0.0
	 */
	public function display_billing_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-payment.php';
		include_once 'views/billing.php';
	}

	/**
	 * Render the locations page.
	 *
	 * @since    1.0.0
	 */
	public function display_locations_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-location.php';
		include_once 'views/locations.php';
	}

	/**
	 * Render the roles page.
	 *
	 * @since    1.0.0
	 */
	public function display_roles_page() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-role.php';
		include_once 'views/roles.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		include_once 'views/settings.php';
	}
}
