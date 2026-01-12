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

global $wpdb;

$member_model    = new Remember_Member();
$event_model     = new Remember_Event();
$application_model = new Remember_Application();
$vetting_model   = new Remember_Vetting();

// Get statistics (only count valid members with existing WordPress users)
$all_members = $member_model->get_all_valid();
$total_members = count( $all_members );

// Filter status-based queries to only include valid members
$pending_vetting = $member_model->get_by_status( 'pending_vetting' );
$in_vetting = $member_model->get_by_status( 'in_vetting' );
$vetted = $member_model->get_by_status( 'vetted' );

// Filter out orphaned records (members without WordPress users)
$pending_vetting = array_filter( $pending_vetting, function( $member ) {
	return get_user_by( 'id', $member->member_id ) !== false;
} );
$in_vetting = array_filter( $in_vetting, function( $member ) {
	return get_user_by( 'id', $member->member_id ) !== false;
} );
$vetted = array_filter( $vetted, function( $member ) {
	return get_user_by( 'id', $member->member_id ) !== false;
} );

$pending_vetting_count = count( $pending_vetting );
$in_vetting_count = count( $in_vetting );
$vetted_count = count( $vetted );

$open_events = $event_model->get_open();
$upcoming_events = $event_model->get_upcoming();

$pending_applications = $application_model->get_by_status( 'pending' );
$pending_applications_count = count( $pending_applications );

$open_vetting_records = $vetting_model->get_open();
$open_vetting_records_count = count( $open_vetting_records );

// Load timezone utility
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-timezone.php';

// Get To Do items for logged-in user
$current_user_id = get_current_user_id();
$todo_items = array();

// Get vetting cases assigned to current user
if ( current_user_can( 'remember_read_vetting' ) ) {
	$assigned_vetting = $vetting_model->get_by_vetter( $current_user_id );
	foreach ( $assigned_vetting as $vetting ) {
		if ( in_array( $vetting->status, array( 'pending', 'scheduled', 'in_progress' ), true ) ) {
			$member = $member_model->get( $vetting->member_id );
			$member_user = $member ? get_user_by( 'id', $vetting->member_id ) : null;
			$member_name = $member_user ? $member_user->display_name : __( 'Unknown Member', 'remember' );
			
			// Get scheduled time with timezone conversion
			$scheduled_time = '';
			$is_overdue = false;
			if ( 'scheduled' === $vetting->status && ! empty( $vetting->scheduled_at ) ) {
				$scheduled_time = Remember_Timezone::format_with_your_time( $vetting->scheduled_at, $current_user_id, false );
				
				// Check if scheduled time is in the past (in user's timezone)
				$user_tz = Remember_Timezone::get_user_timezone( $current_user_id );
				$scheduled_dt = Remember_Timezone::convert_to_user_timezone( $vetting->scheduled_at, $current_user_id );
				$now = new DateTime( 'now', $user_tz );
				$is_overdue = $scheduled_dt < $now;
			}
			
			// Determine sort timestamp (use scheduled_at if scheduled, otherwise created_at)
			$sort_timestamp = ! empty( $vetting->scheduled_at ) ? $vetting->scheduled_at : $vetting->created_at;
			
			$todo_items[] = array(
				'type' => 'vetting',
				'title' => sprintf( __( 'Review vetting: %s', 'remember' ), $member_name ),
				'url' => admin_url( 'admin.php?page=remember-vetting&view=' . $vetting->vetting_id ),
				'priority' => $is_overdue ? 'overdue' : ( 'pending' === $vetting->status ? 'high' : 'normal' ),
				'status' => $vetting->status,
				'scheduled_time' => $scheduled_time,
				'sort_timestamp' => $sort_timestamp,
				'is_overdue' => $is_overdue,
			);
		}
	}
	
	// Get vetting cases where user is a collaborator
	$collaborator_vetting_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT v.vetting_id 
		FROM {$wpdb->prefix}remember_vetting v
		INNER JOIN {$wpdb->prefix}remember_vetting_collaborators vc ON v.vetting_id = vc.vetting_id
		WHERE vc.member_id = %d 
		AND vc.status = 'accepted'
		AND v.status IN ('pending', 'scheduled', 'in_progress')
		ORDER BY v.created_at DESC",
		$current_user_id
	) );
	
	foreach ( $collaborator_vetting_ids as $vetting_id ) {
		$vetting = $vetting_model->get( $vetting_id );
		if ( $vetting ) {
			$member = $member_model->get( $vetting->member_id );
			$member_user = $member ? get_user_by( 'id', $vetting->member_id ) : null;
			$member_name = $member_user ? $member_user->display_name : __( 'Unknown Member', 'remember' );
			
			// Get scheduled time with timezone conversion
			$scheduled_time = '';
			$is_overdue = false;
			if ( 'scheduled' === $vetting->status && ! empty( $vetting->scheduled_at ) ) {
				$scheduled_time = Remember_Timezone::format_with_your_time( $vetting->scheduled_at, $current_user_id, false );
				
				// Check if scheduled time is in the past (in user's timezone)
				$user_tz = Remember_Timezone::get_user_timezone( $current_user_id );
				$scheduled_dt = Remember_Timezone::convert_to_user_timezone( $vetting->scheduled_at, $current_user_id );
				$now = new DateTime( 'now', $user_tz );
				$is_overdue = $scheduled_dt < $now;
			}
			
			// Determine sort timestamp (use scheduled_at if scheduled, otherwise created_at)
			$sort_timestamp = ! empty( $vetting->scheduled_at ) ? $vetting->scheduled_at : $vetting->created_at;
			
			$todo_items[] = array(
				'type' => 'collaboration',
				'title' => sprintf( __( 'Collaborate on vetting: %s', 'remember' ), $member_name ),
				'url' => admin_url( 'admin.php?page=remember-vetting&view=' . $vetting_id ),
				'priority' => $is_overdue ? 'overdue' : 'normal',
				'status' => $vetting->status,
				'scheduled_time' => $scheduled_time,
				'sort_timestamp' => $sort_timestamp,
				'is_overdue' => $is_overdue,
			);
		}
	}
	
	// Sort tasks chronologically (most pressing first - earliest scheduled/created first)
	usort( $todo_items, function( $a, $b ) {
		$time_a = strtotime( $a['sort_timestamp'] );
		$time_b = strtotime( $b['sort_timestamp'] );
		return $time_a - $time_b; // Ascending order (earliest first)
	} );
}

