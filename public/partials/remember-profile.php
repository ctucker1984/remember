<?php
/**
 * Member profile template
 *
 * @package    reMember
 * @subpackage reMember/public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-countries.php';

$user = wp_get_current_user();
$member_model = new Remember_Member();
$member = $member_model->get( $user->ID );

// Get profile
global $wpdb;
$profile = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
		$user->ID
	)
);

// $is_edit should be set by shortcode handler
if ( ! isset( $is_edit ) ) {
	$is_edit = isset( $_GET['edit'] ) && $_GET['edit'];
}

// Handle form submission
if ( isset( $_POST['remember_profile_action'] ) && check_admin_referer( 'remember_profile_action', 'remember_profile_nonce' ) ) {
	$profile_data = array(
		'legal_first_name'            => isset( $_POST['legal_first_name'] ) ? sanitize_text_field( $_POST['legal_first_name'] ) : '',
		'legal_last_name'             => isset( $_POST['legal_last_name'] ) ? sanitize_text_field( $_POST['legal_last_name'] ) : '',
		'address_street'              => isset( $_POST['address_street'] ) ? sanitize_text_field( $_POST['address_street'] ) : '',
		'address_city'                => isset( $_POST['address_city'] ) ? sanitize_text_field( $_POST['address_city'] ) : '',
		'address_state'               => isset( $_POST['address_state'] ) ? sanitize_text_field( $_POST['address_state'] ) : '',
		'address_postal'              => isset( $_POST['address_postal'] ) ? sanitize_text_field( $_POST['address_postal'] ) : '',
		'address_country'             => isset( $_POST['address_country'] ) ? sanitize_text_field( $_POST['address_country'] ) : 'US',
		'cell_phone'                  => isset( $_POST['cell_phone'] ) ? sanitize_text_field( $_POST['cell_phone'] ) : '',
		'im_handle'                   => isset( $_POST['im_handle'] ) ? sanitize_text_field( $_POST['im_handle'] ) : '',
		'im_type'                     => isset( $_POST['im_type'] ) ? sanitize_text_field( $_POST['im_type'] ) : 'telegram',
		'interests'                   => isset( $_POST['interests'] ) ? sanitize_textarea_field( $_POST['interests'] ) : '',
		'emergency_contact_first'     => isset( $_POST['emergency_contact_first'] ) ? sanitize_text_field( $_POST['emergency_contact_first'] ) : '',
		'emergency_contact_last'      => isset( $_POST['emergency_contact_last'] ) ? sanitize_text_field( $_POST['emergency_contact_last'] ) : '',
		'emergency_contact_phone'     => isset( $_POST['emergency_contact_phone'] ) ? sanitize_text_field( $_POST['emergency_contact_phone'] ) : '',
		'emergency_contact_relationship' => isset( $_POST['emergency_contact_relationship'] ) ? sanitize_text_field( $_POST['emergency_contact_relationship'] ) : '',
		'share_email_with_events'     => isset( $_POST['share_email_with_events'] ) ? 1 : 0,
		'share_phone_with_events'     => isset( $_POST['share_phone_with_events'] ) ? 1 : 0,
		'share_location_with_events'  => isset( $_POST['share_location_with_events'] ) ? 1 : 0,
		'share_im_with_events'        => isset( $_POST['share_im_with_events'] ) ? 1 : 0,
		'share_interests_with_events' => isset( $_POST['share_interests_with_events'] ) ? 1 : 0,
		'updated_at'                  => current_time( 'mysql' ),
	);

	if ( $profile ) {
		$wpdb->update(
			$wpdb->prefix . 'remember_member_profiles',
			$profile_data,
			array( 'member_id' => $user->ID )
		);
	} else {
		$profile_data['member_id'] = $user->ID;
		$profile_data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $wpdb->prefix . 'remember_member_profiles', $profile_data );
	}

	// Update WordPress user meta
	if ( ! empty( $profile_data['legal_first_name'] ) ) {
		update_user_meta( $user->ID, 'first_name', $profile_data['legal_first_name'] );
	}
	if ( ! empty( $profile_data['legal_last_name'] ) ) {
		update_user_meta( $user->ID, 'last_name', $profile_data['legal_last_name'] );
	}
	
	// Save timezone to WP user meta (not member_profiles)
	if ( isset( $_POST['timezone_string'] ) ) {
		$timezone_string = sanitize_text_field( $_POST['timezone_string'] );
		if ( ! empty( $timezone_string ) ) {
			update_user_meta( $user->ID, 'timezone_string', $timezone_string );
		} else {
			// Default if empty
			update_user_meta( $user->ID, 'timezone_string', 'America/Los_Angeles' );
		}
	}

	// Redirect to view mode
	wp_safe_redirect( remove_query_arg( 'edit' ) );
	exit;
}

// Load timezone utility
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-timezone.php';

// Get timezone from WP user meta (not from member_profiles)
$selected_timezone = get_user_meta( $user->ID, 'timezone_string', true );
if ( empty( $selected_timezone ) ) {
	$selected_timezone = 'America/Los_Angeles'; // Default
	// Auto-assign default timezone
	update_user_meta( $user->ID, 'timezone_string', $selected_timezone );
}
?>

<div class="remember-profile">
	<?php if ( $is_edit ) : ?>
		<h2><?php esc_html_e( 'Edit Profile', 'remember' ); ?></h2>
		<form method="post" action="" class="remember-form">
			<?php wp_nonce_field( 'remember_profile_action', 'remember_profile_nonce' ); ?>
			<input type="hidden" name="remember_profile_action" value="update">

			<div class="remember-form-group">
				<label for="legal_first_name" class="remember-form-label">
					<?php esc_html_e( 'Legal First Name', 'remember' ); ?>
					<span class="remember-required">*</span>
				</label>
				<input type="text" id="legal_first_name" name="legal_first_name" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->legal_first_name : get_user_meta( $user->ID, 'first_name', true ) ); ?>" required>
			</div>

			<div class="remember-form-group">
				<label for="legal_last_name" class="remember-form-label">
					<?php esc_html_e( 'Legal Last Name', 'remember' ); ?>
					<span class="remember-required">*</span>
				</label>
				<input type="text" id="legal_last_name" name="legal_last_name" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->legal_last_name : get_user_meta( $user->ID, 'last_name', true ) ); ?>" required>
			</div>

			<h3><?php esc_html_e( 'Contact Information', 'remember' ); ?></h3>

			<div class="remember-form-group">
				<label for="cell_phone" class="remember-form-label"><?php esc_html_e( 'Cell Phone', 'remember' ); ?></label>
				<input type="text" id="cell_phone" name="cell_phone" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->cell_phone : '' ); ?>" placeholder="<?php esc_attr_e( 'International format', 'remember' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="address_street" class="remember-form-label"><?php esc_html_e( 'Street Address', 'remember' ); ?></label>
				<input type="text" id="address_street" name="address_street" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->address_street : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="address_city" class="remember-form-label"><?php esc_html_e( 'City', 'remember' ); ?></label>
				<input type="text" id="address_city" name="address_city" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->address_city : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="address_state" class="remember-form-label"><?php esc_html_e( 'State/Province', 'remember' ); ?></label>
				<input type="text" id="address_state" name="address_state" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->address_state : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="address_postal" class="remember-form-label"><?php esc_html_e( 'Postal Code', 'remember' ); ?></label>
				<input type="text" id="address_postal" name="address_postal" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->address_postal : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="address_country" class="remember-form-label"><?php esc_html_e( 'Country', 'remember' ); ?></label>
				<?php 
				$selected_country = $profile && $profile->address_country ? $profile->address_country : 'US';
				echo Remember_Countries::dropdown( 'address_country', $selected_country, array( 'id' => 'address_country', 'class' => 'remember-form-control' ) );
				?>
			</div>

			<div class="remember-form-group">
				<label for="timezone_string" class="remember-form-label">
					<?php esc_html_e( 'Time Zone', 'remember' ); ?>
					<span class="remember-required">*</span>
				</label>
				<?php echo Remember_Timezone::dropdown( $selected_timezone, 'timezone_string', 'timezone_string', true ); ?>
				<p class="description"><?php esc_html_e( 'Your timezone is used to display scheduled times in your local time.', 'remember' ); ?></p>
			</div>

			<div class="remember-form-group">
				<label for="im_type" class="remember-form-label"><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></label>
				<select id="im_type" name="im_type" class="remember-form-control" style="max-width: 200px; display: inline-block;">
					<option value="telegram" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'remember' ); ?></option>
					<option value="discord" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'discord' ); ?>><?php esc_html_e( 'Discord', 'remember' ); ?></option>
					<option value="signal" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'signal' ); ?>><?php esc_html_e( 'Signal', 'remember' ); ?></option>
					<option value="whatsapp" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'remember' ); ?></option>
					<option value="other" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'other' ); ?>><?php esc_html_e( 'Other', 'remember' ); ?></option>
				</select>
				<input type="text" id="im_handle" name="im_handle" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->im_handle : '' ); ?>" placeholder="<?php esc_attr_e( 'Handle', 'remember' ); ?>" style="max-width: 200px; display: inline-block; margin-left: 10px;">
			</div>

			<div class="remember-form-group">
				<label for="interests" class="remember-form-label"><?php esc_html_e( 'Interests', 'remember' ); ?></label>
				<textarea id="interests" name="interests" class="remember-form-control" rows="4"><?php echo esc_textarea( $profile ? $profile->interests : '' ); ?></textarea>
			</div>

			<h3><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>

			<div class="remember-form-group">
				<label for="emergency_contact_first" class="remember-form-label"><?php esc_html_e( 'First Name', 'remember' ); ?></label>
				<input type="text" id="emergency_contact_first" name="emergency_contact_first" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->emergency_contact_first : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="emergency_contact_last" class="remember-form-label"><?php esc_html_e( 'Last Name', 'remember' ); ?></label>
				<input type="text" id="emergency_contact_last" name="emergency_contact_last" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->emergency_contact_last : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="emergency_contact_phone" class="remember-form-label"><?php esc_html_e( 'Phone', 'remember' ); ?></label>
				<input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->emergency_contact_phone : '' ); ?>">
			</div>

			<div class="remember-form-group">
				<label for="emergency_contact_relationship" class="remember-form-label"><?php esc_html_e( 'Relationship', 'remember' ); ?></label>
				<input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="remember-form-control" 
					value="<?php echo esc_attr( $profile ? $profile->emergency_contact_relationship : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Spouse, Parent, Friend', 'remember' ); ?>">
			</div>

			<h3><?php esc_html_e( 'Privacy Settings', 'remember' ); ?></h3>
			<p class="remember-form-help"><?php esc_html_e( 'Control what contact information is shared with other members when you are accepted into events.', 'remember' ); ?></p>

			<div class="remember-form-group">
				<label class="remember-form-label">
					<input type="checkbox" name="share_email_with_events" value="1" <?php checked( $profile && isset( $profile->share_email_with_events ) ? $profile->share_email_with_events : 0, 1 ); ?>>
					<?php esc_html_e( 'Share Email Address', 'remember' ); ?>
				</label>
			</div>

			<div class="remember-form-group">
				<label class="remember-form-label">
					<input type="checkbox" name="share_phone_with_events" value="1" <?php checked( $profile && isset( $profile->share_phone_with_events ) ? $profile->share_phone_with_events : 0, 1 ); ?>>
					<?php esc_html_e( 'Share Phone Number', 'remember' ); ?>
				</label>
			</div>

			<div class="remember-form-group">
				<label class="remember-form-label">
					<input type="checkbox" name="share_location_with_events" value="1" <?php checked( $profile && isset( $profile->share_location_with_events ) ? $profile->share_location_with_events : 0, 1 ); ?>>
					<?php esc_html_e( 'Share City, State, Country', 'remember' ); ?>
				</label>
			</div>

			<div class="remember-form-group">
				<label class="remember-form-label">
					<input type="checkbox" name="share_im_with_events" value="1" <?php checked( $profile && isset( $profile->share_im_with_events ) ? $profile->share_im_with_events : 0, 1 ); ?>>
					<?php esc_html_e( 'Share Instant Messenger', 'remember' ); ?>
				</label>
			</div>

			<div class="remember-form-group">
				<label class="remember-form-label">
					<input type="checkbox" name="share_interests_with_events" value="1" <?php checked( $profile && isset( $profile->share_interests_with_events ) ? $profile->share_interests_with_events : 0, 1 ); ?>>
					<?php esc_html_e( 'Share Interests', 'remember' ); ?>
				</label>
			</div>

			<div class="remember-form-group">
				<button type="submit" class="remember-button remember-button-primary">
					<?php esc_html_e( 'Save Profile', 'remember' ); ?>
				</button>
				<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="remember-button remember-button-secondary">
					<?php esc_html_e( 'Cancel', 'remember' ); ?>
				</a>
			</div>
		</form>
	<?php else : ?>
		<div class="remember-profile-header">
			<h2><?php echo esc_html( $user->display_name ); ?></h2>
			<a href="<?php echo esc_url( add_query_arg( 'edit', '1' ) ); ?>" class="remember-button remember-button-primary">
				<?php esc_html_e( 'Edit Profile', 'remember' ); ?>
			</a>
		</div>

		<?php if ( $profile ) : ?>
			<div class="remember-profile-content">
				<div class="remember-profile-section">
					<h3><?php esc_html_e( 'Profile Information', 'remember' ); ?></h3>
					<p><strong><?php esc_html_e( 'Legal Name:', 'remember' ); ?></strong> <?php echo esc_html( trim( ( $profile->legal_first_name ?? '' ) . ' ' . ( $profile->legal_last_name ?? '' ) ) ); ?></p>
					<?php if ( ! empty( $profile->cell_phone ) ) : ?>
						<p><strong><?php esc_html_e( 'Phone:', 'remember' ); ?></strong> 
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile->cell_phone ) ); ?>">
								<?php echo esc_html( $profile->cell_phone ); ?>
							</a>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $profile->address_city ) || ! empty( $profile->address_state ) || ! empty( $profile->address_country ) ) : ?>
						<p><strong><?php esc_html_e( 'Location:', 'remember' ); ?></strong>
							<?php 
							$location_parts = array_filter( array(
								$profile->address_city,
								$profile->address_state,
								$profile->address_country
							) );
							echo esc_html( implode( ', ', $location_parts ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( ! empty( $profile->im_handle ) ) : ?>
						<p><strong><?php esc_html_e( 'IM:', 'remember' ); ?></strong> <?php echo esc_html( ucfirst( $profile->im_type ) ); ?>: <?php echo esc_html( $profile->im_handle ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $profile->interests ) ) : ?>
						<p><strong><?php esc_html_e( 'Interests:', 'remember' ); ?></strong> <?php echo esc_html( $profile->interests ); ?></p>
					<?php endif; ?>
				</div>

				<div class="remember-profile-section">
					<h3><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>
					<p><strong><?php esc_html_e( 'Name:', 'remember' ); ?></strong> <?php echo esc_html( trim( ( $profile->emergency_contact_first ?? '' ) . ' ' . ( $profile->emergency_contact_last ?? '' ) ) ); ?></p>
					<?php if ( ! empty( $profile->emergency_contact_phone ) ) : ?>
						<p><strong><?php esc_html_e( 'Phone:', 'remember' ); ?></strong> <?php echo esc_html( $profile->emergency_contact_phone ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $profile->emergency_contact_relationship ) ) : ?>
						<p><strong><?php esc_html_e( 'Relationship:', 'remember' ); ?></strong> <?php echo esc_html( $profile->emergency_contact_relationship ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<p class="remember-description"><?php esc_html_e( 'No profile information yet. Edit your profile to get started.', 'remember' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
