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

// Get timezones
$timezones = timezone_identifiers_list();

// Load countries helper
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-countries.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-image-uploader.php';

// Get max image dimensions from settings
$options = get_option( 'remember_options', array() );
$max_image_size = isset( $options['photo_max_dimensions'] ) ? absint( $options['photo_max_dimensions'] ) : 800;
?>

<form method="post" action="" enctype="multipart/form-data" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
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
				<?php if ( ! empty( $view_member->photo_url ) ) : ?>
					<p>
						<img src="<?php echo esc_url( $view_member->photo_url ); ?>" alt="<?php echo esc_attr( $view_user->display_name ); ?>" style="max-width: 150px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
					</p>
					<label><input type="checkbox" name="delete_photo" value="1"> <?php esc_html_e( 'Delete current photo', 'remember' ); ?></label>
					<p class="description"><?php esc_html_e( 'Upload a new photo to replace the current one.', 'remember' ); ?></p>
				<?php endif; ?>
				<input type="file" id="photo_file" name="photo_file" accept="image/*">
				<p class="description"><?php echo esc_html( sprintf( __( 'Square image, max %dpx. WordPress will resize if needed.', 'remember' ), $max_image_size ) ); ?></p>
			</td>
		</tr>
	</table>
	
	<!-- Profile Information -->
	<h3><?php esc_html_e( 'Profile Information', 'remember' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="legal_first_name"><?php esc_html_e( 'Legal First Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<?php
				$legal_first = $view_profile ? $view_profile->legal_first_name : '';
				// Fall back to WP user meta if empty
				if ( empty( $legal_first ) ) {
					$legal_first = get_user_meta( $view_member_id, 'first_name', true );
				}
				?>
				<input type="text" id="legal_first_name" name="legal_first_name" class="regular-text" value="<?php echo esc_attr( $legal_first ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="legal_last_name"><?php esc_html_e( 'Legal Last Name', 'remember' ); ?> <span class="description">(required)</span></label></th>
			<td>
				<?php
				$legal_last = $view_profile ? $view_profile->legal_last_name : '';
				// Fall back to WP user meta if empty
				if ( empty( $legal_last ) ) {
					$legal_last = get_user_meta( $view_member_id, 'last_name', true );
				}
				?>
				<input type="text" id="legal_last_name" name="legal_last_name" class="regular-text" value="<?php echo esc_attr( $legal_last ); ?>" required>
			</td>
		</tr>
		<tr>
			<th><label for="address_street"><?php esc_html_e( 'Street Address', 'remember' ); ?></label></th>
			<td>
				<input type="text" id="address_street" name="address_street" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_street : '' ); ?>">
			</td>
		</tr>
		<tr>
			<th><label for="address_city"><?php esc_html_e( 'City', 'remember' ); ?></label></th>
			<td>
				<input type="text" id="address_city" name="address_city" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_city : '' ); ?>">
			</td>
		</tr>
		<tr>
			<th><label for="address_state"><?php esc_html_e( 'State/Province', 'remember' ); ?></label></th>
			<td>
				<input type="text" id="address_state" name="address_state" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_state : '' ); ?>">
			</td>
		</tr>
		<tr>
			<th><label for="address_postal"><?php esc_html_e( 'Postal Code', 'remember' ); ?></label></th>
			<td>
				<input type="text" id="address_postal" name="address_postal" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->address_postal : '' ); ?>">
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
			<th><label for="cell_phone"><?php esc_html_e( 'Cell Phone', 'remember' ); ?></label></th>
			<td>
				<input type="text" id="cell_phone" name="cell_phone" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->cell_phone : '' ); ?>" placeholder="<?php esc_attr_e( 'International format', 'remember' ); ?>">
			</td>
		</tr>
		<tr>
			<th><label for="timezone"><?php esc_html_e( 'Time Zone', 'remember' ); ?></label></th>
			<td>
				<select id="timezone" name="timezone" class="regular-text">
					<option value=""><?php esc_html_e( '-- Select Time Zone --', 'remember' ); ?></option>
					<?php 
					$selected_timezone = $view_profile && $view_profile->timezone ? $view_profile->timezone : '';
					foreach ( $timezones as $timezone ) : 
					?>
						<option value="<?php echo esc_attr( $timezone ); ?>" <?php selected( $selected_timezone, $timezone ); ?>>
							<?php echo esc_html( str_replace( '_', ' ', $timezone ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="im_type"><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></label></th>
			<td>
				<select id="im_type" name="im_type" style="width: 150px;">
					<option value="telegram" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'remember' ); ?></option>
					<option value="discord" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'discord' ); ?>><?php esc_html_e( 'Discord', 'remember' ); ?></option>
					<option value="signal" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'signal' ); ?>><?php esc_html_e( 'Signal', 'remember' ); ?></option>
					<option value="whatsapp" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'remember' ); ?></option>
					<option value="other" <?php selected( $view_profile && $view_profile->im_type ? $view_profile->im_type : 'telegram', 'other' ); ?>><?php esc_html_e( 'Other', 'remember' ); ?></option>
				</select>
				<input type="text" id="im_handle" name="im_handle" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->im_handle : '' ); ?>" placeholder="<?php esc_attr_e( 'Handle', 'remember' ); ?>" style="margin-left: 10px;">
			</td>
		</tr>
		<tr>
			<th><label for="interests"><?php esc_html_e( 'Interests', 'remember' ); ?></label></th>
			<td>
				<?php
				$interests_value = $view_profile && isset( $view_profile->interests ) ? $view_profile->interests : '';
				?>
				<textarea id="interests" name="interests" class="large-text" rows="4"><?php echo esc_textarea( $interests_value ); ?></textarea>
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
				<input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="regular-text" value="<?php echo esc_attr( $view_profile ? $view_profile->emergency_contact_phone : '' ); ?>" required>
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
	
	<p class="submit">
		<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile', 'remember' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=remember-members&view=' . $view_member_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'remember' ); ?></a>
	</p>
</form>
