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
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-vetting.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-location.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-timezone.php';

$user = wp_get_current_user();
$member_model = new Remember_Member();
$event_model = new Remember_Event();
$application_model = new Remember_Application();
$vetting_model = new Remember_Vetting();
$location_model = new Remember_Location();

// Get member record
$member = $member_model->get( get_current_user_id() );

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
$past_events = array();
$upcoming_events = array();
$today = current_time( 'Y-m-d' );

foreach ( $applications as $app ) {
	if ( 'accepted' === $app->status ) {
		$event = $event_model->get( $app->event_id );
		if ( $event ) {
			$accepted_events[] = $event;
			// Separate into past and upcoming
			if ( $event->end_date < $today ) {
				$past_events[] = $event;
			} else {
				$upcoming_events[] = $event;
			}
		}
	}
}

// Get all vetting cases for this member
$vetting_cases = $vetting_model->get_all_by_member( get_current_user_id() );

// Get profile page URL
$created_pages = get_option( 'remember_created_pages', array() );
$profile_page_id = isset( $created_pages['profile'] ) ? $created_pages['profile'] : 0;
$profile_page_url = $profile_page_id ? get_permalink( $profile_page_id ) : '';

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
	<div class="remember-dashboard-header-compact">
		<div class="remember-header-left">
			<?php if ( $member && ! empty( $member->photo_url ) ) : ?>
				<div class="remember-member-avatar-large">
					<img src="<?php echo esc_url( $member->photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>">
				</div>
			<?php endif; ?>
			<div class="remember-header-info">
				<h2><?php echo esc_html( sprintf( __( 'Welcome, %s', 'remember' ), $user->display_name ) ); ?></h2>
				<?php if ( $member ) : ?>
					<p class="remember-member-status" style="color: <?php echo esc_attr( $status_colors[ $member->status ] ?? '#666' ); ?>;">
						<?php echo esc_html( $status_labels[ $member->status ] ?? $member->status ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $profile ) : ?>
					<div class="remember-header-profile-info">
						<?php if ( ! empty( $profile->legal_first_name ) || ! empty( $profile->legal_last_name ) ) : ?>
							<span class="remember-header-info-item">
								<strong><?php esc_html_e( 'Legal Name:', 'remember' ); ?></strong>
								<?php echo esc_html( trim( ( $profile->legal_first_name ?? '' ) . ' ' . ( $profile->legal_last_name ?? '' ) ) ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $profile->cell_phone ) ) : ?>
							<span class="remember-header-info-item">
								<strong><?php esc_html_e( 'Phone:', 'remember' ); ?></strong>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile->cell_phone ) ); ?>">
									<?php echo esc_html( $profile->cell_phone ); ?>
								</a>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $profile_page_url ) : ?>
			<a href="<?php echo esc_url( $profile_page_url ); ?>" class="remember-button remember-button-primary">
				<?php esc_html_e( 'View Profile', 'remember' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="remember-dashboard-grid-compact">

		<!-- Vetting Status -->
		<div class="remember-dashboard-card-compact">
			<h3><?php esc_html_e( 'Vetting Status', 'remember' ); ?></h3>
			<?php if ( ! empty( $vetting_cases ) ) : ?>
				<div class="remember-vetting-cases">
					<?php 
					$current_user_id = get_current_user_id();
					foreach ( array_slice( $vetting_cases, 0, 3 ) as $case ) : 
						$case_notes = $vetting_model->get_notes( $case->vetting_id );
						// Filter out admin-only notes
						$member_visible_notes = array_filter( $case_notes, function( $note ) {
							return ! $note->is_admin_only;
						} );
					?>
						<div class="remember-vetting-case-item">
							<div class="remember-vetting-case-header">
								<strong><?php printf( esc_html__( 'Case #%d', 'remember' ), $case->vetting_id ); ?></strong>
								<span class="remember-status-badge remember-status-<?php echo esc_attr( $case->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $case->status ) ) ); ?>
								</span>
							</div>
							<?php if ( 'scheduled' === $case->status && ! empty( $case->scheduled_at ) ) : 
								$scheduled_display = Remember_Timezone::format_with_your_time( $case->scheduled_at, $current_user_id, true );
							?>
								<div class="remember-vetting-scheduled">
									<small><?php esc_html_e( 'Scheduled:', 'remember' ); ?> <?php echo esc_html( $scheduled_display ); ?></small>
								</div>
							<?php endif; ?>
							<?php if ( 'completed' === $case->status && ! empty( $case->decision ) && 'pending' !== $case->decision ) : ?>
								<div class="remember-vetting-decision">
									<small><strong><?php esc_html_e( 'Decision:', 'remember' ); ?></strong> <?php echo esc_html( ucfirst( $case->decision ) ); ?></small>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $member_visible_notes ) ) : ?>
								<div class="remember-vetting-notes-preview">
									<details>
										<summary><?php printf( esc_html__( 'Notes (%d)', 'remember' ), count( $member_visible_notes ) ); ?></summary>
										<ul class="remember-vetting-notes-list">
											<?php foreach ( array_slice( $member_visible_notes, 0, 3 ) as $note ) : 
												$note_author = get_user_by( 'ID', $note->member_id );
											?>
												<li>
													<small>
														<strong><?php echo $note_author ? esc_html( $note_author->display_name ) : esc_html__( 'System', 'remember' ); ?>:</strong>
														<?php echo esc_html( wp_trim_words( $note->note_content, 15 ) ); ?>
														<br>
														<span class="remember-note-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $note->created_at ) ) ); ?></span>
													</small>
												</li>
											<?php endforeach; ?>
										</ul>
									</details>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<?php if ( count( $vetting_cases ) > 3 ) : ?>
						<p class="remember-view-more"><small><?php esc_html_e( 'View all cases in profile', 'remember' ); ?></small></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="remember-description"><?php esc_html_e( 'No vetting cases yet.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Applications -->
		<div class="remember-dashboard-card-compact">
			<h3><?php esc_html_e( 'Your Applications', 'remember' ); ?></h3>
			<?php if ( ! empty( $applications ) ) : ?>
				<ul class="remember-application-list-compact">
					<?php foreach ( array_slice( $applications, 0, 5 ) as $app ) : 
						$event = $event_model->get( $app->event_id );
					?>
						<li>
							<span class="remember-app-event"><?php echo $event ? esc_html( $event->event_name ) : __( 'Unknown Event', 'remember' ); ?></span>
							<span class="remember-status-badge remember-status-<?php echo esc_attr( $app->status ); ?>">
								<?php echo esc_html( $app_status_labels[ $app->status ] ?? $app->status ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $applications ) > 5 ) : ?>
					<p class="remember-view-more"><small><a href="<?php echo esc_url( get_permalink() . '?view=applications' ); ?>">
						<?php esc_html_e( 'View all applications', 'remember' ); ?>
					</a></small></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="remember-description"><?php esc_html_e( 'No applications yet.', 'remember' ); ?></p>
			<?php endif; ?>
			<p style="margin-top: 0.75em;">
				<a href="<?php echo esc_url( get_permalink() . '?view=events' ); ?>" class="remember-button remember-button-secondary">
					<?php esc_html_e( 'Browse Events', 'remember' ); ?>
				</a>
			</p>
		</div>

		<!-- My Events -->
		<div class="remember-dashboard-card-compact">
			<div class="remember-events-header">
				<h3><?php esc_html_e( 'My Events', 'remember' ); ?></h3>
				<?php if ( ! empty( $past_events ) ) : ?>
					<label class="remember-show-past-events">
						<input type="checkbox" id="remember-show-past-events" onchange="document.querySelectorAll('.remember-past-event').forEach(el => el.style.display = this.checked ? 'block' : 'none');">
						<small><?php esc_html_e( 'Show past events', 'remember' ); ?></small>
					</label>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $accepted_events ) ) : ?>
				<div class="remember-vetting-cases">
					<?php foreach ( $upcoming_events as $event ) : 
						$event_detail_pages = get_option( 'remember_created_pages', array() );
						$event_detail_page_id = isset( $event_detail_pages['event_detail'] ) ? $event_detail_pages['event_detail'] : 0;
						
						if ( $event_detail_page_id ) {
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink( $event_detail_page_id ) );
						} else {
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink() );
						}
						
						// Get location
						$location = $event->location_id ? $location_model->get( $event->location_id ) : null;
						$location_display = '';
						if ( $location ) {
							$location_parts = array_filter( array( $location->location_name, $location->address_city, $location->address_state ) );
							$location_display = implode( ', ', $location_parts );
						}
						
						// Format dates
						$start_date = date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) );
						$end_date = date_i18n( get_option( 'date_format' ), strtotime( $event->end_date ) );
						$date_display = $event->start_date === $event->end_date ? $start_date : $start_date . ' - ' . $end_date;
					?>
						<div class="remember-vetting-case-item">
							<div class="remember-vetting-case-header">
								<strong><a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->event_name ); ?></a></strong>
							</div>
							<?php if ( ! empty( $location_display ) ) : ?>
								<div class="remember-vetting-scheduled">
									<small><strong><?php esc_html_e( 'Location:', 'remember' ); ?></strong> <?php echo esc_html( $location_display ); ?></small>
								</div>
							<?php endif; ?>
							<div class="remember-vetting-scheduled">
								<small><strong><?php esc_html_e( 'Dates:', 'remember' ); ?></strong> <?php echo esc_html( $date_display ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
					<?php foreach ( $past_events as $event ) : 
						$event_detail_pages = get_option( 'remember_created_pages', array() );
						$event_detail_page_id = isset( $event_detail_pages['event_detail'] ) ? $event_detail_pages['event_detail'] : 0;
						
						if ( $event_detail_page_id ) {
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink( $event_detail_page_id ) );
						} else {
							$event_url = add_query_arg( 'event', $event->event_id, get_permalink() );
						}
						
						// Get location
						$location = $event->location_id ? $location_model->get( $event->location_id ) : null;
						$location_display = '';
						if ( $location ) {
							$location_parts = array_filter( array( $location->location_name, $location->address_city, $location->address_state ) );
							$location_display = implode( ', ', $location_parts );
						}
						
						// Format dates
						$start_date = date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) );
						$end_date = date_i18n( get_option( 'date_format' ), strtotime( $event->end_date ) );
						$date_display = $event->start_date === $event->end_date ? $start_date : $start_date . ' - ' . $end_date;
					?>
						<div class="remember-vetting-case-item remember-past-event" style="display: none;">
							<div class="remember-vetting-case-header">
								<strong><a href="<?php echo esc_url( $event_url ); ?>"><?php echo esc_html( $event->event_name ); ?></a></strong>
							</div>
							<?php if ( ! empty( $location_display ) ) : ?>
								<div class="remember-vetting-scheduled">
									<small><strong><?php esc_html_e( 'Location:', 'remember' ); ?></strong> <?php echo esc_html( $location_display ); ?></small>
								</div>
							<?php endif; ?>
							<div class="remember-vetting-scheduled">
								<small><strong><?php esc_html_e( 'Dates:', 'remember' ); ?></strong> <?php echo esc_html( $date_display ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="remember-description"><?php esc_html_e( 'No accepted events yet.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
