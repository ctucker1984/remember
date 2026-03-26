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

// Show success message if pages were just set up
if ( isset( $_GET['pages_setup'] ) ) {
	$message = urldecode( sanitize_text_field( $_GET['pages_setup'] ) );
	if ( ! empty( $message ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}

if ( isset( $_GET['qb_oauth_error'] ) && 'nocreds' === $_GET['qb_oauth_error'] ) {
	echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Save your Client ID and Client Secret before connecting to QuickBooks.', 'remember' ) . '</p></div>';
}

$qb_oauth_notice = get_transient( 'remember_qb_oauth_notice_' . get_current_user_id() );
if ( $qb_oauth_notice && is_array( $qb_oauth_notice ) && ! empty( $qb_oauth_notice['message'] ) ) {
	delete_transient( 'remember_qb_oauth_notice_' . get_current_user_id() );
	$notice_class = ( isset( $qb_oauth_notice['type'] ) && 'success' === $qb_oauth_notice['type'] ) ? 'notice-success' : 'notice-error';
	echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $qb_oauth_notice['message'] ) . '</p></div>';
}

$notification_model = new Remember_Notification_Setting();

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

// Handle QuickBooks product mapping save (must run here; nested <form> inside the main settings form is invalid HTML
// and browsers submit product rows as action "update" instead of "save_product_mapping").
if ( isset( $_POST['remember_settings_action'] ) && 'save_product_mapping' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-product.php';
	$product_model = new Remember_Product();
	if ( isset( $_POST['product_mappings'] ) && is_array( $_POST['product_mappings'] ) ) {
		foreach ( $_POST['product_mappings'] as $product_id => $qb_product_id ) {
			$product_id      = absint( $product_id );
			$qb_product_id   = sanitize_text_field( wp_unslash( $qb_product_id ) );
			if ( $product_id > 0 && ! empty( $qb_product_id ) ) {
				$product_model->update(
					$product_id,
					array(
						'quickbooks_product_id' => $qb_product_id,
						'last_sync_at'          => current_time( 'mysql' ),
						'updated_at'            => current_time( 'mysql' ),
					)
				);
			}
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Product mappings saved successfully.', 'remember' ) . '</p></div>';
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
		
		// Update log level
		if ( isset( $_POST['log_level'] ) ) {
			$log_level = sanitize_text_field( $_POST['log_level'] );
			$valid_levels = array( 'NONE', 'ERROR', 'WARNING', 'INFO', 'DEBUG' );
			if ( in_array( $log_level, $valid_levels, true ) ) {
				$options['log_level'] = $log_level;
			}
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
	
	// Handle social media platform management
	if ( 'add_social_platform' === $action ) {
		global $wpdb;
		$platform_name = isset( $_POST['new_platform_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_platform_name'] ) ) : '';
		if ( ! empty( $platform_name ) ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT platform_id FROM {$wpdb->prefix}remember_social_media_platforms WHERE platform_name = %s",
				$platform_name
			) );
			if ( ! $existing ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_social_media_platforms',
					array(
						'platform_name' => $platform_name,
						'is_active'     => 1,
						'sort_order'    => 999,
						'created_at'    => current_time( 'mysql' ),
					)
				);
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Social media platform added successfully.', 'remember' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Platform already exists.', 'remember' ) . '</p></div>';
			}
		}
	}
	
	if ( 'update_social_platforms' === $action ) {
		global $wpdb;
		if ( isset( $_POST['platforms'] ) && is_array( $_POST['platforms'] ) ) {
			$updated_count = 0;
			$error_count = 0;
			$skipped_count = 0;
			
			// First, verify all platform IDs exist
			$all_platform_ids = array_keys( $_POST['platforms'] );
			$all_platform_ids = array_map( 'absint', $all_platform_ids );
			$all_platform_ids = array_filter( $all_platform_ids );
			
			if ( ! empty( $all_platform_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $all_platform_ids ), '%d' ) );
				$existing_platforms = $wpdb->get_col( $wpdb->prepare(
					"SELECT platform_id FROM {$wpdb->prefix}remember_social_media_platforms WHERE platform_id IN ($placeholders)",
					$all_platform_ids
				) );
				$existing_platform_ids = array_map( 'absint', $existing_platforms );
			} else {
				$existing_platform_ids = array();
			}
			
			foreach ( $_POST['platforms'] as $platform_id => $platform_data ) {
				$platform_id = absint( $platform_id );
				if ( $platform_id <= 0 ) {
					$skipped_count++;
					continue; // Skip invalid platform IDs
				}
				
				// Verify platform exists
				if ( ! in_array( $platform_id, $existing_platform_ids, true ) ) {
					$skipped_count++;
					Remember_Logger::warning( 'Attempted to update non-existent social media platform', array( 'platform_id' => $platform_id ) );
					continue;
				}
				
				// Validate and sanitize data
				$platform_name = isset( $platform_data['name'] ) ? sanitize_text_field( wp_unslash( $platform_data['name'] ) ) : '';
				if ( empty( $platform_name ) ) {
					$error_count++;
					Remember_Logger::warning( 'Skipped update for platform with empty name', array( 'platform_id' => $platform_id ) );
					continue; // Skip if name is empty
				}
				
				$is_active = isset( $platform_data['is_active'] ) ? 1 : 0;
				$sort_order = isset( $platform_data['sort_order'] ) && $platform_data['sort_order'] !== '' 
					? absint( $platform_data['sort_order'] ) 
					: 0;
				
				$result = $wpdb->update(
					$wpdb->prefix . 'remember_social_media_platforms',
					array(
						'platform_name' => $platform_name,
						'is_active'     => $is_active,
						'sort_order'    => $sort_order,
					),
					array( 'platform_id' => $platform_id ),
					array( '%s', '%d', '%d' ),
					array( '%d' )
				);
				
				if ( false === $result ) {
					$error_count++;
					Remember_Logger::error( 'Failed to update social media platform', array(
						'platform_id' => $platform_id,
						'platform_name' => $platform_name,
						'sort_order' => $sort_order,
						'db_error' => $wpdb->last_error,
						'db_query' => $wpdb->last_query,
					) );
				} elseif ( $result >= 0 ) {
					// $result can be 0 if no rows changed, or > 0 if rows were updated
					$updated_count++;
				}
			}
			
			$message_parts = array();
			if ( $updated_count > 0 ) {
				$message_parts[] = sprintf( esc_html__( '%d platform(s) updated', 'remember' ), $updated_count );
			}
			if ( $error_count > 0 ) {
				$message_parts[] = sprintf( esc_html__( '%d update(s) failed', 'remember' ), $error_count );
			}
			if ( $skipped_count > 0 ) {
				$message_parts[] = sprintf( esc_html__( '%d platform(s) skipped', 'remember' ), $skipped_count );
			}
			
			if ( $error_count > 0 || $skipped_count > 0 ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . 
					implode( ', ', $message_parts ) . 
					'. ' . esc_html__( 'Please check the error log for details.', 'remember' ) . 
					'</p></div>';
			} elseif ( $updated_count > 0 ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . 
					esc_html__( 'Social media platforms updated successfully.', 'remember' ) . 
					'</p></div>';
			}
		}
	}
	
	if ( 'delete_social_platform' === $action && isset( $_POST['platform_id'] ) ) {
		global $wpdb;
		$platform_id = absint( $_POST['platform_id'] );
		$wpdb->delete(
			$wpdb->prefix . 'remember_social_media_platforms',
			array( 'platform_id' => $platform_id ),
			array( '%d' )
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Social media platform deleted successfully.', 'remember' ) . '</p></div>';
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

// Get social media platforms
global $wpdb;
$social_platforms = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_social_media_platforms ORDER BY sort_order ASC, platform_name ASC"
);
?>

<div class="wrap remember-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form id="remember-main-settings" method="post" action="">
		<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
		<input type="hidden" name="remember_settings_action" value="update">
		
		<h2 class="nav-tab-wrapper">
			<a href="#general" class="nav-tab nav-tab-active"><?php esc_html_e( 'General', 'remember' ); ?></a>
			<a href="#shortcodes" class="nav-tab" id="shortcodes-tab-link"><?php esc_html_e( 'Shortcodes', 'remember' ); ?></a>
			<a href="#social-media" class="nav-tab"><?php esc_html_e( 'Social Media', 'remember' ); ?></a>
			<a href="#quickbooks" class="nav-tab"><?php esc_html_e( 'QuickBooks', 'remember' ); ?></a>
			<a href="#notifications" class="nav-tab"><?php esc_html_e( 'Notifications', 'remember' ); ?></a>
			<a href="#logging" class="nav-tab"><?php esc_html_e( 'Logging', 'remember' ); ?></a>
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

		<!-- Shortcodes Documentation -->
		<div id="shortcodes" class="remember-settings-tab" style="display: none;">
			<?php require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-page-creator.php'; ?>
			<?php $default_pages = Remember_Page_Creator::get_default_pages(); ?>
			<?php $created_pages = Remember_Page_Creator::get_created_pages(); ?>

			<h2><?php esc_html_e( 'Shortcode Documentation', 'remember' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'reMember provides shortcodes that you can add to any page or post. These shortcodes display member-facing content and forms.', 'remember' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'For Full Site Editing (FSE) themes, you can also use the block patterns available in the "reMember" category.', 'remember' ); ?>
			</p>

			<div style="margin-top: 30px;">
				<?php foreach ( $default_pages as $key => $page_data ) : 
					$is_created = isset( $created_pages[ $key ] );
					$page_id = $is_created ? $created_pages[ $key ] : 0;
				?>
					<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
						<h3 style="margin-top: 0; display: flex; align-items: center; justify-content: space-between;">
							<span><?php echo esc_html( $page_data['title'] ); ?></span>
							<?php if ( $is_created ) : ?>
								<span style="font-size: 0.875em; font-weight: normal; color: #46b450;">
									✓ <?php esc_html_e( 'Page created', 'remember' ); ?>
									<a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" target="_blank" style="margin-left: 10px;">
										<?php esc_html_e( 'Edit', 'remember' ); ?>
									</a>
									<a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" style="margin-left: 10px;">
										<?php esc_html_e( 'View', 'remember' ); ?>
									</a>
								</span>
							<?php endif; ?>
						</h3>
						
						<p style="color: #646970; margin: 10px 0;">
							<?php echo esc_html( $page_data['description'] ); ?>
						</p>

						<div style="background: #f6f7f7; border: 1px solid #ddd; border-radius: 3px; padding: 15px; margin: 15px 0;">
							<strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Shortcode:', 'remember' ); ?></strong>
							<code style="display: block; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 14px; word-break: break-all;">
								<?php echo esc_html( $page_data['shortcode'] ); ?>
							</code>
							<button type="button" class="button button-small remember-copy-shortcode" data-shortcode="<?php echo esc_attr( $page_data['shortcode'] ); ?>" style="margin-top: 8px;">
								<?php esc_html_e( 'Copy to Clipboard', 'remember' ); ?>
							</button>
						</div>

						<?php if ( 'remember_dashboard' === $page_data['shortcode'] ) : ?>
							<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
								<h4 style="margin-top: 0;"><?php esc_html_e( 'Features:', 'remember' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px;">
									<li><?php esc_html_e( 'Member status and profile summary', 'remember' ); ?></li>
									<li><?php esc_html_e( 'List of accepted events with links to event directories', 'remember' ); ?></li>
									<li><?php esc_html_e( 'Recent applications with status badges', 'remember' ); ?></li>
									<li><?php esc_html_e( 'Quick links to browse events and edit profile', 'remember' ); ?></li>
								</ul>
							</div>
						<?php elseif ( 'remember_events' === $page_data['shortcode'] ) : ?>
							<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
								<h4 style="margin-top: 0;"><?php esc_html_e( 'Attributes:', 'remember' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px;">
									<li><code>status="open"</code> - <?php esc_html_e( 'Show only open events (default)', 'remember' ); ?></li>
									<li><code>status="all"</code> - <?php esc_html_e( 'Show all events regardless of status', 'remember' ); ?></li>
									<li><code>status="upcoming"</code> - <?php esc_html_e( 'Show only upcoming events', 'remember' ); ?></li>
									<li><code>limit="5"</code> - <?php esc_html_e( 'Limit the number of events displayed', 'remember' ); ?></li>
								</ul>
								<p style="margin-top: 10px;"><strong><?php esc_html_e( 'Example:', 'remember' ); ?></strong> <code>[remember_events status="upcoming" limit="10"]</code></p>
							</div>
						<?php elseif ( 'remember_apply' === $page_data['shortcode'] ) : ?>
							<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
								<h4 style="margin-top: 0;"><?php esc_html_e( 'Attributes:', 'remember' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px;">
									<li><code>event_id="123"</code> - <?php esc_html_e( 'Pre-select a specific event (optional)', 'remember' ); ?></li>
								</ul>
								<p style="margin-top: 10px;"><strong><?php esc_html_e( 'Example:', 'remember' ); ?></strong> <code>[remember_apply event_id="5"]</code></p>
								<p style="margin-top: 10px;"><strong><?php esc_html_e( 'Note:', 'remember' ); ?></strong> <?php esc_html_e( 'If no event_id is provided, users can select an event from a dropdown. The form will automatically load available roles for the selected event.', 'remember' ); ?></p>
							</div>
						<?php elseif ( 'remember_profile' === $page_data['shortcode'] ) : ?>
							<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
								<h4 style="margin-top: 0;"><?php esc_html_e( 'Features:', 'remember' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px;">
									<li><?php esc_html_e( 'View and edit profile information', 'remember' ); ?></li>
									<li><?php esc_html_e( 'Manage privacy settings for event sharing', 'remember' ); ?></li>
									<li><?php esc_html_e( 'Supports ?edit=1 URL parameter to show edit mode', 'remember' ); ?></li>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<!-- Event Detail Shortcode -->
				<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Event Detail', 'remember' ); ?></h3>
					<p style="color: #646970; margin: 10px 0;">
						<?php esc_html_e( 'Display detailed information about an event, including dates, location, status, and the attendee directory (for accepted members).', 'remember' ); ?>
					</p>

					<div style="background: #f6f7f7; border: 1px solid #ddd; border-radius: 3px; padding: 15px; margin: 15px 0;">
						<strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Shortcode:', 'remember' ); ?></strong>
						<code style="display: block; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 14px; word-break: break-all;">
							[remember_event_detail]
						</code>
						<button type="button" class="button button-small remember-copy-shortcode" data-shortcode="[remember_event_detail]" style="margin-top: 8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'remember' ); ?>
						</button>
					</div>

					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
						<h4 style="margin-top: 0;"><?php esc_html_e( 'Attributes:', 'remember' ); ?></h4>
						<ul style="margin: 0; padding-left: 20px;">
							<li><code>event_id="123"</code> - <?php esc_html_e( 'Optional: The ID of the event. If not provided, uses ?event=ID from URL.', 'remember' ); ?></li>
						</ul>
						<p style="margin-top: 10px;"><strong><?php esc_html_e( 'Note:', 'remember' ); ?></strong> <?php esc_html_e( 'The attendee directory is only visible to members who are accepted to the event. Contact information is displayed only if members have enabled sharing in their privacy settings.', 'remember' ); ?></p>
					</div>
				</div>

				<!-- Event Directory Shortcode -->
				<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Event Member Directory', 'remember' ); ?></h3>
					<p style="color: #646970; margin: 10px 0;">
						<?php esc_html_e( 'Display a directory of members accepted to a specific event. Only shows contact information that members have chosen to share via privacy settings. This is typically embedded within the Event Detail page.', 'remember' ); ?>
					</p>

					<div style="background: #f6f7f7; border: 1px solid #ddd; border-radius: 3px; padding: 15px; margin: 15px 0;">
						<strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Shortcode:', 'remember' ); ?></strong>
						<code style="display: block; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; font-size: 14px; word-break: break-all;">
							[remember_event_directory event_id="123"]
						</code>
						<button type="button" class="button button-small remember-copy-shortcode" data-shortcode="[remember_event_directory event_id=&quot;123&quot;]" style="margin-top: 8px;">
							<?php esc_html_e( 'Copy to Clipboard', 'remember' ); ?>
						</button>
					</div>

					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
						<h4 style="margin-top: 0;"><?php esc_html_e( 'Attributes:', 'remember' ); ?></h4>
						<ul style="margin: 0; padding-left: 20px;">
							<li><code>event_id="123"</code> - <?php esc_html_e( 'Required: The ID of the event to show members for', 'remember' ); ?></li>
						</ul>
						<p style="margin-top: 10px;"><strong><?php esc_html_e( 'Note:', 'remember' ); ?></strong> <?php esc_html_e( 'Only members who are accepted to the event can view the directory. Contact information is displayed only if members have enabled sharing for that type of information in their privacy settings.', 'remember' ); ?></p>
					</div>
				</div>

				<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-top: 30px;">
					<h3 style="margin-top: 0;"><?php esc_html_e( 'Page Creation', 'remember' ); ?></h3>
					<p>
						<?php esc_html_e( 'You can automatically create pages with these shortcodes using the setup wizard.', 'remember' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-setup' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Open Setup Wizard', 'remember' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
	</form>

		<!-- Social Media Platforms -->
		<div id="social-media" class="remember-settings-tab" style="display: none;">
			<h2><?php esc_html_e( 'Social Media Platforms', 'remember' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage social media platforms that members can link to their profiles.', 'remember' ); ?></p>
			
			<form method="post" action="" style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
				<input type="hidden" name="remember_settings_action" value="add_social_platform">
				<h3><?php esc_html_e( 'Add New Platform', 'remember' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><label for="new_platform_name"><?php esc_html_e( 'Platform Name', 'remember' ); ?></label></th>
						<td>
							<input type="text" id="new_platform_name" name="new_platform_name" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Enter the name of the social media platform (e.g., "Mastodon", "Bluesky").', 'remember' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Platform', 'remember' ); ?>">
				</p>
			</form>
			
			<form method="post" action="" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
				<input type="hidden" name="remember_settings_action" value="update_social_platforms">
				<h3><?php esc_html_e( 'Manage Platforms', 'remember' ); ?></h3>
				<?php if ( ! empty( $social_platforms ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 40%;"><?php esc_html_e( 'Platform Name', 'remember' ); ?></th>
								<th style="width: 15%;"><?php esc_html_e( 'Sort Order', 'remember' ); ?></th>
								<th style="width: 15%;"><?php esc_html_e( 'Active', 'remember' ); ?></th>
								<th style="width: 30%;"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $social_platforms as $platform ) : ?>
								<tr>
									<td>
										<input type="text" name="platforms[<?php echo esc_attr( $platform->platform_id ); ?>][name]" 
											value="<?php echo esc_attr( $platform->platform_name ); ?>" class="regular-text" required>
									</td>
									<td>
										<input type="number" name="platforms[<?php echo esc_attr( $platform->platform_id ); ?>][sort_order]" 
											value="<?php echo esc_attr( $platform->sort_order ); ?>" class="small-text" min="0">
									</td>
									<td>
										<label>
											<input type="checkbox" name="platforms[<?php echo esc_attr( $platform->platform_id ); ?>][is_active]" 
												value="1" <?php checked( $platform->is_active, 1 ); ?>>
											<?php esc_html_e( 'Active', 'remember' ); ?>
										</label>
									</td>
									<td>
										<button type="button" class="button button-small remember-delete-platform" 
											data-platform-id="<?php echo esc_attr( $platform->platform_id ); ?>"
											data-platform-name="<?php echo esc_attr( $platform->platform_name ); ?>">
											<?php esc_html_e( 'Delete', 'remember' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit">
						<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'remember' ); ?>">
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'No social media platforms configured.', 'remember' ); ?></p>
				<?php endif; ?>
			</form>
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
							<label for="remember_qb_redirect_uri"><?php esc_html_e( 'Redirect URI', 'remember' ); ?></label>
						</th>
						<td>
							<input type="text" id="remember_qb_redirect_uri" class="large-text" readonly
								value="<?php echo esc_attr( Remember_QuickBooks_OAuth::get_redirect_uri() ); ?>"
								onclick="this.select();" />
							<p class="description">
								<?php esc_html_e( 'Add this exact URL as a Redirect URI in your Intuit Developer app (Keys & credentials). It must match for sandbox and production.', 'remember' ); ?>
							</p>
						</td>
					</tr>
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
					<form method="post" action="" class="remember-qb-connect-form" style="margin-bottom: 1em;">
						<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
						<input type="hidden" name="remember_settings_action" value="start_qb_oauth" />
						<input type="hidden" name="qb_environment_oauth" id="remember_qb_oauth_environment_field" value="<?php echo esc_attr( isset( $qb_settings['environment'] ) ? $qb_settings['environment'] : 'sandbox' ); ?>" />
						<?php submit_button( __( 'Connect to QuickBooks', 'remember' ), 'primary large', 'submit', false ); ?>
					</form>
					<p class="description">
						<?php esc_html_e( 'Uses the Environment selected above (Sandbox or Production). Authorize the matching company type in Intuit—sandbox tokens only work with the sandbox API host.', 'remember' ); ?>
					</p>
					<script>
					(function() {
						var sel = document.getElementById( 'qb_environment' );
						var mirror = document.getElementById( 'remember_qb_oauth_environment_field' );
						if ( sel && mirror ) {
							mirror.value = sel.value;
							sel.addEventListener( 'change', function() { mirror.value = sel.value; } );
						}
					})();
					</script>
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
				
				// Handle sync products from QuickBooks
				if ( isset( $_POST['remember_settings_action'] ) && 'sync_qb_products' === $_POST['remember_settings_action'] && check_admin_referer( 'remember_settings_action', 'remember_settings_nonce' ) ) {
					$qb_items = Remember_QuickBooks_API::query_items();
					if ( ! is_wp_error( $qb_items ) ) {
						$product_model = new Remember_Product();
						foreach ( $qb_items as $item ) {
							$qb_id   = isset( $item['Id'] ) ? (string) $item['Id'] : '';
							$qb_name = isset( $item['Name'] ) ? (string) $item['Name'] : '';
							if ( '' === $qb_id ) {
								continue;
							}
							$existing = $product_model->get_by_qb_id( $qb_id );
							if ( $existing ) {
								$product_model->update(
									$existing->product_id,
									array(
										'quickbooks_product_name' => $item['Name'] ?? $existing->quickbooks_product_name,
										'last_sync_at'           => current_time( 'mysql' ),
										'updated_at'             => current_time( 'mysql' ),
									)
								);
								continue;
							}
							$by_name = $qb_name !== '' ? $product_model->get_by_name( $qb_name ) : null;
							if ( $by_name ) {
								$product_model->update(
									$by_name->product_id,
									array(
										'quickbooks_product_id'   => $qb_id,
										'quickbooks_product_name' => $item['Name'] ?? $by_name->quickbooks_product_name,
										'product_type'            => $item['Type'] ?? $by_name->product_type,
										'last_sync_at'            => current_time( 'mysql' ),
										'updated_at'              => current_time( 'mysql' ),
									)
								);
							} else {
								$product_model->insert(
									array(
										'product_name'            => $item['Name'] ?? '',
										'description'             => $item['Description'] ?? '',
										'quickbooks_product_id'   => $qb_id,
										'quickbooks_product_name' => $item['Name'] ?? '',
										'product_type'            => $item['Type'] ?? 'Service',
										'is_active'               => 1,
										'last_sync_at'            => current_time( 'mysql' ),
										'created_at'              => current_time( 'mysql' ),
										'updated_at'              => current_time( 'mysql' ),
									)
								);
							}
						}
						// Remove QuickBooks Category rows imported before we filtered them out.
						global $wpdb;
						$wpdb->query( "DELETE FROM {$wpdb->prefix}remember_products WHERE LOWER(product_type) = 'category'" );
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Products synced from QuickBooks successfully.', 'remember' ) . '</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to sync products from QuickBooks: ', 'remember' ) . esc_html( $qb_items->get_error_message() ) . '</p></div>';
					}
				}
				
				// Get all products (ensure rows exist for every event role + merchandise so the left column is not QB-only).
				$product_model = new Remember_Product();
				$product_model->ensure_mapping_rows_for_line_items();
				$all_products = $product_model->get_all(
					array(
						'orderby' => 'product_name',
						'order'   => 'ASC',
					)
				);
				require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-role.php';
				$mapping_role_model   = new Remember_Role();
				$mapping_role_names   = array();
				foreach ( $mapping_role_model->get_event_roles() as $r ) {
					$mapping_role_names[ $r->role_name ] = true;
				}
				global $wpdb;
				$mapping_merch_names = array();
				$merch_distinct      = $wpdb->get_col(
					"SELECT DISTINCT merchandise_name FROM {$wpdb->prefix}remember_event_merchandise
					WHERE merchandise_name IS NOT NULL AND merchandise_name != ''"
				);
				foreach ( $merch_distinct as $mn ) {
					$mapping_merch_names[ $mn ] = true;
				}
				$mapping_tier = function ( $name ) use ( $mapping_role_names, $mapping_merch_names ) {
					if ( isset( $mapping_role_names[ $name ] ) ) {
						return 0;
					}
					if ( isset( $mapping_merch_names[ $name ] ) ) {
						return 1;
					}
					return 2;
				};
				usort(
					$all_products,
					function ( $a, $b ) use ( $mapping_tier ) {
						$ta = $mapping_tier( $a->product_name );
						$tb = $mapping_tier( $b->product_name );
						if ( $ta !== $tb ) {
							return $ta - $tb;
						}
						return strcasecmp( $a->product_name, $b->product_name );
					}
				);
				$qb_products = array();
				if ( $is_connected ) {
					$qb_items_result = Remember_QuickBooks_API::query_items();
					if ( ! is_wp_error( $qb_items_result ) ) {
						$qb_products = $qb_items_result;
					}
				}
			?>
				<form id="remember-product-mapping-form" method="post" action="" class="screen-reader-text" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
					<?php wp_nonce_field( 'remember_settings_action', 'remember_settings_nonce' ); ?>
					<input type="hidden" name="remember_settings_action" value="save_product_mapping" />
				</form>
				<div class="remember-product-mapping" style="margin-top: 30px;">
					<h3><?php esc_html_e( 'Product Mapping', 'remember' ); ?></h3>
					<div class="notice notice-info inline" style="margin: 12px 0; padding: 10px 12px;">
						<p style="margin: 0 0 8px 0;"><strong><?php esc_html_e( 'Where billable line items are defined (not in QuickBooks)', 'remember' ); ?></strong></p>
						<ul style="margin: 0 0 0 1.2em; list-style: disc;">
							<li>
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: 1: opening link to Roles screen, 2: closing link */
										__( '%1$sRoles%2$s — create and name the event roles people apply for (e.g. “Weekend pass”). That name is what reMember matches to a row below.', 'remember' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=remember-roles' ) ) . '">',
										'</a>'
									)
								);
								?>
							</li>
							<li>
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: 1: opening link to Events screen, 2: closing link */
										__( '%1$sEvents%2$s — attach which roles are offered for each event. When an application is accepted, the invoice line uses the role name and the per-event role cost stored for that application.', 'remember' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=remember-events' ) ) . '">',
										'</a>'
									)
								);
								?>
							</li>
							<li><?php esc_html_e( 'Optional merchandise add-ons on applications use the merchandise name as the line item key (same mapping table).', 'remember' ); ?></li>
						</ul>
						<p style="margin: 8px 0 0 0;" class="description">
							<?php esc_html_e( 'This screen does not define those roles—it only links a reMember line item name (left) to a QuickBooks Item (right) so invoices use the correct QB product/service.', 'remember' ); ?>
						</p>
					</div>
					<p class="description">
						<?php esc_html_e( 'The right-hand dropdown lists QuickBooks Items (service, inventory, etc.). Category folders from QuickBooks are excluded.', 'remember' ); ?>
					</p>
					
					<?php if ( $is_connected ) : ?>
						<p>
							<button type="button" class="button button-secondary" onclick="document.getElementById('sync-qb-products-form').submit();">
								<?php esc_html_e( 'Sync Products from QuickBooks', 'remember' ); ?>
							</button>
							<?php if ( empty( $qb_products ) ) : ?>
								<span class="description" style="display: block; margin-top: 8px;">
									<?php esc_html_e( 'Optional: loads QB items into the dropdown and can create rows in the table below that match QB names. You still map each row to the role or merchandise name you use in reMember.', 'remember' ); ?>
								</span>
							<?php endif; ?>
						</p>
						<?php if ( ! empty( $qb_products ) ) : ?>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Optional: re-imports QB items into the dropdown and updates synced rows. It does not replace defining roles under Roles / Events.', 'remember' ); ?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
					
					<?php if ( ! empty( $all_products ) ) : ?>
						<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
							<thead>
								<tr>
									<th style="width: 180px;"><?php esc_html_e( 'reMember line item', 'remember' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Used for', 'remember' ); ?></th>
									<th><?php esc_html_e( 'QuickBooks item', 'remember' ); ?></th>
									<th style="width: 120px;"><?php esc_html_e( 'QB item ID', 'remember' ); ?></th>
									<th style="width: 100px;"><?php esc_html_e( 'Last Synced', 'remember' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $all_products as $product ) : ?>
									<?php
									$pname = isset( $product->product_name ) ? (string) $product->product_name : '';
									if ( isset( $mapping_role_names[ $pname ] ) ) {
										$used_for = __( 'Event role', 'remember' );
									} elseif ( isset( $mapping_merch_names[ $pname ] ) ) {
										$used_for = __( 'Merchandise', 'remember' );
									} else {
										$used_for = __( 'QuickBooks only', 'remember' );
									}
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $product->product_name ); ?></strong>
											<?php if ( ! empty( $product->description ) ) : ?>
												<br><span class="description"><?php echo esc_html( $product->description ); ?></span>
											<?php endif; ?>
										</td>
										<td><span class="description"><?php echo esc_html( $used_for ); ?></span></td>
										<td>
											<select name="product_mappings[<?php echo esc_attr( $product->product_id ); ?>]" class="regular-text" form="remember-product-mapping-form">
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
						
						<p class="submit" style="margin-bottom: 1.5em;">
							<?php submit_button( __( 'Save Product Mappings', 'remember' ), 'primary', 'submit', false, array( 'form' => 'remember-product-mapping-form' ) ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No products found. Products will be created automatically when you sync from QuickBooks or when invoices are created.', 'remember' ); ?>
						</p>
					<?php endif; ?>
				</div>
				
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

		<!-- Logging Settings -->
		<div id="logging" class="remember-settings-tab" style="display: none;">
			<h3><?php esc_html_e( 'Logging Settings', 'remember' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Configure logging for the reMember plugin. Logs are written to the WordPress debug log file.', 'remember' ); ?>
			</p>
			
			<?php
			$current_log_level = isset( $options['log_level'] ) ? $options['log_level'] : 'ERROR';
			$log_file_path = WP_CONTENT_DIR . '/debug.log';
			$log_file_exists = file_exists( $log_file_path );
			$log_file_size = $log_file_exists ? size_format( filesize( $log_file_path ) ) : '0 B';
			$wp_debug_log_enabled = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
			?>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="log_level"><?php esc_html_e( 'Log Level', 'remember' ); ?></label>
					</th>
					<td>
						<select id="log_level" name="log_level" form="remember-main-settings">
							<option value="NONE" <?php selected( $current_log_level, 'NONE' ); ?>><?php esc_html_e( 'None (disable logging)', 'remember' ); ?></option>
							<option value="ERROR" <?php selected( $current_log_level, 'ERROR' ); ?>><?php esc_html_e( 'Error only', 'remember' ); ?></option>
							<option value="WARNING" <?php selected( $current_log_level, 'WARNING' ); ?>><?php esc_html_e( 'Warning and above', 'remember' ); ?></option>
							<option value="INFO" <?php selected( $current_log_level, 'INFO' ); ?>><?php esc_html_e( 'Info and above', 'remember' ); ?></option>
							<option value="DEBUG" <?php selected( $current_log_level, 'DEBUG' ); ?>><?php esc_html_e( 'Debug (all messages)', 'remember' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the minimum log level to record. Lower levels include higher levels (e.g., WARNING includes ERROR).', 'remember' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log File Information', 'remember' ); ?></th>
					<td>
						<p>
							<strong><?php esc_html_e( 'Log File Path:', 'remember' ); ?></strong><br>
							<code><?php echo esc_html( $log_file_path ); ?></code>
						</p>
						<p>
							<strong><?php esc_html_e( 'Status:', 'remember' ); ?></strong><br>
							<?php if ( $log_file_exists ) : ?>
								<span style="color: green;"><?php esc_html_e( 'File exists', 'remember' ); ?></span> (<?php echo esc_html( $log_file_size ); ?>)
							<?php else : ?>
								<span style="color: orange;"><?php esc_html_e( 'File does not exist yet', 'remember' ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( ! $wp_debug_log_enabled ) : ?>
							<p style="color: red;">
								<strong><?php esc_html_e( 'Warning:', 'remember' ); ?></strong> 
								<?php esc_html_e( 'WP_DEBUG_LOG is not enabled in wp-config.php. Logging will not work until this is enabled.', 'remember' ); ?>
							</p>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Logs are written to the WordPress debug log file. Make sure WP_DEBUG_LOG is enabled in your wp-config.php file.', 'remember' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit remember-settings-main-save" style="margin-top: 1.75em; padding-top: 1em; border-top: 1px solid #c3c4c7;">
			<?php submit_button( __( 'Save Changes', 'remember' ), 'primary', 'submit', false, array( 'form' => 'remember-main-settings' ) ); ?>
		</p>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Handle delete platform button clicks
	$('.remember-delete-platform').on('click', function(e) {
		e.preventDefault();
		var $button = $(this);
		var platformId = $button.data('platform-id');
		var platformName = $button.data('platform-name');
		
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the platform "%s"?', 'remember' ) ); ?>'.replace('%s', platformName))) {
			return;
		}
		
		// Create a form and submit it
		var $form = $('<form>', {
			method: 'post',
			action: ''
		});
		
		$form.append($('<input>', {
			type: 'hidden',
			name: 'remember_settings_action',
			value: 'delete_social_platform'
		}));
		
		$form.append($('<input>', {
			type: 'hidden',
			name: 'platform_id',
			value: platformId
		}));
		
		$form.append($('<input>', {
			type: 'hidden',
			name: 'remember_settings_nonce',
			value: '<?php echo esc_js( wp_create_nonce( 'remember_settings_action' ) ); ?>'
		}));
		
		$('body').append($form);
		$form.submit();
	});
});
</script>
