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
require_once plugin_dir_path( __FILE__ ) . '../../includes/integrations/class-remember-quickbooks-oauth.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/integrations/class-remember-quickbooks-api.php';

Remember_Logger::debug( 'Settings page loaded' );

$notification_model = new Remember_Notification_Setting();

// Handle QuickBooks OAuth callback
if ( isset( $_GET['qb_oauth_callback'] ) && isset( $_GET['code'] ) ) {
	$code = sanitize_text_field( $_GET['code'] );
	$state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
	
	if ( ! wp_verify_nonce( $state, 'remember_qb_oauth' ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid OAuth state. Please try again.', 'remember' ) . '</p></div>';
	} else {
		$settings = Remember_QuickBooks_OAuth::get_settings();
		
		if ( ! $settings || empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'QuickBooks credentials not configured. Please enter Client ID and Client Secret first.', 'remember' ) . '</p></div>';
		} else {
			$redirect_uri = Remember_QuickBooks_OAuth::get_redirect_uri();
			$token_data = Remember_QuickBooks_OAuth::exchange_code_for_token(
				$code,
				$settings['client_id'],
				$settings['client_secret'],
				$redirect_uri
			);
			
			if ( is_wp_error( $token_data ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $token_data->get_error_message() ) . '</p></div>';
			} else {
				// Save tokens
				$settings['access_token'] = $token_data['access_token'];
				$settings['refresh_token'] = $token_data['refresh_token'];
				$settings['expires_at'] = time() + $token_data['expires_in'];
				$settings['realm_id'] = isset( $_GET['realmId'] ) ? sanitize_text_field( $_GET['realmId'] ) : '';
				
				if ( Remember_QuickBooks_OAuth::save_settings( $settings ) ) {
					// Activate QuickBooks processor
					global $wpdb;
					$wpdb->update(
						$wpdb->prefix . 'remember_payment_processors',
						array( 'is_active' => 1 ),
						array( 'processor_type' => 'quickbooks' ),
						array( '%d' ),
						array( '%s' )
					);
					
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'QuickBooks connected successfully!', 'remember' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to save QuickBooks connection.', 'remember' ) . '</p></div>';
				}
			}
		}
	}
}

// Handle QuickBooks manual sync
if ( isset( $_POST['remember_settings_action'] ) && 'sync_qb_payments' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../../includes/integrations/class-remember-quickbooks-sync.php';
	$sync_results = Remember_QuickBooks_Sync::sync_all_payments();
	
	if ( $sync_results['success'] > 0 || $sync_results['error'] > 0 ) {
		$message = sprintf(
			__( 'Sync completed: %d successful, %d errors.', 'remember' ),
			$sync_results['success'],
			$sync_results['error']
		);
		if ( $sync_results['error'] > 0 ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	} else {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'No payments to sync.', 'remember' ) . '</p></div>';
	}
}

// Handle QuickBooks disconnect
if ( isset( $_POST['remember_settings_action'] ) && 'disconnect_qb' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
	$settings = Remember_QuickBooks_OAuth::get_settings();
	if ( $settings && ! empty( $settings['access_token'] ) ) {
		Remember_QuickBooks_OAuth::revoke_token(
			$settings['access_token'],
			$settings['client_id'],
			$settings['client_secret']
		);
	}
	
	// Clear settings
	Remember_QuickBooks_OAuth::save_settings( array() );
	
	// Deactivate processor
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'remember_payment_processors',
		array( 'is_active' => 0 ),
		array( 'processor_type' => 'quickbooks' ),
		array( '%d' ),
		array( '%s' )
	);
	
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'QuickBooks disconnected successfully.', 'remember' ) . '</p></div>';
}

