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

		<form class="remember-register-form__inner" method="post" action="" autocomplete="on">
			<?php wp_nonce_field( 'remember_register_member', 'remember_register_nonce' ); ?>
			<input type="hidden" name="remember_register_submit" value="1" />

			<div class="remember-register-hp" aria-hidden="true">
				<input type="text" name="remember_hp" value="" tabindex="-1" autocomplete="off" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_username"><?php esc_html_e( 'Username', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_username" id="remember_reg_username" required autocomplete="username" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_username'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_username'] ) ) ) : ''; ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_first_name"><?php esc_html_e( 'First Name', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_first_name" id="remember_reg_first_name" required autocomplete="given-name" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_first_name'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_first_name'] ) ) ) : ''; ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_last_name"><?php esc_html_e( 'Last Name', 'remember' ); ?> <span class="required">*</span></label>
				<input type="text" name="remember_reg_last_name" id="remember_reg_last_name" required autocomplete="family-name" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_last_name'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['remember_reg_last_name'] ) ) ) : ''; ?>" />
			</div>

			<div class="remember-register-row">
				<label for="remember_reg_email"><?php esc_html_e( 'Email Address', 'remember' ); ?> <span class="required">*</span></label>
				<input type="email" name="remember_reg_email" id="remember_reg_email" required autocomplete="email" class="remember-register-input" value="<?php echo isset( $_POST['remember_reg_email'] ) ? esc_attr( sanitize_email( wp_unslash( $_POST['remember_reg_email'] ) ) ) : ''; ?>" />
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
