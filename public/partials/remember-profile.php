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
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-import-export.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-image-uploader.php';

$user = wp_get_current_user();
$member_model = new Remember_Member();
$member = $member_model->get( $user->ID );

$remember_options     = get_option( 'remember_options', array() );
$photo_max_dimensions = isset( $remember_options['photo_max_dimensions'] ) ? absint( $remember_options['photo_max_dimensions'] ) : 800;
$photo_max_bytes      = isset( $remember_options['photo_max_size'] ) ? absint( $remember_options['photo_max_size'] ) : 2097152;
if ( $photo_max_dimensions < 1 ) {
	$photo_max_dimensions = 800;
}
if ( $photo_max_bytes < 1 ) {
	$photo_max_bytes = 2097152;
}

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
	$cell_phone = isset( $_POST['cell_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cell_phone'] ) ) : '';
	if ( '' === $cell_phone ) {
		wp_safe_redirect( add_query_arg( array( 'edit' => '1', 'remember_profile_error' => 'cell_phone' ) ) );
		exit;
	}

	$photo_error   = '';
	$has_new_photo = ! empty( $_FILES['photo_file']['name'] );

	// Upload / replace photo (takes precedence over delete).
	if ( $has_new_photo ) {
		$file_size = isset( $_FILES['photo_file']['size'] ) ? absint( $_FILES['photo_file']['size'] ) : 0;
		if ( $file_size > 0 && $file_size > $photo_max_bytes ) {
			$photo_error = sprintf(
				/* translators: %d: max size in MB */
				__( 'Photo is too large. Maximum size is %d MB.', 'remember' ),
				(int) max( 1, round( $photo_max_bytes / 1024 / 1024 ) )
			);
		} else {
			$current_member = $member_model->get( $user->ID );
			if ( $current_member && ! empty( $current_member->photo_url ) ) {
				Remember_Image_Uploader::delete_image( $current_member->photo_url );
			}

			$upload_result = Remember_Image_Uploader::upload_square_image( $_FILES['photo_file'], $photo_max_dimensions );
			if ( is_wp_error( $upload_result ) ) {
				$photo_error = $upload_result->get_error_message();
			} else {
				$member_model->update_photo( $user->ID, $upload_result['url'] );
			}
		}
	} elseif ( isset( $_POST['delete_photo'] ) && '1' === (string) wp_unslash( $_POST['delete_photo'] ) ) {
		$current_member = $member_model->get( $user->ID );
		if ( $current_member && ! empty( $current_member->photo_url ) ) {
			Remember_Image_Uploader::delete_image( $current_member->photo_url );
			$member_model->update_photo( $user->ID, null );
		}
	}

	$profile_data = array(
		'legal_first_name'            => isset( $_POST['legal_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['legal_first_name'] ) ) : '',
		'legal_last_name'             => isset( $_POST['legal_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['legal_last_name'] ) ) : '',
		'address_street'              => isset( $_POST['address_street'] ) ? sanitize_text_field( wp_unslash( $_POST['address_street'] ) ) : '',
		'address_city'                => isset( $_POST['address_city'] ) ? sanitize_text_field( wp_unslash( $_POST['address_city'] ) ) : '',
		'address_state'               => isset( $_POST['address_state'] ) ? sanitize_text_field( wp_unslash( $_POST['address_state'] ) ) : '',
		'address_postal'              => isset( $_POST['address_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['address_postal'] ) ) : '',
		'address_country'             => isset( $_POST['address_country'] ) ? sanitize_text_field( wp_unslash( $_POST['address_country'] ) ) : 'US',
		'cell_phone'                  => $cell_phone,
		'im_handle'                   => isset( $_POST['im_handle'] ) ? sanitize_text_field( wp_unslash( $_POST['im_handle'] ) ) : '',
		'im_type'                     => isset( $_POST['im_type'] ) ? sanitize_text_field( wp_unslash( $_POST['im_type'] ) ) : 'telegram',
		'interests'                   => isset( $_POST['interests'] ) ? wp_kses_post( wp_unslash( $_POST['interests'] ) ) : '',
		'emergency_contact_first'     => isset( $_POST['emergency_contact_first'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_contact_first'] ) ) : '',
		'emergency_contact_last'      => isset( $_POST['emergency_contact_last'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_contact_last'] ) ) : '',
		'emergency_contact_phone'     => isset( $_POST['emergency_contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_contact_phone'] ) ) : '',
		'emergency_contact_relationship' => isset( $_POST['emergency_contact_relationship'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_contact_relationship'] ) ) : '',
		'share_email_with_events'     => isset( $_POST['share_email_with_events'] ) ? 1 : 0,
		'share_phone_with_events'     => isset( $_POST['share_phone_with_events'] ) ? 1 : 0,
		'share_location_with_events'  => isset( $_POST['share_location_with_events'] ) ? 1 : 0,
		'share_im_with_events'        => isset( $_POST['share_im_with_events'] ) ? 1 : 0,
		'share_interests_with_events' => isset( $_POST['share_interests_with_events'] ) ? 1 : 0,
		'share_photo_with_events'     => isset( $_POST['share_photo_with_events'] ) ? 1 : 0,
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

	// Update WordPress name meta (legal names feed the public display-name choices).
	if ( ! empty( $profile_data['legal_first_name'] ) ) {
		update_user_meta( $user->ID, 'first_name', $profile_data['legal_first_name'] );
	}
	if ( ! empty( $profile_data['legal_last_name'] ) ) {
		update_user_meta( $user->ID, 'last_name', $profile_data['legal_last_name'] );
	}

	// Nickname (WP user meta) + public display_name — never auto-derived from legal name.
	$nickname = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : '';
	if ( '' === $nickname ) {
		$nickname = ! empty( $user->user_login ) ? $user->user_login : $user->display_name;
	}
	update_user_meta( $user->ID, 'nickname', $nickname );

	// Refresh user object so display-name choices include updated first/last/nickname.
	$user = new WP_User( $user->ID );
	$user->first_name = $profile_data['legal_first_name'];
	$user->last_name  = $profile_data['legal_last_name'];

	$requested_display = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
	$safe_display      = Remember_Member::resolve_public_display_name( $user, $nickname, $requested_display );

	wp_update_user(
		array(
			'ID'           => $user->ID,
			'display_name' => $safe_display,
			'nickname'     => $nickname,
		)
	);
	
	// Save timezone to WP user meta (not member_profiles)
	if ( isset( $_POST['timezone_string'] ) ) {
		$timezone_string = sanitize_text_field( wp_unslash( $_POST['timezone_string'] ) );
		if ( ! empty( $timezone_string ) ) {
			update_user_meta( $user->ID, 'timezone_string', $timezone_string );
		} else {
			// Default if empty
			update_user_meta( $user->ID, 'timezone_string', 'America/Los_Angeles' );
		}
	}
	
	// Save social media profiles (limit to 3)
	$wpdb->delete( $wpdb->prefix . 'remember_member_social_media', array( 'member_id' => $user->ID ), array( '%d' ) );
	if ( isset( $_POST['social_media'] ) && is_array( $_POST['social_media'] ) ) {
		$social_count = 0;
		foreach ( $_POST['social_media'] as $social_data ) {
			if ( $social_count >= 3 ) {
				break; // Limit to 3
			}
			$platform_id = isset( $social_data['platform_id'] ) ? absint( $social_data['platform_id'] ) : 0;
			$handle = isset( $social_data['handle'] ) ? sanitize_text_field( wp_unslash( $social_data['handle'] ) ) : '';
			if ( $platform_id > 0 && ! empty( $handle ) ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_social_media',
					array(
						'member_id'   => $user->ID,
						'platform_id' => $platform_id,
						'handle'      => $handle,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s', '%s' )
				);
				$social_count++;
			}
		}
	}

	// Dietary restrictions / medical accommodations / allergies (junction tables).
	$wpdb->delete( $wpdb->prefix . 'remember_member_dietary_restrictions', array( 'member_id' => $user->ID ), array( '%d' ) );
	if ( isset( $_POST['dietary_restrictions'] ) && is_array( $_POST['dietary_restrictions'] ) ) {
		foreach ( $_POST['dietary_restrictions'] as $restriction_id ) {
			$restriction_id = absint( $restriction_id );
			if ( $restriction_id > 0 ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_dietary_restrictions',
					array(
						'member_id'      => $user->ID,
						'restriction_id' => $restriction_id,
					),
					array( '%d', '%d' )
				);
			}
		}
	}

	$wpdb->delete( $wpdb->prefix . 'remember_member_medical_accommodations', array( 'member_id' => $user->ID ), array( '%d' ) );
	if ( isset( $_POST['medical_accommodations'] ) && is_array( $_POST['medical_accommodations'] ) ) {
		foreach ( $_POST['medical_accommodations'] as $accommodation_id ) {
			$accommodation_id = absint( $accommodation_id );
			if ( $accommodation_id > 0 ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_medical_accommodations',
					array(
						'member_id'        => $user->ID,
						'accommodation_id' => $accommodation_id,
					),
					array( '%d', '%d' )
				);
			}
		}
	}

	$wpdb->delete( $wpdb->prefix . 'remember_member_allergies', array( 'member_id' => $user->ID ), array( '%d' ) );
	if ( isset( $_POST['allergies'] ) && is_array( $_POST['allergies'] ) ) {
		foreach ( $_POST['allergies'] as $allergy_id ) {
			$allergy_id = absint( $allergy_id );
			if ( $allergy_id > 0 ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_allergies',
					array(
						'member_id'  => $user->ID,
						'allergy_id' => $allergy_id,
					),
					array( '%d', '%d' )
				);
			}
		}
	}

	do_action( 'remember_member_profile_saved', $user->ID );

	// Redirect: stay on edit if photo failed so the member can retry.
	if ( ! empty( $photo_error ) ) {
		set_transient( 'remember_profile_photo_error_' . $user->ID, $photo_error, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'edit', '1' ) );
		exit;
	}

	wp_safe_redirect( remove_query_arg( array( 'edit', 'remember_photo_error' ) ) );
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

// Get social media platforms
$social_platforms = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_social_media_platforms WHERE is_active = 1 ORDER BY sort_order ASC, platform_name ASC"
);

// Get existing social media profiles for this member
$existing_social = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT sm.*, p.platform_name 
		FROM {$wpdb->prefix}remember_member_social_media sm
		JOIN {$wpdb->prefix}remember_social_media_platforms p ON sm.platform_id = p.platform_id
		WHERE sm.member_id = %d
		ORDER BY sm.created_at ASC
		LIMIT 3",
		$user->ID
	)
);
// Initialize with empty slots if less than 3
$social_profiles = array();
for ( $i = 0; $i < 3; $i++ ) {
	$social_profiles[ $i ] = isset( $existing_social[ $i ] ) ? $existing_social[ $i ] : null;
}

// Public identity fields (WordPress nickname + display_name).
$user            = new WP_User( $user->ID );
$nickname_value  = get_user_meta( $user->ID, 'nickname', true );
if ( '' === (string) $nickname_value ) {
	$nickname_value = $user->user_login;
}
$display_choices = Remember_Member::get_public_display_name_choices( $user, $nickname_value );
$current_display = $user->display_name;
if ( $current_display && ! in_array( $current_display, $display_choices, true ) ) {
	$display_choices[] = $current_display;
}

// Health / accommodation lookup lists for edit + view.
$dietary_restrictions = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_dietary_restrictions WHERE is_active = 1 ORDER BY sort_order ASC, restriction_name ASC"
);
$medical_accommodations = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_medical_accommodations WHERE is_active = 1 ORDER BY sort_order ASC, accommodation_name ASC"
);
$allergies = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}remember_allergies WHERE is_active = 1 ORDER BY sort_order ASC, allergy_name ASC"
);

$selected_dietary_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT restriction_id FROM {$wpdb->prefix}remember_member_dietary_restrictions WHERE member_id = %d",
		$user->ID
	)
);
$selected_medical_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT accommodation_id FROM {$wpdb->prefix}remember_member_medical_accommodations WHERE member_id = %d",
		$user->ID
	)
);
$selected_allergy_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT allergy_id FROM {$wpdb->prefix}remember_member_allergies WHERE member_id = %d",
		$user->ID
	)
);

