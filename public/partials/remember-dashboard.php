<?php
/**
 * Member dashboard template
 *
 * @package    reMember
 * @subpackage reMember/public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-application.php';

$user = wp_get_current_user();
$member_model = new Remember_Member();
$event_model = new Remember_Event();
$application_model = new Remember_Application();

// Get member profile
global $wpdb;
$profile = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
		get_current_user_id()
	)
);

// Get applications
$applications = $application_model->get_by_member( get_current_user_id() );

// Get accepted events (where member has accepted application)
$accepted_events = array();
foreach ( $applications as $app ) {
	if ( 'accepted' === $app->status ) {
		$event = $event_model->get( $app->event_id );
		if ( $event ) {
			$accepted_events[] = $event;
		}
	}
}

// Status labels
$status_labels = array(
	'pending_vetting' => __( 'Pending Vetting', 'remember' ),
	'unvetted'        => __( 'Unvetted', 'remember' ),
	'in_vetting'      => __( 'In Vetting', 'remember' ),
	'vetted'          => __( 'Vetted', 'remember' ),
	'rejected'        => __( 'Rejected', 'remember' ),
	'inactive'        => __( 'Inactive', 'remember' ),
);

$status_colors = array(
	'pending_vetting' => '#666',
	'unvetted'        => '#666',
	'in_vetting'      => '#2271b1',
	'vetted'          => '#46b450',
	'rejected'        => '#dc3232',
	'inactive'        => '#666',
);

$app_status_labels = array(
	'pending'    => __( 'Pending', 'remember' ),
	'accepted'   => __( 'Accepted', 'remember' ),
	'declined'   => __( 'Declined', 'remember' ),
	'waitlisted' => __( 'Waitlisted', 'remember' ),
	'withdrawn'  => __( 'Withdrawn', 'remember' ),
);
?>

<div class="remember-dashboard">
	<div class="remember-dashboard-header">
		<h2><?php echo esc_html( sprintf( __( 'Welcome, %s', 'remember' ), $user->display_name ) ); ?></h2>
		<p class="remember-member-status" style="color: <?php echo esc_attr( $status_colors[ $member->status ] ); ?>;">
			<?php echo esc_html( $status_labels[ $member->status ] ); ?>
		</p>
	</div>

	<div class="remember-dashboard-grid">
		<!-- Profile Summary -->
		<div class="remember-dashboard-card">
			<h3><?php esc_html_e( 'Profile', 'remember' ); ?></h3>
			<?php if ( $profile ) : ?>
				<p>
					<strong><?php esc_html_e( 'Legal Name:', 'remember' ); ?></strong><br>
					<?php echo esc_html( trim( ( $profile->legal_first_name ?? '' ) . ' ' . ( $profile->legal_last_name ?? '' ) ) ); ?>
				</p>
				<?php if ( ! empty( $profile->cell_phone ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Phone:', 'remember' ); ?></strong><br>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile->cell_phone ) ); ?>">
							<?php echo esc_html( $profile->cell_phone ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( add_query_arg( 'edit', '1', get_permalink() ) ); ?>" class="remember-button remember-button-primary">
					<?php esc_html_e( 'Edit Profile', 'remember' ); ?>
				</a>
			</p>
		</div>

		<!-- Accepted Events -->
		<div class="remember-dashboard-card">
			<h3><?php esc_html_e( 'Accepted Events', 'remember' ); ?></h3>
			<?php if ( ! empty( $accepted_events ) ) : ?>
				<ul class="remember-event-list">
					<?php foreach ( $accepted_events as $event ) : 
						// Get event detail page URL
						$event_detail_pages = get_option( 'remember_created_pages', array() );
						$event_detail_page_id = isset( $event_detail_pages['event_detail'] ) ? $event_detail_pages['event_detail'] : 0;
						
						if ( $event_detail_page_id ) {
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink( $event_detail_page_id ) );
						} else {
							// Fallback to dashboard with event parameter
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink() );
						}
					?>
						<li>
							<strong><a href="<?php echo esc_url( $event_url ); ?>">
								<?php echo esc_html( $event->event_name ); ?>
							</a></strong><br>
							<small>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) ) ); ?>
								<?php if ( $event->start_date !== $event->end_date ) : ?>
									- <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->end_date ) ) ); ?>
								<?php endif; ?>
							</small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="remember-description"><?php esc_html_e( 'No accepted events yet.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Applications -->
		<div class="remember-dashboard-card">
			<h3><?php esc_html_e( 'Your Applications', 'remember' ); ?></h3>
			<?php if ( ! empty( $applications ) ) : ?>
				<ul class="remember-application-list">
					<?php foreach ( array_slice( $applications, 0, 5 ) as $app ) : 
						$event = $event_model->get( $app->event_id );
					?>
						<li>
							<strong><?php echo $event ? esc_html( $event->event_name ) : __( 'Unknown Event', 'remember' ); ?></strong><br>
							<span class="remember-status-badge remember-status-<?php echo esc_attr( $app->status ); ?>">
								<?php echo esc_html( $app_status_labels[ $app->status ] ?? $app->status ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $applications ) > 5 ) : ?>
					<p><a href="<?php echo esc_url( get_permalink() . '?view=applications' ); ?>">
						<?php esc_html_e( 'View all applications', 'remember' ); ?>
					</a></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="remember-description"><?php esc_html_e( 'No applications yet.', 'remember' ); ?></p>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( get_permalink() . '?view=events' ); ?>" class="remember-button remember-button-secondary">
					<?php esc_html_e( 'Browse Events', 'remember' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>
