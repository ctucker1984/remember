<?php
/**
 * Applications view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-payment.php';

Remember_Logger::debug( 'Applications page loaded' );

$application_model = new Remember_Application();
$event_model = new Remember_Event();
$member_model = new Remember_Member();
$payment_model = new Remember_Payment();

// Handle form submissions
if ( isset( $_POST['remember_application_action'] ) && check_admin_referer( 'remember_application_action', 'remember_application_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_application_action'] );
	$application_id = isset( $_POST['application_id'] ) ? absint( $_POST['application_id'] ) : 0;
	
	if ( 'add' === $action ) {
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$member_id = isset( $_POST['member_id'] ) ? absint( $_POST['member_id'] ) : 0;
		$event_role_id = isset( $_POST['event_role_id'] ) ? absint( $_POST['event_role_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'pending';
		
		if ( $event_id > 0 && $member_id > 0 && $event_role_id > 0 ) {
			// Check if application already exists
			$existing = $application_model->get_existing_application( $event_id, $member_id, $event_role_id );
			if ( $existing ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'An application for this member, event, and role already exists.', 'remember' ) . '</p></div>';
			} else {
				$data = array(
					'event_id'     => $event_id,
					'member_id'    => $member_id,
					'event_role_id' => $event_role_id,
					'status'       => $status,
				);
				$new_application_id = $application_model->create( $data );
				if ( $new_application_id ) {
					Remember_Logger::info( 'Application created', array( 'application_id' => $new_application_id ) );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Application created successfully.', 'remember' ) . '</p></div>';
				} else {
					Remember_Logger::error( 'Failed to create application' );
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create application.', 'remember' ) . '</p></div>';
				}
			}
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please select an event, member, and role.', 'remember' ) . '</p></div>';
		}
	} elseif ( $application_id > 0 ) {
		if ( 'accept' === $action ) {
			$result = $application_model->update_status( $application_id, 'accepted', get_current_user_id() );
			if ( $result !== false ) {
				Remember_Logger::info( 'Application accepted', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Application accepted successfully.', 'remember' ) . '</p></div>';
			} else {
				Remember_Logger::error( 'Failed to accept application', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to accept application.', 'remember' ) . '</p></div>';
			}
		} elseif ( 'decline' === $action ) {
			$result = $application_model->update_status( $application_id, 'declined', get_current_user_id() );
			if ( $result !== false ) {
				Remember_Logger::info( 'Application declined', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Application declined successfully.', 'remember' ) . '</p></div>';
			} else {
				Remember_Logger::error( 'Failed to decline application', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to decline application.', 'remember' ) . '</p></div>';
			}
		} elseif ( 'waitlist' === $action ) {
			$result = $application_model->update_status( $application_id, 'waitlisted', get_current_user_id() );
			if ( $result !== false ) {
				Remember_Logger::info( 'Application waitlisted', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Application moved to waitlist.', 'remember' ) . '</p></div>';
			} else {
				Remember_Logger::error( 'Failed to waitlist application', array( 'application_id' => $application_id ) );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to waitlist application.', 'remember' ) . '</p></div>';
			}
		}
	}
}

// Get filter parameters
$filter_event = isset( $_GET['filter_event'] ) ? absint( $_GET['filter_event'] ) : 0;
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

// Get applications
if ( $filter_event > 0 ) {
	$applications = $application_model->get_by_event( $filter_event );
} elseif ( ! empty( $filter_status ) ) {
	$applications = $application_model->get_by_status( $filter_status );
} else {
	$applications = $application_model->get_all();
}

// Get events for filter
$events = $event_model->get_all();

// Status labels and colors
$status_labels = array(
	'pending'   => __( 'Pending', 'remember' ),
	'accepted'  => __( 'Accepted', 'remember' ),
	'declined'  => __( 'Declined', 'remember' ),
	'cancelled' => __( 'Cancelled', 'remember' ),
	'waitlisted' => __( 'Waitlisted', 'remember' ),
);
$status_colors = array(
	'pending'   => '#f0b849',
	'accepted'  => '#46b450',
	'declined'  => '#dc3232',
	'cancelled' => '#72777c',
	'waitlisted' => '#00a0d2',
);
?>

<div class="wrap remember-applications">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<button type="button" class="page-title-action" onclick="document.getElementById('remember-add-application').style.display='block'; this.style.display='none';"><?php esc_html_e( 'Add New', 'remember' ); ?></button>
	<hr class="wp-header-end">

	<!-- Add Form -->
	<div id="remember-add-application" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<h2><?php esc_html_e( 'Add New Application', 'remember' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
			<input type="hidden" name="remember_application_action" value="add">
			
			<table class="form-table">
				<tr>
					<th><label for="event_id"><?php esc_html_e( 'Event', 'remember' ); ?> <span class="description">(required)</span></label></th>
					<td>
						<select id="event_id" name="event_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '-- Select Event --', 'remember' ); ?></option>
							<?php foreach ( $events as $event ) : ?>
								<option value="<?php echo esc_attr( $event->event_id ); ?>"><?php echo esc_html( $event->event_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="member_id"><?php esc_html_e( 'Member', 'remember' ); ?> <span class="description">(required)</span></label></th>
					<td>
						<select id="member_id" name="member_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '-- Select Member --', 'remember' ); ?></option>
							<?php foreach ( $all_members as $member ) : 
								$user = get_user_by( 'ID', $member->member_id );
								if ( ! $user ) continue;
							?>
								<option value="<?php echo esc_attr( $member->member_id ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="event_role_id"><?php esc_html_e( 'Event Role', 'remember' ); ?> <span class="description">(required)</span></label></th>
					<td>
						<select id="event_role_id" name="event_role_id" class="regular-text" required>
							<option value=""><?php esc_html_e( '-- Select Event Role --', 'remember' ); ?></option>
							<?php foreach ( $all_event_roles as $event_role ) : ?>
								<option value="<?php echo esc_attr( $event_role->event_role_id ); ?>" data-event-id="<?php echo esc_attr( $event_role->event_id ); ?>">
									<?php echo esc_html( $event_role->event_name . ' - ' . $event_role->role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Filtered by selected event. Role must belong to the selected event.', 'remember' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="status"><?php esc_html_e( 'Initial Status', 'remember' ); ?></label></th>
					<td>
						<select id="status" name="status" class="regular-text">
							<?php foreach ( $status_labels as $status => $label ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( 'pending', $status ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Application', 'remember' ); ?>">
				<button type="button" class="button" onclick="document.getElementById('remember-add-application').style.display='none'; document.querySelector('.page-title-action').style.display='inline-block';"><?php esc_html_e( 'Cancel', 'remember' ); ?></button>
			</p>
		</form>
	</div>

	<!-- Filters -->
	<div class="remember-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<form method="get" action="">
			<input type="hidden" name="page" value="remember-applications">
			
			<label for="filter_event"><?php esc_html_e( 'Filter by Event:', 'remember' ); ?></label>
			<select id="filter_event" name="filter_event" style="margin-right: 20px;">
				<option value="0"><?php esc_html_e( 'All Events', 'remember' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( $event->event_id ); ?>" <?php selected( $filter_event, $event->event_id ); ?>>
						<?php echo esc_html( $event->event_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="filter_status"><?php esc_html_e( 'Filter by Status:', 'remember' ); ?></label>
			<select id="filter_status" name="filter_status" style="margin-right: 20px;">
				<option value=""><?php esc_html_e( 'All Statuses', 'remember' ); ?></option>
				<?php foreach ( $status_labels as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'remember' ); ?>">
			<?php if ( $filter_event > 0 || ! empty( $filter_status ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-applications' ) ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'remember' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Applications List -->
	<?php if ( ! empty( $applications ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th class="column-event"><?php esc_html_e( 'Event', 'remember' ); ?></th>
					<th class="column-member"><?php esc_html_e( 'Member', 'remember' ); ?></th>
					<th class="column-role"><?php esc_html_e( 'Role', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Applied', 'remember' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $applications as $application ) : 
					$event = $event_model->get( $application->event_id );
					$member = $member_model->get( $application->member_id );
					$user = $member ? get_user_by( 'ID', $application->member_id ) : null;
					
					// Get role info (we'll need to add this to the model later)
					global $wpdb;
					$event_role = $wpdb->get_row( $wpdb->prepare(
						"SELECT er.*, r.role_name FROM {$wpdb->prefix}remember_event_roles er 
						JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
						WHERE er.event_role_id = %d",
						$application->event_role_id
					) );
				?>
					<tr>
						<td class="column-event">
							<strong><?php echo $event ? esc_html( $event->event_name ) : '<span class="description">—</span>'; ?></strong>
						</td>
						<td class="column-member">
							<?php if ( $user ) : ?>
								<?php echo esc_html( $user->display_name ); ?><br>
								<span class="description"><?php echo esc_html( $user->user_email ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Member not found', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-role">
							<?php echo $event_role ? esc_html( $event_role->role_name ) : '<span class="description">—</span>'; ?>
						</td>
						<td class="column-status">
							<span style="color: <?php echo esc_attr( $status_colors[ $application->status ] ); ?>; font-weight: bold;">
								<?php echo esc_html( $status_labels[ $application->status ] ); ?>
							</span>
						</td>
						<td class="column-date">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $application->applied_at ) ) ); ?>
						</td>
						<td class="column-actions">
							<?php if ( 'pending' === $application->status || 'waitlisted' === $application->status ) : ?>
								<form method="post" action="" style="display: inline;">
									<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
									<input type="hidden" name="remember_application_action" value="accept">
									<input type="hidden" name="application_id" value="<?php echo esc_attr( $application->application_id ); ?>">
									<input type="submit" class="button button-small" value="<?php esc_attr_e( 'Accept', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Accept this application?', 'remember' ); ?>');">
								</form>
								
								<form method="post" action="" style="display: inline;">
									<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
									<input type="hidden" name="remember_application_action" value="decline">
									<input type="hidden" name="application_id" value="<?php echo esc_attr( $application->application_id ); ?>">
									<input type="submit" class="button button-small" value="<?php esc_attr_e( 'Decline', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Decline this application?', 'remember' ); ?>');">
								</form>
								
								<?php if ( 'pending' === $application->status ) : ?>
									<form method="post" action="" style="display: inline;">
										<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
										<input type="hidden" name="remember_application_action" value="waitlist">
										<input type="hidden" name="application_id" value="<?php echo esc_attr( $application->application_id ); ?>">
										<input type="submit" class="button button-small" value="<?php esc_attr_e( 'Waitlist', 'remember' ); ?>">
									</form>
								<?php endif; ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Processed', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<p class="description" style="margin-top: 15px;">
			<?php echo esc_html( sprintf( __( 'Showing %d application(s)', 'remember' ), count( $applications ) ) ); ?>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'No applications found.', 'remember' ); ?></p>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	// Filter event roles by selected event
	$('#event_id').on('change', function() {
		var selectedEventId = $(this).val();
		$('#event_role_id option').each(function() {
			var eventId = $(this).data('event-id');
			if (selectedEventId === '' || eventId === selectedEventId) {
				$(this).show();
			} else {
				$(this).hide();
				if ($(this).prop('selected')) {
					$('#event_role_id').val('');
				}
			}
		});
	});
});
</script>
