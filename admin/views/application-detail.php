<?php
/**
 * Application detail view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-billing-messaging.php';
$subtotal_disclaimer = Remember_Billing_Messaging::get_subtotal_disclaimer();

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

<div class="remember-application-detail">
	<!-- Header Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
		<div style="display: flex; align-items: center; gap: 20px; justify-content: space-between;">
			<div style="flex: 1;">
				<h2 style="margin: 0 0 10px 0; font-size: 24px;">
					<?php esc_html_e( 'Application', 'remember' ); ?> #<?php echo esc_html( $viewing_application->application_id ); ?>
					<span style="color: <?php echo esc_attr( $status_colors[ $viewing_application->status ] ); ?>; font-size: 14px; font-weight: normal; margin-left: 10px;">
						<?php echo esc_html( $status_labels[ $viewing_application->status ] ); ?>
					</span>
				</h2>
				<div style="color: #666; margin-top: 5px; line-height: 1.8;">
					<?php if ( $viewing_user ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Member:', 'remember' ); ?></strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $viewing_application->member_id ) ); ?>">
								<?php echo esc_html( $viewing_user->display_name ); ?>
							</a>
							<?php if ( $viewing_user->user_email ) : ?>
								<span style="color: #999;">(<?php echo esc_html( $viewing_user->user_email ); ?>)</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<?php if ( $viewing_event ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Event:', 'remember' ); ?></strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events&view=' . $viewing_event->event_id ) ); ?>">
								<?php echo esc_html( $viewing_event->event_name ); ?>
							</a>
							<?php if ( $viewing_event->start_date ) : ?>
								<span style="color: #999;">
									(<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $viewing_event->start_date ) ) ); ?>
									<?php if ( $viewing_event->start_date !== $viewing_event->end_date ) : ?>
										- <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $viewing_event->end_date ) ) ); ?>
									<?php endif; ?>)
								</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<?php if ( $viewing_event_role ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Role:', 'remember' ); ?></strong>
							<?php echo esc_html( $viewing_event_role->role_name ); ?>
							<?php if ( $viewing_event_role->cost > 0 ) : ?>
								<span style="color: #999;">($<?php echo esc_html( number_format( $viewing_event_role->cost, 2 ) ); ?> <?php esc_html_e( 'subtotal', 'remember' ); ?>)</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<?php if ( isset( $viewing_location ) && $viewing_location ) : ?>
						<p style="margin: 5px 0;">
							<strong><?php esc_html_e( 'Location:', 'remember' ); ?></strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations&view=' . $viewing_location->location_id ) ); ?>">
								<?php echo esc_html( $viewing_location->location_name ); ?>
							</a>
						</p>
					<?php endif; ?>
				<p style="margin: 5px 0;">
					<strong><?php esc_html_e( 'Billing:', 'remember' ); ?></strong>
					<span style="color: <?php echo esc_attr( isset( $viewing_billing_color ) ? $viewing_billing_color : '#72777c' ); ?>;">
						<?php
						if ( ! empty( $viewing_billing_html ) ) {
							echo wp_kses(
								$viewing_billing_html,
								array(
									'a' => array(
										'href'   => true,
										'target' => true,
										'rel'    => true,
										'style'  => true,
									),
								)
							);
						} else {
							echo esc_html( isset( $viewing_billing_label ) ? $viewing_billing_label : __( 'Not invoiced yet', 'remember' ) );
						}
						?>
					</span>
				</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Application Information Grid -->
	<div class="remember-application-detail-grid">
		<!-- Status History Section -->
		<div class="remember-application-detail-section">
			<h3><?php esc_html_e( 'Status History', 'remember' ); ?></h3>
			<table class="form-table" style="margin: 0;">
				<tr>
					<th style="width: 140px;"><?php esc_html_e( 'Applied:', 'remember' ); ?></th>
					<td>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $viewing_application->applied_at ) ) ); ?>
					</td>
				</tr>
				<?php if ( $viewing_application->processed_at ) : ?>
					<tr>
						<th><?php esc_html_e( 'Processed:', 'remember' ); ?></th>
						<td>
							<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $viewing_application->processed_at ) ) ); ?>
							<?php if ( isset( $processed_by_user ) && $processed_by_user ) : ?>
								<span style="color: #999;">
									<?php esc_html_e( 'by', 'remember' ); ?> <?php echo esc_html( $processed_by_user->display_name ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $viewing_application->notes ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Notes:', 'remember' ); ?></th>
						<td>
							<div style="color: #555; line-height: 1.6;">
								<?php echo wp_kses_post( nl2br( esc_html( $viewing_application->notes ) ) ); ?>
							</div>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</div>

		<!-- Itemized Charges Section -->
		<div class="remember-application-detail-section">
			<h3><?php esc_html_e( 'Itemized Charges (Subtotal)', 'remember' ); ?></h3>
			<table class="wp-list-table widefat striped" style="margin: 0;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item', 'remember' ); ?></th>
						<th style="width: 110px;"><?php esc_html_e( 'Qty', 'remember' ); ?></th>
						<th style="width: 140px;"><?php esc_html_e( 'Unit Price', 'remember' ); ?></th>
						<th style="width: 140px;"><?php esc_html_e( 'Line Subtotal', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<?php
							$role_label = $viewing_event_role ? $viewing_event_role->role_name : __( 'Role', 'remember' );
							echo esc_html( $role_label );
							?>
						</td>
						<td>1</td>
						<td>$<?php echo esc_html( number_format( $viewing_event_role ? (float) $viewing_event_role->cost : 0, 2 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $viewing_event_role ? (float) $viewing_event_role->cost : 0, 2 ) ); ?></td>
					</tr>
					<?php if ( ! empty( $viewing_application_addons ) ) : ?>
						<?php foreach ( $viewing_application_addons as $addon_item ) : ?>
							<tr>
								<td>
									<?php echo esc_html( ! empty( $addon_item->merchandise_name ) ? $addon_item->merchandise_name : __( 'Add-on', 'remember' ) ); ?>
								</td>
								<td><?php echo esc_html( intval( $addon_item->quantity ) ); ?></td>
								<td>$<?php echo esc_html( number_format( (float) $addon_item->unit_cost, 2 ) ); ?></td>
								<td>$<?php echo esc_html( number_format( (float) $addon_item->total_cost, 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php
					$itemized_subtotal = $viewing_event_role ? (float) $viewing_event_role->cost : 0;
					if ( ! empty( $viewing_application_addons ) ) {
						foreach ( $viewing_application_addons as $addon_item ) {
							$itemized_subtotal += (float) $addon_item->total_cost;
						}
					}
					?>
					<tr>
						<th colspan="3" style="text-align:right;"><?php esc_html_e( 'Subtotal', 'remember' ); ?></th>
						<th>$<?php echo esc_html( number_format( $itemized_subtotal, 2 ) ); ?></th>
					</tr>
				</tbody>
			</table>
			<p class="description" style="margin-top: 8px;"><?php echo esc_html( $subtotal_disclaimer ); ?></p>
		</div>

		<!-- Payment Information Section -->
		<?php if ( $viewing_payment ) : ?>
			<div class="remember-application-detail-section">
				<h3><?php esc_html_e( 'Payment Information', 'remember' ); ?></h3>
				<table class="form-table" style="margin: 0;">
					<tr>
						<th style="width: 140px;"><?php esc_html_e( 'Subtotal Amount:', 'remember' ); ?></th>
						<td>
							<strong>$<?php echo esc_html( number_format( $viewing_payment->total_amount, 2 ) ); ?></strong>
							<p class="description" style="margin: 6px 0 0 0;">
								<?php echo esc_html( $subtotal_disclaimer ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Amount Paid:', 'remember' ); ?></th>
						<td>
							$<?php echo esc_html( number_format( $viewing_payment->amount_paid, 2 ) ); ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Amount Due:', 'remember' ); ?></th>
						<td>
							<strong style="color: <?php echo $viewing_payment->amount_due > 0 ? '#dc3232' : '#46b450'; ?>;">
								$<?php echo esc_html( number_format( $viewing_payment->amount_due, 2 ) ); ?>
							</strong>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status:', 'remember' ); ?></th>
						<td>
							<?php
							$payment_status_labels = array(
								'pending'  => __( 'Pending', 'remember' ),
								'partial'  => __( 'Partial', 'remember' ),
								'paid'     => __( 'Paid', 'remember' ),
								'refunded' => __( 'Refunded', 'remember' ),
								'cancelled' => __( 'Cancelled', 'remember' ),
							);
							$payment_status_colors = array(
								'pending'  => '#f0b849',
								'partial'  => '#00a0d2',
								'paid'     => '#46b450',
								'refunded' => '#72777c',
								'cancelled' => '#dc3232',
							);
							?>
							<span style="color: <?php echo esc_attr( $payment_status_colors[ $viewing_payment->payment_status ] ); ?>;">
								<?php echo esc_html( $payment_status_labels[ $viewing_payment->payment_status ] ); ?>
							</span>
						</td>
					</tr>
					<?php if ( $viewing_payment->payment_date ) : ?>
						<tr>
							<th><?php esc_html_e( 'Payment Date:', 'remember' ); ?></th>
							<td>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $viewing_payment->payment_date ) ) ); ?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $viewing_payment->payment_method ) : ?>
						<tr>
							<th><?php esc_html_e( 'Payment Method:', 'remember' ); ?></th>
							<td>
								<?php echo esc_html( ucfirst( $viewing_payment->payment_method ) ); ?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $viewing_payment->transaction_id ) : ?>
						<tr>
							<th><?php esc_html_e( 'Transaction ID:', 'remember' ); ?></th>
							<td>
								<code><?php echo esc_html( $viewing_payment->transaction_id ); ?></code>
							</td>
						</tr>
					<?php endif; ?>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<!-- Action Buttons -->
	<?php if ( 'pending' === $viewing_application->status || 'waitlisted' === $viewing_application->status || 'accepted' === $viewing_application->status ) : ?>
		<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Actions', 'remember' ); ?></h3>
			<?php if ( 'pending' === $viewing_application->status || 'waitlisted' === $viewing_application->status ) : ?>
				<form method="post" action="" style="display: inline-block; margin-right: 10px;">
					<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
					<input type="hidden" name="remember_application_action" value="accept">
					<input type="hidden" name="application_id" value="<?php echo esc_attr( $viewing_application->application_id ); ?>">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Accept Application', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Accept this application?', 'remember' ); ?>');">
				</form>

				<form method="post" action="" style="display: inline-block; margin-right: 10px;">
					<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
					<input type="hidden" name="remember_application_action" value="decline">
					<input type="hidden" name="application_id" value="<?php echo esc_attr( $viewing_application->application_id ); ?>">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Decline Application', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Decline this application?', 'remember' ); ?>');">
				</form>
			<?php endif; ?>

			<?php if ( 'pending' === $viewing_application->status ) : ?>
				<form method="post" action="" style="display: inline-block;">
					<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
					<input type="hidden" name="remember_application_action" value="waitlist">
					<input type="hidden" name="application_id" value="<?php echo esc_attr( $viewing_application->application_id ); ?>">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Move to Waitlist', 'remember' ); ?>">
				</form>
			<?php endif; ?>

			<?php if ( 'accepted' === $viewing_application->status ) : ?>
				<form method="post" action="" style="display: inline-block; margin-right: 10px;">
					<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
					<input type="hidden" name="remember_application_action" value="reopen_pending">
					<input type="hidden" name="application_id" value="<?php echo esc_attr( $viewing_application->application_id ); ?>">
					<input type="submit" class="button" value="<?php esc_attr_e( 'Move to Pending', 'remember' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Move this accepted application back to pending?', 'remember' ); ?>');">
				</form>
				<form method="post" action="" style="display: inline-block;">
					<?php wp_nonce_field( 'remember_application_action', 'remember_application_nonce' ); ?>
					<input type="hidden" name="remember_application_action" value="reprocess_billing">
					<input type="hidden" name="application_id" value="<?php echo esc_attr( $viewing_application->application_id ); ?>">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Reprocess Billing', 'remember' ); ?>">
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
