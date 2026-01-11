<?php
/**
 * Locations view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-location.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-countries.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-image-uploader.php';

Remember_Logger::debug( 'Locations page loaded' );

$location_model = new Remember_Location();
$event_model = new Remember_Event();

// Get max image dimensions from settings
$options = get_option( 'remember_options', array() );
$max_image_size = isset( $options['photo_max_dimensions'] ) ? absint( $options['photo_max_dimensions'] ) : 800;

// Handle form submissions
if ( isset( $_POST['remember_location_action'] ) && check_admin_referer( 'remember_location_action', 'remember_location_nonce' ) ) {
	$action = sanitize_text_field( $_POST['remember_location_action'] );
	
	if ( 'add' === $action ) {
		$data = array(
			'location_name'  => sanitize_text_field( $_POST['location_name'] ),
			'address_street' => sanitize_text_field( $_POST['address_street'] ),
			'address_city'   => sanitize_text_field( $_POST['address_city'] ),
			'address_state'  => sanitize_text_field( $_POST['address_state'] ),
			'address_postal' => sanitize_text_field( $_POST['address_postal'] ),
			'address_country' => sanitize_text_field( $_POST['address_country'] ),
			'details'        => sanitize_textarea_field( $_POST['details'] ),
			'is_active'      => isset( $_POST['is_active'] ) ? 1 : 0,
		);
		
		// Handle logo upload
		if ( ! empty( $_FILES['logo_file']['name'] ) ) {
			$upload_result = Remember_Image_Uploader::upload_square_image( $_FILES['logo_file'], $max_image_size );
			if ( ! is_wp_error( $upload_result ) ) {
				$data['logo_url'] = $upload_result['url'];
			} else {
				Remember_Logger::error( 'Logo upload failed', array( 'error' => $upload_result->get_error_message() ) );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $upload_result->get_error_message() ) . '</p></div>';
			}
		}
		
		$location_id = $location_model->create( $data );
		if ( $location_id ) {
			Remember_Logger::info( 'Location created', array( 'location_id' => $location_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Location created successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to create location' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create location.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'edit' === $action && isset( $_POST['location_id'] ) ) {
		$location_id = absint( $_POST['location_id'] );
		$location = $location_model->get( $location_id );
		
		$data = array(
			'location_name'  => sanitize_text_field( $_POST['location_name'] ),
			'address_street' => sanitize_text_field( $_POST['address_street'] ),
			'address_city'   => sanitize_text_field( $_POST['address_city'] ),
			'address_state'  => sanitize_text_field( $_POST['address_state'] ),
			'address_postal' => sanitize_text_field( $_POST['address_postal'] ),
			'address_country' => sanitize_text_field( $_POST['address_country'] ),
			'details'        => sanitize_textarea_field( $_POST['details'] ),
			'is_active'      => isset( $_POST['is_active'] ) ? 1 : 0,
		);
		
		// Handle logo upload
		if ( ! empty( $_FILES['logo_file']['name'] ) ) {
			// Delete old logo if exists
			if ( $location && $location->logo_url ) {
				Remember_Image_Uploader::delete_image( $location->logo_url );
			}
			
			$upload_result = Remember_Image_Uploader::upload_square_image( $_FILES['logo_file'], $max_image_size );
			if ( ! is_wp_error( $upload_result ) ) {
				$data['logo_url'] = $upload_result['url'];
			} else {
				Remember_Logger::error( 'Logo upload failed', array( 'error' => $upload_result->get_error_message() ) );
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $upload_result->get_error_message() ) . '</p></div>';
			}
		}
		
		// Handle logo deletion
		if ( isset( $_POST['delete_logo'] ) && $location && $location->logo_url ) {
			Remember_Image_Uploader::delete_image( $location->logo_url );
			$data['logo_url'] = null;
		}
		
		$result = $location_model->update( $location_id, $data );
		if ( $result !== false ) {
			Remember_Logger::info( 'Location updated', array( 'location_id' => $location_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Location updated successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to update location', array( 'location_id' => $location_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to update location.', 'remember' ) . '</p></div>';
		}
	} elseif ( 'delete' === $action && isset( $_GET['delete'] ) ) {
		$location_id = absint( $_GET['delete'] );
		$location = $location_model->get( $location_id );
		
		// Delete logo if exists
		if ( $location && $location->logo_url ) {
			Remember_Image_Uploader::delete_image( $location->logo_url );
		}
		
		$result = $location_model->delete( $location_id );
		if ( $result !== false ) {
			Remember_Logger::info( 'Location deleted', array( 'location_id' => $location_id ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Location deleted successfully.', 'remember' ) . '</p></div>';
		} else {
			Remember_Logger::error( 'Failed to delete location', array( 'location_id' => $location_id ) );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete location.', 'remember' ) . '</p></div>';
		}
	}
}

// Get filter parameters
$filter_city = isset( $_GET['filter_city'] ) ? sanitize_text_field( $_GET['filter_city'] ) : '';
$filter_state = isset( $_GET['filter_state'] ) ? sanitize_text_field( $_GET['filter_state'] ) : '';

// Get locations
if ( ! empty( $filter_city ) ) {
	$locations = $location_model->get_by_city( $filter_city );
} elseif ( ! empty( $filter_state ) ) {
	$locations = $location_model->get_by_state( $filter_state );
} else {
	$locations = $location_model->get_all();
}

// Get unique cities and states for filters
$cities = $location_model->get_cities();
$states = $location_model->get_states();

// Check if viewing detail or editing
$viewing_location = null;
$editing_location = null;
if ( isset( $_GET['view'] ) ) {
	$view_id = absint( $_GET['view'] );
	$viewing_location = $location_model->get( $view_id );
	if ( $viewing_location ) {
		// Get historical events for this location
		$location_events = $event_model->get_historical_by_location( $view_id );
	}
} elseif ( isset( $_GET['edit'] ) ) {
	$edit_id = absint( $_GET['edit'] );
	$editing_location = $location_model->get( $edit_id );
}

// Helper function to format address
function remember_format_address( $location ) {
	$parts = array();
	if ( ! empty( $location->address_street ) ) {
		$parts[] = $location->address_street;
	}
	if ( ! empty( $location->address_city ) || ! empty( $location->address_state ) || ! empty( $location->address_postal ) ) {
		$city_parts = array();
		if ( ! empty( $location->address_city ) ) {
			$city_parts[] = $location->address_city;
		}
		if ( ! empty( $location->address_state ) ) {
			$city_parts[] = $location->address_state;
		}
		if ( ! empty( $location->address_postal ) ) {
			$city_parts[] = $location->address_postal;
		}
		$parts[] = implode( ', ', $city_parts );
	}
	if ( ! empty( $location->address_country ) && 'US' !== $location->address_country ) {
		$parts[] = Remember_Countries::get_country_name( $location->address_country );
	}
	return implode( '<br>', $parts );
}
?>

<div class="wrap remember-locations">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<?php if ( ! $viewing_location && ! $editing_location ) : ?>
		<button type="button" class="page-title-action" onclick="document.getElementById('remember-add-location').style.display='block'; this.style.display='none';"><?php esc_html_e( 'Add New', 'remember' ); ?></button>
	<?php elseif ( $viewing_location ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations&edit=' . $viewing_location->location_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit', 'remember' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to List', 'remember' ); ?></a>
	<?php else : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
	<?php endif; ?>
	
	<hr class="wp-header-end">

	<?php if ( $viewing_location ) : ?>
		<?php include 'location-detail.php'; ?>
	<?php elseif ( $editing_location ) : ?>
		<!-- Edit Form -->
		<div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Edit Location', 'remember' ); ?></h2>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_location_action', 'remember_location_nonce' ); ?>
				<input type="hidden" name="remember_location_action" value="edit">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing_location->location_id ); ?>">
				
				<table class="form-table">
					<tr>
						<th><label for="location_name"><?php esc_html_e( 'Location Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="text" id="location_name" name="location_name" class="regular-text" value="<?php echo esc_attr( $editing_location->location_name ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="logo_file"><?php esc_html_e( 'Logo', 'remember' ); ?></label></th>
						<td>
							<?php if ( ! empty( $editing_location->logo_url ) ) : ?>
								<p>
									<img src="<?php echo esc_url( $editing_location->logo_url ); ?>" alt="<?php echo esc_attr( $editing_location->location_name ); ?>" style="max-width: 150px; height: auto; border: 1px solid #ddd; padding: 5px;">
								</p>
								<label><input type="checkbox" name="delete_logo" value="1"> <?php esc_html_e( 'Delete current logo', 'remember' ); ?></label>
								<p class="description"><?php esc_html_e( 'Upload a new logo to replace the current one.', 'remember' ); ?></p>
							<?php endif; ?>
							<input type="file" id="logo_file" name="logo_file" accept="image/*">
							<p class="description"><?php echo esc_html( sprintf( __( 'Square image, max %dpx. WordPress will resize if needed.', 'remember' ), $max_image_size ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="address_street"><?php esc_html_e( 'Street Address', 'remember' ); ?></label></th>
						<td><input type="text" id="address_street" name="address_street" class="regular-text" value="<?php echo esc_attr( $editing_location->address_street ); ?>"></td>
					</tr>
					<tr>
						<th><label for="address_city"><?php esc_html_e( 'City', 'remember' ); ?></label></th>
						<td><input type="text" id="address_city" name="address_city" class="regular-text" value="<?php echo esc_attr( $editing_location->address_city ); ?>"></td>
					</tr>
					<tr>
						<th><label for="address_state"><?php esc_html_e( 'State/Province', 'remember' ); ?></label></th>
						<td><input type="text" id="address_state" name="address_state" class="regular-text" value="<?php echo esc_attr( $editing_location->address_state ); ?>"></td>
					</tr>
					<tr>
						<th><label for="address_postal"><?php esc_html_e( 'Postal/Zip Code', 'remember' ); ?></label></th>
						<td><input type="text" id="address_postal" name="address_postal" class="regular-text" value="<?php echo esc_attr( $editing_location->address_postal ); ?>"></td>
					</tr>
					<tr>
						<th><label for="address_country"><?php esc_html_e( 'Country', 'remember' ); ?></label></th>
						<td><?php echo Remember_Countries::dropdown( 'address_country', ! empty( $editing_location->address_country ) ? $editing_location->address_country : 'US' ); ?></td>
					</tr>
					<tr>
						<th><label for="details"><?php esc_html_e( 'Details', 'remember' ); ?></label></th>
						<td><textarea id="details" name="details" class="large-text" rows="5"><?php echo esc_textarea( $editing_location->details ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="is_active"><?php esc_html_e( 'Active', 'remember' ); ?></label></th>
						<td><label><input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $editing_location->is_active, 1 ); ?>> <?php esc_html_e( 'Location is active', 'remember' ); ?></label></td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update Location', 'remember' ); ?>">
				</p>
			</form>
		</div>
	<?php else : ?>
		<!-- Filters -->
		<div class="remember-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<form method="get" action="">
				<input type="hidden" name="page" value="remember-locations">
				
				<label for="filter_city"><?php esc_html_e( 'Filter by City:', 'remember' ); ?></label>
				<select id="filter_city" name="filter_city" style="margin-right: 20px;">
					<option value=""><?php esc_html_e( 'All Cities', 'remember' ); ?></option>
					<?php foreach ( $cities as $city ) : ?>
						<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $filter_city, $city ); ?>>
							<?php echo esc_html( $city ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter_state"><?php esc_html_e( 'Filter by State:', 'remember' ); ?></label>
				<select id="filter_state" name="filter_state" style="margin-right: 20px;">
					<option value=""><?php esc_html_e( 'All States', 'remember' ); ?></option>
					<?php foreach ( $states as $state ) : ?>
						<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $filter_state, $state ); ?>>
							<?php echo esc_html( $state ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'remember' ); ?>">
				<?php if ( ! empty( $filter_city ) || ! empty( $filter_state ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations' ) ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'remember' ); ?></a>
				<?php endif; ?>
			</form>
		</div>

		<!-- Add Form -->
		<div id="remember-add-location" style="display:none; margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
			<h2><?php esc_html_e( 'Add New Location', 'remember' ); ?></h2>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_location_action', 'remember_location_nonce' ); ?>
				<input type="hidden" name="remember_location_action" value="add">
				
				<table class="form-table">
					<tr>
						<th><label for="location_name"><?php esc_html_e( 'Location Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
						<td><input type="text" id="location_name" name="location_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="logo_file"><?php esc_html_e( 'Logo', 'remember' ); ?></label></th>
						<td>
							<input type="file" id="logo_file" name="logo_file" accept="image/*">
							<p class="description"><?php echo esc_html( sprintf( __( 'Square image, max %dpx. WordPress will resize if needed.', 'remember' ), $max_image_size ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="address_street"><?php esc_html_e( 'Street Address', 'remember' ); ?></label></th>
						<td><input type="text" id="address_street" name="address_street" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="address_city"><?php esc_html_e( 'City', 'remember' ); ?></label></th>
						<td><input type="text" id="address_city" name="address_city" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="address_state"><?php esc_html_e( 'State/Province', 'remember' ); ?></label></th>
						<td><input type="text" id="address_state" name="address_state" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="address_postal"><?php esc_html_e( 'Postal/Zip Code', 'remember' ); ?></label></th>
						<td><input type="text" id="address_postal" name="address_postal" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="address_country"><?php esc_html_e( 'Country', 'remember' ); ?></label></th>
						<td><?php echo Remember_Countries::dropdown( 'address_country', 'US' ); ?></td>
					</tr>
					<tr>
						<th><label for="details"><?php esc_html_e( 'Details', 'remember' ); ?></label></th>
						<td><textarea id="details" name="details" class="large-text" rows="5"></textarea></td>
					</tr>
					<tr>
						<th><label for="is_active"><?php esc_html_e( 'Active', 'remember' ); ?></label></th>
						<td><label><input type="checkbox" id="is_active" name="is_active" value="1" checked> <?php esc_html_e( 'Location is active', 'remember' ); ?></label></td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Add Location', 'remember' ); ?>">
					<button type="button" class="button" onclick="document.getElementById('remember-add-location').style.display='none'; document.querySelector('.page-title-action').style.display='inline-block';"><?php esc_html_e( 'Cancel', 'remember' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Locations List -->
		<?php if ( ! empty( $locations ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th class="column-logo"><?php esc_html_e( 'Logo', 'remember' ); ?></th>
					<th class="column-name"><?php esc_html_e( 'Name', 'remember' ); ?></th>
					<th class="column-address"><?php esc_html_e( 'Address', 'remember' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'remember' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'remember' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $locations as $location ) : ?>
					<tr>
						<td class="column-logo">
							<?php if ( ! empty( $location->logo_url ) ) : ?>
								<img src="<?php echo esc_url( $location->logo_url ); ?>" alt="<?php echo esc_attr( $location->location_name ); ?>" style="max-width: 50px; height: auto;">
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
						<td class="column-name"><strong><?php echo esc_html( $location->location_name ); ?></strong></td>
						<td class="column-address"><?php echo remember_format_address( $location ); ?></td>
						<td class="column-status">
							<?php if ( $location->is_active ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <?php esc_html_e( 'Active', 'remember' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> <?php esc_html_e( 'Inactive', 'remember' ); ?>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations&view=' . $location->location_id ) ); ?>"><?php esc_html_e( 'View', 'remember' ); ?></a> |
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-locations&edit=' . $location->location_id ) ); ?>"><?php esc_html_e( 'Edit', 'remember' ); ?></a> |
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-locations&delete=' . $location->location_id ), 'remember_location_action', 'remember_location_nonce' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this location?', 'remember' ); ?>');"><?php esc_html_e( 'Delete', 'remember' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No locations found. Add your first location above.', 'remember' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
