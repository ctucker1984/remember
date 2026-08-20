<?php
/**
 * Quiet hosting chrome on wp-login.php.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Hide the Bluehost (Newfold) “Login with Bluehost” control on the WordPress
 * login screen. Members should use username/password. Admins still use
 * wp-login.php and /wp-admin; Bluehost Account Manager → Log in to WordPress
 * still works. Filter remember_hide_hosting_sso to keep the button.
 */
class Remember_Login_Screen {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'newfold/sso/hosting_login', array( __CLASS__, 'disable_newfold_hosting_login' ), PHP_INT_MAX );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'hide_hosting_login_css' ), 99 );
	}

	/**
	 * Whether the hosting SSO button should be suppressed.
	 *
	 * @return bool
	 */
	public static function should_hide() {
		/**
		 * Hide Newfold/Bluehost “Login with …” on wp-login.php.
		 *
		 * @param bool $hide True to hide (default).
		 */
		return (bool) apply_filters( 'remember_hide_hosting_sso', true );
	}

	/**
	 * Official Newfold disable: incomplete config is not rendered.
	 *
	 * @param mixed $config Filter payload from wp-module-sso.
	 * @return array
	 */
	public static function disable_newfold_hosting_login( $config ) {
		if ( ! is_array( $config ) ) {
			$config = array();
		}
		if ( self::should_hide() ) {
			$config['enabled'] = false;
		}
		return $config;
	}

	/**
	 * CSS fallback if an older Bluehost build still prints the markup.
	 *
	 * @return void
	 */
	public static function hide_hosting_login_css() {
		if ( ! self::should_hide() ) {
			return;
		}

		$handle = 'remember-hide-hosting-sso';
		wp_register_style( $handle, false, array(), defined( 'REMEMBER_VERSION' ) ? REMEMBER_VERSION : '1.0.0' );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'.nfd-sso-hosting-login{display:none!important;}'
		);
	}
}
