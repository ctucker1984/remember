<?php
/**
 * Settings view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-notification-setting.php';

Remember_Logger::debug( 'Settings page loaded' );

$notification_model = new Remember_Notification_Setting();

// Handle form submissions
if ( isset( $_POST['remember_settings_action'] ) && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_settings_action'] );
	
	if ( 'update' === $action ) {
		$options = get_option( 'remember_options', array() );
		
		// Update photo settings
		if ( isset( $_POST['photo_max_size'] ) ) {
			$options['photo_max_size'] = absint( $_POST['photo_max_size'] ) * 1024 * 1024; // Convert MB to bytes
		}
		if ( isset( $_POST['photo_max_dimensions'] ) ) {
			$options['photo_max_dimensions'] = absint( $_POST['photo_max_dimensions'] );
		}
		
		// Update QuickBooks sync interval
		if ( isset( $_POST['qb_sync_interval'] ) ) {
			$options['qb_sync_interval'] = absint( $_POST['qb_sync_interval'] ) * 3600; // Convert hours to seconds
		}
		
		// Update vetting workflow
		if ( isset( $_POST['vetting_workflow'] ) ) {
			$options['vetting_workflow'] = sanitize_text_field( $_POST['vetting_workflow'] );
		}
		
		update_option( 'remember_options', $options );
		Remember_Logger::info( 'Settings updated' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'remember' ) . '</p></div>';
	}
	
	// Handle notification settings update
	if ( 'update_notifications' === $action ) {
		if ( isset( $_POST['notification_settings'] ) && is_array( $_POST['notification_settings'] ) ) {
			foreach ( $_POST['notification_settings'] as $notification_type => $settings ) {
				$data = array(
					'is_enabled' => isset( $settings['enabled'] ) ? 1 : 0,
					'subject_template' => isset( $settings['subject'] ) ? sanitize_text_field( $settings['subject'] ) : '',
					'body_template' => isset( $settings['body'] ) ? wp_kses_post( $settings['body'] ) : '',
				);
				$notification_model->update_by_type( $notification_type, $data );
			}
			Remember_Logger::info( 'Notification settings updated' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Notification settings saved successfully.', 'remember' ) . '</p></div>';
		}
	}
}

// Get all notification settings
$all_notifications = $notification_model->get_all();
$notifications_by_category = array();
foreach ( $all_notifications as $notification ) {
	$category = Remember_Notification_Setting::get_type_category( $notification->notification_type );
	if ( ! isset( $notifications_by_category[ $category ] ) ) {
		$notifications_by_category[ $category ] = array();
	}
	$notifications_by_category[ $category ][] = $notification;
}

$options = get_option( 'remember_options', array() );
$plugin_version = get_option( 'remember_version', REMEMBER_VERSION );
?>

<div class="wrap remember-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
		<input type="hidden" name="remember_settings_action" value="update">
		
		<h2 class="nav-tab-wrapper">
			<a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'remember' ); ?></a>
			<a href="#quickbooks" class="nav-tab"><?php esc_html_e( 'QuickBooks', 'remember' ); ?></a>
			<a href="#notifications" class="nav-tab"><?php esc_html_e( 'Notifications', 'remember' ); ?></a>
		</h2>

		<!-- General Settings -->
		<div id="general" class="remember-settings-tab" style="display: block;">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="photo_max_size"><?php esc_html_e( 'Photo Max Size', 'remember' ); ?></label>
					</th>
					<td>
						<input type="number" id="photo_max_size" name="photo_max_size" value="<?php echo esc_attr( isset( $options['photo_max_size'] ) ? round( $options['photo_max_size'] / 1024 / 1024 ) : 2 ); ?>" min="1" max="10" class="small-text">
						<span class="description"><?php esc_html_e( 'MB (Maximum file size for profile photos)', 'remember' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="photo_max_dimensions"><?php esc_html_e( 'Photo Max Dimensions', 'remember' ); ?></label>
					</th>
					<td>
						<input type="number" id="photo_max_dimensions" name="photo_max_dimensions" value="<?php echo esc_attr( isset( $options['photo_max_dimensions'] ) ? $options['photo_max_dimensions'] : 800 ); ?>" min="100" max="2000" class="small-text">
						<span class="description"><?php esc_html_e( 'px (Maximum width/height for profile photos)', 'remember' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="vetting_workflow"><?php esc_html_e( 'Vetting Workflow', 'remember' ); ?></label>
					</th>
					<td>
						<select id="vetting_workflow" name="vetting_workflow" class="regular-text">
							<option value="on_join" <?php selected( isset( $options['vetting_workflow'] ) ? $options['vetting_workflow'] : 'on_join', 'on_join' ); ?>>
								<?php esc_html_e( 'On Member Join (Default)', 'remember' ); ?>
							</option>
							<option value="first_application" <?php selected( isset( $options['vetting_workflow'] ) ? $options['vetting_workflow'] : 'on_join', 'first_application' ); ?>>
								<?php esc_html_e( 'On First Event Application', 'remember' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'When should vetting be triggered? "On Member Join" creates a vetting case immediately when a member is created. "On First Event Application" delays vetting until the member applies for their first event.', 'remember' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Plugin Version', 'remember' ); ?></label>
					</th>
					<td>
						<strong><?php echo esc_html( $plugin_version ); ?></strong>
					</td>
				</tr>
			</table>
		</div>

		<!-- QuickBooks Settings -->
		<div id="quickbooks" class="remember-settings-tab" style="display: none;">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="qb_sync_interval"><?php esc_html_e( 'Sync Interval', 'remember' ); ?></label>
					</th>
					<td>
						<input type="number" id="qb_sync_interval" name="qb_sync_interval" value="<?php echo esc_attr( isset( $options['qb_sync_interval'] ) ? round( $options['qb_sync_interval'] / 3600 ) : 1 ); ?>" min="1" max="24" class="small-text">
						<span class="description"><?php esc_html_e( 'hours (How often to sync with QuickBooks Online)', 'remember' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'QuickBooks Connection', 'remember' ); ?></label>
					</th>
					<td>
						<p class="description"><?php esc_html_e( 'QuickBooks Online integration coming soon.', 'remember' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Last Sync', 'remember' ); ?></label>
					</th>
					<td>
						<?php
						global $wpdb;
						$last_sync = $wpdb->get_var( "SELECT last_sync_at FROM {$wpdb->prefix}remember_payment_processors WHERE processor_type = 'quickbooks' AND is_active = 1 LIMIT 1" );
						if ( $last_sync ) {
							echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) );
						} else {
							echo '<span class="description">' . esc_html__( 'Never', 'remember' ) . '</span>';
						}
						?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Notification Settings -->
		<div id="notifications" class="remember-settings-tab" style="display: none;">
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
				<input type="hidden" name="remember_settings_action" value="update_notifications">
				
				<p class="description" style="margin-bottom: 20px;">
					<?php esc_html_e( 'Configure email notifications and templates. Enable or disable specific notification types and customize the email subject and body templates.', 'remember' ); ?>
				</p>
				
				<?php
				$category_labels = array(
					'vetting'      => __( 'Vetting Notifications', 'remember' ),
					'applications' => __( 'Application Notifications', 'remember' ),
					'billing'      => __( 'Billing Notifications', 'remember' ),
					'general'      => __( 'General Notifications', 'remember' ),
				);
				
				foreach ( $category_labels as $category => $label ) :
					if ( ! isset( $notifications_by_category[ $category ] ) ) {
						continue;
					}
				?>
					<div style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
							<?php echo esc_html( $label ); ?>
						</h3>
						
						<?php foreach ( $notifications_by_category[ $category ] as $notification ) : 
							$type_id = 'notification_' . esc_attr( $notification->notification_type );
							// Use defaults if templates are empty
							$subject_template = ! empty( $notification->subject_template ) 
								? $notification->subject_template 
								: Remember_Notification_Setting::get_default_subject( $notification->notification_type );
							$body_template = ! empty( $notification->body_template ) 
								? $notification->body_template 
								: Remember_Notification_Setting::get_default_body( $notification->notification_type );
						?>
							<div style="margin-bottom: 25px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px;">
								<div style="display: flex; align-items: center; margin-bottom: 10px;">
									<label class="remember-toggle-switch" style="margin-right: 10px;">
										<input type="checkbox" 
										       id="<?php echo esc_attr( $type_id ); ?>_enabled"
										       name="notification_settings[<?php echo esc_attr( $notification->notification_type ); ?>][enabled]" 
										       value="1" 
										       <?php checked( $notification->is_enabled, 1 ); ?>>
										<span class="remember-toggle-slider"></span>
									</label>
									<label for="<?php echo esc_attr( $type_id ); ?>_enabled" style="font-weight: 600; cursor: pointer; margin: 0;">
										<?php echo esc_html( Remember_Notification_Setting::get_type_label( $notification->notification_type ) ); ?>
									</label>
								</div>
								<p class="description" style="margin: 5px 0 15px 25px; font-size: 13px;">
									<?php echo esc_html( Remember_Notification_Setting::get_type_description( $notification->notification_type ) ); ?>
								</p>
								
								<div style="margin-left: 25px;">
									<table class="form-table" style="margin: 0;">
										<tr>
											<th scope="row" style="width: 120px; padding: 10px 0;">
												<label for="<?php echo esc_attr( $type_id ); ?>_subject"><?php esc_html_e( 'Subject', 'remember' ); ?></label>
											</th>
											<td style="padding: 10px 0;">
												<input type="text" 
												       id="<?php echo esc_attr( $type_id ); ?>_subject" 
												       name="notification_settings[<?php echo esc_attr( $notification->notification_type ); ?>][subject]" 
												       value="<?php echo esc_attr( $subject_template ); ?>" 
												       class="regular-text" 
												       placeholder="<?php esc_attr_e( 'Email subject line', 'remember' ); ?>">
												<p class="description" style="margin-top: 5px; font-size: 12px;">
													<?php esc_html_e( 'Available variables: {member_name}, {event_name}, {application_id}, {vetting_id}, {amount}, {date}', 'remember' ); ?>
												</p>
											</td>
										</tr>
										<tr>
											<th scope="row" style="width: 120px; padding: 10px 0; vertical-align: top;">
												<label for="<?php echo esc_attr( $type_id ); ?>_body"><?php esc_html_e( 'Body', 'remember' ); ?></label>
											</th>
											<td style="padding: 10px 0;">
												<textarea id="<?php echo esc_attr( $type_id ); ?>_body" 
												          name="notification_settings[<?php echo esc_attr( $notification->notification_type ); ?>][body]" 
												          class="large-text" 
												          rows="6" 
												          placeholder="<?php esc_attr_e( 'Email body template', 'remember' ); ?>"><?php echo esc_textarea( $body_template ); ?></textarea>
												<p class="description" style="margin-top: 5px; font-size: 12px;">
													<?php esc_html_e( 'HTML is allowed. Use the same variables as in the subject line.', 'remember' ); ?>
												</p>
											</td>
										</tr>
									</table>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
				
				<?php submit_button( __( 'Save Notification Settings', 'remember' ) ); ?>
			</form>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
