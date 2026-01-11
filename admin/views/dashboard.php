<?php
/**
 * Dashboard view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
Remember_Logger::debug( 'Dashboard page loaded' );

// Load models
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-vetting.php';

$member_model    = new Remember_Member();
$event_model     = new Remember_Event();
$application_model = new Remember_Application();
$vetting_model   = new Remember_Vetting();

// Get statistics
$all_members = $member_model->get_all();
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

$pending_vetting_records = $vetting_model->get_pending();
$pending_vetting_records_count = count( $pending_vetting_records );
?>

<div class="wrap remember-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="remember-dashboard-stats">
		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Total Members', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( $total_members ); ?></p>
		</div>

		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Pending Vetting', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( $pending_vetting_count ); ?></p>
		</div>

		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Vetted Members', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( $vetted_count ); ?></p>
		</div>

		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Pending Applications', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( $pending_applications_count ); ?></p>
		</div>

		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Open Events', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( count( $open_events ) ); ?></p>
		</div>

		<div class="remember-stat-box">
			<h3><?php esc_html_e( 'Upcoming Events', 'remember' ); ?></h3>
			<p class="remember-stat-number"><?php echo esc_html( count( $upcoming_events ) ); ?></p>
		</div>
	</div>

	<div class="remember-dashboard-content">
		<div class="remember-dashboard-column">
			<h2><?php esc_html_e( 'Recent Activity', 'remember' ); ?></h2>
			<p><?php esc_html_e( 'Recent activity will appear here as the plugin is used.', 'remember' ); ?></p>
		</div>

		<div class="remember-dashboard-column">
			<h2><?php esc_html_e( 'Quick Actions', 'remember' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members' ) ); ?>"><?php esc_html_e( 'Manage Members', 'remember' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events' ) ); ?>"><?php esc_html_e( 'Manage Events', 'remember' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting' ) ); ?>"><?php esc_html_e( 'Vetting Queue', 'remember' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-applications' ) ); ?>"><?php esc_html_e( 'Review Applications', 'remember' ); ?></a></li>
			</ul>
		</div>
	</div>
</div>
