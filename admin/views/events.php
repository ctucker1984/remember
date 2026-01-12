<?php
/**
 * Events view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-location.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-role.php';

Remember_Logger::debug( 'Events page loaded' );

$event_model = new Remember_Event();
$location_model = new Remember_Location();
$application_model = new Remember_Application();
$role_model = new Remember_Role();

// Get all available event roles for the forms
$event_roles = $role_model->get_event_roles();

// Initialize editing event role IDs (will be set if editing)
$editing_event_role_ids = array();

// Handle form submissions
if ( isset( $_POST['remember_event_action'] ) && check_admin_referer( 'remember_event_action', 'remember_event_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_event_action'] );
	
	if ( 'add' === $action ) {
		// Check capability
		if ( ! current_user_can( 'remember_create_events' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		$data = array(
			'event_name'        => sanitize_text_field( wp_unslash( $_POST['event_name'] ) ),
			'event_description' => sanitize_textarea_field( wp_unslash( $_POST['event_description'] ) ),
			'location_id'       => ! empty( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : null,
			'start_date'        => sanitize_text_field( wp_unslash( $_POST['start_date'] ) ),
			'end_date'          => sanitize_text_field( wp_unslash( $_POST['end_date'] ) ),
			'is_private'        => isset( $_POST['is_private'] ) ? 1 : 0,
			'status'            => sanitize_text_field( wp_unslash( $_POST['status'] ) ),
			'created_by'        => get_current_user_id(),
		);
		$event_id = $event_model->create( $data );
		if ( $event_id ) {
			// Handle event roles
			if ( isset( $_POST['event_roles'] ) && is_array( $_POST['event_roles'] ) ) {
				$role_ids = array_map( 'absint', $_POST['event_roles'] );
				$event_model->set_event_roles( $event_id, $role_ids );
			}
			Remember_Logger::info( 'Event created', array( 'event_id' => $event_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event created successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to create event' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create event.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'edit' === $action && isset( $_POST['event_id'] ) ) {
		// Check capability
		if ( ! current_user_can( 'remember_update_events' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		$event_id = absint( $_POST['event_id'] );
		$data = array(
			'event_name'        => sanitize_text_field( wp_unslash( $_POST['event_name'] ) ),
			'event_description' => sanitize_textarea_field( wp_unslash( $_POST['event_description'] ) ),
			'location_id'       => ! empty( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : null,
			'start_date'        => sanitize_text_field( wp_unslash( $_POST['start_date'] ) ),
			'end_date'          => sanitize_text_field( wp_unslash( $_POST['end_date'] ) ),
			'is_private'        => isset( $_POST['is_private'] ) ? 1 : 0,
			'status'            => sanitize_text_field( wp_unslash( $_POST['status'] ) ),
		);
		$result = $event_model->update( $event_id, $data );
		if ( $result !== false ) {
			// Handle event roles
			if ( isset( $_POST['event_roles'] ) && is_array( $_POST['event_roles'] ) ) {
				$role_ids = array_map( 'absint', $_POST['event_roles'] );
				$event_model->set_event_roles( $event_id, $role_ids );
			} else {
				// If no roles selected, clear all event roles
				$event_model->set_event_roles( $event_id, array() );
			}
			Remember_Logger::info( 'Event updated', array( 'event_id' => $event_id, 'role_ids' => isset( $_POST['event_roles'] ) ? $_POST['event_roles'] : array() ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event updated successfully.', 'remember' ) . '</p></div>';
			// Reload the event data to reflect changes
			$editing_event = $event_model->get( $event_id );
			if ( $editing_event ) {
				$editing_event_roles = $event_model->get_event_roles( $event_id );
				$editing_event_role_ids = array_map( function( $role ) {
					return $role->role_id;
				}, $editing_event_roles );
			}
		} else {
			Remember_Logger::error( 'Failed to update event', array( 'event_id' => $event_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to update event.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'delete' === $action && isset( $_GET['delete'] ) ) {
		// Check capability
		if ( ! current_user_can( 'remember_delete_events' ) ) {
			wp_die( __( 'You do not have sufficient permissions to perform this action.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		
		$event_id = absint( $_GET['delete'] );
		$result = $event_model->delete( $event_id );
		if ( $result !== false ) {
			Remember_Logger::info( 'Event deleted', array( 'event_id' => $event_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event deleted successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to delete event', array( 'event_id' => $event_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete event.', 'remember' ) . '</p></div>';
		}
	}
}

// Get events and locations
$events = $event_model->get_all();
$locations = $location_model->get_active();

// Check if viewing detail or editing
$viewing_event = null;
$editing_event = null;
if ( isset( $_GET['view'] ) ) {
	$view_id = absint( $_GET['view'] );
	$viewing_event = $event_model->get( $view_id );
	if ( $viewing_event ) {
		// Get applications for this event
		$event_applications = $application_model->get_by_event( $view_id );
		// Get location if exists
		if ( $viewing_event->location_id ) {
			$viewing_location = $location_model->get( $viewing_event->location_id );
		}
	}
} elseif ( isset( $_GET['edit'] ) ) {
	// Check capability
	if ( ! current_user_can( 'remember_update_events' ) ) {
		wp_die( __( 'You do not have sufficient permissions to edit events.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
	}
	
	$edit_id = absint( $_GET['edit'] );
	$editing_event = $event_model->get( $edit_id );
	if ( $editing_event ) {
		// Get existing event roles for this event
		$editing_event_roles = $event_model->get_event_roles( $edit_id );
		$editing_event_role_ids = array_map( function( $role ) {
			return $role->role_id;
		}, $editing_event_roles );
	}
}
?>

<div class="wrap remember-events">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php if ( ! $viewing_event && ! $editing_event ) : ?>
		<?php if ( current_user_can( 'remember_create_events' ) ) : ?>
			<button type="button" class="page-title-action" onclick="document.getElementById('remember-add-event').style.display='block'; this.style.display='none';"><?php esc_html_e( 'Add New', 'remember' ); ?></button>
		<?php endif; ?>
	<?php elseif ( $viewing_event ) : ?>
		<?php if ( current_user_can( 'remember_update_events' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events&edit=' . $viewing_event->event_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit', 'remember' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to List', 'remember' ); ?></a>
	<?php else : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
	<?php endif; ?>
	
	<hr class="wp-header-end">

	<?php if ( $viewing_event ) : ?>
		<?php include 'event-detail.php'; ?>
	<?php elseif ( $editing_event ) : ?>
		<!-- Edit Form -->
		<div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Edit Event', 'remember' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_event_action', 'remember_event_nonce' ); ?>
				<input type="hidden" name="remember_event_action" value="edit">
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $editing_event->event_id ); ?>">
				
				<table class="form-table">
					<tr>
						<th><label for="event_name"><?php esc_html_e( 'Event Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="text" id="event_name" name="event_name" class="regular-text" value="<?php echo esc_attr( $editing_event->event_name ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="event_description"><?php esc_html_e( 'Description', 'remember' ); ?></label></th>
						<td><textarea id="event_description" name="event_description" class="large-text" rows="5"><?php echo esc_textarea( $editing_event->event_description ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="location_id"><?php esc_html_e( 'Location', 'remember' ); ?></label></th>
						<td>
							<select id="location_id" name="location_id" class="regular-text">
								<option value=""><?php esc_html_e( '-- Select Location --', 'remember' ); ?></option>
								<?php foreach ( $locations as $location ) : ?>
									<option value="<?php echo esc_attr( $location->location_id ); ?>" <?php selected( $editing_event->location_id, $location->location_id ); ?>><?php echo esc_html( $location->location_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="start_date"><?php esc_html_e( 'Start Date', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="date" id="start_date" name="start_date" class="regular-text" value="<?php echo esc_attr( $editing_event->start_date ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="end_date"><?php esc_html_e( 'End Date', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="date" id="end_date" name="end_date" class="regular-text" value="<?php echo esc_attr( $editing_event->end_date ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="is_private"><?php esc_html_e( 'Private Event', 'remember' ); ?></label></th>
						<td><label><input type="checkbox" id="is_private" name="is_private" value="1" <?php checked( $editing_event->is_private, 1 ); ?>> <?php esc_html_e( 'This is a private event (invite only)', 'remember' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="status"><?php esc_html_e( 'Status', 'remember' ); ?></label></th>
						<td>
							<select id="status" name="status" class="regular-text">
								<option value="draft" <?php selected( $editing_event->status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'remember' ); ?></option>
								<option value="open" <?php selected( $editing_event->status, 'open' ); ?>><?php esc_html_e( 'Open', 'remember' ); ?></option>
								<option value="closed" <?php selected( $editing_event->status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'remember' ); ?></option>
								<option value="completed" <?php selected( $editing_event->status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'remember' ); ?></option>
								<option value="cancelled" <?php selected( $editing_event->status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'remember' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Event Roles', 'remember' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Select the roles that are available for this event. Members can apply for these roles when submitting applications.', 'remember' ); ?></p>
							<fieldset style="border: 1px solid #ccd0d4; padding: 10px; margin-top: 10px;">
								<?php if ( ! empty( $event_roles ) ) : ?>
									<?php foreach ( $event_roles as $role ) : ?>
										<label style="display: block; margin: 5px 0;">
											<input type="checkbox" name="event_roles[]" value="<?php echo esc_attr( $role->role_id ); ?>" <?php checked( isset( $editing_event_role_ids ) && in_array( $role->role_id, $editing_event_role_ids ) ); ?>>
											<?php echo esc_html( $role->role_name ); ?>
										</label>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'No event roles available. Create event roles in the Roles section first.', 'remember' ); ?></p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update Event', 'remember' ); ?>">
				</p>
			</form>
		</div>
	<?php else : ?>
		<!-- Add Form -->
		<div id="remember-add-event" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Add New Event', 'remember' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_event_action', 'remember_event_nonce' ); ?>
				<input type="hidden" name="remember_event_action" value="add">
				
				<table class="form-table">
					<tr>
						<th><label for="event_name"><?php esc_html_e( 'Event Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="text" id="event_name" name="event_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="event_description"><?php esc_html_e( 'Description', 'remember' ); ?></label></th>
						<td><textarea id="event_description" name="event_description" class="large-text" rows="5"></textarea></td>
					</tr>
					<tr>
						<th><label for="location_id"><?php esc_html_e( 'Location', 'remember' ); ?></label></th>
						<td>
							<select id="location_id" name="location_id" class="regular-text">
								<option value=""><?php esc_html_e( '-- Select Location --', 'remember' ); ?></option>
								<?php foreach ( $locations as $location ) : ?>
									<option value="<?php echo esc_attr( $location->location_id ); ?>"><?php echo esc_html( $location->location_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="start_date"><?php esc_html_e( 'Start Date', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="date" id="start_date" name="start_date" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="end_date"><?php esc_html_e( 'End Date', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="date" id="end_date" name="end_date" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="is_private"><?php esc_html_e( 'Private Event', 'remember' ); ?></label></th>
						<td><label><input type="checkbox" id="is_private" name="is_private" value="1"> <?php esc_html_e( 'This is a private event (invite only)', 'remember' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="status"><?php esc_html_e( 'Status', 'remember' ); ?></label></th>
						<td>
							<select id="status" name="status" class="regular-text">
								<option value="draft"><?php esc_html_e( 'Draft', 'remember' ); ?></option>
								<option value="open"><?php esc_html_e( 'Open', 'remember' ); ?></option>
								<option value="closed"><?php esc_html_e( 'Closed', 'remember' ); ?></option>
								<option value="completed"><?php esc_html_e( 'Completed', 'remember' ); ?></option>
								<option value="cancelled"><?php esc_html_e( 'Cancelled', 'remember' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Event Roles', 'remember' ); ?></label></th>
						<td>
							<p class="description"><?php esc_html_e( 'Select the roles that are available for this event. Members can apply for these roles when submitting applications.', 'remember' ); ?></p>
							<fieldset style="border: 1px solid #ccd0d4; padding: 10px; margin-top: 10px;">
								<?php if ( ! empty( $event_roles ) ) : ?>
									<?php foreach ( $event_roles as $role ) : ?>
										<label style="display: block; margin: 5px 0;">
											<input type="checkbox" name="event_roles[]" value="<?php echo esc_attr( $role->role_id ); ?>">
											<?php echo esc_html( $role->role_name ); ?>
										</label>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'No event roles available. Create event roles in the Roles section first.', 'remember' ); ?></p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Event', 'remember' ); ?>">
					<button type="button" class="button" onclick="document.getElementById('remember-add-event').style.display='none'; document.querySelector('.page-title-action').style.display='inline-block';"><?php esc_html_e( 'Cancel', 'remember' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Events List -->
		<?php if ( ! empty( $events ) ) : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
					<th class="column-name"><?php esc_html_e( 'Event Name', 'remember' ); ?></th>
					<th class="column-location"><?php esc_html_e( 'Location', 'remember' ); ?></th>
					<th class="column-dates"><?php esc_html_e( 'Dates', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Type', 'remember' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
			</tr>
		</thead>
		<tbody>
				<?php foreach ( $events as $event ) : 
					$location = $event->location_id ? $location_model->get( $event->location_id ) : null;
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
				?>
					<tr>
						<td class="column-name"><strong><?php echo esc_html( $event->event_name ); ?></strong></td>
						<td class="column-location"><?php echo $location ? esc_html( $location->location_name ) : '<span class="description">—</span>'; ?></td>
						<td class="column-dates">
							<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) ) ); ?>
							<?php if ( $event->start_date !== $event->end_date ) : ?>
								<br><span class="description"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $event->end_date ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-status">
							<span style="color: <?php echo esc_attr( $status_colors[ $event->status ] ); ?>;">
								<?php echo esc_html( $status_labels[ $event->status ] ); ?>
							</span>
						</td>
						<td class="column-type">
							<?php if ( $event->is_private ) : ?>
								<span class="dashicons dashicons-lock" title="<?php esc_attr_e( 'Private', 'remember' ); ?>"></span> <?php esc_html_e( 'Private', 'remember' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-unlock" title="<?php esc_attr_e( 'Public', 'remember' ); ?>"></span> <?php esc_html_e( 'Public', 'remember' ); ?>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events&view=' . $event->event_id ) ); ?>"><?php esc_html_e( 'View', 'remember' ); ?></a>
							<?php if ( current_user_can( 'remember_update_events' ) ) : ?>
								| <a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events&edit=' . $event->event_id ) ); ?>"><?php esc_html_e( 'Edit', 'remember' ); ?></a>
							<?php endif; ?>
							<?php if ( current_user_can( 'remember_delete_events' ) ) : ?>
								| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-events&delete=' . $event->event_id ), 'remember_event_action', 'remember_event_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this event?', 'remember' ); ?>');"><?php esc_html_e( 'Delete', 'remember' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
		</tbody>
	</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No events found. Add your first event above.', 'remember' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
