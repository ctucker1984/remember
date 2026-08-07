<?php
/**
 * Public member registration form (minimal signup).
 *
 * @package    reMember
 * @subpackage reMember/public/partials
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Variables: $remember_register_success, $remember_register_error_message (optional).
$remember_register_success = isset( $remember_register_success ) ? (bool) $remember_register_success : false;
$remember_register_error_message = isset( $remember_register_error_message ) ? $remember_register_error_message : '';

$remember_pw_min = (int) apply_filters( 'remember_registration_password_min_length', 8 );
if ( $remember_pw_min < 1 ) {
	$remember_pw_min = 8;
}

$remember_options      = get_option( 'remember_options', array() );
$photo_max_dimensions  = isset( $remember_options['photo_max_dimensions'] ) ? absint( $remember_options['photo_max_dimensions'] ) : 800;
$photo_max_bytes       = isset( $remember_options['photo_max_size'] ) ? absint( $remember_options['photo_max_size'] ) : 2097152;
if ( $photo_max_dimensions < 1 ) {
	$photo_max_dimensions = 800;
}
if ( $photo_max_bytes < 1 ) {
	$photo_max_bytes = 2097152;
}

?>
<div class="remember-register remember-register-form">
	<?php if ( $remember_register_success ) : ?>
		<div class="remember-notice remember-success" role="status">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Log in link */
						__( 'Your member account was created. %s', 'remember' ),
						'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'remember' ) . '</a>'
					)
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<?php if ( $remember_register_error_message ) : ?>
			<div class="remember-notice remember-error" role="alert">
				<p><?php echo esc_html( $remember_register_error_message ); ?></p>
			</div>
		<?php endif; ?>

		<form class="remember-register-form__inner" method="post" action="" autocomplete="on" enctype="multipart/form-data">
			<?php wp_nonce_field( 'remember_register_member', 'remember_register_nonce' ); ?>
			<input type="hidden" name="remember_register_submit" value="1" />

			<div class="remember-register-hp" aria-hidden="true">
				<input type="text" name="remember_hp" value="" tabindex="-1" autocomplete="off" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_username"><?php esc_html_e( 'Username', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_username" id="remember_reg_username" required autocomplete="username" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_username'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_username'] ) ) ) : ''; ?>" />
				<p class="remember-register-help"><?php esc_html_e( 'Used to log into the website. Usernames cannot be changed later.', 'remember' ); ?></p>
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_display_name"><?php esc_html_e( 'Display Name', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_display_name" id="remember_reg_display_name" required autocomplete="nickname" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_display_name'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_display_name'] ) ) ) : ''; ?>" />
				<p class="remember-register-help"><?php esc_html_e( 'How you appear to other members. This is never set from your legal name unless you manually change it later.', 'remember' ); ?></p>
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_first_name"><?php esc_html_e( 'Legal First Name', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_first_name" id="remember_reg_first_name" required autocomplete="given-name" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_first_name'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_first_name'] ) ) ) : ''; ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_last_name"><?php esc_html_e( 'Legal Last Name', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_last_name" id="remember_reg_last_name" required autocomplete="family-name" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_last_name'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_last_name'] ) ) ) : ''; ?>" />
				<p class="remember-register-help"><?php esc_html_e( 'Used for admin and vetting only — not shown to other members.', 'remember' ); ?></p>
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_email"><?php esc_html_e( 'Email Address', 'remember' ); ?> <span class="required">*</span></label>
				<input type="email" name="remember_reg_email" id="remember_reg_email" required autocomplete="email" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['remember_reg_email'] ) ) ) : ''; ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_cell_phone"><?php esc_html_e( 'Cell Phone', 'remember' ); ?> <span class="required">*</span></label>
				<input type="tel" name="remember_reg_cell_phone" id="remember_reg_cell_phone" required autocomplete="tel" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_cell_phone'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_cell_phone'] ) ) ) : ''; ?>" placeholder="<?php esc_attr_e( '+18055551212', 'remember' ); ?>" />
				<p class="remember-register-help"><?php esc_html_e( 'Include a leading + and country code. Examples: +18055551212 (USA/Canada), +447700900123 (UK).', 'remember' ); ?></p>
			</div>

			<?php
			require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-timezone.php';
			$remember_reg_timezone = isset( $_POST['remember_reg_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['remember_reg_timezone'] ) ) : 'America/Los_Angeles';
			?>
			<div class="remember-register-row">
				<label for="remember_reg_timezone"><?php esc_html_e( 'Time Zone', 'remember' ); ?> <span class="required">*</span></label>
				<?php echo Remember_Timezone::dropdown( $remember_reg_timezone, 'remember_reg_timezone', 'remember_reg_timezone', true, 'remember-register-input' ); ?>
				<p class="remember-register-help"><?php esc_html_e( 'Used to display scheduled times in your local time.', 'remember' ); ?></p>
			</div>

			<div class="remember-register-row remember-register-row--photo">
				<span class="remember-register-label"><?php esc_html_e( 'Profile Photo', 'remember' ); ?></span>
				<p class="remember-register-help"><?php esc_html_e( 'Optional. Drag to recenter and use zoom to frame the photo. You can change this later in your profile.', 'remember' ); ?></p>
				<div class="remember-profile-photo-edit" data-output-size="<?php echo esc_attr( (string) $photo_max_dimensions ); ?>">
					<div class="remember-profile-photo-cropper" hidden>
						<div class="remember-profile-photo-cropper-viewport" aria-label="<?php esc_attr_e( 'Photo framing preview', 'remember' ); ?>">
							<img src="" alt="" class="remember-profile-photo-cropper-image" draggable="false">
						</div>
						<div class="remember-profile-photo-cropper-controls">
							<button type="button" class="remember-button remember-button-secondary remember-photo-zoom-out" aria-label="<?php esc_attr_e( 'Zoom out', 'remember' ); ?>">−</button>
							<input type="range" class="remember-photo-zoom-range" min="1" max="3" step="0.01" value="1" aria-label="<?php esc_attr_e( 'Zoom', 'remember' ); ?>">
							<button type="button" class="remember-button remember-button-secondary remember-photo-zoom-in" aria-label="<?php esc_attr_e( 'Zoom in', 'remember' ); ?>">+</button>
						</div>
						<p class="remember-register-help"><?php esc_html_e( 'Drag to recenter. Use zoom to frame the photo inside the circle.', 'remember' ); ?></p>
						<button type="button" class="remember-button remember-button-secondary remember-photo-clear">
							<?php esc_html_e( 'Clear selected photo', 'remember' ); ?>
						</button>
					</div>
					<label for="remember_reg_photo_file" class="remember-form-label"><?php esc_html_e( 'Upload photo', 'remember' ); ?></label>
					<input type="file" id="remember_reg_photo_file" name="photo_file" class="remember-form-control remember-form-control-file remember-register-input" accept="image/jpeg,image/png,image/gif">
					<p class="remember-register-help">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: max px, 2: max MB */
								__( 'Square crop from your framing, max %1$dpx. Maximum file size %2$d MB. JPEG, PNG, or GIF.', 'remember' ),
								$photo_max_dimensions,
								(int) max( 1, round( $photo_max_bytes / 1024 / 1024 ) )
							)
						);
						?>
					</p>
				</div>
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_password"><?php esc_html_e( 'Password', 'remember' ); ?> <span class="required">*</span></label>
				<input type="password" name="remember_reg_password" id="remember_reg_password" required autocomplete="new-password" class="remember-register-input" minlength="<?php echo esc_attr( (string) $remember_pw_min ); ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_password_confirm"><?php esc_html_e( 'Confirm Password', 'remember' ); ?> <span class="required">*</span></label>
				<input type="password" name="remember_reg_password_confirm" id="remember_reg_password_confirm" required autocomplete="new-password" class="remember-register-input" minlength="<?php echo esc_attr( (string) $remember_pw_min ); ?>" />
			</div>

			<div class="remember-register-row remember-register-row--actions">
				<span class="remember-register-row__spacer" aria-hidden="true"></span>
				<div class="remember-register-actions">
					<button type="submit" class="button button-primary remember-register-button"><?php esc_html_e( 'Create member account', 'remember' ); ?></button>
				</div>
			</div>
		</form>
	<?php endif; ?>
</div>