$selected_dietary_names = array();
$selected_medical_names = array();
$selected_allergy_names = array();
if ( ! empty( $selected_dietary_ids ) ) {
	$selected_dietary_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT dr.restriction_name
			FROM {$wpdb->prefix}remember_dietary_restrictions dr
			INNER JOIN {$wpdb->prefix}remember_member_dietary_restrictions mdr ON dr.restriction_id = mdr.restriction_id
			WHERE mdr.member_id = %d
			ORDER BY dr.sort_order ASC, dr.restriction_name ASC",
			$user->ID
		)
	);
}
if ( ! empty( $selected_medical_ids ) ) {
	$selected_medical_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ma.accommodation_name
			FROM {$wpdb->prefix}remember_medical_accommodations ma
			INNER JOIN {$wpdb->prefix}remember_member_medical_accommodations mma ON ma.accommodation_id = mma.accommodation_id
			WHERE mma.member_id = %d
			ORDER BY ma.sort_order ASC, ma.accommodation_name ASC",
			$user->ID
		)
	);
}
if ( ! empty( $selected_allergy_ids ) ) {
	$selected_allergy_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT a.allergy_name
			FROM {$wpdb->prefix}remember_allergies a
			INNER JOIN {$wpdb->prefix}remember_member_allergies ma ON a.allergy_id = ma.allergy_id
			WHERE ma.member_id = %d
			ORDER BY a.sort_order ASC, a.allergy_name ASC",
			$user->ID
		)
	);
}
?>

