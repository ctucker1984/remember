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
					<th class="column-qb-invoice"><?php esc_html_e( 'QB Invoice #', 'remember' ); ?></th>
					<th class="column-amount"><?php esc_html_e( 'Subtotal Amount', 'remember' ); ?></th>
					<th class="column-paid"><?php esc_html_e( 'Amount Paid', 'remember' ); ?></th>
					<th class="column-due"><?php esc_html_e( 'Amount Due', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-method"><?php esc_html_e( 'Method', 'remember' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Date', 'remember' ); ?></th>
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
						<td class="column-qb-invoice">
							<?php if ( ! empty( $payment->quickbooks_invoice_number ) ) : ?>
								<strong><?php echo esc_html( $payment->quickbooks_invoice_number ); ?></strong>
							<?php elseif ( ! empty( $payment->quickbooks_invoice_id ) ) : ?>
								<span class="description"><?php esc_html_e( 'Sync payments to load invoice #', 'remember' ); ?></span>
							<?php else : ?>
								<span class="description">—</span>
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
					</tr>
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
