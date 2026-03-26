<?php
/**
 * Payments view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-payment.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-billing-messaging.php';

Remember_Logger::debug( 'Payments page loaded' );

$payment_model = new Remember_Payment();
$application_model = new Remember_Application();
$member_model = new Remember_Member();
$subtotal_disclaimer = Remember_Billing_Messaging::get_subtotal_disclaimer();

// Handle form submissions
if ( isset( $_POST['remember_payment_action'] ) && check_admin_referer( 'remember_payment_action', 'remember_payment_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_payment_action'] );
	$payment_id = isset( $_POST['payment_id'] ) ? absint( $_POST['payment_id'] ) : 0;
	
	if ( $payment_id > 0 && 'record' === $action ) {
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$method = isset( $_POST['payment_method'] ) ? sanitize_text_field( $_POST['payment_method'] ) : 'manual';
		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : '';
		
		$extra_data = array(
			'payment_method' => $method,
		);
		if ( ! empty( $transaction_id ) ) {
			$extra_data['transaction_id'] = $transaction_id;
		}
		
		$result = $payment_model->record_payment( $payment_id, $amount, $extra_data );
		if ( $result !== false ) {
			Remember_Logger::info( 'Payment recorded', array( 'payment_id' => $payment_id, 'amount' => $amount ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Payment recorded successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to record payment', array( 'payment_id' => $payment_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to record payment.', 'remember' ) . '</p></div>';
		}
	}
}

// Get filter parameters
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

// Get payments
if ( ! empty( $filter_status ) ) {
	$payments = $payment_model->get_by_status( $filter_status );
} else {
	$payments = $payment_model->get_all();
}

// Status labels and colors
$status_labels = array(
	'pending'  => __( 'Pending', 'remember' ),
	'partial'  => __( 'Partial', 'remember' ),
	'paid'     => __( 'Paid', 'remember' ),
	'refunded' => __( 'Refunded', 'remember' ),
	'cancelled' => __( 'Cancelled', 'remember' ),
);
$status_colors = array(
	'pending'  => '#f0b849',
	'partial'  => '#00a0d2',
	'paid'     => '#46b450',
	'refunded' => '#dc3232',
	'cancelled' => '#72777c',
);
?>

<div class="wrap remember-billing">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<hr class="wp-header-end">

	<div class="notice notice-info" style="margin: 15px 0;">
		<p>
			<strong><?php esc_html_e( 'Billing note:', 'remember' ); ?></strong>
			<?php echo esc_html( $subtotal_disclaimer ); ?>
		</p>
	</div>

	<!-- Filters -->
	<div class="remember-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
		<form method="get" action="">
			<input type="hidden" name="page" value="remember-billing">
			
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
			<?php if ( ! empty( $filter_status ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-billing' ) ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'remember' ); ?></a>
			<?php endif; ?>
		</form>
	</div>

	<!-- Payments List -->
	<?php if ( ! empty( $payments ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th class="column-member"><?php esc_html_e( 'Member', 'remember' ); ?></th>
					<th class="column-amount"><?php esc_html_e( 'Subtotal Amount', 'remember' ); ?></th>
					<th class="column-paid"><?php esc_html_e( 'Amount Paid', 'remember' ); ?></th>
					<th class="column-due"><?php esc_html_e( 'Amount Due', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-method"><?php esc_html_e( 'Method', 'remember' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Date', 'remember' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $payments as $payment ) : 
					$member = $member_model->get( $payment->member_id );
					$user = $member ? get_user_by( 'ID', $payment->member_id ) : null;
				?>
					<tr>
						<td class="column-member">
							<?php if ( $user ) : ?>
								<?php echo esc_html( $user->display_name ); ?><br>
								<span class="description"><?php echo esc_html( $user->user_email ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Member not found', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-amount">
							<strong>$<?php echo number_format( $payment->total_amount, 2 ); ?></strong>
						</td>
						<td class="column-paid">
							$<?php echo number_format( $payment->amount_paid, 2 ); ?>
						</td>
						<td class="column-due">
							<strong style="color: <?php echo $payment->amount_due > 0 ? '#dc3232' : '#46b450'; ?>;">
								$<?php echo number_format( $payment->amount_due, 2 ); ?>
							</strong>
						</td>
						<td class="column-status">
							<span style="color: <?php echo esc_attr( $status_colors[ $payment->payment_status ] ); ?>; font-weight: bold;">
								<?php echo esc_html( $status_labels[ $payment->payment_status ] ); ?>
							</span>
						</td>
						<td class="column-method">
							<?php echo esc_html( ucfirst( $payment->payment_method ? $payment->payment_method : 'manual' ) ); ?>
						</td>
						<td class="column-date">
							<?php if ( $payment->payment_date ) : ?>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payment->payment_date ) ) ); ?>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<?php if ( $payment->amount_due > 0 ) : ?>
								<button type="button" class="button button-small" onclick="document.getElementById('payment-record-<?php echo esc_attr( $payment->payment_id ); ?>').style.display='block';"><?php esc_html_e( 'Record Payment', 'remember' ); ?></button>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Paid', 'remember' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					
					<!-- Record Payment Form (hidden by default) -->
					<?php if ( $payment->amount_due > 0 ) : ?>
						<tr id="payment-record-<?php echo esc_attr( $payment->payment_id ); ?>" style="display:none; background: #f9f9f9;">
							<td colspan="8" style="padding: 20px;">
								<form method="post" action="">
									<?php wp_nonce_field( 'remember_payment_action', 'remember_payment_nonce' ); ?>
									<input type="hidden" name="remember_payment_action" value="record">
									<input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment->payment_id ); ?>">
									
									<table class="form-table">
										<tr>
											<th><label for="amount-<?php echo esc_attr( $payment->payment_id ); ?>"><?php esc_html_e( 'Amount', 'remember' ); ?></label></th>
											<td>
												<input type="number" id="amount-<?php echo esc_attr( $payment->payment_id ); ?>" name="amount" step="0.01" min="0" max="<?php echo esc_attr( $payment->amount_due ); ?>" value="<?php echo esc_attr( $payment->amount_due ); ?>" class="small-text" required>
												<span class="description"><?php echo esc_html( sprintf( __( 'Maximum: $%s', 'remember' ), number_format( $payment->amount_due, 2 ) ) ); ?></span>
											</td>
										</tr>
										<tr>
											<th><label for="payment_method-<?php echo esc_attr( $payment->payment_id ); ?>"><?php esc_html_e( 'Payment Method', 'remember' ); ?></label></th>
											<td>
												<select id="payment_method-<?php echo esc_attr( $payment->payment_id ); ?>" name="payment_method">
													<option value="manual"><?php esc_html_e( 'Manual Entry', 'remember' ); ?></option>
													<option value="cash"><?php esc_html_e( 'Cash', 'remember' ); ?></option>
													<option value="check"><?php esc_html_e( 'Check', 'remember' ); ?></option>
													<option value="bank_transfer"><?php esc_html_e( 'Bank Transfer', 'remember' ); ?></option>
													<option value="quickbooks"><?php esc_html_e( 'QuickBooks', 'remember' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th><label for="transaction_id-<?php echo esc_attr( $payment->payment_id ); ?>"><?php esc_html_e( 'Transaction ID', 'remember' ); ?></label></th>
											<td>
												<input type="text" id="transaction_id-<?php echo esc_attr( $payment->payment_id ); ?>" name="transaction_id" class="regular-text">
												<span class="description"><?php esc_html_e( 'Optional: Reference number or transaction ID', 'remember' ); ?></span>
											</td>
										</tr>
									</table>
									
									<p class="submit">
										<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Record Payment', 'remember' ); ?>">
										<button type="button" class="button" onclick="document.getElementById('payment-record-<?php echo esc_attr( $payment->payment_id ); ?>').style.display='none';"><?php esc_html_e( 'Cancel', 'remember' ); ?></button>
									</p>
								</form>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<p class="description" style="margin-top: 15px;">
			<?php echo esc_html( sprintf( __( 'Showing %d payment(s)', 'remember' ), count( $payments ) ) ); ?>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'No payments found.', 'remember' ); ?></p>
	<?php endif; ?>
</div>
