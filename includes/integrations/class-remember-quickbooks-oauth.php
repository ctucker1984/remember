<?php
/**
 * QuickBooks OAuth integration class
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * QuickBooks OAuth integration class.
 *
 * Handles OAuth 2.0 authentication flow with QuickBooks Online.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_QuickBooks_OAuth {

	/**
	 * QuickBooks OAuth endpoints.
	 */
	const QB_AUTH_URL = 'https://appcenter.intuit.com/connect/oauth2';
	const QB_TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
	const QB_REVOKE_URL = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

	/**
	 * OAuth redirect URI registered with Intuit and sent on authorize/token exchange.
	 *
	 * @return string Redirect URI for this site’s admin settings tab.
	 */
	public static function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=remember-settings&tab=quickbooks&qb_oauth_callback=1' );
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @param string $client_id Client ID.
	 * @param string $redirect_uri Redirect URI.
	 * @param string $scope OAuth scope (default: accounting).
	 * @return string Authorization URL.
	 */
	public static function get_authorization_url( $client_id, $redirect_uri, $scope = 'com.intuit.quickbooks.accounting' ) {
		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'scope'         => $scope,
			'redirect_uri'  => $redirect_uri,
			'state'         => wp_create_nonce( 'remember_qb_oauth' ),
		);

		return self::QB_AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @param string $redirect_uri Redirect URI.
	 * @return array|WP_Error Token data or error.
	 */
	public static function exchange_code_for_token( $code, $client_id, $client_secret, $redirect_uri ) {
		$response = wp_remote_post(
			self::QB_TOKEN_URL,
			array(
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
				'body' => array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new WP_Error(
				'qb_oauth_error',
				isset( $body['error_description'] ) ? $body['error_description'] : __( 'Failed to exchange authorization code for token.', 'remember' ),
				$body
			);
		}

		return $body;
	}

	/**
	 * Refresh access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @return array|WP_Error Token data or error.
	 */
	public static function refresh_token( $refresh_token, $client_id, $client_secret ) {
		$response = wp_remote_post(
			self::QB_TOKEN_URL,
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
				'body' => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new WP_Error(
				'qb_refresh_error',
				isset( $body['error_description'] ) ? $body['error_description'] : __( 'Failed to refresh access token.', 'remember' ),
				$body
			);
		}

		return $body;
	}

	/**
	 * Revoke access token.
	 *
	 * @param string $token Access token or refresh token.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function revoke_token( $token, $client_id, $client_secret ) {
		$response = wp_remote_post(
			self::QB_REVOKE_URL,
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				),
				'body' => array(
					'token' => $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return $status_code === 200;
	}

	/**
	 * Get stored OAuth settings.
	 *
	 * @return array|false Settings array or false if not found.
	 */
	public static function get_settings() {
		global $wpdb;
		$processor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_payment_processors WHERE processor_type = %s",
				'quickbooks'
			)
		);

		if ( ! $processor ) {
			return false;
		}

		$settings = ! empty( $processor->settings ) ? json_decode( $processor->settings, true ) : array();

		// Decrypt sensitive data if encrypted
		if ( ! empty( $settings['client_secret_encrypted'] ) ) {
			$settings['client_secret'] = self::decrypt( $settings['client_secret_encrypted'] );
		}
		if ( ! empty( $settings['access_token_encrypted'] ) ) {
			$settings['access_token'] = self::decrypt( $settings['access_token_encrypted'] );
		}
		if ( ! empty( $settings['refresh_token_encrypted'] ) ) {
			$settings['refresh_token'] = self::decrypt( $settings['refresh_token_encrypted'] );
		}

		// Trim whitespace/newlines that break the Authorization header or OAuth body.
		foreach ( array( 'client_secret', 'access_token', 'refresh_token' ) as $k ) {
			if ( isset( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
				$settings[ $k ] = trim( $settings[ $k ] );
			}
		}

		return $settings;
	}

	/**
	 * Save OAuth settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function save_settings( $settings ) {
		global $wpdb;

		// Encrypt sensitive data
		if ( ! empty( $settings['client_secret'] ) ) {
			$settings['client_secret_encrypted'] = self::encrypt( $settings['client_secret'] );
			unset( $settings['client_secret'] );
		}
		if ( ! empty( $settings['access_token'] ) ) {
			$settings['access_token_encrypted'] = self::encrypt( $settings['access_token'] );
			unset( $settings['access_token'] );
		}
		if ( ! empty( $settings['refresh_token'] ) ) {
			$settings['refresh_token_encrypted'] = self::encrypt( $settings['refresh_token'] );
			unset( $settings['refresh_token'] );
		}

		$settings_json = json_encode( $settings );

		$result = $wpdb->update(
			$wpdb->prefix . 'remember_payment_processors',
			array(
				'settings'   => $settings_json,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'processor_type' => 'quickbooks' ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @param string $data Data to encrypt.
	 * @return string Encrypted data.
	 */
	private static function encrypt( $data ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback to base64 if OpenSSL not available (not secure, but better than plain text)
			return base64_encode( $data );
		}

		$key = self::get_encryption_key();
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @param string $data Encrypted data.
	 * @return string Decrypted data.
	 */
	private static function decrypt( $data ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback to base64 decode if OpenSSL not available
			return base64_decode( $data );
		}

		$key = self::get_encryption_key();
		$data = base64_decode( $data );
		$iv_length = openssl_cipher_iv_length( 'AES-256-CBC' );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string Encryption key.
	 */
	private static function get_encryption_key() {
		$key = get_option( 'remember_qb_encryption_key' );
		if ( ! $key ) {
			$key = wp_generate_password( 32, true, true );
			update_option( 'remember_qb_encryption_key', $key );
		}
		return $key;
	}
}