// Get recent activity (system-wide, subject to capabilities)
$recent_activity = array();

// Recent vetting cases (if user can view vetting)
if ( current_user_can( 'remember_read_vetting' ) ) {
	$recent_vetting = $wpdb->get_results(
		"SELECT v.*, u.display_name as member_name 
		FROM {$wpdb->prefix}remember_vetting v
		LEFT JOIN {$wpdb->prefix}users u ON v.member_id = u.ID
		ORDER BY v.created_at DESC 
		LIMIT 10"
	);
	
	foreach ( $recent_vetting as $vetting ) {
		$recent_activity[] = array(
			'type' => 'vetting',
			'timestamp' => $vetting->created_at,
			'message' => sprintf( 
				__( 'Vetting case %s created for %s', 'remember' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=remember-vetting&view=' . $vetting->vetting_id ) ) . '">#' . $vetting->vetting_id . '</a>',
				$vetting->member_name ?: __( 'Unknown Member', 'remember' )
			),
		);
	}
}

// Recent applications (if user can view applications)
if ( current_user_can( 'remember_read_applications' ) ) {
	$recent_applications = $application_model->get_all( array( 'limit' => 10 ) );
	
	foreach ( $recent_applications as $app ) {
		$member_user = get_user_by( 'id', $app->member_id );
		$event = $event_model->get( $app->event_id );
		
		$recent_activity[] = array(
			'type' => 'application',
			'timestamp' => $app->applied_at,
			'message' => sprintf(
				__( '%s applied to %s', 'remember' ),
				$member_user ? $member_user->display_name : __( 'Unknown Member', 'remember' ),
				$event ? $event->event_name : __( 'Unknown Event', 'remember' )
			),
		);
	}
}

// Sort recent activity by timestamp
usort( $recent_activity, function( $a, $b ) {
	return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
} );
$recent_activity = array_slice( $recent_activity, 0, 10 ); // Limit to 10 most recent
?>

<div class="wrap remember-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="remember-dashboard-stats-box">
		<div class="remember-dashboard-stats">
			<?php if ( current_user_can( 'remember_read_members' ) || current_user_can( 'remember_read_attendees' ) ) : ?>
			<div class="remember-stat">
				<span class="remember-stat-number"><?php echo esc_html( $total_members ); ?></span>
				<span class="remember-stat-label"><?php esc_html_e( 'Total Members', 'remember' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members' ) ); ?>" class="button button-small button-secondary">
					<?php esc_html_e( 'Manage Members', 'remember' ); ?>
				</a>
			</div>
			<?php endif; ?>

				<?php if ( current_user_can( 'remember_read_vetting' ) ) : ?>
				<div class="remember-stat">
					<span class="remember-stat-number"><?php echo esc_html( $open_vetting_records_count ); ?></span>
					<span class="remember-stat-label"><?php esc_html_e( 'Open Vetting', 'remember' ); ?></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting' ) ); ?>" class="button button-small button-secondary">
						<?php esc_html_e( 'Vetting Queue', 'remember' ); ?>
					</a>
				</div>
				<?php endif; ?>

			<?php if ( current_user_can( 'remember_read_vetting' ) ) : ?>
			<div class="remember-stat">
				<span class="remember-stat-number"><?php echo esc_html( $vetted_count ); ?></span>
				<span class="remember-stat-label"><?php esc_html_e( 'Vetted Members', 'remember' ); ?></span>
			</div>
			<?php endif; ?>

			<?php if ( current_user_can( 'remember_read_applications' ) ) : ?>
			<div class="remember-stat">
				<span class="remember-stat-number"><?php echo esc_html( $pending_applications_count ); ?></span>
				<span class="remember-stat-label"><?php esc_html_e( 'Pending Applications', 'remember' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-applications' ) ); ?>" class="button button-small button-secondary">
					<?php esc_html_e( 'Review Applications', 'remember' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<?php if ( current_user_can( 'remember_read_events' ) ) : ?>
			<div class="remember-stat">
				<span class="remember-stat-number"><?php echo esc_html( count( $open_events ) ); ?></span>
				<span class="remember-stat-label"><?php esc_html_e( 'Open Events', 'remember' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events' ) ); ?>" class="button button-small button-secondary">
					<?php esc_html_e( 'Manage Events', 'remember' ); ?>
				</a>
			</div>

			<div class="remember-stat">
				<span class="remember-stat-number"><?php echo esc_html( count( $upcoming_events ) ); ?></span>
				<span class="remember-stat-label"><?php esc_html_e( 'Upcoming Events', 'remember' ); ?></span>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="remember-dashboard-content">
		<div class="remember-dashboard-column">
			<h2><?php esc_html_e( 'Recent Activity', 'remember' ); ?></h2>
			<?php if ( ! empty( $recent_activity ) ) : ?>
				<ul class="remember-activity-list">
					<?php foreach ( $recent_activity as $activity ) : ?>
						<li class="remember-activity-item remember-activity-<?php echo esc_attr( $activity['type'] ); ?>">
							<span class="remember-activity-time"><?php echo esc_html( human_time_diff( strtotime( $activity['timestamp'] ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
							<span class="remember-activity-message"><?php echo wp_kses_post( $activity['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No recent activity to display.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="remember-dashboard-column">
			<h2><?php esc_html_e( 'My Tasks', 'remember' ); ?></h2>
			<?php if ( ! empty( $todo_items ) ) : ?>
				<ul class="remember-todo-list">
					<?php foreach ( $todo_items as $todo ) : ?>
						<li class="remember-todo-item remember-todo-<?php echo esc_attr( $todo['priority'] ); ?>">
							<a href="<?php echo esc_url( $todo['url'] ); ?>">
								<span class="remember-todo-type"><?php echo esc_html( 'collaboration' === $todo['type'] ? __( 'Collaboration', 'remember' ) : __( 'Vetting', 'remember' ) ); ?></span>
								<span class="remember-todo-title"><?php echo esc_html( $todo['title'] ); ?></span>
								<?php if ( ! empty( $todo['scheduled_time'] ) ) : ?>
									<span class="remember-todo-scheduled <?php echo $todo['is_overdue'] ? 'remember-todo-overdue' : ''; ?>">
										<?php echo esc_html( $todo['scheduled_time'] ); ?>
										<?php if ( $todo['is_overdue'] ) : ?>
											<span class="remember-todo-overdue-badge"><?php esc_html_e( 'Overdue', 'remember' ); ?></span>
										<?php endif; ?>
									</span>
								<?php elseif ( 'scheduled' === $todo['status'] ) : ?>
									<span class="remember-todo-badge"><?php esc_html_e( 'Scheduled', 'remember' ); ?></span>
								<?php elseif ( 'in_progress' === $todo['status'] ) : ?>
									<span class="remember-todo-badge"><?php esc_html_e( 'In Progress', 'remember' ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No tasks assigned to you.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