<div class="remember-profile">
	<?php if ( $is_edit ) : ?>
		<?php
		$photo_error_notice = get_transient( 'remember_profile_photo_error_' . $user->ID );
		if ( $photo_error_notice ) {
			delete_transient( 'remember_profile_photo_error_' . $user->ID );
		}
		$profile_error = isset( $_GET['remember_profile_error'] ) ? sanitize_text_field( wp_unslash( $_GET['remember_profile_error'] ) ) : '';
		// Refresh member so photo preview reflects latest save.
		$member = $member_model->get( $user->ID );
		?>
		<div class="remember-profile-edit-header">
			<h2><?php esc_html_e( 'Edit Profile', 'remember' ); ?></h2>
		</div>
		<?php if ( 'cell_phone' === $profile_error ) : ?>
			<div class="remember-notice remember-error" role="alert">
				<p><?php esc_html_e( 'Cell phone is required.', 'remember' ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( $photo_error_notice ) : ?>
			<div class="remember-notice remember-error" role="alert">
				<p><?php echo esc_html( $photo_error_notice ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post" action="" class="remember-profile-form-modern" enctype="multipart/form-data">
			<?php wp_nonce_field( 'remember_profile_action', 'remember_profile_nonce' ); ?>
			<input type="hidden" name="remember_profile_action" value="update">

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Profile Photo', 'remember' ); ?></h3>
				<p class="remember-form-help"><?php esc_html_e( 'Shown to other event participants only if you enable Share Profile Photo under Privacy Settings.', 'remember' ); ?></p>
				<div class="remember-profile-photo-edit" data-output-size="<?php echo esc_attr( (string) $photo_max_dimensions ); ?>">
					<div class="remember-profile-photo-current"<?php echo ( $member && ! empty( $member->photo_url ) ) ? '' : ' hidden'; ?>>
						<?php if ( $member && ! empty( $member->photo_url ) ) : ?>
							<div class="remember-profile-photo-preview">
								<img src="<?php echo esc_url( $member->photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>">
							</div>
							<label class="remember-checkbox-label remember-profile-photo-delete">
								<input type="checkbox" name="delete_photo" value="1" id="delete_photo">
								<span><?php esc_html_e( 'Delete current photo', 'remember' ); ?></span>
							</label>
							<p class="remember-form-help"><?php esc_html_e( 'Upload a new photo to replace the current one.', 'remember' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="remember-profile-photo-cropper" hidden>
						<div class="remember-profile-photo-cropper-viewport" aria-label="<?php esc_attr_e( 'Photo framing preview', 'remember' ); ?>">
							<img src="" alt="" class="remember-profile-photo-cropper-image" draggable="false">
						</div>
						<div class="remember-profile-photo-cropper-controls">
							<button type="button" class="remember-button remember-button-secondary remember-photo-zoom-out" aria-label="<?php esc_attr_e( 'Zoom out', 'remember' ); ?>">−</button>
							<input type="range" class="remember-photo-zoom-range" min="1" max="3" step="0.01" value="1" aria-label="<?php esc_attr_e( 'Zoom', 'remember' ); ?>">
							<button type="button" class="remember-button remember-button-secondary remember-photo-zoom-in" aria-label="<?php esc_attr_e( 'Zoom in', 'remember' ); ?>">+</button>
						</div>
						<p class="remember-form-help"><?php esc_html_e( 'Drag to recenter. Use zoom to frame the photo inside the circle. Your framing is saved when you click Save Profile.', 'remember' ); ?></p>
						<button type="button" class="remember-button remember-button-secondary remember-photo-clear">
							<?php esc_html_e( 'Clear selected photo', 'remember' ); ?>
						</button>
					</div>

					<label for="photo_file" class="remember-form-label"><?php esc_html_e( 'Upload photo', 'remember' ); ?></label>
					<input type="file" id="photo_file" name="photo_file" class="remember-form-control remember-form-control-file" accept="image/jpeg,image/png,image/gif">
					<p class="remember-form-help">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: max dimension in px, 2: max file size in MB */
								__( 'Square crop from your framing, max %1$dpx. Maximum file size %2$d MB. JPEG, PNG, or GIF.', 'remember' ),
								$photo_max_dimensions,
								(int) max( 1, round( $photo_max_bytes / 1024 / 1024 ) )
							)
						);
						?>
					</p>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Public Identity', 'remember' ); ?></h3>
				<p class="remember-form-help"><?php esc_html_e( 'These fields control how you appear to other members. Your legal name is never used here unless you choose it.', 'remember' ); ?></p>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="nickname" class="remember-form-label">
							<?php esc_html_e( 'Nickname', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<input type="text" id="nickname" name="nickname" class="remember-form-control" required
							value="<?php echo esc_attr( $nickname_value ); ?>">
						<p class="remember-form-help"><?php esc_html_e( 'WordPress nickname — available as a public display option.', 'remember' ); ?></p>
					</div>
					<div class="remember-form-col">
						<label for="display_name" class="remember-form-label">
							<?php esc_html_e( 'Display name publicly as', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<select id="display_name" name="display_name" class="remember-form-control" required>
							<?php foreach ( $display_choices as $choice ) : ?>
								<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $current_display, $choice ); ?>>
									<?php echo esc_html( $choice ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="remember-form-help"><?php esc_html_e( 'Shown in directories, dashboards, and other member-facing views.', 'remember' ); ?></p>
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Basic Information', 'remember' ); ?></h3>
				<p class="remember-form-help"><?php esc_html_e( 'Legal name is for admin and vetting only — not shown to other members.', 'remember' ); ?></p>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="legal_first_name" class="remember-form-label">
							<?php esc_html_e( 'Legal First Name', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<input type="text" id="legal_first_name" name="legal_first_name" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->legal_first_name : get_user_meta( $user->ID, 'first_name', true ) ); ?>" required>
					</div>
					<div class="remember-form-col">
						<label for="legal_last_name" class="remember-form-label">
							<?php esc_html_e( 'Legal Last Name', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<input type="text" id="legal_last_name" name="legal_last_name" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->legal_last_name : get_user_meta( $user->ID, 'last_name', true ) ); ?>" required>
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Contact Information', 'remember' ); ?></h3>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="cell_phone" class="remember-form-label">
							<?php esc_html_e( 'Cell Phone', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<input type="text" id="cell_phone" name="cell_phone" class="remember-form-control" required
							value="<?php echo esc_attr( $profile ? $profile->cell_phone : '' ); ?>" placeholder="<?php esc_attr_e( '+18055551212', 'remember' ); ?>">
						<p class="remember-form-help"><?php esc_html_e( 'Include a leading + and country code. Examples: +18055551212 (USA/Canada), +447700900123 (UK).', 'remember' ); ?></p>
					</div>
					<div class="remember-form-col">
						<label for="timezone_string" class="remember-form-label">
							<?php esc_html_e( 'Time Zone', 'remember' ); ?>
							<span class="remember-required">*</span>
						</label>
						<?php echo Remember_Timezone::dropdown( $selected_timezone, 'timezone_string', 'timezone_string', true ); ?>
						<p class="remember-form-help"><?php esc_html_e( 'Used to display scheduled times in your local time.', 'remember' ); ?></p>
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Address', 'remember' ); ?></h3>
				<div class="remember-form-row">
					<div class="remember-form-col remember-form-col-full">
						<label for="address_street" class="remember-form-label"><?php esc_html_e( 'Street Address', 'remember' ); ?></label>
						<input type="text" id="address_street" name="address_street" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->address_street : '' ); ?>">
					</div>
				</div>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="address_city" class="remember-form-label"><?php esc_html_e( 'City', 'remember' ); ?></label>
						<input type="text" id="address_city" name="address_city" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->address_city : '' ); ?>">
					</div>
					<div class="remember-form-col">
						<label for="address_state" class="remember-form-label"><?php esc_html_e( 'State/Province', 'remember' ); ?></label>
						<input type="text" id="address_state" name="address_state" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->address_state : '' ); ?>">
					</div>
					<div class="remember-form-col">
						<label for="address_postal" class="remember-form-label"><?php esc_html_e( 'Postal Code', 'remember' ); ?></label>
						<input type="text" id="address_postal" name="address_postal" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->address_postal : '' ); ?>">
					</div>
					<div class="remember-form-col">
						<label for="address_country" class="remember-form-label"><?php esc_html_e( 'Country', 'remember' ); ?></label>
						<?php 
						$selected_country = $profile && $profile->address_country ? $profile->address_country : 'US';
						echo Remember_Countries::dropdown( 'address_country', $selected_country, array( 'id' => 'address_country', 'class' => 'remember-form-control' ) );
						?>
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Social Media Profiles', 'remember' ); ?></h3>
				<p class="remember-form-help"><?php esc_html_e( 'Link up to 3 social media profiles.', 'remember' ); ?></p>
				<?php for ( $i = 0; $i < 3; $i++ ) : ?>
					<div class="remember-form-row remember-social-media-row">
						<div class="remember-form-col">
							<label for="social_platform_<?php echo esc_attr( $i ); ?>" class="remember-form-label">
								<?php esc_html_e( 'Platform', 'remember' ); ?>
							</label>
							<select id="social_platform_<?php echo esc_attr( $i ); ?>" name="social_media[<?php echo esc_attr( $i ); ?>][platform_id]" class="remember-form-control">
								<option value=""><?php esc_html_e( '-- Select Platform --', 'remember' ); ?></option>
								<?php foreach ( $social_platforms as $platform ) : 
									$selected = $social_profiles[ $i ] && $social_profiles[ $i ]->platform_id == $platform->platform_id;
								?>
									<option value="<?php echo esc_attr( $platform->platform_id ); ?>" <?php selected( $selected ); ?>>
										<?php echo esc_html( $platform->platform_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="remember-form-col">
							<label for="social_handle_<?php echo esc_attr( $i ); ?>" class="remember-form-label">
								<?php esc_html_e( 'Handle/Username', 'remember' ); ?>
							</label>
							<input type="text" id="social_handle_<?php echo esc_attr( $i ); ?>" name="social_media[<?php echo esc_attr( $i ); ?>][handle]" 
								class="remember-form-control" 
								value="<?php echo esc_attr( $social_profiles[ $i ] ? $social_profiles[ $i ]->handle : '' ); ?>" 
								placeholder="<?php esc_attr_e( 'e.g., @username', 'remember' ); ?>">
						</div>
					</div>
				<?php endfor; ?>
			</div>

			<?php if ( ! empty( $dietary_restrictions ) ) : ?>
				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Dietary Restrictions', 'remember' ); ?></h3>
					<p class="remember-form-help"><?php esc_html_e( 'Select any that apply. Used by event organizers — not shown to other participants.', 'remember' ); ?></p>
					<div class="remember-checkbox-grid">
						<?php foreach ( $dietary_restrictions as $restriction ) : ?>
							<label class="remember-checkbox-label">
								<input type="checkbox" name="dietary_restrictions[]" value="<?php echo esc_attr( $restriction->restriction_id ); ?>" <?php checked( in_array( (string) $restriction->restriction_id, array_map( 'strval', (array) $selected_dietary_ids ), true ) ); ?>>
								<span><?php echo esc_html( $restriction->restriction_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $medical_accommodations ) ) : ?>
				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Medical Accommodations', 'remember' ); ?></h3>
					<p class="remember-form-help"><?php esc_html_e( 'Select any that apply. Used by event organizers — not shown to other participants.', 'remember' ); ?></p>
					<div class="remember-checkbox-grid">
						<?php foreach ( $medical_accommodations as $accommodation ) : ?>
							<label class="remember-checkbox-label">
								<input type="checkbox" name="medical_accommodations[]" value="<?php echo esc_attr( $accommodation->accommodation_id ); ?>" <?php checked( in_array( (string) $accommodation->accommodation_id, array_map( 'strval', (array) $selected_medical_ids ), true ) ); ?>>
								<span>
									<?php echo esc_html( $accommodation->accommodation_name ); ?>
									<?php if ( ! empty( $accommodation->description ) ) : ?>
										<span class="remember-form-help" style="display:inline; margin-left:0.35em;">— <?php echo esc_html( $accommodation->description ); ?></span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $allergies ) ) : ?>
				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Known Allergies', 'remember' ); ?></h3>
					<p class="remember-form-help"><?php esc_html_e( 'Select any that apply. Used by event organizers — not shown to other participants.', 'remember' ); ?></p>
					<div class="remember-checkbox-grid">
						<?php foreach ( $allergies as $allergy ) : ?>
							<label class="remember-checkbox-label">
								<input type="checkbox" name="allergies[]" value="<?php echo esc_attr( $allergy->allergy_id ); ?>" <?php checked( in_array( (string) $allergy->allergy_id, array_map( 'strval', (array) $selected_allergy_ids ), true ) ); ?>>
								<span><?php echo esc_html( $allergy->allergy_name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></h3>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="im_type" class="remember-form-label"><?php esc_html_e( 'IM Type', 'remember' ); ?></label>
						<select id="im_type" name="im_type" class="remember-form-control">
							<option value="telegram" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'telegram' ); ?>><?php esc_html_e( 'Telegram', 'remember' ); ?></option>
							<option value="discord" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'discord' ); ?>><?php esc_html_e( 'Discord', 'remember' ); ?></option>
							<option value="signal" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'signal' ); ?>><?php esc_html_e( 'Signal', 'remember' ); ?></option>
							<option value="whatsapp" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'remember' ); ?></option>
							<option value="other" <?php selected( $profile && $profile->im_type ? $profile->im_type : 'telegram', 'other' ); ?>><?php esc_html_e( 'Other', 'remember' ); ?></option>
						</select>
					</div>
					<div class="remember-form-col">
						<label for="im_handle" class="remember-form-label"><?php esc_html_e( 'Handle', 'remember' ); ?></label>
						<input type="text" id="im_handle" name="im_handle" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->im_handle : '' ); ?>" placeholder="<?php esc_attr_e( 'Handle', 'remember' ); ?>">
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Interests', 'remember' ); ?></h3>
				<div class="remember-form-row">
					<div class="remember-form-col remember-form-col-full">
						<label for="interests" class="remember-form-label"><?php esc_html_e( 'Interests', 'remember' ); ?></label>
						<?php
						if ( ! wp_script_is( 'editor', 'enqueued' ) && ! wp_script_is( 'editor', 'done' ) ) {
							wp_enqueue_editor();
						}
						wp_editor(
							$profile && isset( $profile->interests ) ? $profile->interests : '',
							'interests',
							array(
								'textarea_name' => 'interests',
								'textarea_rows' => 6,
								'media_buttons' => false,
								'teeny'         => true,
								'quicktags'     => true,
								'editor_class'  => 'remember-form-control',
							)
						);
						?>
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="emergency_contact_first" class="remember-form-label"><?php esc_html_e( 'First Name', 'remember' ); ?></label>
						<input type="text" id="emergency_contact_first" name="emergency_contact_first" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->emergency_contact_first : '' ); ?>">
					</div>
					<div class="remember-form-col">
						<label for="emergency_contact_last" class="remember-form-label"><?php esc_html_e( 'Last Name', 'remember' ); ?></label>
						<input type="text" id="emergency_contact_last" name="emergency_contact_last" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->emergency_contact_last : '' ); ?>">
					</div>
				</div>
				<div class="remember-form-row">
					<div class="remember-form-col">
						<label for="emergency_contact_phone" class="remember-form-label"><?php esc_html_e( 'Phone', 'remember' ); ?></label>
						<input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->emergency_contact_phone : '' ); ?>">
					</div>
					<div class="remember-form-col">
						<label for="emergency_contact_relationship" class="remember-form-label"><?php esc_html_e( 'Relationship', 'remember' ); ?></label>
						<input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="remember-form-control" 
							value="<?php echo esc_attr( $profile ? $profile->emergency_contact_relationship : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Spouse, Parent, Friend', 'remember' ); ?>">
					</div>
				</div>
			</div>

			<div class="remember-form-section">
				<h3 class="remember-form-section-title"><?php esc_html_e( 'Privacy Settings', 'remember' ); ?></h3>
				<p class="remember-form-help"><?php esc_html_e( 'Control what is shared with other members when you are accepted into events.', 'remember' ); ?></p>
				<div class="remember-privacy-checkboxes">
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_photo_with_events" value="1" <?php checked( $profile && isset( $profile->share_photo_with_events ) ? $profile->share_photo_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share Profile Photo', 'remember' ); ?></span>
					</label>
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_email_with_events" value="1" <?php checked( $profile && isset( $profile->share_email_with_events ) ? $profile->share_email_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share Email Address', 'remember' ); ?></span>
					</label>
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_phone_with_events" value="1" <?php checked( $profile && isset( $profile->share_phone_with_events ) ? $profile->share_phone_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share Phone Number', 'remember' ); ?></span>
					</label>
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_location_with_events" value="1" <?php checked( $profile && isset( $profile->share_location_with_events ) ? $profile->share_location_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share City, State, Country', 'remember' ); ?></span>
					</label>
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_im_with_events" value="1" <?php checked( $profile && isset( $profile->share_im_with_events ) ? $profile->share_im_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share Instant Messenger', 'remember' ); ?></span>
					</label>
					<label class="remember-checkbox-label">
						<input type="checkbox" name="share_interests_with_events" value="1" <?php checked( $profile && isset( $profile->share_interests_with_events ) ? $profile->share_interests_with_events : 0, 1 ); ?>>
						<span><?php esc_html_e( 'Share Interests', 'remember' ); ?></span>
					</label>
				</div>
			</div>

			<div class="remember-form-actions">
				<button type="submit" class="remember-button remember-button-primary">
					<?php esc_html_e( 'Save Profile', 'remember' ); ?>
				</button>
				<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="remember-button remember-button-secondary">
					<?php esc_html_e( 'Cancel', 'remember' ); ?>
				</a>
			</div>
		</form>
	<?php else : ?>
		<?php
		$member = $member_model->get( $user->ID );
		?>
		<div class="remember-profile-edit-header">
			<?php if ( $member && ! empty( $member->photo_url ) ) : ?>
				<div class="remember-profile-photo-preview remember-profile-photo-preview--header">
					<img src="<?php echo esc_url( $member->photo_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>">
				</div>
			<?php endif; ?>
			<h2><?php echo esc_html( $user->display_name ); ?></h2>
			<a href="<?php echo esc_url( add_query_arg( 'edit', '1' ) ); ?>" class="remember-button remember-button-primary">
				<?php esc_html_e( 'Edit Profile', 'remember' ); ?>
			</a>
		</div>

		<?php if ( $profile ) : ?>
			<div class="remember-profile-view-modern">
				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Basic Information', 'remember' ); ?></h3>
					<div class="remember-profile-view-grid">
						<div class="remember-profile-view-item">
							<strong class="remember-profile-view-label"><?php esc_html_e( 'Nickname', 'remember' ); ?></strong>
							<span class="remember-profile-view-value"><?php echo esc_html( $nickname_value ); ?></span>
						</div>
						<div class="remember-profile-view-item">
							<strong class="remember-profile-view-label"><?php esc_html_e( 'Display Name', 'remember' ); ?></strong>
							<span class="remember-profile-view-value"><?php echo esc_html( $user->display_name ); ?></span>
						</div>
						<?php
						$remember_legal_name_line = trim( Remember_Import_Export::member_list_legal_name_line( $profile, (int) $user->ID ) );
						if ( ! empty( $remember_legal_name_line ) ) :
							?>
							<div class="remember-profile-view-item">
								<strong class="remember-profile-view-label"><?php esc_html_e( 'Legal Name (private)', 'remember' ); ?></strong>
								<span class="remember-profile-view-value"><?php echo esc_html( $remember_legal_name_line ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $profile->cell_phone ) ) : ?>
							<div class="remember-profile-view-item">
								<strong class="remember-profile-view-label"><?php esc_html_e( 'Cell Phone', 'remember' ); ?></strong>
								<span class="remember-profile-view-value">
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile->cell_phone ) ); ?>">
										<?php echo esc_html( $profile->cell_phone ); ?>
									</a>
								</span>
							</div>
						<?php endif; ?>
						<?php
						$selected_timezone = get_user_meta( $user->ID, 'timezone_string', true );
						if ( ! empty( $selected_timezone ) ) :
						?>
							<div class="remember-profile-view-item">
								<strong class="remember-profile-view-label"><?php esc_html_e( 'Time Zone', 'remember' ); ?></strong>
								<span class="remember-profile-view-value"><?php echo esc_html( $selected_timezone ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( ! empty( $profile->address_street ) || ! empty( $profile->address_city ) || ! empty( $profile->address_state ) || ! empty( $profile->address_postal ) || ! empty( $profile->address_country ) ) : ?>
					<div class="remember-form-section">
						<h3 class="remember-form-section-title"><?php esc_html_e( 'Address', 'remember' ); ?></h3>
						<div class="remember-profile-view-grid">
							<?php if ( ! empty( $profile->address_street ) ) : ?>
								<div class="remember-profile-view-item remember-profile-view-item-full">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Street Address', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->address_street ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->address_city ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'City', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->address_city ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->address_state ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'State/Province', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->address_state ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->address_postal ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Postal Code', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->address_postal ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->address_country ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Country', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->address_country ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php
				// Get social media profiles
				$social_profiles = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT sm.*, p.platform_name 
						FROM {$wpdb->prefix}remember_member_social_media sm
						JOIN {$wpdb->prefix}remember_social_media_platforms p ON sm.platform_id = p.platform_id
						WHERE sm.member_id = %d
						ORDER BY sm.created_at ASC",
						$user->ID
					)
				);
				if ( ! empty( $social_profiles ) ) :
				?>
					<div class="remember-form-section">
						<h3 class="remember-form-section-title"><?php esc_html_e( 'Social Media Profiles', 'remember' ); ?></h3>
						<div class="remember-profile-view-grid">
							<?php foreach ( $social_profiles as $social ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php echo esc_html( $social->platform_name ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $social->handle ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Dietary Restrictions', 'remember' ); ?></h3>
					<div class="remember-profile-view-item remember-profile-view-item-full">
						<span class="remember-profile-view-value"><?php echo esc_html( ! empty( $selected_dietary_names ) ? implode( ', ', $selected_dietary_names ) : __( 'None Selected', 'remember' ) ); ?></span>
					</div>
				</div>

				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Medical Accommodations', 'remember' ); ?></h3>
					<div class="remember-profile-view-item remember-profile-view-item-full">
						<span class="remember-profile-view-value"><?php echo esc_html( ! empty( $selected_medical_names ) ? implode( ', ', $selected_medical_names ) : __( 'None Selected', 'remember' ) ); ?></span>
					</div>
				</div>

				<div class="remember-form-section">
					<h3 class="remember-form-section-title"><?php esc_html_e( 'Known Allergies', 'remember' ); ?></h3>
					<div class="remember-profile-view-item remember-profile-view-item-full">
						<span class="remember-profile-view-value"><?php echo esc_html( ! empty( $selected_allergy_names ) ? implode( ', ', $selected_allergy_names ) : __( 'None Selected', 'remember' ) ); ?></span>
					</div>
				</div>

				<?php if ( ! empty( $profile->im_handle ) ) : ?>
					<div class="remember-form-section">
						<h3 class="remember-form-section-title"><?php esc_html_e( 'Instant Messenger', 'remember' ); ?></h3>
						<div class="remember-profile-view-grid">
							<div class="remember-profile-view-item">
								<strong class="remember-profile-view-label"><?php esc_html_e( 'IM Type', 'remember' ); ?></strong>
								<span class="remember-profile-view-value"><?php echo esc_html( ucfirst( $profile->im_type ) ); ?></span>
							</div>
							<div class="remember-profile-view-item">
								<strong class="remember-profile-view-label"><?php esc_html_e( 'Handle', 'remember' ); ?></strong>
								<span class="remember-profile-view-value"><?php echo esc_html( $profile->im_handle ); ?></span>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $profile->interests ) ) : ?>
					<div class="remember-form-section">
						<h3 class="remember-form-section-title"><?php esc_html_e( 'Interests', 'remember' ); ?></h3>
						<div class="remember-profile-view-item remember-profile-view-item-full">
							<span class="remember-profile-view-value remember-richtext"><?php echo wp_kses_post( wpautop( $profile->interests ) ); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $profile->emergency_contact_first ) || ! empty( $profile->emergency_contact_last ) || ! empty( $profile->emergency_contact_phone ) || ! empty( $profile->emergency_contact_relationship ) ) : ?>
					<div class="remember-form-section">
						<h3 class="remember-form-section-title"><?php esc_html_e( 'Emergency Contact', 'remember' ); ?></h3>
						<div class="remember-profile-view-grid">
							<?php if ( ! empty( $profile->emergency_contact_first ) || ! empty( $profile->emergency_contact_last ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Name', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( trim( ( $profile->emergency_contact_first ?? '' ) . ' ' . ( $profile->emergency_contact_last ?? '' ) ) ); ?></span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->emergency_contact_phone ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Phone', 'remember' ); ?></strong>
									<span class="remember-profile-view-value">
										<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $profile->emergency_contact_phone ) ); ?>">
											<?php echo esc_html( $profile->emergency_contact_phone ); ?>
										</a>
									</span>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $profile->emergency_contact_relationship ) ) : ?>
								<div class="remember-profile-view-item">
									<strong class="remember-profile-view-label"><?php esc_html_e( 'Relationship', 'remember' ); ?></strong>
									<span class="remember-profile-view-value"><?php echo esc_html( $profile->emergency_contact_relationship ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<p class="remember-description"><?php esc_html_e( 'No profile information yet. Edit your profile to get started.', 'remember' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
