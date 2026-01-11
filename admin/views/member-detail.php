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
						<strong><?php esc_html_e( 'Email:', 'remember' ); ?></strong> <?php echo esc_html( $view_user->user_email ); ?>
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
			<div>
				<?php if ( ! $is_editing ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $view_member_id . '&edit=1' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Edit Profile', 'remember' ); ?>
					</a>
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
		<!-- View Mode -->
		
		<!-- Two Column Grid: Left (Profile & Emergency), Right (Tags) -->
		<div class="remember-member-detail-grid">
			<!-- Left Column: Profile Information & Emergency Contact -->
			<div>
				<!-- Profile Information -->
				<div class="remember-member-detail-section" style="margin-bottom: 15px;">
					<h3><?php esc_html_e( 'Profile Information', 'remember' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Legal Name', 'remember' ); ?></th>
							<td>
								<?php
								$legal_first = $view_profile ? $view_profile->legal_first_name : '';
								$legal_last = $view_profile ? $view_profile->legal_last_name : '';
								
								// Fall back to WP user meta if legal names are empty
								if ( empty( $legal_first ) && empty( $legal_last ) ) {
									$legal_first = get_user_meta( $view_member_id, 'first_name', true );
									$legal_last = get_user_meta( $view_member_id, 'last_name', true );
								}
								
								// Fall back to display name if still empty
								if ( empty( $legal_first ) && empty( $legal_last ) && ! empty( $view_user->display_name ) ) {
									$name_parts = explode( ' ', $view_user->display_name, 2 );
									$legal_first = $name_parts[0];
									$legal_last = isset( $name_parts[1] ) ? $name_parts[1] : '';
								}
								
								$full_legal_name = trim( $legal_first . ' ' . $legal_last );
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
								<?php echo $view_profile && $view_profile->timezone ? esc_html( $view_profile->timezone ) : '<span class="description">' . esc_html__( 'Not provided', 'remember' ) . '</span>'; ?>
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
			</div>

			<!-- Right Column: Tags (Dietary, Allergies, Medical) -->
			<div>
				<!-- Dietary Restrictions -->
				<?php if ( ! empty( $view_dietary_restrictions ) ) : ?>
					<div class="remember-member-detail-section" style="margin-bottom: 15px;">
						<h3><?php esc_html_e( 'Dietary Restrictions', 'remember' ); ?></h3>
						<div style="display: flex; flex-wrap: wrap; gap: 8px;">
							<?php foreach ( $view_dietary_restrictions as $restriction ) : ?>
								<span style="display: inline-block; padding: 4px 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 3px; font-size: 12px;">
									<?php echo esc_html( $restriction ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Known Allergies -->
				<?php if ( ! empty( $view_allergies ) ) : ?>
					<div class="remember-member-detail-section" style="margin-bottom: 15px;">
						<h3><?php esc_html_e( 'Known Allergies', 'remember' ); ?></h3>
						<div style="display: flex; flex-wrap: wrap; gap: 8px;">
							<?php foreach ( $view_allergies as $allergy ) : ?>
								<span style="display: inline-block; padding: 4px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 3px; font-size: 12px;">
									<?php echo esc_html( $allergy ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Medical Accommodations -->
				<?php if ( ! empty( $view_medical_accommodations ) ) : ?>
					<div class="remember-member-detail-section">
						<h3><?php esc_html_e( 'Medical Accommodations', 'remember' ); ?></h3>
						<div style="display: flex; flex-wrap: wrap; gap: 8px;">
							<?php foreach ( $view_medical_accommodations as $accommodation ) : ?>
								<span style="display: inline-block; padding: 4px 12px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 3px; font-size: 12px;">
									<?php echo esc_html( $accommodation ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Billing Register (Full Width) -->
		<div class="remember-member-detail-section">
			<h3><?php esc_html_e( 'Billing Register', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Chronological accounting register of invoices and payments.', 'remember' ); ?></p>
			
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
