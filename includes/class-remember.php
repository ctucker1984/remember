<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    reMember
 * @subpackage reMember/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    reMember
 * @subpackage reMember/includes
 */
class Remember {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @var      Remember_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'REMEMBER_VERSION' ) ) {
			$this->version = REMEMBER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'remember';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Remember_Loader. Orchestrates the hooks of the plugin.
	 * - Remember_i18n. Defines internationalization functionality.
	 * - Remember_Admin. Defines all hooks for the admin area.
	 * - Remember_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-remember-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-remember-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-remember-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-remember-public.php';

		$this->loader = new Remember_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Remember_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Remember_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Remember_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_ajax_remember_get_event_roles', $plugin_admin, 'ajax_get_event_roles' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'wp_dashboard_setup', $plugin_admin, 'register_dashboard_widget' );
		
		// QuickBooks sync hooks
		$this->loader->add_action( 'remember_member_vetted', $this, 'sync_vetted_member_to_qb' );
		$this->loader->add_action( 'remember_qb_sync', $this, 'sync_qb_payments' );

		// Setup wizard redirect and form processing
		$this->loader->add_action( 'admin_init', $plugin_admin, 'maybe_show_setup_wizard' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'process_setup_wizard' );

		// Clean up member records when WordPress user is deleted
		$this->loader->add_action( 'delete_user', $this, 'cleanup_member_on_user_delete' );
		
		// Add timezone field to WordPress user profile
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'add_timezone_field_to_profile' );
		$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'add_timezone_field_to_profile' );
		$this->loader->add_action( 'personal_options_update', $plugin_admin, 'save_timezone_field' );
		$this->loader->add_action( 'edit_user_profile_update', $plugin_admin, 'save_timezone_field' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Remember_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );

		// Register FSE block patterns
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-fse.php';
		$this->loader->add_action( 'init', 'Remember_FSE', 'register_block_pattern_category' );
		$this->loader->add_action( 'init', 'Remember_FSE', 'register_block_patterns' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Run database updates on admin init
		$this->loader->add_action( 'admin_init', $this, 'maybe_update_database' );
		
		// Schedule QuickBooks sync cron job
		$this->loader->add_action( 'admin_init', $this, 'maybe_schedule_qb_sync' );
		
		$this->loader->run();
	}

	/**
	 * Check and update database schema if needed.
	 */
	public function maybe_update_database() {
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'database/class-remember-database-updater.php';
			Remember_Database_Updater::update_schema();
		}
	}

	/**
	 * Sync vetted member to QuickBooks customer.
	 *
	 * @param int $member_id Member ID.
	 */
	public function sync_vetted_member_to_qb( $member_id ) {
		require_once plugin_dir_path( __FILE__ ) . 'integrations/class-remember-quickbooks-oauth.php';
		$qb_settings = Remember_QuickBooks_OAuth::get_settings();
		
		if ( $qb_settings && ! empty( $qb_settings['access_token'] ) && ! empty( $qb_settings['realm_id'] ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'integrations/class-remember-quickbooks-sync.php';
			Remember_QuickBooks_Sync::sync_member_to_customer( $member_id );
		}
	}

	/**
	 * Schedule QuickBooks sync cron job if needed.
	 */
	public function maybe_schedule_qb_sync() {
		if ( ! wp_next_scheduled( 'remember_qb_sync' ) ) {
			$options = get_option( 'remember_options', array() );
			$interval = isset( $options['qb_sync_interval'] ) ? absint( $options['qb_sync_interval'] ) : 3600; // Default 1 hour
			
			// Schedule recurring event
			wp_schedule_event( time(), 'hourly', 'remember_qb_sync' );
			
			// Note: WordPress doesn't support custom intervals easily, so we'll use hourly
			// and check the interval in the sync function itself
		}
	}

	/**
	 * Sync QuickBooks payments via cron.
	 */
	public function sync_qb_payments() {
		require_once plugin_dir_path( __FILE__ ) . 'integrations/class-remember-quickbooks-oauth.php';
		$qb_settings = Remember_QuickBooks_OAuth::get_settings();
		
		if ( $qb_settings && ! empty( $qb_settings['access_token'] ) && ! empty( $qb_settings['realm_id'] ) ) {
			// Check if enough time has passed since last sync
			global $wpdb;
			$last_sync = $wpdb->get_var( "SELECT last_sync_at FROM {$wpdb->prefix}remember_payment_processors WHERE processor_type = 'quickbooks' AND is_active = 1 LIMIT 1" );
			
			$options = get_option( 'remember_options', array() );
			$interval = isset( $options['qb_sync_interval'] ) ? absint( $options['qb_sync_interval'] ) : 3600;
			
			if ( $last_sync ) {
				$time_since_sync = time() - strtotime( $last_sync );
				if ( $time_since_sync < $interval ) {
					return; // Not enough time has passed
				}
			}
			
			require_once plugin_dir_path( __FILE__ ) . 'integrations/class-remember-quickbooks-sync.php';
			Remember_QuickBooks_Sync::sync_all_payments();
		}
	}

	/**
	 * Clean up member record when WordPress user is deleted.
	 *
	 * @param int $user_id The ID of the user being deleted.
	 * @param int|null $reassign The ID of the user to reassign posts to, or null.
	 * @param WP_User $user The user object being deleted.
	 */
	public function cleanup_member_on_user_delete( $user_id, $reassign = null, $user = null ) {
		require_once plugin_dir_path( __FILE__ ) . 'models/class-member.php';
		$member_model = new Remember_Member();
		
		// Delete the member record if it exists
		$member = $member_model->get( $user_id );
		if ( $member ) {
			$member_model->delete( $user_id );
			
			require_once plugin_dir_path( __FILE__ ) . 'utilities/class-remember-logger.php';
			Remember_Logger::debug( 'Deleted member record on user deletion', array( 'user_id' => $user_id ) );
		}
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Remember_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
