<?php
/**
 * Application form template
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
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-vetting-workflow.php';

// $event_id should be set by shortcode handler
if ( ! isset( $event_id ) ) {
	$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
}
$event_model = new Remember_Event();
$application_model = new Remember_Application();
$member_id = get_current_user_id();

// Handle form submission
$submission_success = false;
$submission_error = '';

if ( isset( $_POST['remember_apply_action'] ) && check_admin_referer( 'remember_apply_action', 'remember_apply_nonce' ) ) {
	$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
	$event_role_id = isset( $_POST['event_role_id'] ) ? absint( $_POST['event_role_id'] ) : 0;

	if ( $event_id > 0 && $event_role_id > 0 ) {
		// Check if application already exists
		$existing = $application_model->get_existing_application( $event_id, $member_id, $event_role_id );
		if ( $existing ) {
			$submission_error = __( 'You have already applied for this event and role.', 'remember' );
		} else {
			$data = array(
				'event_id'      => $event_id,
				'member_id'     => $member_id,
				'event_role_id' => $event_role_id,
				'status'        => 'pending',
			);

			$is_first = Remember_Vetting_Workflow::is_first_application( $member_id );
			$new_application_id = $application_model->create( $data );

			if ( $new_application_id ) {
				// Create vetting case if this is first application and workflow is "first_application"
				if ( $is_first && Remember_Vetting_Workflow::should_vet_on_first_application() ) {
					Remember_Vetting_Workflow::create_vetting_case( $member_id );
				}

				$submission_success = true;
			} else {
				$submission_error = __( 'Failed to submit application. Please try again.', 'remember' );
			}
		}
	} else {
		$submission_error = __( 'Please select an event and role.', 'remember' );
	}
}

// Get selected event if provided
$selected_event = null;
if ( $event_id > 0 ) {
	$selected_event = $event_model->get( $event_id );
}

// Get open events if no event is pre-selected
$open_events = array();
if ( ! $selected_event ) {
	$open_events = $event_model->get_by_status( 'open' );
}
?>

<?php if ( $submission_success ) : ?>
	<div class="remember-notice remember-success">
		<p><?php esc_html_e( 'Application submitted successfully! You will be notified when your application is reviewed.', 'remember' ); ?></p>
		<p><a href="<?php echo esc_url( get_permalink() . '?view=dashboard' ); ?>" class="remember-button remember-button-primary">
			<?php esc_html_e( 'Return to Dashboard', 'remember' ); ?>
		</a></p>
	</div>
<?php elseif ( ! empty( $submission_error ) ) : ?>
	<div class="remember-notice remember-error">
		<p><?php echo esc_html( $submission_error ); ?></p>
	</div>
<?php else : ?>
	<div class="remember-apply-form">
		<h2><?php esc_html_e( 'Apply for Event', 'remember' ); ?></h2>

		<?php if ( $selected_event ) : ?>
			<div class="remember-event-summary">
				<h3><?php echo esc_html( $selected_event->event_name ); ?></h3>
				<?php if ( ! empty( $selected_event->event_description ) ) : ?>
					<div class="remember-event-description">
						<?php echo wp_kses_post( wpautop( $selected_event->event_description ) ); ?>
					</div>
				<?php endif; ?>
				<p>
					<strong><?php esc_html_e( 'Dates:', 'remember' ); ?></strong>
					<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $selected_event->start_date ) ) ); ?>
					<?php if ( $selected_event->start_date !== $selected_event->end_date ) : ?>
						- <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $selected_event->end_date ) ) ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="remember-form">
			<?php wp_nonce_field( 'remember_apply_action', 'remember_apply_nonce' ); ?>
			<input type="hidden" name="remember_apply_action" value="submit">

			<?php if ( ! $selected_event ) : ?>
				<div class="remember-form-group">
					<label for="event_id" class="remember-form-label">
						<?php esc_html_e( 'Event', 'remember' ); ?>
						<span class="remember-required">*</span>
					</label>
					<select id="event_id" name="event_id" class="remember-form-control" required>
						<option value=""><?php esc_html_e( '-- Select Event --', 'remember' ); ?></option>
						<?php foreach ( $open_events as $event ) : ?>
							<option value="<?php echo esc_attr( $event->event_id ); ?>">
								<?php echo esc_html( $event->event_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php else : ?>
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
			<?php endif; ?>

			<div class="remember-form-group">
				<label for="event_role_id" class="remember-form-label">
					<?php esc_html_e( 'Role', 'remember' ); ?>
					<span class="remember-required">*</span>
				</label>
				<select id="event_role_id" name="event_role_id" class="remember-form-control" required <?php echo $selected_event ? '' : 'disabled'; ?>>
					<option value=""><?php echo $selected_event ? esc_html__( '-- Select Role --', 'remember' ) : esc_html__( '-- Select Event First --', 'remember' ); ?></option>
				</select>
				<p class="remember-form-help">
					<?php esc_html_e( 'Select an event first to see available roles for that event.', 'remember' ); ?>
				</p>
			</div>

			<div class="remember-form-group">
				<button type="submit" class="remember-button remember-button-primary">
					<?php esc_html_e( 'Submit Application', 'remember' ); ?>
				</button>
				<a href="<?php echo esc_url( get_permalink() . '?view=dashboard' ); ?>" class="remember-button remember-button-secondary">
					<?php esc_html_e( 'Cancel', 'remember' ); ?>
				</a>
			</div>
		</form>
	</div>

	<script>
	jQuery(document).ready(function($) {
		var eventId = <?php echo $selected_event ? $event_id : '0'; ?>;
		var $roleSelect = $('#event_role_id');
		var $eventSelect = $('#event_id');

		// Load roles when event is selected or if pre-selected
		function loadEventRoles(selectedEventId) {
			if (!selectedEventId) {
				$roleSelect.html('<option value=""><?php esc_html_e( '-- Select Event First --', 'remember' ); ?></option>').prop('disabled', true);
				return;
			}

			$roleSelect.html('<option value=""><?php esc_html_e( 'Loading roles...', 'remember' ); ?></option>').prop('disabled', true);

			$.ajax({
				url: typeof rememberPublic !== 'undefined' && rememberPublic.ajaxurl ? rememberPublic.ajaxurl : ajaxurl,
				type: 'POST',
				data: {
					action: 'remember_get_event_roles',
					event_id: selectedEventId,
					nonce: '<?php echo wp_create_nonce( 'remember_get_event_roles' ); ?>'
				},
				success: function(response) {
					if (response.success && response.data && response.data.length > 0) {
						var options = '<option value=""><?php esc_html_e( '-- Select Role --', 'remember' ); ?></option>';
						$.each(response.data, function(index, role) {
							options += '<option value="' + role.event_role_id + '">' + role.role_name + '</option>';
						});
						$roleSelect.html(options).prop('disabled', false);
					} else {
						$roleSelect.html('<option value=""><?php esc_html_e( 'No roles available. You may not have any event roles assigned, or this event has no roles matching your assigned roles.', 'remember' ); ?></option>').prop('disabled', true);
					}
				},
				error: function() {
					$roleSelect.html('<option value=""><?php esc_html_e( 'Error loading roles', 'remember' ); ?></option>').prop('disabled', true);
				}
			});
		}

		// Load roles if event is pre-selected
		if (eventId > 0) {
			loadEventRoles(eventId);
		}

		// Load roles when event selection changes
		$eventSelect.on('change', function() {
			loadEventRoles($(this).val());
		});
	});
	</script>
<?php endif; ?>
