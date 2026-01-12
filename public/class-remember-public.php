<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    reMember
 * @subpackage reMember/public
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    reMember
 * @subpackage reMember/public
 */
class Remember_Public {

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
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/css/public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/js/public.js', array( 'jquery' ), $this->version, false );
		wp_localize_script( $this->plugin_name, 'rememberPublic', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'remember_public_nonce' ),
		) );
	}

	/**
	 * Register shortcodes.
	 *
	 * @since    1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'remember_dashboard', array( $this, 'shortcode_dashboard' ) );
		add_shortcode( 'remember_events', array( $this, 'shortcode_events' ) );
		add_shortcode( 'remember_apply', array( $this, 'shortcode_apply' ) );
		add_shortcode( 'remember_profile', array( $this, 'shortcode_profile' ) );
		add_shortcode( 'remember_event_directory', array( $this, 'shortcode_event_directory' ) );
		add_shortcode( 'remember_event_detail', array( $this, 'shortcode_event_detail' ) );
	}

	/**
	 * Member dashboard shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_dashboard( $atts ) {
		// Check if user is logged in and is a member
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			return '<p class="remember-notice remember-error">' . 
				sprintf(
					__( 'Please %s to view your dashboard.', 'remember' ),
					'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'log in', 'remember' ) . '</a>'
				) .
				'</p>';
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		$member_model = new Remember_Member();
		$member = $member_model->get( get_current_user_id() );

		if ( ! $member ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'You are not registered as a member. Please contact an administrator.', 'remember' ) . '</p>';
		}

		ob_start();
		include plugin_dir_path( __FILE__ ) . 'partials/remember-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Events list shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_events( $atts ) {
		$atts = shortcode_atts( array(
			'status' => 'open', // open, all, upcoming
			'limit'  => 0,
		), $atts );

		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-event.php';
		$event_model = new Remember_Event();

		if ( 'upcoming' === $atts['status'] ) {
			$events = $event_model->get_upcoming();
		} elseif ( 'all' === $atts['status'] ) {
			$events = $event_model->get_all();
		} else {
			$events = $event_model->get_by_status( 'open' );
		}

		if ( $atts['limit'] > 0 ) {
			$events = array_slice( $events, 0, $atts['limit'] );
		}

		ob_start();
		$status = $atts['status'];
		$limit = $atts['limit'];
		include plugin_dir_path( __FILE__ ) . 'partials/remember-events.php';
		return ob_get_clean();
	}

	/**
	 * Application form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_apply( $atts ) {
		// Check if user is logged in and is a member
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			return '<p class="remember-notice remember-error">' . 
				sprintf(
					__( 'Please %s to apply for events.', 'remember' ),
					'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'log in', 'remember' ) . '</a>'
				) .
				'</p>';
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		$member_model = new Remember_Member();
		$member = $member_model->get( get_current_user_id() );

		if ( ! $member ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'You are not registered as a member. Please contact an administrator.', 'remember' ) . '</p>';
		}

		$atts = shortcode_atts( array(
			'event_id' => isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0,
		), $atts );

		ob_start();
		$event_id = $atts['event_id'];
		include plugin_dir_path( __FILE__ ) . 'partials/remember-apply.php';
		return ob_get_clean();
	}

	/**
	 * Member profile shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_profile( $atts ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			return '<p class="remember-notice remember-error">' . 
				sprintf(
					__( 'Please %s to view your profile.', 'remember' ),
					'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'log in', 'remember' ) . '</a>'
				) .
				'</p>';
		}

		$atts = shortcode_atts( array(
			'edit' => isset( $_GET['edit'] ) && $_GET['edit'] ? true : false,
		), $atts );

		ob_start();
		$is_edit = $atts['edit'];
		include plugin_dir_path( __FILE__ ) . 'partials/remember-profile.php';
		return ob_get_clean();
	}

	/**
	 * Event member directory shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_directory( $atts ) {
		// Check if user is logged in and is a member
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			return '<p class="remember-notice remember-error">' . 
				sprintf(
					__( 'Please %s to view event members.', 'remember' ),
					'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'log in', 'remember' ) . '</a>'
				) .
				'</p>';
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		$member_model = new Remember_Member();
		$member = $member_model->get( get_current_user_id() );

		if ( ! $member ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'You are not registered as a member.', 'remember' ) . '</p>';
		}

		$atts = shortcode_atts( array(
			'event_id' => isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0,
		), $atts );

		if ( $atts['event_id'] <= 0 ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'Event ID is required.', 'remember' ) . '</p>';
		}

		ob_start();
		$event_id = $atts['event_id'];
		include plugin_dir_path( __FILE__ ) . 'partials/remember-event-directory.php';
		return ob_get_clean();
	}

	/**
	 * Event detail shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_event_detail( $atts ) {
		// Check if user is logged in and is a member
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( get_permalink() );
			return '<p class="remember-notice remember-error">' . 
				sprintf(
					__( 'Please %s to view event details.', 'remember' ),
					'<a href="' . esc_url( $login_url ) . '">' . esc_html__( 'log in', 'remember' ) . '</a>'
				) .
				'</p>';
		}

		require_once plugin_dir_path( __FILE__ ) . '../includes/models/class-member.php';
		$member_model = new Remember_Member();
		$member = $member_model->get( get_current_user_id() );

		if ( ! $member ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'You are not registered as a member.', 'remember' ) . '</p>';
		}

		$atts = shortcode_atts( array(
			'event_id' => isset( $_GET['event'] ) ? absint( $_GET['event'] ) : ( isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0 ),
		), $atts );

		if ( $atts['event_id'] <= 0 ) {
			return '<p class="remember-notice remember-error">' . esc_html__( 'Event ID is required.', 'remember' ) . '</p>';
		}

		ob_start();
		$event_id = $atts['event_id'];
		include plugin_dir_path( __FILE__ ) . 'partials/remember-event-detail.php';
		return ob_get_clean();
	}
}
