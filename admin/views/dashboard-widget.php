<?php
/**
 * Dashboard widget view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="remember-dashboard-widget">
	<div class="remember-widget-stats">
		<div class="remember-widget-stat">
			<span class="remember-widget-stat-number"><?php echo esc_html( $total_members ); ?></span>
			<span class="remember-widget-stat-label"><?php esc_html_e( 'Total Members', 'remember' ); ?></span>
		</div>

		<?php if ( current_user_can( 'remember_read_applications' ) ) : ?>
		<div class="remember-widget-stat">
			<span class="remember-widget-stat-number"><?php echo esc_html( $pending_applications_count ); ?></span>
			<span class="remember-widget-stat-label"><?php esc_html_e( 'Pending Applications', 'remember' ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( current_user_can( 'remember_read_events' ) ) : ?>
		<div class="remember-widget-stat">
			<span class="remember-widget-stat-number"><?php echo esc_html( count( $open_events ) ); ?></span>
			<span class="remember-widget-stat-label"><?php esc_html_e( 'Open Events', 'remember' ); ?></span>
		</div>

		<div class="remember-widget-stat">
			<span class="remember-widget-stat-number"><?php echo esc_html( count( $upcoming_events ) ); ?></span>
			<span class="remember-widget-stat-label"><?php esc_html_e( 'Upcoming Events', 'remember' ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( current_user_can( 'remember_read_vetting' ) ) : ?>
		<div class="remember-widget-stat">
			<span class="remember-widget-stat-number"><?php echo esc_html( $open_vetting_records_count ); ?></span>
			<span class="remember-widget-stat-label"><?php esc_html_e( 'Open Vetting', 'remember' ); ?></span>
		</div>
		<?php endif; ?>
	</div>

	<div class="remember-widget-actions">
		<?php if ( current_user_can( 'remember_read_members' ) || current_user_can( 'remember_read_attendees' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Manage Members', 'remember' ); ?>
		</a>
		<?php endif; ?>

		<?php if ( current_user_can( 'remember_read_events' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-events' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Manage Events', 'remember' ); ?>
		</a>
		<?php endif; ?>

		<?php if ( current_user_can( 'remember_read_applications' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-applications' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Review Applications', 'remember' ); ?>
		</a>
		<?php endif; ?>

		<?php if ( current_user_can( 'remember_read_vetting' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-vetting' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Vetting Queue', 'remember' ); ?>
		</a>
		<?php endif; ?>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'View Dashboard', 'remember' ); ?>
		</a>
	</div>
</div>

<style>
.remember-dashboard-widget {
	margin: -6px -12px -12px;
}

.remember-widget-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
	gap: 15px;
	margin-bottom: 20px;
	padding: 0 12px;
}

.remember-widget-stat {
	text-align: center;
}

.remember-widget-stat-number {
	display: block;
	font-size: 28px;
	font-weight: 600;
	color: #2271b1;
	line-height: 1.2;
	margin-bottom: 5px;
}

.remember-widget-stat-label {
	display: block;
	font-size: 12px;
	color: #646970;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.remember-widget-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	padding: 12px;
	border-top: 1px solid #dcdcde;
}

.remember-widget-actions .button {
	flex: 1 1 auto;
	min-width: 120px;
	text-align: center;
}
</style>
