<?php
/**
 * Xero API wrapper class (Phase 1 shell).
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-xero-oauth.php';
require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * Xero Accounting API helpers.
 *
 * Phase 1: auth header helpers + organisation display.
 * Contact/invoice/payment methods land in later phases.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_Xero_API {

	const API_BASE = 'https://api.xero.com/api.xro/2.0/';

	/**
	 * Ensure a valid access token (refresh if expired / near expiry).
	 *
	 * @return array|WP_Error Settings with access_token, or error.
	 */
	public static function ensure_access_token() {
		$settings = Remember_Xero_OAuth::get_settings();
		if ( ! $settings || empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			return new WP_Error( 'xero_not_configured', __( 'Xero credentials are not configured.', 'remember' ) );
		}

		$expires_at = isset( $settings['expires_at'] ) ? (int) $settings['expires_at'] : 0;
		$needs_refresh = empty( $settings['access_token'] ) || ( $expires_at > 0 && $expires_at <= ( time() + 60 ) );

		if ( ! $needs_refresh ) {
			return $settings;
		}

		if ( empty( $settings['refresh_token'] ) ) {
			return new WP_Error( 'xero_reauth_required', __( 'Xero connection expired. Please reconnect.', 'remember' ) );
		}

		$token_data = Remember_Xero_OAuth::refresh_token(
			$settings['refresh_token'],
			$settings['client_id'],
			$settings['client_secret']
		);

		if ( is_wp_error( $token_data ) ) {
			return $token_data;
		}

		$settings['access_token']  = isset( $token_data['access_token'] ) ? $token_data['access_token'] : '';
		if ( ! empty( $token_data['refresh_token'] ) ) {
			$settings['refresh_token'] = $token_data['refresh_token'];
		}
		$expires_in = isset( $token_data['expires_in'] ) ? (int) $token_data['expires_in'] : 1800;
		if ( $expires_in < 60 ) {
			$expires_in = 1800;
		}
		$settings['expires_at'] = time() + $expires_in;

		Remember_Xero_OAuth::save_settings( $settings );

		// Re-load so decrypted tokens are present for callers.
		return Remember_Xero_OAuth::get_settings();
	}

	/**
	 * Perform an authenticated Xero API request.
	 *
	 * @param string $method   HTTP method.
	 * @param string $path     Path under api.xro/2.0/ (no leading slash), or absolute URL.
	 * @param array  $body     Optional JSON body (array).
	 * @param array  $query    Optional query args.
	 * @return array|WP_Error Decoded JSON or error.
	 */
	public static function request( $method, $path, $body = null, $query = array() ) {
		$settings = self::ensure_access_token();
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		if ( empty( $settings['tenant_id'] ) ) {
			return new WP_Error( 'xero_no_tenant', __( 'No Xero organisation (tenant) is connected.', 'remember' ) );
		}

		$url = ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) )
			? $path
			: self::API_BASE . ltrim( $path, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Accept'          => 'application/json',
				'Content-Type'    => 'application/json',
				'Authorization'   => 'Bearer ' . $settings['access_token'],
				'Xero-tenant-id'  => $settings['tenant_id'],
			),
			'timeout' => 45,
		);

		if ( null !== $body && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status   = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = __( 'Xero API request failed.', 'remember' );
			if ( is_array( $decoded ) ) {
				if ( ! empty( $decoded['Message'] ) ) {
					$message = $decoded['Message'];
				} elseif ( ! empty( $decoded['Detail'] ) ) {
					$message = $decoded['Detail'];
				} elseif ( ! empty( $decoded['Elements'][0]['ValidationErrors'][0]['Message'] ) ) {
					$message = $decoded['Elements'][0]['ValidationErrors'][0]['Message'];
				}
			}
			Remember_Logger::error(
				'Xero API error',
				array(
					'status' => $status,
					'path'   => $path,
					'body'   => $decoded ? $decoded : $raw_body,
				)
			);
			return new WP_Error( 'xero_api_error', $message, array( 'status' => $status, 'body' => $decoded ) );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Fetch organisation info for the connected tenant (Settings UI).
	 *
	 * @return array|WP_Error Organisation payload or error.
	 */
	public static function get_organisation() {
		$result = self::request( 'GET', 'Organisation' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['Organisations'][0] ) && is_array( $result['Organisations'][0] ) ) {
			return $result['Organisations'][0];
		}
		return $result;
	}
}
