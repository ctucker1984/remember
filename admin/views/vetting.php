<?php
/**
 * Vetting view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-vetting.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';

Remember_Logger::debug( 'Vetting page loaded' );

$vetting_model = new Remember_Vetting();
$member_model = new Remember_Member();

// Handle form submissions
if ( isset( $_POST['remember_vetting_action'] ) && check_admin_referer( 'remember_vetting_action', 'remember_vetting_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_vetting_action'] );
	$vetting_id = isset( $_POST['vetting_id'] ) ? absint( $_POST['vetting_id'] ) : 0;
	
	if ( $vetting_id > 0 ) {
		if ( 'assign' === $action ) {
			$vetter_id = isset( $_POST['primary_vetter_id'] ) ? absint( $_POST['primary_vetter_id'] ) : 0;
			if ( $vetter_id > 0 ) {
				$result = $vetting_model->update( $vetting_id, array(
					'primary_vetter_id' => $vetter_id,
					'updated_at' => current_time( 'mysql' ),
				) );
				if ( $result !== false ) {
					Remember_Logger::info( 'Vetter assigned', array( 'vetting_id' => $vetting_id, 'vetter_id' => $vetter_id ) );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Vetter assigned successfully.', 'remember' ) . '</p></div>';
				}
			}
		} elseif ( 'schedule' === $action ) {
			$scheduled_at = isset( $_POST['scheduled_at'] ) ? sanitize_text_field( $_POST['scheduled_at'] ) : '';
			if ( ! empty( $scheduled_at ) ) {
				$result = $vetting_model->schedule( $vetting_id, $scheduled_at );
				if ( $result !== false ) {
					Remember_Logger::info( 'Vetting scheduled', array( 'vetting_id' => $vetting_id, 'scheduled_at' => $scheduled_at ) );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Vetting scheduled successfully.', 'remember' ) . '</p></div>';
				}
			}
		} elseif ( 'complete' === $action ) {
			$decision = isset( $_POST['decision'] ) ? sanitize_text_field( $_POST['decision'] ) : '';
			if ( in_array( $decision, array( 'accepted', 'rejected' ), true ) ) {
				$result = $vetting_model->complete( $vetting_id, $decision );
				if ( $result !== false ) {
					Remember_Logger::info( 'Vetting completed', array( 'vetting_id' => $vetting_id, 'decision' => $decision ) );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Vetting decision recorded successfully.', 'remember' ) . '</p></div>';
				}
			}
		}
	}
}

// Get filter parameters
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : 'pending';

// Get vetting records
if ( ! empty( $filter_status ) && 'all' !== $filter_status ) {
	$vetting_records = $vetting_model->get_by_status( $filter_status );
} else {
	$vetting_records = $vetting_model->get_all();
}

// Get users with vetting capability for assign dropdown
$vetters = get_users( array(
	'capability__in' => array( 'remember_vet_applicants' ),
) );

// Status labels and colors
$status_labels = array(
	'pending'     => __( 'Pending', 'remember' ),
	'scheduled'   => __( 'Scheduled', 'remember' ),
	'in_progress' => __( 'In Progress', 'remember' ),
	'completed'   => __( 'Completed', 'remember' ),
);
$status_colors = array(
	'pending'     => '#f0b849',
	'scheduled'   => '#00a0d2',
	'in_progress' => '#2271b1',
	'completed'   => '#46b450',
);
$decision_labels = array(
	'pending'  => __( 'Pending', 'remember' ),
	'accepted' => __( 'Accepted', 'remember' ),
	'rejected' => __( 'Rejected', 'remember' ),
);
?>

<div class="wrap remember-vetting">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<hr class="wp-header-end">

	<!-- Filters -->
	<div class="remember-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<form method="get" action="">
			<input type="hidden" name="page" value="remember-vetting">
			
			<label for="filter_status"><?php esc_html_e( 'Filter by Status:', 'remember' ); ?></label>
			<select id="filter_status" name="filter_status" style="margin-right: 20px;">
				<option value="all"><?php esc_html_e( 'All Statuses', 'remember' ); ?></option>
				<?php foreach ( $status_labels as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'remember' ); ?>">
			<?php if ( 'all' !== $filter_status ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting&filter_status=all' ) ); ?>" class="button"><?php esc_html_e( 'Show All', 'remember' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Vetting Records List -->
	<?php if ( ! empty( $vetting_records ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th class="column-member"><?php esc_html_e( 'Member', 'remember' ); ?></th>
					<th class="column-vetter"><?php esc_html_e( 'Primary Vetter', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-scheduled"><?php esc_html_e( 'Scheduled', 'remember' ); ?></th>
					<th class="column-decision"><?php esc_html_e( 'Decision', 'remember' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vetting_records as $vetting ) : 
					$member = $member_model->get( $vetting->member_id );
					$user = $member ? get_user_by( 'ID', $vetting->member_id ) : null;
					$vetter = get_user_by( 'ID', $vetting->primary_vetter_id );
				?>
					<tr>
						<td class="column-member">
							<?php if ( $user ) : ?>
								<strong><?php echo esc_html( $user->display_name ); ?></strong><br>
								<span class="description"><?php echo esc_html( $user->user_email ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Member not found', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-vetter">
							<?php if ( $vetter ) : ?>
								<?php echo esc_html( $vetter->display_name ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="column-status">
							<span style="color: <?php echo esc_attr( $status_colors[ $vetting->status ] ); ?>; font-weight: bold;">
								<?php echo esc_html( $status_labels[ $vetting->status ] ); ?>
							</span>
						</td>
						<td class="column-scheduled">
							<?php if ( $vetting->scheduled_at ) : ?>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $vetting->scheduled_at ) ) ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="column-decision">
							<?php if ( 'completed' === $vetting->status ) : ?>
								<span style="color: <?php echo 'accepted' === $vetting->decision ? '#46b450' : '#dc3232'; ?>; font-weight: bold;">
									<?php echo esc_html( $decision_labels[ $vetting->decision ] ); ?>
								</span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<button type="button" class="button button-small" onclick="document.getElementById('vetting-actions-<?php echo esc_attr( $vetting->vetting_id ); ?>').style.display='block';"><?php esc_html_e( 'Manage', 'remember' ); ?></button>
						</td>
					</tr>
					
					<!-- Actions Form (hidden by default) -->
					<tr id="vetting-actions-<?php echo esc_attr( $vetting->vetting_id ); ?>" style="display:none; background: #f9f9f9;">
						<td colspan="6" style="padding: 20px;">
							<h3><?php esc_html_e( 'Manage Vetting', 'remember' ); ?></h3>
							
							<!-- Assign Vetter -->
							<?php if ( empty( $vetting->primary_vetter_id ) || 'pending' === $vetting->status ) : ?>
								<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
									<h4><?php esc_html_e( 'Assign Vetter', 'remember' ); ?></h4>
									<form method="post" action="" style="display: inline-block;">
										<?php wp_nonce_field( 'remember_vetting_action', 'remember_vetting_nonce' ); ?>
										<input type="hidden" name="remember_vetting_action" value="assign">
										<input type="hidden" name="vetting_id" value="<?php echo esc_attr( $vetting->vetting_id ); ?>">
										
										<select name="primary_vetter_id" required>
											<option value=""><?php esc_html_e( '-- Select Vetter --', 'remember' ); ?></option>
											<?php foreach ( $vetters as $v ) : ?>
												<option value="<?php echo esc_attr( $v->ID ); ?>" <?php selected( $vetting->primary_vetter_id, $v->ID ); ?>>
													<?php echo esc_html( $v->display_name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										
										<input type="submit" class="button" value="<?php esc_attr_e( 'Assign', 'remember' ); ?>">
									</form>
								</div>
							<?php endif; ?>
							
							<!-- Schedule Vetting -->
							<?php if ( 'pending' === $vetting->status || 'scheduled' === $vetting->status ) : ?>
								<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
									<h4><?php esc_html_e( 'Schedule Vetting', 'remember' ); ?></h4>
									<form method="post" action="" style="display: inline-block;">
										<?php wp_nonce_field( 'remember_vetting_action', 'remember_vetting_nonce' ); ?>
										<input type="hidden" name="remember_vetting_action" value="schedule">
										<input type="hidden" name="vetting_id" value="<?php echo esc_attr( $vetting->vetting_id ); ?>">
										
										<input type="datetime-local" name="scheduled_at" value="<?php echo $vetting->scheduled_at ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $vetting->scheduled_at ) ) ) : ''; ?>" required>
										
										<input type="submit" class="button" value="<?php esc_attr_e( 'Schedule', 'remember' ); ?>">
									</form>
								</div>
							<?php endif; ?>
							
							<!-- Complete Vetting -->
							<?php if ( 'pending' === $vetting->status || 'scheduled' === $vetting->status || 'in_progress' === $vetting->status ) : ?>
								<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
									<h4><?php esc_html_e( 'Complete Vetting', 'remember' ); ?></h4>
									<form method="post" action="" style="display: inline-block;">
										<?php wp_nonce_field( 'remember_vetting_action', 'remember_vetting_nonce' ); ?>
										<input type="hidden" name="remember_vetting_action" value="complete">
										<input type="hidden" name="vetting_id" value="<?php echo esc_attr( $vetting->vetting_id ); ?>">
										
										<select name="decision" required>
											<option value=""><?php esc_html_e( '-- Select Decision --', 'remember' ); ?></option>
											<option value="accepted"><?php esc_html_e( 'Accepted', 'remember' ); ?></option>
											<option value="rejected"><?php esc_html_e( 'Rejected', 'remember' ); ?></option>
										</select>
										
										<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Complete', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to complete this vetting?', 'remember' ); ?>');">
									</form>
								</div>
							<?php endif; ?>
							
							<p>
								<button type="button" class="button" onclick="document.getElementById('vetting-actions-<?php echo esc_attr( $vetting->vetting_id ); ?>').style.display='none';"><?php esc_html_e( 'Close', 'remember' ); ?></button>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<p class="description" style="margin-top: 15px;">
			<?php echo esc_html( sprintf( __( 'Showing %d vetting record(s)', 'remember' ), count( $vetting_records ) ) ); ?>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'No vetting records found.', 'remember' ); ?></p>
	<?php endif; ?>
</div>
