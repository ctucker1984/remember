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

Remember_Logger::debug( 'Settings page loaded' );

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
			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Email Notifications', 'remember' ); ?></label>
					</th>
					<td>
						<p class="description"><?php esc_html_e( 'Notification settings and email templates coming soon.', 'remember' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
