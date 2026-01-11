<?php
/**
 * Location detail view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Status labels and colors for events
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

<div class="remember-location-detail">
	<!-- Header Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
		<div style="display: flex; align-items: center; gap: 20px; justify-content: space-between;">
			<div style="display: flex; align-items: center; gap: 20px; flex: 1;">
				<?php if ( ! empty( $viewing_location->logo_url ) ) : ?>
					<img src="<?php echo esc_url( $viewing_location->logo_url ); ?>" alt="<?php echo esc_attr( $viewing_location->location_name ); ?>" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd;">
				<?php else : ?>
					<div style="width: 120px; height: 120px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #999; border: 2px solid #ddd;">
						<span class="dashicons dashicons-location"></span>
					</div>
				<?php endif; ?>
				<div style="flex: 1;">
					<h2 style="margin: 0 0 10px 0; font-size: 24px;">
						<?php echo esc_html( $viewing_location->location_name ); ?>
						<?php if ( $viewing_location->is_active ) : ?>
							<span style="color: #46b450; font-size: 14px; font-weight: normal; margin-left: 10px;">
								<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active', 'remember' ); ?>
							</span>
						<?php else : ?>
							<span style="color: #dc3232; font-size: 14px; font-weight: normal; margin-left: 10px;">
								<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Inactive', 'remember' ); ?>
							</span>
						<?php endif; ?>
					</h2>
					<?php if ( ! empty( $viewing_location->address_street ) || ! empty( $viewing_location->address_city ) ) : ?>
						<div style="color: #666; margin-top: 5px; line-height: 1.6;">
							<?php echo remember_format_address( $viewing_location ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Location Information Grid -->
	<div class="remember-location-detail-grid">
		<!-- Details Section -->
		<?php if ( ! empty( $viewing_location->details ) ) : ?>
			<div class="remember-location-detail-section">
				<h3><?php esc_html_e( 'Details', 'remember' ); ?></h3>
				<div style="color: #555; line-height: 1.6;">
					<?php echo wp_kses_post( nl2br( esc_html( $viewing_location->details ) ) ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Historical Events Section -->
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Historical Events', 'remember' ); ?></h3>
		<?php if ( ! empty( $location_events ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-name"><?php esc_html_e( 'Event Name', 'remember' ); ?></th>
						<th class="column-dates"><?php esc_html_e( 'Dates', 'remember' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
						<th class="column-type"><?php esc_html_e( 'Type', 'remember' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $location_events as $event ) : ?>
						<tr>
							<td class="column-name">
								<strong><?php echo esc_html( $event->event_name ); ?></strong>
								<?php if ( ! empty( $event->event_description ) ) : ?>
									<br><span class="description" style="font-size: 12px;">
										<?php echo esc_html( wp_trim_words( $event->event_description, 20 ) ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="column-dates">
								<?php 
								$start_date = date_i18n( get_option( 'date_format' ), strtotime( $event->start_date ) );
								$end_date = date_i18n( get_option( 'date_format' ), strtotime( $event->end_date ) );
								if ( $event->start_date === $event->end_date ) {
									echo esc_html( $start_date );
								} else {
									echo esc_html( $start_date . ' - ' . $end_date );
								}
								?>
							</td>
							<td class="column-status">
								<span style="color: <?php echo esc_attr( $status_colors[ $event->status ] ); ?>;">
									<?php echo esc_html( $status_labels[ $event->status ] ); ?>
								</span>
							</td>
							<td class="column-type">
								<?php if ( $event->is_private ) : ?>
									<span class="dashicons dashicons-lock" style="color: #72777c;" title="<?php esc_attr_e( 'Private', 'remember' ); ?>"></span>
									<span class="description"><?php esc_html_e( 'Private', 'remember' ); ?></span>
								<?php else : ?>
									<span class="dashicons dashicons-unlock" style="color: #46b450;" title="<?php esc_attr_e( 'Public', 'remember' ); ?>"></span>
									<span class="description"><?php esc_html_e( 'Public', 'remember' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No historical events found for this location.', 'remember' ); ?></p>
		<?php endif; ?>
	</div>
</div>