// Handle QuickBooks credentials save
if ( isset( $_POST['remember_settings_action'] ) && 'save_qb_credentials' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
	$settings = Remember_QuickBooks_OAuth::get_settings() ?: array();
	$settings['client_id'] = isset( $_POST['qb_client_id'] ) ? sanitize_text_field( $_POST['qb_client_id'] ) : '';
	
	// Only update client_secret if a new value was provided
	if ( ! empty( $_POST['qb_client_secret'] ) ) {
		$settings['client_secret'] = sanitize_text_field( $_POST['qb_client_secret'] );
	}
	
	$settings['environment'] = isset( $_POST['qb_environment'] ) ? sanitize_text_field( $_POST['qb_environment'] ) : 'sandbox';
	
	if ( Remember_QuickBooks_OAuth::save_settings( $settings ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'QuickBooks credentials saved successfully.', 'remember' ) . '</p></div>';
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to save QuickBooks credentials.', 'remember' ) . '</p></div>';
	}
}

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
			<?php
			$qb_settings = Remember_QuickBooks_OAuth::get_settings();
			$is_connected = $qb_settings && ! empty( $qb_settings['access_token'] ) && ! empty( $qb_settings['realm_id'] );
			$company_info = array();
			if ( $is_connected ) {
				$company_info_result = Remember_QuickBooks_API::get_company_info();
				if ( ! is_wp_error( $company_info_result ) ) {
					$company_info = $company_info_result;
				}
			}
			?>
			
			<h2><?php esc_html_e( 'QuickBooks Online Integration', 'remember' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Connect your QuickBooks Online account to automatically create invoices and sync payment status.', 'remember' ); ?>
			</p>
			
			<!-- Connection Status -->
			<?php if ( $is_connected ) : ?>
				<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
					<p style="margin: 0; color: #155724;">
						<strong><?php esc_html_e( 'Connected', 'remember' ); ?></strong>
						<?php if ( ! empty( $company_info['CompanyName'] ) ) : ?>
							- <?php echo esc_html( $company_info['CompanyName'] ); ?>
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $company_info['CompanyInfo']['CompanyName'] ) ) : ?>
						<p style="margin: 5px 0 0 0; font-size: 13px;">
							<?php esc_html_e( 'Company:', 'remember' ); ?> <?php echo esc_html( $company_info['CompanyInfo']['CompanyName'] ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
					<p style="margin: 0; color: #721c24;">
						<strong><?php esc_html_e( 'Not Connected', 'remember' ); ?></strong>
					</p>
				</div>
			<?php endif; ?>
			
			<!-- Credentials Form -->
			<form method="post" action="" style="margin-bottom: 30px;">
				<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
				<input type="hidden" name="remember_settings_action" value="save_qb_credentials">
				
				<h3><?php esc_html_e( 'QuickBooks Credentials', 'remember' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Enter your QuickBooks App credentials from the Intuit Developer Portal.', 'remember' ); ?>
					<a href="https://developer.intuit.com/app/developer/qbo/docs/get-started" target="_blank"><?php esc_html_e( 'Get started with QuickBooks API', 'remember' ); ?></a>
				</p>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="qb_environment"><?php esc_html_e( 'Environment', 'remember' ); ?></label>
						</th>
						<td>
							<select id="qb_environment" name="qb_environment" class="regular-text">
								<option value="sandbox" <?php selected( isset( $qb_settings['environment'] ) ? $qb_settings['environment'] : 'sandbox', 'sandbox' ); ?>>
									<?php esc_html_e( 'Sandbox (Testing)', 'remember' ); ?>
								</option>
								<option value="production" <?php selected( isset( $qb_settings['environment'] ) ? $qb_settings['environment'] : 'sandbox', 'production' ); ?>>
									<?php esc_html_e( 'Production', 'remember' ); ?>
								</option>
							</select>
							<p class="description"><?php esc_html_e( 'Use Sandbox for testing, Production for live accounts.', 'remember' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qb_client_id"><?php esc_html_e( 'Client ID (OAuth 2.0 Client ID)', 'remember' ); ?></label>
						</th>
						<td>
							<input type="text" id="qb_client_id" name="qb_client_id" class="regular-text" 
							       value="<?php echo esc_attr( isset( $qb_settings['client_id'] ) ? $qb_settings['client_id'] : '' ); ?>" 
							       placeholder="<?php esc_attr_e( 'Enter your Client ID', 'remember' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="qb_client_secret"><?php esc_html_e( 'Client Secret (OAuth 2.0 Client Secret)', 'remember' ); ?></label>
						</th>
						<td>
							<input type="password" id="qb_client_secret" name="qb_client_secret" class="regular-text" 
							       value="" 
							       placeholder="<?php echo esc_attr( isset( $qb_settings['client_secret'] ) ? __( 'Enter new Client Secret to update', 'remember' ) : __( 'Enter your Client Secret', 'remember' ) ); ?>">
							<p class="description">
								<?php if ( isset( $qb_settings['client_secret'] ) ) : ?>
									<?php esc_html_e( 'Client Secret is already saved. Enter a new value to update it.', 'remember' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Enter your Client Secret from the Intuit Developer Portal.', 'remember' ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Save Credentials', 'remember' ) ); ?>
			</form>
			
			<!-- Connect/Disconnect -->
			<?php if ( ! empty( $qb_settings['client_id'] ) && ! empty( $qb_settings['client_secret'] ) ) : ?>
				<?php if ( $is_connected ) : ?>
					<form method="post" action="" style="margin-bottom: 30px;">
						<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
						<input type="hidden" name="remember_settings_action" value="disconnect_qb">
						<?php submit_button( __( 'Disconnect QuickBooks', 'remember' ), 'secondary' ); ?>
					</form>
				<?php else : ?>
					<?php
					$auth_url = Remember_QuickBooks_OAuth::get_authorization_url(
						$qb_settings['client_id'],
						Remember_QuickBooks_OAuth::get_redirect_uri()
					);
					?>
					<p>
						<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary button-large">
							<?php esc_html_e( 'Connect to QuickBooks', 'remember' ); ?>
						</a>
					</p>
					<p class="description">
						<?php esc_html_e( 'Click the button above to authorize this plugin to access your QuickBooks Online account.', 'remember' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
			
			<!-- Sync Settings -->
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
				<input type="hidden" name="remember_settings_action" value="update">
				
				<h3><?php esc_html_e( 'Sync Settings', 'remember' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="qb_sync_interval"><?php esc_html_e( 'Sync Interval', 'remember' ); ?></label>
						</th>
						<td>
							<input type="number" id="qb_sync_interval" name="qb_sync_interval" value="<?php echo esc_attr( isset( $options['qb_sync_interval'] ) ? round( $options['qb_sync_interval'] / 3600 ) : 1 ); ?>" min="1" max="24" class="small-text">
							<span class="description"><?php esc_html_e( 'hours (How often to sync payment status from QuickBooks)', 'remember' ); ?></span>
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
				
				<?php submit_button( __( 'Save Sync Settings', 'remember' ) ); ?>
			</form>
			
			<!-- Manual Sync -->
			<?php if ( $is_connected ) : ?>
				<form method="post" action="" style="margin-top: 20px;">
					<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
					<input type="hidden" name="remember_settings_action" value="sync_qb_payments">
					<h3><?php esc_html_e( 'Manual Sync', 'remember' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Manually sync payment status from QuickBooks for all pending and partial payments.', 'remember' ); ?>
					</p>
					<?php submit_button( __( 'Sync Payments Now', 'remember' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>
			
			<!-- Product Mapping -->
			<?php if ( $is_connected ) : 
				require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-product.php';
				require_once plugin_dir_path( __FILE__ ) . '../../includes/integrations/class-remember-quickbooks-api.php';
				
				// Handle product mapping save
				if ( isset( $_POST['remember_settings_action'] ) && 'save_product_mapping' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
					$product_model = new Remember_Product();
					
					if ( isset( $_POST['product_mappings'] ) && is_array( $_POST['product_mappings'] ) ) {
						foreach ( $_POST['product_mappings'] as $product_id => $qb_product_id ) {
							$product_id = absint( $product_id );
							$qb_product_id = sanitize_text_field( $qb_product_id );
							
							if ( $product_id > 0 && ! empty( $qb_product_id ) ) {
								$product_model->update( $product_id, array(
									'quickbooks_product_id' => $qb_product_id,
									'last_sync_at'          => current_time( 'mysql' ),
									'updated_at'            => current_time( 'mysql' ),
								) );
							}
						}
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product mappings saved successfully.', 'remember' ) . '</p></div>';
					}
				}
				
				// Handle sync products from QuickBooks
				if ( isset( $_POST['remember_settings_action'] ) && 'sync_qb_products' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
					$qb_items = Remember_QuickBooks_API::query_items();
					if ( ! is_wp_error( $qb_items ) ) {
						$product_model = new Remember_Product();
						foreach ( $qb_items as $item ) {
							$existing = $product_model->get_by_qb_id( $item['Id'] );
							if ( ! $existing ) {
								$product_model->insert( array(
									'product_name'            => $item['Name'] ?? '',
									'description'            => $item['Description'] ?? '',
									'quickbooks_product_id'  => $item['Id'],
									'quickbooks_product_name' => $item['Name'] ?? '',
									'product_type'           => $item['Type'] ?? 'Service',
									'is_active'              => 1,
									'last_sync_at'           => current_time( 'mysql' ),
									'created_at'            => current_time( 'mysql' ),
									'updated_at'            => current_time( 'mysql' ),
								) );
							} else {
								$product_model->update( $existing->product_id, array(
									'quickbooks_product_name' => $item['Name'] ?? $existing->quickbooks_product_name,
									'last_sync_at'           => current_time( 'mysql' ),
									'updated_at'             => current_time( 'mysql' ),
								) );
							}
						}
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Products synced from QuickBooks successfully.', 'remember' ) . '</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to sync products from QuickBooks: ', 'remember' ) . esc_html( $qb_items->get_error_message() ) . '</p></div>';
					}
				}
				
				// Get all products
				$product_model = new Remember_Product();
				$all_products = $product_model->get_all();
				$qb_products = array();
				if ( $is_connected ) {
					$qb_items_result = Remember_QuickBooks_API::query_items();
					if ( ! is_wp_error( $qb_items_result ) ) {
						$qb_products = $qb_items_result;
					}
				}
			?>
				<form method="post" action="" style="margin-top: 30px;">
					<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
					<input type="hidden" name="remember_settings_action" value="save_product_mapping">
					
					<h3><?php esc_html_e( 'Product Mapping', 'remember' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Map your WordPress products (roles and merchandise) to QuickBooks products. This allows invoices to be created automatically when applications are accepted.', 'remember' ); ?>
					</p>
					
					<?php if ( ! empty( $qb_products ) ) : ?>
						<p>
							<button type="button" class="button" onclick="document.getElementById('sync-qb-products-form').submit();">
								<?php esc_html_e( 'Sync Products from QuickBooks', 'remember' ); ?>
							</button>
						</p>
					<?php endif; ?>
					
					<?php if ( ! empty( $all_products ) ) : ?>
						<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
							<thead>
								<tr>
									<th style="width: 200px;"><?php esc_html_e( 'Product Name', 'remember' ); ?></th>
									<th><?php esc_html_e( 'QuickBooks Product', 'remember' ); ?></th>
									<th style="width: 120px;"><?php esc_html_e( 'QB Product ID', 'remember' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Last Synced', 'remember' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $all_products as $product ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $product->product_name ); ?></strong>
											<?php if ( ! empty( $product->description ) ) : ?>
												<br><span class="description"><?php echo esc_html( $product->description ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<select name="product_mappings[<?php echo esc_attr( $product->product_id ); ?>]" class="regular-text">
												<option value=""><?php esc_html_e( '-- Not Mapped --', 'remember' ); ?></option>
												<?php foreach ( $qb_products as $qb_product ) : ?>
													<option value="<?php echo esc_attr( $qb_product['Id'] ); ?>" <?php selected( $product->quickbooks_product_id, $qb_product['Id'] ); ?>>
														<?php echo esc_html( ( $qb_product['Name'] ?? '' ) . ' (' . ( $qb_product['Type'] ?? 'Service' ) . ')' ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<?php if ( ! empty( $product->quickbooks_product_id ) ) : ?>
												<code><?php echo esc_html( $product->quickbooks_product_id ); ?></code>
											<?php else : ?>
												<span class="description">—</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $product->last_sync_at ) ) : ?>
												<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $product->last_sync_at ) ) ); ?>
											<?php else : ?>
												<span class="description">—</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						
						<?php submit_button( __( 'Save Product Mappings', 'remember' ) ); ?>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No products found. Products will be created automatically when you sync from QuickBooks or when invoices are created.', 'remember' ); ?>
						</p>
					<?php endif; ?>
				</form>
				
				<form id="sync-qb-products-form" method="post" action="" style="display: none;">
					<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
					<input type="hidden" name="remember_settings_action" value="sync_qb_products">
				</form>
			<?php endif; ?>
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
