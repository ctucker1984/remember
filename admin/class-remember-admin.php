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
			// Localize script for AJAX
			wp_localize_script( $this->plugin_name, 'rememberAjax', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'remember_ajax_nonce' ),
			) );
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

		// Check if user has any reMember capability
		$has_remember_cap = false;
		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-capabilities.php';
		$all_caps = Remember_Capabilities::get_all_capabilities();
		foreach ( array_keys( $all_caps ) as $cap ) {
			if ( current_user_can( $cap ) ) {
				$has_remember_cap = true;
				break;
			}
		}
		
		// If user has no reMember capabilities, don't show menu
		if ( ! $has_remember_cap ) {
			return;
		}

		// Main menu item (Dashboard) - use the first capability the user has
		$main_cap = 'remember_read_events';
		// Try to find a capability the user actually has
		foreach ( array( 'remember_read_vetting', 'remember_read_events', 'remember_read_members', 'remember_read_applications' ) as $cap ) {
			if ( current_user_can( $cap ) ) {
				$main_cap = $cap;
				break;
			}
		}
		
		add_menu_page(
			__( 'reMember Dashboard', 'remember' ),
			__( 'reMember', 'remember' ),
			$main_cap,
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
			$main_cap,
			'remember',
			array( $this, 'display_dashboard_page' )
		);

		// Members (accessible with either members or attendees capability)
		// Check at menu registration time which capability the user has
		// The members page will handle filtering based on actual capabilities
		$members_cap = 'remember_read_members'; // Default to full access
		if ( ! current_user_can( 'remember_read_members' ) && current_user_can( 'remember_read_attendees' ) ) {
			$members_cap = 'remember_read_attendees';
		}
		add_submenu_page(
			'remember',
			__( 'Members', 'remember' ),
			__( 'Members', 'remember' ),
			$members_cap,
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

		// Import/Export
		add_submenu_page(
			'remember',
			__( 'Import/Export', 'remember' ),
			__( 'Import/Export', 'remember' ),
			'remember_access_settings',
			'remember-import-export',
			array( $this, 'display_import_export_page' )
		);

		// Setup Wizard (hidden page, only shown when needed)
		// Use 'remember' as parent to avoid null issues, but it won't show in menu
		add_submenu_page(
			'remember',
			__( 'reMember Setup', 'remember' ),
			'', // Empty menu title makes it hidden
			'remember_access_settings',
			'remember-setup',
			array( $this, 'display_setup_wizard' )
		);

		Remember_Logger::debug( 'Admin menu registered' );
	}

	/**
	 * Render the dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function display_dashboard_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_events' ) && ! current_user_can( 'remember_read_vetting' ) && ! current_user_can( 'remember_read_members' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
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
		// Check capability
		if ( ! current_user_can( 'remember_read_members' ) && ! current_user_can( 'remember_read_attendees' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		include_once 'views/members.php';
	}

	/**
	 * Render the events page.
	 *
	 * @since    1.0.0
	 */
	public function display_events_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_events' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
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
		// Check capability
		if ( ! current_user_can( 'remember_read_applications' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-application.php';
		include_once 'views/applications.php';
	}

	/**
	 * Render the vetting page.
	 *
	 * @since    1.0.0
	 */
	public function display_vetting_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_vetting' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-vetting.php';
		include_once 'views/vetting.php';
	}

	/**
	 * Render the billing page.
	 *
	 * @since    1.0.0
	 */
	public function display_billing_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_billing' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-payment.php';
		include_once 'views/billing.php';
	}

	/**
	 * Render the locations page.
	 *
	 * @since    1.0.0
	 */
	public function display_locations_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_locations' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-location.php';
		include_once 'views/locations.php';
	}

	/**
	 * Render the roles page.
	 *
	 * @since    1.0.0
	 */
	public function display_roles_page() {
		// Check capability
		if ( ! current_user_can( 'remember_read_roles' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-role.php';
		include_once 'views/roles.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		// Check capability
		if ( ! current_user_can( 'remember_access_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		include_once 'views/settings.php';
	}

	/**
	 * Render the import/export page.
	 *
	 * @since    1.0.0
	 */
	public function display_import_export_page() {
		// Check capability
		if ( ! current_user_can( 'remember_access_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-import-export.php';
		include_once 'views/import-export.php';
	}

	/**
	 * Process setup wizard form submission.
	 * Called on admin_init to process before any output.
	 *
	 * @since    1.0.0
	 */
	public function process_setup_wizard() {
		// Only process if we're on the setup wizard page
		if ( ! isset( $_GET['page'] ) || 'remember-setup' !== $_GET['page'] ) {
			return;
		}

		// Only process POST requests
		if ( ! isset( $_POST['remember_setup_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'remember_setup_wizard', 'remember_setup_nonce' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-page-creator.php';
		
		$action = sanitize_text_field( $_POST['remember_setup_action'] );
		
		if ( 'setup_pages' === $action ) {
			$default_pages = Remember_Page_Creator::get_default_pages();
			$pages_created = 0;
			$pages_linked = 0;
			
			foreach ( $default_pages as $key => $page_data ) {
				$selection = isset( $_POST[ 'page_' . $key ] ) ? sanitize_text_field( $_POST[ 'page_' . $key ] ) : 'skip';
				
				if ( 'create' === $selection ) {
					// Create new page
					$created = Remember_Page_Creator::create_pages( array( $key ) );
					if ( ! empty( $created ) ) {
						$pages_created++;
					}
				} elseif ( 'skip' !== $selection && is_numeric( $selection ) ) {
					// Link to existing page - add shortcode if not already present
					$page_id = absint( $selection );
					$page = get_post( $page_id );
					
					if ( $page && 'page' === $page->post_type ) {
						// Store the mapping
						$created_pages = Remember_Page_Creator::get_created_pages();
						$created_pages[ $key ] = $page_id;
						update_option( 'remember_created_pages', $created_pages );
						
						// Add shortcode to page if not already present
						if ( strpos( $page->post_content, $page_data['shortcode'] ) === false ) {
							$new_content = $page->post_content . "\n\n" . $page_data['shortcode'];
							wp_update_post( array(
								'ID' => $page_id,
								'post_content' => $new_content,
							) );
						}
						$pages_linked++;
					}
				}
			}
			
			// Clear the setup wizard transient
			delete_transient( 'remember_show_setup_wizard' );
			
			// Redirect to settings page with success message
			$message = '';
			if ( $pages_created > 0 && $pages_linked > 0 ) {
				$message = sprintf(
					__( 'Successfully created %d page(s) and linked %d page(s).', 'remember' ),
					$pages_created,
					$pages_linked
				);
			} elseif ( $pages_created > 0 ) {
				$message = sprintf(
					_n( 'Successfully created %d page.', 'Successfully created %d pages.', $pages_created, 'remember' ),
					$pages_created
				);
			} elseif ( $pages_linked > 0 ) {
				$message = sprintf(
					_n( 'Successfully linked %d page.', 'Successfully linked %d pages.', $pages_linked, 'remember' ),
					$pages_linked
				);
			}
			
			if ( $message ) {
				wp_safe_redirect( add_query_arg( 'pages_setup', urlencode( $message ), admin_url( 'admin.php?page=remember-settings' ) ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=remember-settings' ) );
			}
			exit;
		} elseif ( 'skip' === $action ) {
			// Clear the setup wizard transient
			delete_transient( 'remember_show_setup_wizard' );
			wp_safe_redirect( admin_url( 'admin.php?page=remember-settings' ) );
			exit;
		}
	}

	/**
	 * Render the setup wizard page.
	 *
	 * @since    1.0.0
	 */
	public function display_setup_wizard() {
		include_once 'views/setup-wizard.php';
	}

	/**
	 * Check if setup wizard should be shown and redirect.
	 *
	 * @since    1.0.0
	 */
	public function maybe_show_setup_wizard() {
		// Only show to admins
		if ( ! current_user_can( 'remember_access_settings' ) ) {
			return;
		}

		// Check if we should show the setup wizard
		if ( get_transient( 'remember_show_setup_wizard' ) ) {
			// Don't redirect if already on setup page
			if ( isset( $_GET['page'] ) && 'remember-setup' === $_GET['page'] ) {
				return;
			}

			// Redirect to setup wizard
			wp_safe_redirect( admin_url( 'admin.php?page=remember-setup' ) );
			exit;
		}
	}

	/**
	 * AJAX handler to get event roles for an event.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_event_roles() {
		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-logger.php';
		
		Remember_Logger::debug( 'ajax_get_event_roles: Starting', array( 'POST' => $_POST ) );
		
		check_ajax_referer( 'remember_get_event_roles', 'nonce' );
		
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		Remember_Logger::debug( 'ajax_get_event_roles: Event ID', array( 'event_id' => $event_id ) );
		
		if ( $event_id <= 0 ) {
			Remember_Logger::error( 'ajax_get_event_roles: Invalid event ID' );
			wp_send_json_error( array( 'message' => __( 'Invalid event ID.', 'remember' ) ) );
		}
		
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-event.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		
		$event_model = new Remember_Event();
		$member_model = new Remember_Member();
		
		global $wpdb;
		
		// Check if show_in_frontend column exists
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}remember_roles" );
		$has_show_in_frontend = in_array( 'show_in_frontend', $columns, true );
		Remember_Logger::debug( 'ajax_get_event_roles: Column check', array( 
			'has_show_in_frontend' => $has_show_in_frontend,
			'columns' => $columns 
		) );
		
		// Check if this is a request from the admin area (not front-end)
		// The HTTP_REFERER will tell us where the request came from
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
		$is_from_admin = ( strpos( $referer, '/wp-admin/' ) !== false );
		
		// Check if user can manage applications (create/update)
		$can_create_apps = current_user_can( 'remember_create_applications' );
		$can_update_apps = current_user_can( 'remember_update_applications' );
		
		// For front-end application forms, NEVER treat anyone as admin
		// Only treat as admin if the request came from the admin area AND they can manage applications
		// OR if they're a full WordPress administrator
		$is_wordpress_admin = current_user_can( 'manage_options' );
		$is_admin = ( ( $can_create_apps || $can_update_apps ) && $is_from_admin ) || $is_wordpress_admin;
		
		Remember_Logger::debug( 'ajax_get_event_roles: Admin check', array( 
			'is_admin' => $is_admin,
			'can_create_applications' => $can_create_apps,
			'can_update_applications' => $can_update_apps,
			'is_from_admin' => $is_from_admin,
			'is_wordpress_admin' => $is_wordpress_admin,
			'referer' => $referer,
			'user_id' => get_current_user_id(),
			'is_logged_in' => is_user_logged_in()
		) );
		
		if ( $is_admin ) {
			// Admin users get all roles
			Remember_Logger::debug( 'ajax_get_event_roles: Admin path - getting all roles' );
			$event_roles = $event_model->get_event_roles( $event_id );
			Remember_Logger::debug( 'ajax_get_event_roles: Admin results', array( 
				'count' => count( $event_roles ),
				'roles' => array_map( function( $r ) { 
					return array( 
						'event_role_id' => $r->event_role_id, 
						'role_id' => $r->role_id,
						'role_name' => $r->role_name,
						'show_in_frontend' => isset( $r->show_in_frontend ) ? $r->show_in_frontend : 'not set'
					); 
				}, $event_roles )
			) );
		} else {
			// For non-admin users, filter at the SQL level:
			// 1. Only roles the member has assigned (join with member_roles)
			// 2. Only roles with show_in_frontend != 0
			// 3. Only event roles (role_type = 'event')
			if ( is_user_logged_in() ) {
				$member_id = get_current_user_id();
				Remember_Logger::debug( 'ajax_get_event_roles: Non-admin logged in user', array( 'member_id' => $member_id ) );
				
				// Get member's assigned event role IDs for logging
				$member_event_role_ids = $member_model->get_member_event_role_ids( $member_id );
				Remember_Logger::debug( 'ajax_get_event_roles: Member event role IDs', array( 
					'member_id' => $member_id,
					'role_ids' => $member_event_role_ids 
				) );
				
				if ( $has_show_in_frontend ) {
					// Filter by member roles AND show_in_frontend
					$query = $wpdb->prepare(
						"SELECT DISTINCT er.*, r.role_name, r.show_in_frontend 
						FROM {$wpdb->prefix}remember_event_roles er 
						JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
						JOIN {$wpdb->prefix}remember_member_roles mr ON r.role_id = mr.role_id 
						WHERE er.event_id = %d 
						AND mr.member_id = %d 
						AND r.role_type = 'event'
						AND COALESCE(r.show_in_frontend, 1) != 0
						ORDER BY r.role_name ASC",
						$event_id,
						$member_id
					);
					Remember_Logger::debug( 'ajax_get_event_roles: Executing SQL with show_in_frontend filter', array( 
						'query' => $query,
						'event_id' => $event_id,
						'member_id' => $member_id
					) );
					$event_roles = $wpdb->get_results( $query );
					Remember_Logger::debug( 'ajax_get_event_roles: Query results', array( 
						'count' => count( $event_roles ),
						'roles' => array_map( function( $r ) { 
							return array( 
								'event_role_id' => $r->event_role_id, 
								'role_id' => $r->role_id,
								'role_name' => $r->role_name,
								'show_in_frontend' => isset( $r->show_in_frontend ) ? $r->show_in_frontend : 'not set'
							); 
						}, $event_roles ),
						'wpdb_last_error' => $wpdb->last_error
					) );
				} else {
					// Column doesn't exist yet - filter by member roles only
					$query = $wpdb->prepare(
						"SELECT DISTINCT er.*, r.role_name 
						FROM {$wpdb->prefix}remember_event_roles er 
						JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
						JOIN {$wpdb->prefix}remember_member_roles mr ON r.role_id = mr.role_id 
						WHERE er.event_id = %d 
						AND mr.member_id = %d 
						AND r.role_type = 'event'
						ORDER BY r.role_name ASC",
						$event_id,
						$member_id
					);
					Remember_Logger::debug( 'ajax_get_event_roles: Executing SQL without show_in_frontend filter', array( 
						'query' => $query,
						'event_id' => $event_id,
						'member_id' => $member_id
					) );
					$event_roles = $wpdb->get_results( $query );
					Remember_Logger::debug( 'ajax_get_event_roles: Query results', array( 
						'count' => count( $event_roles ),
						'roles' => array_map( function( $r ) { 
							return array( 
								'event_role_id' => $r->event_role_id, 
								'role_id' => $r->role_id,
								'role_name' => $r->role_name
							); 
						}, $event_roles ),
						'wpdb_last_error' => $wpdb->last_error
					) );
				}
			} else {
				// Not logged in - return empty (can't apply without being logged in)
				Remember_Logger::debug( 'ajax_get_event_roles: User not logged in, returning empty' );
				$event_roles = array();
			}
		}
		
		// Format for response - return event_role_id and role_name
		$formatted_roles = array();
		foreach ( $event_roles as $event_role ) {
			$formatted_roles[] = array(
				'event_role_id' => $event_role->event_role_id,
				'role_name'     => $event_role->role_name,
			);
		}
		
		Remember_Logger::debug( 'ajax_get_event_roles: Final formatted response', array( 
			'count' => count( $formatted_roles ),
			'roles' => $formatted_roles
		) );
		
		wp_send_json_success( $formatted_roles );
	}

	/**
	 * Register dashboard widget.
	 *
	 * @since    1.0.0
	 */
	public function register_dashboard_widget() {
		// Only show to users with at least read_events capability
		if ( ! current_user_can( 'remember_read_events' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'remember_dashboard_widget',
			__( 'reMember Overview', 'remember' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget content.
	 *
	 * @since    1.0.0
	 */
	public function render_dashboard_widget() {
		// Load models
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-event.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-application.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-vetting.php';

		$member_model      = new Remember_Member();
		$event_model       = new Remember_Event();
		$application_model = new Remember_Application();
		$vetting_model     = new Remember_Vetting();

		// Get statistics (only count valid members with existing WordPress users)
		$all_members = $member_model->get_all_valid();
		$total_members = count( $all_members );

		$pending_vetting = $member_model->get_by_status( 'pending_vetting' );
		$in_vetting = $member_model->get_by_status( 'in_vetting' );
		$vetted = $member_model->get_by_status( 'vetted' );

		$pending_vetting_count = count( $pending_vetting );
		$in_vetting_count = count( $in_vetting );
		$vetted_count = count( $vetted );

		$open_events = $event_model->get_open();
		$upcoming_events = $event_model->get_upcoming();

		$pending_applications = $application_model->get_by_status( 'pending' );
		$pending_applications_count = count( $pending_applications );

		$open_vetting_records = $vetting_model->get_open();
		$open_vetting_records_count = count( $open_vetting_records );

		// Include widget view
		include plugin_dir_path( __FILE__ ) . 'views/dashboard-widget.php';
	}

	/**
	 * Add timezone field to WordPress user profile.
	 *
	 * @param WP_User $user User object.
	 */
	public function add_timezone_field_to_profile( $user ) {
		require_once plugin_dir_path( __FILE__ ) . '../includes/utilities/class-remember-timezone.php';
		
		$selected_timezone = get_user_meta( $user->ID, 'timezone_string', true );
		if ( empty( $selected_timezone ) ) {
			$selected_timezone = 'America/Los_Angeles'; // Default
			// Auto-assign default timezone
			update_user_meta( $user->ID, 'timezone_string', $selected_timezone );
		}
		?>
		<h2><?php esc_html_e( 'reMember Settings', 'remember' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="timezone_string"><?php esc_html_e( 'Time Zone', 'remember' ); ?> <span class="description"><?php esc_html_e( '(required)', 'remember' ); ?></span></label></th>
				<td>
					<?php echo Remember_Timezone::dropdown( $selected_timezone, 'timezone_string', 'timezone_string', true ); ?>
					<p class="description"><?php esc_html_e( 'Your timezone is used to display scheduled times in your local time.', 'remember' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save timezone field from WordPress user profile.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_timezone_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		
		if ( isset( $_POST['timezone_string'] ) ) {
			$timezone_string = sanitize_text_field( $_POST['timezone_string'] );
			if ( ! empty( $timezone_string ) ) {
				update_user_meta( $user_id, 'timezone_string', $timezone_string );
			} else {
				// Default if empty
				update_user_meta( $user_id, 'timezone_string', 'America/Los_Angeles' );
			}
		}
	}
}
