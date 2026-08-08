<?php
/**
 * Member edit form (included from member-detail.php)
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Variables are set in members.php: $view_member, $view_user, $view_profile, etc.
// Get lookup data for form fields
global $wpdb;

// Get social media platforms
$social_media_platforms = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_social_media_platforms WHERE is_active = 1 ORDER BY sort_order ASC, platform_name ASC"
);

// Get dietary restrictions
$dietary_restrictions = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_dietary_restrictions WHERE is_active = 1 ORDER BY sort_order ASC, restriction_name ASC"
);

// Get medical accommodations
$medical_accommodations = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_medical_accommodations WHERE is_active = 1 ORDER BY sort_order ASC, accommodation_name ASC"
);

// Get allergies
$allergies = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_allergies WHERE is_active = 1 ORDER BY sort_order ASC, allergy_name ASC"
);

// Get selected IDs for multi-selects (from junction tables)
$selected_dietary_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT restriction_id FROM {$wpdb->prefix}remember_member_dietary_restrictions WHERE member_id = %d",
	$view_member_id
) );

$selected_medical_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT accommodation_id FROM {$wpdb->prefix}remember_member_medical_accommodations WHERE member_id = %d",
	$view_member_id
) );

$selected_allergy_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT allergy_id FROM {$wpdb->prefix}remember_member_allergies WHERE member_id = %d",
	$view_member_id
) );

// Load timezone utility
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-timezone.php';

// Get timezone from WP user meta (not from member_profiles)
$selected_timezone = get_user_meta( $view_user->ID, 'timezone_string', true );
if ( empty( $selected_timezone ) ) {
	$selected_timezone = 'America/Los_Angeles'; // Default
	// Auto-assign default timezone
	update_user_meta( $view_user->ID, 'timezone_string', $selected_timezone );
}

// Load countries helper
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-countries.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-image-uploader.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-import-export.php';

list( $legal_first_resolved, $legal_last_resolved ) = Remember_Import_Export::member_resolve_legal_name_parts( $view_profile, (int) $view_member_id );

// Get max image dimensions from settings
$options = get_option( 'remember_options', array() );
$max_image_size = isset( $options['photo_max_dimensions'] ) ? absint( $options['photo_max_dimensions'] ) : 800;
$photo_max_bytes = isset( $options['photo_max_size'] ) ? absint( $options['photo_max_size'] ) : 2097152;
if ( $max_image_size < 1 ) {
	$max_image_size = 800;
}
if ( $photo_max_bytes < 1 ) {
	$photo_max_bytes = 2097152;
}
?>

<form method="post" action="" enctype="multipart/form-data" class="remember-member-edit-form" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
	<?php wp_nonce_field( 'remember_member_action', 'remember_member_nonce' ); ?>
	<input type="hidden" name="remember_member_action" value="update_profile">
	<input type="hidden" name="member_id" value="<?php echo esc_attr( $view_member_id ); ?>">
	
	<h2><?php esc_html_e( 'Edit Member Profile', 'remember' ); ?></h2>
	
	<!-- Profile Photo -->
	<h3><?php esc_html_e( 'Profile Photo', 'remember' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="photo_file"><?php esc_html_e( 'Photo', 'remember' ); ?></label></th>
			<td>
				<div class="remember-profile-photo-edit" data-output-size="<?php echo esc_attr( (string) $max_image_size ); ?>">
					<div class="remember-profile-photo-current"<?php echo ( ! empty( $view_member->photo_url ) ) ? '' : ' hidden'; ?>>
						<?php if ( ! empty( $view_member->photo_url ) ) : ?>
							<div class="remember-profile-photo-preview">
								<img src="<?php echo esc_url( $view_member->photo_url ); ?>" alt="<?php echo esc_attr( $view_user->display_name ); ?>">
							</div>
							<label class="remember-checkbox-label remember-profile-photo-delete">
								<input type="checkbox" name="delete_photo" value="1" id="delete_photo">
								<span><?php esc_html_e( 'Delete current photo', 'remember' ); ?></span>
							</label>
							<p class="description"><?php esc_html_e( 'Upload a new photo to replace the current one.', 'remember' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="remember-profile-photo-cropper" hidden>
						<div class="remember-profile-photo-cropper-viewport" aria-label="<?php esc_attr_e( 'Photo framing preview', 'remember' ); ?>">
							<img src="" alt="" class="remember-profile-photo-cropper-image" draggable="false">
						</div>
						<div class="remember-profile-photo-cropper-controls">
							<button type="button" class="button remember-photo-zoom-out" aria-label="<?php esc_attr_e( 'Zoom out', 'remember' ); ?>">−</button>
							<input type="range" class="remember-photo-zoom-range" min="1" max="3" step="0.01" value="1" aria-label="<?php esc_attr_e( 'Zoom', 'remember' ); ?>">
							<button type="button" class="button remember-photo-zoom-in" aria-label="<?php esc_attr_e( 'Zoom in', 'remember' ); ?>">+</button>
						</div>
						<p class="description"><?php esc_html_e( 'Drag to recenter. Use zoom to frame the photo inside the circle. Framing is applied when you save.', 'remember' ); ?></p>
						<button type="button" class="button remember-photo-clear">
							<?php esc_html_e( 'Clear selected photo', 'remember' ); ?>
						</button>
					</div>

					<input type="file" id="photo_file" name="photo_file" accept="image/jpeg,image/png,image/gif">
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: max px, 2: max MB */
								__( 'Square crop from your framing, max %1$dpx. Maximum file size %2$d MB. JPEG, PNG, or GIF.', 'remember' ),
								$max_image_size,
								(int) max( 1, round( $photo_max_bytes / 1024 / 1024 ) )
							)
						);
						?>
					</p>
				</div>
			</td>
		</tr>
	</table>
	
	<!-- WordPress User Information -->
	<h3><?php esc_html_e( 'WordPress User Information', 'remember' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="nickname"><?php esc_html_e( 'Nickname', 'remember' ); ?> <span class="description"><?php esc_html_e( '(required)', 'remember' ); ?></span></label></th>
			<td>
				<input type="text" id="nickname" name="nickname" class="regular-text" value="<?php echo esc_attr( get_user_meta( $view_user->ID, 'nickname', true ) ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="display_name"><?php esc_html_e( 'Display name publicly as', 'remember' ); ?></label></th>
			<td>
				<?php
				// Get first and last name from WordPress user meta (not legal names)
				$wp_first_name = get_user_meta( $view_user->ID, 'first_name', true );
				$wp_last_name = get_user_meta( $view_user->ID, 'last_name', true );
				$nickname = get_user_meta( $view_user->ID, 'nickname', true );
				
				// Generate display name options like WordPress does
				$public_display = array();
				if ( ! empty( $nickname ) ) {
					$public_display['display_nickname'] = $nickname;
				}
				$public_display['display_username'] = $view_user->user_login;
				
				if ( ! empty( $wp_first_name ) ) {
					$public_display['display_firstname'] = $wp_first_name;
				}
				
				if ( ! empty( $wp_last_name ) ) {
					$public_display['display_lastname'] = $wp_last_name;
				}
				
				if ( ! empty( $wp_first_name ) && ! empty( $wp_last_name ) ) {
					$public_display['display_firstlast'] = $wp_first_name . ' ' . $wp_last_name;
					$public_display['display_lastfirst'] = $wp_last_name . ' ' . $wp_first_name;
				}
				
				// Add current display name if not already in the list
				if ( ! in_array( $view_user->display_name, $public_display, true ) ) {
					$public_display = array( 'display_displayname' => $view_user->display_name ) + $public_display;
				}
				
				// Clean up the array
				$public_display = array_map( 'trim', $public_display );
				$public_display = array_unique( $public_display );
				?>
				<select name="display_name" id="display_name">
					<?php foreach ( $public_display as $id => $item ) : ?>
						<option <?php selected( $view_user->display_name, $item ); ?>><?php echo esc_html( $item ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
	
	<!-- Profile Information -->
	<h3><?php esc_html_e( 'Profile Information', 'remember' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="legal_first_name"><?php esc_html_e( 'Legal First Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="legal_first_name" name="legal_first_name" class="regular-text" value="<?php echo esc_attr( $legal_first_resolved ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="legal_last_name"><?php esc_html_e( 'Legal Last Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="legal_last_name" name="legal_last_name" class="regular-text" value="<?php echo esc_attr( $legal_last_resolved ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_street"><?php esc_html_e( 'Street Address', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="address_street" name="address_street" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_street : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_city"><?php esc_html_e( 'City', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="address_city" name="address_city" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_city : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_state"><?php esc_html_e( 'State/Province', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="address_state" name="address_state" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_state : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_postal"><?php esc_html_e( 'Postal Code', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="address_postal" name="address_postal" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_postal : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_country"><?php esc_html_e( 'Country', 'remember' ); ?></label></th>
			<td>
				<?php 
				$selected_country = $view_profile && $view_profile->address_country ? $view_profile->address_country : 'US';
				echo Remember_Countries::dropdown( 'address_country', $selected_country, array( 'id' => 'address_country', 'class' => 'regular-text' ) );
				?>
			</td>
		</tr>
		<tr>
			<th><label for="cell_phone"><?php esc_html_e( 'Cell Phone', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="cell_phone" name="cell_phone" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->cell_phone : '' ); ?>" placeholder="<?php esc_attr_e( '+18055551212', 'remember' ); ?>" required>
				<p class="description"><?php esc_html_e( 'Include a leading + and country code. Examples: +18055551212 (USA/Canada), +447700900123 (UK).', 'remember' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="timezone_string"><?php esc_html_e( 'Time Zone', 'remember' ); ?> <span class="description"><?php esc_html_e( '(required)', 'remember' ); ?></span></label></th>
			<td>
				<?php echo Remember_Timezone::dropdown( $selected_timezone, 'timezone_string', 'timezone_string', true ); ?>
				<p class="description"><?php esc_html_e( 'Your timezone is used to display scheduled times in your local time.', 'remember' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="im_type"><?php esc_html_e( 'Instant Messenger', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<select id="im_type" name="im_type" style="width: 150px;" required>
					<option value="telegram" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'remember' ); ?></option>
					<option value="discord" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'discord' ); ?>><?php esc_html_e( 'Discord', 'remember' ); ?></option>
					<option value="signal" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'signal' ); ?>><?php esc_html_e( 'Signal', 'remember' ); ?></option>
					<option value="whatsapp" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'remember' ); ?></option>
					<option value="other" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'other' ); ?>><?php esc_html_e( 'Other', 'remember' ); ?></option>
				</select>
				<input type="text" id="im_handle" name="im_handle" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->im_handle : '' ); ?>" placeholder="<?php esc_attr_e( 'Handle', 'remember' ); ?>" style="margin-left: 10px;" required>
			</td>
		</tr>
		<?php
		require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-clothing-sizes.php';
		$shirt_size = $view_profile && isset( $view_profile->shirt_size ) ? $view_profile->shirt_size : '';
		$pants_size = $view_profile && isset( $view_profile->pants_size ) ? $view_profile->pants_size : '';
		$shoe_size  = $view_profile && isset( $view_profile->shoe_size ) ? $view_profile->shoe_size : '';
		?>
		<tr>
			<th><label for="shirt_size"><?php esc_html_e( 'Shirt Size', 'remember' ); ?></label></th>
			<td><?php echo Remember_Clothing_Sizes::dropdown( 'shirt', $shirt_size, 'shirt_size', 'shirt_size' ); ?></td>
		</tr>
		<tr>
			<th><label for="pants_size"><?php esc_html_e( 'Pants Size', 'remember' ); ?></label></th>
			<td><?php echo Remember_Clothing_Sizes::dropdown( 'pants', $pants_size, 'pants_size', 'pants_size' ); ?></td>
		</tr>
		<tr>
			<th><label for="shoe_size"><?php esc_html_e( 'Shoe Size', 'remember' ); ?></label></th>
			<td>
				<?php echo Remember_Clothing_Sizes::dropdown( 'shoe', $shoe_size, 'shoe_size', 'shoe_size' ); ?>
				<p class="description"><?php esc_html_e( 'US men\'s sizes. Shirt/pants: S-6XL. Shoes: 6-15.', 'remember' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="interests"><?php esc_html_e( 'Interests', 'remember' ); ?></label></th>
			<td>
				<?php
				$interests_value = $view_profile && isset( $view_profile->interests ) ? $view_profile->interests : '';
				wp_editor(
					$interests_value,
					'interests',
					array(
						'textarea_name' => 'interests',
						'textarea_rows' => 6,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					)
				);
				?>
			</td>
		</tr>
	</table>
	
	<!-- Emergency Contact -->
	<h3><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="emergency_contact_first"><?php esc_html_e( 'First Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="emergency_contact_first" name="emergency_contact_first" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->emergency_contact_first : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="emergency_contact_last"><?php esc_html_e( 'Last Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="emergency_contact_last" name="emergency_contact_last" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->emergency_contact_last : '' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="emergency_contact_phone"><?php esc_html_e( 'Phone', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->emergency_contact_phone : '' ); ?>" placeholder="<?php esc_attr_e( '+18055551212', 'remember' ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="emergency_contact_relationship"><?php esc_html_e( 'Relationship', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->emergency_contact_relationship : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Spouse, Parent, Friend', 'remember' ); ?>" required>
			</td>
		</tr>
	</table>
	
	<!-- Social Media -->
	<?php if ( ! empty( $social_media_platforms ) ) : ?>
		<h3><?php esc_html_e( 'Social Media', 'remember' ); ?></h3>
		<table class="form-table">
			<?php foreach ( $social_media_platforms as $platform ) : 
				$current_handle = '';
				foreach ( $view_social_media as $social ) {
					if ( $social->platform_id == $platform->platform_id ) {
						$current_handle = $social->handle;
						break;
					}
				}
			?>
				<tr>
					<th><label for="social_media_<?php echo esc_attr( $platform->platform_id ); ?>"><?php echo esc_html( $platform->platform_name ); ?></label></th>
					<td>
						<input type="text" id="social_media_<?php echo esc_attr( $platform->platform_id ); ?>" name="social_media[<?php echo esc_attr( $platform->platform_id ); ?>]" class="regular-text" value="<?php echo esc_attr( $current_handle ); ?>" placeholder="<?php esc_attr_e( 'Handle/Username', 'remember' ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
	
	<!-- Dietary Restrictions -->
	<?php if ( ! empty( $dietary_restrictions ) ) : ?>
		<h3><?php esc_html_e( 'Dietary Restrictions', 'remember' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Select Restrictions', 'remember' ); ?></th>
				<td>
					<fieldset style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
						<?php foreach ( $dietary_restrictions as $restriction ) : ?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" name="dietary_restrictions[]" value="<?php echo esc_attr( $restriction->restriction_id ); ?>" <?php checked( in_array( $restriction->restriction_id, $selected_dietary_ids ) ); ?>>
								<?php echo esc_html( $restriction->restriction_name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>
	<?php endif; ?>
	
	<!-- Medical Accommodations -->
	<?php if ( ! empty( $medical_accommodations ) ) : ?>
		<h3><?php esc_html_e( 'Medical Accommodations', 'remember' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Select Accommodations', 'remember' ); ?></th>
				<td>
					<fieldset style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
						<?php foreach ( $medical_accommodations as $accommodation ) : ?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" name="medical_accommodations[]" value="<?php echo esc_attr( $accommodation->accommodation_id ); ?>" <?php checked( in_array( $accommodation->accommodation_id, $selected_medical_ids ) ); ?>>
								<?php echo esc_html( $accommodation->accommodation_name ); ?>
								<?php if ( ! empty( $accommodation->description ) ) : ?>
									<span class="description" style="margin-left: 5px;">- <?php echo esc_html( $accommodation->description ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>
	<?php endif; ?>
	
	<!-- Allergies -->
	<?php if ( ! empty( $allergies ) ) : ?>
		<h3><?php esc_html_e( 'Known Allergies', 'remember' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Select Allergies', 'remember' ); ?></th>
				<td>
					<fieldset style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 3px;">
						<?php foreach ( $allergies as $allergy ) : ?>
							<label style="display: block; margin: 5px 0;">
								<input type="checkbox" name="allergies[]" value="<?php echo esc_attr( $allergy->allergy_id ); ?>" <?php checked( in_array( $allergy->allergy_id, $selected_allergy_ids ) ); ?>>
								<?php echo esc_html( $allergy->allergy_name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>
	<?php endif; ?>
	
	<!-- Member Roles (only if user has update_members capability) -->
	<?php if ( current_user_can( 'remember_update_members' ) ) : 
		require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-role.php';
		$role_model = new Remember_Role();
		$all_roles = $role_model->get_all();
		
		// Get current member roles
		global $wpdb;
		$current_role_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT role_id FROM {$wpdb->prefix}remember_member_roles WHERE member_id = %d",
			$view_member_id
		) );
	?>
		<h3><?php esc_html_e( 'Member Roles', 'remember' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Assigned Roles', 'remember' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Select the roles this member should have. Members with roles receive the capabilities associated with those roles.', 'remember' ); ?></p>
					<fieldset style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 3px; margin-top: 10px;">
						<?php if ( ! empty( $all_roles ) ) : ?>
							<?php 
							// Group roles by type
							$event_roles = array();
							$system_roles = array();
							foreach ( $all_roles as $role ) {
								if ( $role->role_type === 'event' || $role->is_event_role ) {
									$event_roles[] = $role;
								} else {
									$system_roles[] = $role;
								}
							}
							?>
							<?php if ( ! empty( $system_roles ) ) : ?>
								<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;"><?php esc_html_e( 'System Roles', 'remember' ); ?></h4>
								<?php foreach ( $system_roles as $role ) : ?>
									<label style="display: block; margin: 5px 0;">
										<input type="checkbox" name="member_roles[]" value="<?php echo esc_attr( $role->role_id ); ?>" <?php checked( in_array( $role->role_id, $current_role_ids ) ); ?>>
										<?php echo esc_html( $role->role_name ); ?>
										<?php if ( ! empty( $role->description ) ) : ?>
											<span class="description" style="margin-left: 5px;">- <?php echo esc_html( $role->description ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
							
							<?php if ( ! empty( $event_roles ) ) : ?>
								<?php if ( ! empty( $system_roles ) ) : ?>
									<h4 style="margin: 15px 0 10px 0; font-size: 14px; font-weight: bold;"><?php esc_html_e( 'Event Roles', 'remember' ); ?></h4>
								<?php else : ?>
									<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: bold;"><?php esc_html_e( 'Event Roles', 'remember' ); ?></h4>
								<?php endif; ?>
								<?php foreach ( $event_roles as $role ) : ?>
									<label style="display: block; margin: 5px 0;">
										<input type="checkbox" name="member_roles[]" value="<?php echo esc_attr( $role->role_id ); ?>" <?php checked( in_array( $role->role_id, $current_role_ids ) ); ?>>
										<?php echo esc_html( $role->role_name ); ?>
										<?php if ( ! empty( $role->description ) ) : ?>
											<span class="description" style="margin-left: 5px;">- <?php echo esc_html( $role->description ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							<?php endif; ?>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'No roles available. Create roles in the Roles section first.', 'remember' ); ?></p>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
		</table>
	<?php endif; ?>
	
	<!-- Privacy Settings: Share with Event Members -->
	<h3><?php esc_html_e( 'Privacy Settings', 'remember' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Control what contact information is shared with other members when you are accepted into an event. These settings apply globally to all events.', 'remember' ); ?></p>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Share with Event Members', 'remember' ); ?></th>
			<td>
				<fieldset>
					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_photo_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_photo_with_events ) ? $view_profile->share_photo_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_photo_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'Profile Photo', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your profile photo with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>

					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_email_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_email_with_events ) ? $view_profile->share_email_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_email_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'Email Address', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your email address with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>
					
					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_phone_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_phone_with_events ) ? $view_profile->share_phone_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_phone_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'Phone Number', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your cell phone number with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>
					
					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_location_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_location_with_events ) ? $view_profile->share_location_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_location_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'City, State, Country', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your city, state, and country with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>
					
					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_im_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_im_with_events ) ? $view_profile->share_im_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_im_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your IM handle and type with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>
					
					<p>
						<label class="remember-toggle-switch">
							<input type="checkbox" 
								   name="share_interests_with_events" 
								   value="1" 
								   <?php checked( $view_profile && isset( $view_profile->share_interests_with_events ) ? $view_profile->share_interests_with_events : 0, 1 ); ?>>
							<span class="remember-toggle-slider"></span>
						</label>
						<label for="share_interests_with_events" style="margin-left: 10px; cursor: pointer;">
							<strong><?php esc_html_e( 'Interests', 'remember' ); ?></strong>
							<span class="description" style="margin-left: 5px;"><?php esc_html_e( 'Share your interests with other members in events you\'re accepted to.', 'remember' ); ?></span>
						</label>
					</p>
				</fieldset>
			</td>
		</tr>
	</table>
	
	<p class="submit">
		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile', 'remember' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $view_member_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
	</p>
</form>
