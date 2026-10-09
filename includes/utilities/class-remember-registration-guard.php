<?php
/**
 * CAPTCHA, rate limit, and extension points for public registration.
 *
 * CAPTCHA is off until a provider is chosen. The secret is encrypted with the
 * site key and is not included in backups. The rate limit counts submissions
 * that pass the nonce, from one IP, inside a rolling hour.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Public registration checks that run before an account is created.
 */
class Remember_Registration_Guard {

	const SECRET_OPTION = 'remember_registration_captcha_secret';

	const RATE_DEFAULT = 10;

	const RATE_MAX = 100;

	/**
	 * Count this submission and reject it when the IP is over the limit.
	 *
	 * @return string Error code, or an empty string to continue.
	 */
	public static function limit_attempt() {
		$limit = self::rate_limit();
		if ( $limit < 1 ) {
			return '';
		}
		$ip = self::client_ip();
		if ( '' === $ip ) {
			return '';
		}
		$key   = 'remember_reg_' . md5( $ip );
		$count = get_transient( $key );
		$count = false === $count ? 0 : (int) $count;
		if ( $count >= $limit ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
			Remember_Logger::warning( 'Public member registration: rate limited' );
			Remember_Logger::debug( 'Public member registration: rate limited', array( 'ip' => $ip ) );
			return 'rate_limited';
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return '';
	}

	/**
	 * CAPTCHA, disposable-email filter, and a last chance to stop account creation.
	 *
	 * @param string $username Username.
	 * @param string $email    Email address.
	 * @return string Error code, or an empty string to continue.
	 */
	public static function before_create( $username, $email ) {
		$email  = (string) $email;
		$domain = '';
		$at     = strrchr( $email, '@' );
		if ( is_string( $at ) && strlen( $at ) > 1 ) {
			$domain = strtolower( substr( $at, 1 ) );
		}
		$blocked = apply_filters( 'remember_registration_disposable_email', false, $email, $domain );
		if ( $blocked ) {
			return 'disposable_email';
		}

		$captcha = self::verify_captcha();
		if ( '' !== $captcha ) {
			return $captcha;
		}

		$pre = apply_filters(
			'remember_registration_pre_create',
			true,
			array(
				'username' => (string) $username,
				'email'    => $email,
			)
		);
		if ( true === $pre || null === $pre ) {
			return '';
		}
		if ( is_wp_error( $pre ) ) {
			$code = sanitize_key( $pre->get_error_code() );
			return '' !== $code ? $code : 'rejected';
		}
		if ( is_string( $pre ) && '' !== $pre ) {
			$code = sanitize_key( $pre );
			return '' !== $code ? $code : 'rejected';
		}
		return 'rejected';
	}

	/**
	 * Save registration protection fields from the settings form.
	 *
	 * @param array $options remember_options, updated in place.
	 * @return string Empty on success, or site_key, secret, or seal.
	 */
	public static function apply_settings( array &$options ) {
		$provider = isset( $_POST['registration_captcha_provider'] ) ? sanitize_key( wp_unslash( $_POST['registration_captcha_provider'] ) ) : 'none';
		if ( ! in_array( $provider, array( 'none', 'turnstile', 'hcaptcha' ), true ) ) {
			$provider = 'none';
		}
		$site_key = isset( $_POST['registration_captcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['registration_captcha_site_key'] ) ) : '';
		$site_key = substr( $site_key, 0, 200 );
		$limit    = isset( $_POST['registration_rate_limit'] ) ? absint( $_POST['registration_rate_limit'] ) : self::RATE_DEFAULT;
		if ( $limit > self::RATE_MAX ) {
			$limit = self::RATE_MAX;
		}
		$options['registration_rate_limit'] = $limit;

		if ( 'none' === $provider ) {
			$options['registration_captcha_provider'] = 'none';
			$options['registration_captcha_site_key'] = '';
			delete_option( self::SECRET_OPTION );
			return '';
		}

		if ( '' === $site_key ) {
			return 'site_key';
		}

		$posted_secret = isset( $_POST['registration_captcha_secret'] ) ? trim( (string) wp_unslash( $_POST['registration_captcha_secret'] ) ) : '';
		$posted_secret = str_replace( array( "\r", "\n", "\0" ), '', $posted_secret );
		$existing      = get_option( self::SECRET_OPTION, '' );
		$has_secret    = is_string( $existing ) && '' !== $existing;
		if ( '' === $posted_secret && ! $has_secret ) {
			return 'secret';
		}
		if ( '' !== $posted_secret ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-billing-crypto.php';
			$sealed = Remember_Billing_Crypto::seal_site( $posted_secret );
			if ( ! is_string( $sealed ) || '' === $sealed ) {
				return 'seal';
			}
			update_option( self::SECRET_OPTION, $sealed, false );
		}

		$options['registration_captcha_provider'] = $provider;
		$options['registration_captcha_site_key'] = $site_key;
		return '';
	}

	/**
	 * Provider selected in settings.
	 *
	 * @return string none|turnstile|hcaptcha
	 */
	public static function provider() {
		$options  = get_option( 'remember_options', array() );
		$provider = is_array( $options ) && isset( $options['registration_captcha_provider'] ) ? (string) $options['registration_captcha_provider'] : 'none';
		if ( ! in_array( $provider, array( 'turnstile', 'hcaptcha' ), true ) ) {
			return 'none';
		}
		return $provider;
	}

	/**
	 * Public site key. Empty when CAPTCHA is off.
	 *
	 * @return string
	 */
	public static function site_key() {
		if ( 'none' === self::provider() ) {
			return '';
		}
		$options = get_option( 'remember_options', array() );
		$key     = is_array( $options ) && isset( $options['registration_captcha_site_key'] ) ? (string) $options['registration_captcha_site_key'] : '';
		return $key;
	}

	/**
	 * Whether a secret is stored.
	 *
	 * @return bool
	 */
	public static function has_secret() {
		$stored = get_option( self::SECRET_OPTION, '' );
		return is_string( $stored ) && '' !== $stored;
	}

	/**
	 * Configured attempts per IP per hour. Zero disables the limit.
	 *
	 * @return int
	 */
	public static function rate_limit() {
		$options = get_option( 'remember_options', array() );
		if ( ! is_array( $options ) || ! array_key_exists( 'registration_rate_limit', $options ) ) {
			return self::RATE_DEFAULT;
		}
		$limit = absint( $options['registration_rate_limit'] );
		if ( $limit > self::RATE_MAX ) {
			return self::RATE_MAX;
		}
		return $limit;
	}

	/**
	 * Load the provider script on the registration form.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$provider = self::provider();
		if ( 'turnstile' === $provider && '' !== self::site_key() ) {
			wp_enqueue_script( 'remember-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'in_footer' => true, 'strategy' => 'async' ) );
		}
		if ( 'hcaptcha' === $provider && '' !== self::site_key() ) {
			wp_enqueue_script( 'remember-hcaptcha', 'https://js.hcaptcha.com/1/api.js', array(), null, array( 'in_footer' => true, 'strategy' => 'async' ) );
		}
	}

	/**
	 * Widget markup for the registration form.
	 *
	 * @return string
	 */
	public static function widget_html() {
		$provider = self::provider();
		$site_key = self::site_key();
		if ( 'none' === $provider || '' === $site_key ) {
			return '';
		}
		if ( 'turnstile' === $provider ) {
			return '<div class="remember-register-row remember-register-captcha"><div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '"></div></div>';
		}
		return '<div class="remember-register-row remember-register-captcha"><div class="h-captcha" data-sitekey="' . esc_attr( $site_key ) . '"></div></div>';
	}

	/**
	 * Read a provider verify response.
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	public static function interpret_verify_body( $body ) {
		$data = json_decode( (string) $body, true );
		return is_array( $data ) && ! empty( $data['success'] );
	}

	/**
	 * @return string Error code, or an empty string when the check passes.
	 */
	private static function verify_captcha() {
		$provider = self::provider();
		if ( 'none' === $provider ) {
			return '';
		}
		$secret = self::secret();
		if ( '' === self::site_key() || '' === $secret ) {
			return 'captcha_failed';
		}
		$field = 'turnstile' === $provider ? 'cf-turnstile-response' : 'h-captcha-response';
		$token = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' === $token ) {
			return 'captcha_failed';
		}
		$url      = 'turnstile' === $provider ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://api.hcaptcha.com/siteverify';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => self::client_ip(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
			Remember_Logger::warning( 'Public member registration: CAPTCHA verify request failed' );
			return 'captcha_failed';
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! self::interpret_verify_body( $body ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
			Remember_Logger::warning( 'Public member registration: CAPTCHA rejected' );
			return 'captcha_failed';
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function secret() {
		$stored = get_option( self::SECRET_OPTION, '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-billing-crypto.php';
		$plain = Remember_Billing_Crypto::open_site( $stored );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ip = '';
		}
		$ip = apply_filters( 'remember_registration_client_ip', $ip );
		$ip = is_string( $ip ) ? $ip : '';
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		return $ip;
	}
}
