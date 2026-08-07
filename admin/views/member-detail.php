<?php
/**
 * Member detail view (included from members.php)
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Variables are set in members.php: $view_member, $view_user, $view_profile, $view_roles, $view_payments, $billing_register
// Also: $view_social_media, $view_dietary_restrictions, $view_medical_accommodations, $view_allergies
// Check if editing
$is_editing = isset( $_GET['edit'] ) && $_GET['edit'] === '1';
if ( $is_editing && ! current_user_can( 'remember_update_members' ) ) {
	wp_die( __( 'You do not have sufficient permissions to edit members.', 'remember' ), __( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
}
?>

<div class="remember-member-detail" style="margin: 20px 0;">
	<!-- Member Header -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
		<div style="display: flex; align-items: center; gap: 20px; justify-content: space-between;">
			<div style="display: flex; align-items: center; gap: 20px; flex: 1;">
				<?php if ( $view_member->photo_url ) : ?>
					<img src="<?php echo esc_url( $view_member->photo_url ); ?>" alt="<?php echo esc_attr( $view_user->display_name ); ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
				<?php else : ?>
					<div style="width: 100px; height: 100px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #999;">
						<?php echo esc_html( strtoupper( substr( $view_user->display_name, 0, 1 ) ) ); ?>
					</div>
				<?php endif; ?>
				<div style="flex: 1;">
					<h2 style="margin: 0 0 10px 0;">
						<?php echo esc_html( $view_user->display_name ); ?>
						<span style="color: <?php echo esc_attr( $status_colors[ $view_member->status ] ); ?>; font-size: 14px; font-weight: normal; margin-left: 10px;">
							<?php echo esc_html( $status_labels[ $view_member->status ] ); ?>
						</span>
					</h2>
					<p style="margin: 5px 0; color: #666;">
						<?php if ( ! empty( $view_user->user_email ) ) : ?>
							<span class="dashicons dashicons-email-alt" style="font-size: 14px; vertical-align: middle; color: #666; margin-right: 4px;"></span>
							<a href="mailto:<?php echo esc_attr( $view_user->user_email ); ?>" style="text-decoration: none; color: #666;"><?php echo esc_html( $view_user->user_email ); ?></a>
						<?php endif; ?>
						<?php if ( $view_profile && ! empty( $view_profile->cell_phone ) ) : ?>
							<span style="margin-left: 12px;">
								<span class="dashicons dashicons-phone" style="font-size: 14px; vertical-align: middle; color: #666; margin-right: 4px;"></span>
								<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $view_profile->cell_phone ) ); ?>" style="text-decoration: none; color: #666;"><?php echo esc_html( $view_profile->cell_phone ); ?></a>
							</span>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $view_roles ) ) : ?>
						<div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
							<?php foreach ( $view_roles as $member_role ) : ?>
								<span style="display: inline-block; padding: 4px 12px; background: <?php echo $member_role->is_event_role ? '#e8f4f8' : '#fff3cd'; ?>; border: 1px solid <?php echo $member_role->is_event_role ? '#bee5eb' : '#ffc107'; ?>; border-radius: 3px; font-size: 12px;">
									<?php echo esc_html( $member_role->role_name ); ?>
									<span style="color: #666; font-size: 11px;">
										(<?php echo $member_role->is_event_role ? esc_html__( 'Event', 'remember' ) : esc_html__( 'System', 'remember' ); ?>)
									</span>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; justify-content: flex-end;">
				<?php if ( ! $is_editing ) : ?>
					<?php if ( current_user_can( 'remember_update_members' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $view_member_id . '&edit=1' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Edit Profile', 'remember' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $remember_qb_show_sync_customer ) ) : ?>
						<form method="post" action="" style="display: inline-block; margin: 0;">
							<?php wp_nonce_field( 'remember_member_action', 'remember_member_nonce' ); ?>
							<input type="hidden" name="remember_member_action" value="sync_qb_customer" />
							<input type="hidden" name="member_id" value="<?php echo esc_attr( $view_member_id ); ?>" />
							<button type="submit" class="button" title="<?php esc_attr_e( 'Update the linked QuickBooks customer with this member’s name, email, phone, and billing address.', 'remember' ); ?>">
								<?php esc_html_e( 'Sync to QuickBooks', 'remember' ); ?>
							</button>
						</form>
					<?php endif; ?>
					<?php if ( ! empty( $remember_xero_show_sync_contact ) ) : ?>
						<form method="post" action="" style="display: inline-block; margin: 0;">
							<?php wp_nonce_field( 'remember_member_action', 'remember_member_nonce' ); ?>
							<input type="hidden" name="remember_member_action" value="sync_xero_contact" />
							<input type="hidden" name="member_id" value="<?php echo esc_attr( $view_member_id ); ?>" />
							<button type="submit" class="button" title="<?php esc_attr_e( 'Update the linked Xero contact with this member’s name, email, phone, and billing address.', 'remember' ); ?>">
								<?php esc_html_e( 'Sync to Xero', 'remember' ); ?>
							</button>
						</form>
					<?php endif; ?>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $view_member_id ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'remember' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( $is_editing ) : ?>
		<!-- Edit Form -->
		<?php include 'member-edit.php'; ?>
	<?php else : ?>
		<!-- View Mode: three equal columns — Profile | Emergency | Dietary / Allergies / Medical (stacked in column 3) -->
		<div class="remember-member-detail-grid remember-member-detail-grid--three-cols">
			<!-- Profile Information -->
			<div class="remember-member-detail-section">
				<h3><?php esc_html_e( 'Profile Information', 'remember' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Legal Name', 'remember' ); ?></th>
						<td>
							<?php
							$full_legal_name = trim( Remember_Import_Export::member_list_legal_name_line( $view_profile, (int) $view_member_id ) );
							if ( ! empty( $full_legal_name ) ) :
								echo esc_html( $full_legal_name );
							else :
								?>
								<span class="description"><?php esc_html_e( 'Not provided', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Address', 'remember' ); ?></th>
						<td>
							<?php if ( $view_profile && $view_profile->address_street ) : ?>
								<?php
								$address_parts = array_filter( array(
									$view_profile->address_street,
									$view_profile->address_city,
									$view_profile->address_state,
									$view_profile->address_postal,
								) );
								echo esc_html( implode( ', ', $address_parts ) );
								if ( $view_profile->address_country ) {
									echo ', ' . esc_html( $view_profile->address_country );
								}
								?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Not provided', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Cell Phone', 'remember' ); ?></th>
						<td>
							<?php echo $view_profile && $view_profile->cell_phone ? esc_html( $view_profile->cell_phone ) : '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>'; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Time Zone', 'remember' ); ?></th>
						<td>
							<?php
							$view_timezone = $view_user ? get_user_meta( $view_user->ID, 'timezone_string', true ) : '';
							echo ! empty( $view_timezone )
								? esc_html( $view_timezone )
								: '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>';
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></th>
						<td>
							<?php if ( $view_profile && $view_profile->im_handle ) : ?>
								<?php echo esc_html( ucfirst( $view_profile->im_type ?: 'telegram' ) ); ?>: <?php echo esc_html( $view_profile->im_handle ); ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Not provided', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Interests', 'remember' ); ?></th>
						<td>
							<?php echo $view_profile && $view_profile->interests ? esc_html( $view_profile->interests ) : '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>'; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Emergency Contact -->
			<div class="remember-member-detail-section">
				<h3><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Name', 'remember' ); ?></th>
						<td>
							<?php if ( $view_profile ) : ?>
								<?php echo esc_html( trim( $view_profile->emergency_contact_first . ' ' . $view_profile->emergency_contact_last ) ); ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Not provided', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone', 'remember' ); ?></th>
						<td>
							<?php echo $view_profile && $view_profile->emergency_contact_phone ? esc_html( $view_profile->emergency_contact_phone ) : '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>'; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Relationship', 'remember' ); ?></th>
						<td>
							<?php echo $view_profile && $view_profile->emergency_contact_relationship ? esc_html( $view_profile->emergency_contact_relationship ) : '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>'; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Dietary, Allergies, Medical: stacked in the third column; always show all three cards -->
			<div class="remember-member-detail-health-column">
				<div class="remember-member-detail-section remember-member-detail-health-card">
					<h3><?php esc_html_e( 'Dietary Restrictions', 'remember' ); ?></h3>
					<?php if ( ! empty( $view_dietary_restrictions ) ) : ?>
						<div class="remember-member-detail-pill-row">
							<?php foreach ( $view_dietary_restrictions as $restriction ) : ?>
								<span class="remember-member-detail-pill remember-member-detail-pill--dietary"><?php echo esc_html( $restriction ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="remember-member-detail-none"><?php esc_html_e( 'None', 'remember' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="remember-member-detail-section remember-member-detail-health-card">
					<h3><?php esc_html_e( 'Known Allergies', 'remember' ); ?></h3>
					<?php if ( ! empty( $view_allergies ) ) : ?>
						<div class="remember-member-detail-pill-row">
							<?php foreach ( $view_allergies as $allergy ) : ?>
								<span class="remember-member-detail-pill remember-member-detail-pill--allergy"><?php echo esc_html( $allergy ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="remember-member-detail-none"><?php esc_html_e( 'None', 'remember' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="remember-member-detail-section remember-member-detail-health-card">
					<h3><?php esc_html_e( 'Medical Accommodations', 'remember' ); ?></h3>
					<?php if ( ! empty( $view_medical_accommodations ) ) : ?>
						<div class="remember-member-detail-pill-row">
							<?php foreach ( $view_medical_accommodations as $accommodation ) : ?>
								<span class="remember-member-detail-pill remember-member-detail-pill--medical"><?php echo esc_html( $accommodation ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="remember-member-detail-none"><?php esc_html_e( 'None', 'remember' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Vetting Cases (Full Width) -->
		<?php if ( isset( $view_vetting_cases ) ) : ?>
			<div class="remember-member-detail-section" style="position: relative; margin-bottom: 15px;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
					<h3 style="margin: 0;"><?php esc_html_e( 'Vetting Cases', 'remember' ); ?></h3>
					<?php if ( current_user_can( 'remember_create_vetting' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting&member_id=' . $view_member_id ) ); ?>" class="button button-small">
							<?php esc_html_e( 'Create Vetting Case', 'remember' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $view_vetting_cases ) ) : ?>
					<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
						<thead>
							<tr>
								<th style="width: 120px;"><?php esc_html_e( 'Case Start', 'remember' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Status', 'remember' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Decision', 'remember' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Decision Date', 'remember' ); ?></th>
								<th><?php esc_html_e( 'Decider', 'remember' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php 
							$vetting_status_labels = array(
								'pending'     => __( 'Pending', 'remember' ),
								'scheduled'   => __( 'Scheduled', 'remember' ),
								'in_progress' => __( 'In Progress', 'remember' ),
								'completed'   => __( 'Completed', 'remember' ),
							);
							$vetting_status_colors = array(
								'pending'     => '#f0b849',
								'scheduled'   => '#00a0d2',
								'in_progress' => '#2271b1',
								'completed'   => '#46b450',
							);
							$vetting_decision_labels = array(
								'pending'  => __( 'Pending', 'remember' ),
								'accepted' => __( 'Accepted', 'remember' ),
								'rejected' => __( 'Rejected', 'remember' ),
							);
							foreach ( $view_vetting_cases as $case ) : 
								$decider = ! empty( $case->primary_vetter_id ) ? get_user_by( 'ID', $case->primary_vetter_id ) : null;
							?>
								<tr>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $case->created_at ) ) ); ?></td>
									<td>
										<span style="color: <?php echo esc_attr( $vetting_status_colors[ $case->status ] ); ?>; font-weight: bold;">
											<?php echo esc_html( $vetting_status_labels[ $case->status ] ); ?>
										</span>
									</td>
									<td>
										<?php if ( 'completed' === $case->status && ! empty( $case->decision ) && 'pending' !== $case->decision ) : ?>
											<span style="color: <?php echo 'accepted' === $case->decision ? '#46b450' : '#dc3232'; ?>; font-weight: bold;">
												<?php echo esc_html( $vetting_decision_labels[ $case->decision ] ); ?>
											</span>
										<?php else : ?>
											<span class="description">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $case->decision_date ) ) : ?>
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $case->decision_date ) ) ); ?>
										<?php else : ?>
											<span class="description">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php echo $decider ? esc_html( $decider->display_name ) : '<span class="description">—</span>'; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting&view=' . $case->vetting_id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'remember' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No vetting cases found.', 'remember' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Billing Register (Full Width) -->
		<div class="remember-member-detail-section">
			<h3><?php esc_html_e( 'Billing Register', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Chronological accounting register of invoices, payments, and refunds.', 'remember' ); ?></p>
			
			<?php if ( ! empty( $billing_register ) ) : ?>
				<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
					<thead>
						<tr>
							<th style="width: 120px;"><?php esc_html_e( 'Date', 'remember' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Type', 'remember' ); ?></th>
							<th><?php esc_html_e( 'Description', 'remember' ); ?></th>
							<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Debit', 'remember' ); ?></th>
							<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Credit', 'remember' ); ?></th>
							<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Balance', 'remember' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Status', 'remember' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $billing_register as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry['date'] ) ) ); ?></td>
								<td>
									<?php if ( 'invoice' === $entry['type'] ) : ?>
										<span style="color: #d63638;"><?php esc_html_e( 'Invoice', 'remember' ); ?></span>
									<?php elseif ( 'refund' === $entry['type'] ) : ?>
										<span style="color: #b32d2e;"><?php esc_html_e( 'Refund', 'remember' ); ?></span>
									<?php else : ?>
										<span style="color: #00a32a;"><?php esc_html_e( 'Payment', 'remember' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $entry['description'] ); ?></td>
								<td style="text-align: right;">
									<?php if ( $entry['debit'] > 0 ) : ?>
										<?php echo esc_html( number_format( $entry['debit'], 2 ) ); ?>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td style="text-align: right;">
									<?php if ( $entry['credit'] > 0 ) : ?>
										<?php echo esc_html( number_format( $entry['credit'], 2 ) ); ?>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td style="text-align: right; font-weight: <?php echo $entry['balance'] > 0 ? 'bold' : 'normal'; ?>;">
									<?php 
									$balance_color = $entry['balance'] > 0 ? '#d63638' : ( $entry['balance'] < 0 ? '#00a32a' : '#666' );
									?>
									<span style="color: <?php echo esc_attr( $balance_color ); ?>;">
										<?php echo esc_html( number_format( $entry['balance'], 2 ) ); ?>
									</span>
								</td>
								<td>
									<?php
									$status_labels_billing = array(
										'pending' => __( 'Pending', 'remember' ),
										'partial' => __( 'Partial', 'remember' ),
										'paid' => __( 'Paid', 'remember' ),
										'refunded' => __( 'Refunded', 'remember' ),
										'cancelled' => __( 'Cancelled', 'remember' ),
									);
									$status_colors_billing = array(
										'pending' => '#f0b849',
										'partial' => '#00a0d2',
										'paid' => '#46b450',
										'refunded' => '#72777c',
										'cancelled' => '#dc3232',
									);
									$status_label = isset( $status_labels_billing[ $entry['status'] ] ) ? $status_labels_billing[ $entry['status'] ] : $entry['status'];
									$status_color = isset( $status_colors_billing[ $entry['status'] ] ) ? $status_colors_billing[ $entry['status'] ] : '#666';
									?>
									<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: bold;">
										<?php echo esc_html( $status_label ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr style="background: #f9f9f9; font-weight: bold;">
							<td colspan="3" style="text-align: right; padding: 10px;">
								<?php esc_html_e( 'Current Balance:', 'remember' ); ?>
							</td>
							<td colspan="3" style="text-align: right; padding: 10px;">
								<?php 
								$current_balance = $running_balance;
								$balance_color = $current_balance > 0 ? '#d63638' : ( $current_balance < 0 ? '#00a32a' : '#666' );
								?>
								<span style="color: <?php echo esc_attr( $balance_color ); ?>; font-size: 16px;">
									<?php echo esc_html( number_format( $current_balance, 2 ) ); ?>
								</span>
							</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No billing history found.', 'remember' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
