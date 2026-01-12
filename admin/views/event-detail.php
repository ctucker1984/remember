<?php
/**
 * Event detail view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';

$member_model = new Remember_Member();

// Status labels and colors for events
$status_labels = array(
	'draft'     => __( 'Draft', 'remember' ),
	'open'      => __( 'Open', 'remember' ),
	'closed'    => __( 'Closed', 'remember' ),
	'completed' => __( 'Completed', 'remember' ),
	'cancelled' => __( 'Cancelled', 'remember' ),
);
$status_colors = array(
	'draft'     => '#72777c',
	'open'      => '#46b450',
	'closed'    => '#dc3232',
	'completed' => '#00a0d2',
	'cancelled' => '#dc3232',
);

// Application status labels and colors
$app_status_labels = array(
	'pending'   => __( 'Pending', 'remember' ),
	'accepted'  => __( 'Accepted', 'remember' ),
	'declined'  => __( 'Declined', 'remember' ),
	'cancelled' => __( 'Cancelled', 'remember' ),
	'waitlisted' => __( 'Waitlisted', 'remember' ),
);
$app_status_colors = array(
	'pending'   => '#f0b849',
	'accepted'  => '#46b450',
	'declined'  => '#dc3232',
	'cancelled' => '#72777c',
	'waitlisted' => '#00a0d2',
);

// Format dates
$start_date = date_i18n( get_option( 'date_format' ), strtotime( $viewing_event->start_date ) );
$end_date = date_i18n( get_option( 'date_format' ), strtotime( $viewing_event->end_date ) );
$is_multi_day = $viewing_event->start_date !== $viewing_event->end_date;
?>

<div class="remember-event-detail">
	<!-- Header Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
		<div style="display: flex; align-items: center; gap: 20px; justify-content: space-between;">
			<div style="flex: 1;">
				<h2 style="margin: 0 0 10px 0; font-size: 24px;">
					<?php echo esc_html( $viewing_event->event_name ); ?>
					<span style="color: <?php echo esc_attr( $status_colors[ $viewing_event->status ] ); ?>; font-size: 14px; font-weight: normal; margin-left: 10px;">
						<?php echo esc_html( $status_labels[ $viewing_event->status ] ); ?>
					</span>
					<?php if ( $viewing_event->is_private ) : ?>
						<span class="dashicons dashicons-lock" style="color: #72777c; font-size: 18px; vertical-align: middle; margin-left: 5px;" title="<?php esc_attr_e( 'Private Event', 'remember' ); ?>"></span>
					<?php endif; ?>
				</h2>
				<div style="color: #666; margin-top: 5px; line-height: 1.8;">
					<p style="margin: 5px 0;">
						<strong><?php esc_html_e( 'Dates:', 'remember' ); ?></strong>
						<?php if ( $is_multi_day ) : ?>
							<?php echo esc_html( $start_date . ' - ' . $end_date ); ?>
						<?php else : ?>
							<?php echo esc_html( $start_date ); ?>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $viewing_location ) ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Location:', 'remember' ); ?></strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations&view=' . $viewing_location->location_id ) ); ?>">
								<?php echo esc_html( $viewing_location->location_name ); ?>
							</a>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $viewing_event->event_description ) ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Description:', 'remember' ); ?></strong>
							<?php echo esc_html( $viewing_event->event_description ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Applications Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
		<h3 style="margin-top: 0;">
			<?php esc_html_e( 'Applications', 'remember' ); ?>
			<?php if ( ! empty( $event_applications ) ) : ?>
				<span style="font-size: 14px; font-weight: normal; color: #666;">
					(<?php echo esc_html( count( $event_applications ) ); ?>)
				</span>
			<?php endif; ?>
		</h3>
		<?php if ( ! empty( $event_applications ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-member"><?php esc_html_e( 'Member', 'remember' ); ?></th>
						<th class="column-role"><?php esc_html_e( 'Role', 'remember' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
						<th class="column-date"><?php esc_html_e( 'Applied', 'remember' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $event_applications as $application ) : 
						$member = $member_model->get( $application->member_id );
						$user = $member ? get_user_by( 'ID', $application->member_id ) : null;
						
						// Get role info - join through event_roles to get role_name
						$event_role = null;
						if ( ! empty( $application->event_role_id ) ) {
							global $wpdb;
							// Query: application.event_role_id -> event_roles.event_role_id -> event_roles.role_id -> roles.role_id -> roles.role_name
							$event_role = $wpdb->get_row( $wpdb->prepare(
								"SELECT er.event_role_id, er.event_id, er.role_id, r.role_name 
								FROM {$wpdb->prefix}remember_event_roles er 
								LEFT JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
								WHERE er.event_role_id = %d",
								$application->event_role_id
							) );
							
							// Debug: if still no role, check if event_role exists at all
							if ( ! $event_role ) {
								$event_role_check = $wpdb->get_row( $wpdb->prepare(
									"SELECT * FROM {$wpdb->prefix}remember_event_roles WHERE event_role_id = %d",
									$application->event_role_id
								) );
								if ( $event_role_check ) {
									// Event role exists, get role name separately
									$role = $wpdb->get_row( $wpdb->prepare(
										"SELECT role_name FROM {$wpdb->prefix}remember_roles WHERE role_id = %d",
										$event_role_check->role_id
									) );
									if ( $role ) {
										$event_role = (object) array(
											'event_role_id' => $event_role_check->event_role_id,
											'role_id' => $event_role_check->role_id,
											'role_name' => $role->role_name,
										);
									}
								}
							}
						}
					?>
						<tr>
							<td class="column-member">
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $application->member_id ) ); ?>">
										<strong><?php echo esc_html( $user->display_name ); ?></strong>
									</a>
									<?php if ( $user->user_email ) : ?>
										<br><span class="description"><?php echo esc_html( $user->user_email ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Member not found', 'remember' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-role">
								<?php echo $event_role ? esc_html( $event_role->role_name ) : '<span class="description">—</span>'; ?>
							</td>
							<td class="column-status">
								<span style="color: <?php echo esc_attr( $app_status_colors[ $application->status ] ); ?>;">
									<?php echo esc_html( $app_status_labels[ $application->status ] ); ?>
								</span>
							</td>
							<td class="column-date">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $application->applied_at ) ) ); ?>
							</td>
							<td class="column-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-applications&view=' . $application->application_id ) ); ?>">
									<?php esc_html_e( 'View', 'remember' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No applications found for this event.', 'remember' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Attendees Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
		<h3 style="margin-top: 0;">
			<?php esc_html_e( 'Attendees', 'remember' ); ?>
			<?php
			// Get accepted applications (attendees)
			$attendees = array();
			if ( ! empty( $event_applications ) ) {
				foreach ( $event_applications as $app ) {
					if ( 'accepted' === $app->status ) {
						$attendees[] = $app;
					}
				}
			}
			?>
			<?php if ( ! empty( $attendees ) ) : ?>
				<span style="font-size: 14px; font-weight: normal; color: #666;">
					(<?php echo esc_html( count( $attendees ) ); ?>)
				</span>
			<?php endif; ?>
		</h3>
		<?php if ( ! empty( $attendees ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-member"><?php esc_html_e( 'Member', 'remember' ); ?></th>
						<th class="column-role"><?php esc_html_e( 'Role', 'remember' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $attendees as $attendee ) : 
						$member = $member_model->get( $attendee->member_id );
						$user = $member ? get_user_by( 'ID', $attendee->member_id ) : null;
						
						// Get role info - join through event_roles to get role_name
						$event_role = null;
						if ( ! empty( $attendee->event_role_id ) ) {
							global $wpdb;
							// Query: application.event_role_id -> event_roles.event_role_id -> event_roles.role_id -> roles.role_id -> roles.role_name
							$event_role = $wpdb->get_row( $wpdb->prepare(
								"SELECT er.event_role_id, er.event_id, er.role_id, r.role_name 
								FROM {$wpdb->prefix}remember_event_roles er 
								LEFT JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
								WHERE er.event_role_id = %d",
								$attendee->event_role_id
							) );
							
							// Debug: if still no role, check if event_role exists at all
							if ( ! $event_role ) {
								$event_role_check = $wpdb->get_row( $wpdb->prepare(
									"SELECT * FROM {$wpdb->prefix}remember_event_roles WHERE event_role_id = %d",
									$attendee->event_role_id
								) );
								if ( $event_role_check ) {
									// Event role exists, get role name separately
									$role = $wpdb->get_row( $wpdb->prepare(
										"SELECT role_name FROM {$wpdb->prefix}remember_roles WHERE role_id = %d",
										$event_role_check->role_id
									) );
									if ( $role ) {
										$event_role = (object) array(
											'event_role_id' => $event_role_check->event_role_id,
											'role_id' => $event_role_check->role_id,
											'role_name' => $role->role_name,
										);
									}
								}
							}
						}
					?>
						<tr>
							<td class="column-member">
								<?php if ( $user ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $attendee->member_id ) ); ?>">
										<strong><?php echo esc_html( $user->display_name ); ?></strong>
									</a>
									<?php if ( $user->user_email ) : ?>
										<br><span class="description"><?php echo esc_html( $user->user_email ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Member not found', 'remember' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-role">
								<?php echo $event_role ? esc_html( $event_role->role_name ) : '<span class="description">—</span>'; ?>
							</td>
							<td class="column-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $attendee->member_id ) ); ?>">
									<?php esc_html_e( 'View Profile', 'remember' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No attendees yet. Accepted applications will appear here.', 'remember' ); ?></p>
		<?php endif; ?>
	</div>
</div>
