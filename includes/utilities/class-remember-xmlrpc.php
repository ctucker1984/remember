<?php
/**
 * Disable WordPress XML-RPC by default (brute-force / pingback amplifier).
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * XML-RPC hardening.
 *
 * xmlrpc.php is unused by reMember. Leaving it on lets attackers pack many
 * login attempts into one request (system.multicall) and abuse pingbacks.
 * Front-end pages, wp-admin, REST, and member flows do not use XML-RPC.
 *
 * Re-enable for Jetpack or the WordPress mobile app:
 * add_filter( 'remember_xmlrpc_enabled', '__return_true' );
 * system.multicall and pingbacks stay disabled even then.
 */
class Remember_Xmlrpc {

	/**
	 * Register hooks. Safe to call from plugins_loaded (xmlrpc.php loads WP first).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_forbid_xmlrpc_request' ), 0 );
		add_filter( 'xmlrpc_enabled', array( __CLASS__, 'filter_xmlrpc_enabled' ) );
		add_filter( 'xmlrpc_methods', array( __CLASS__, 'filter_xmlrpc_methods' ) );
		add_filter( 'wp_headers', array( __CLASS__, 'strip_pingback_header' ) );
		add_filter( 'pings_open', array( __CLASS__, 'close_pings' ), 999 );
		add_action( 'init', array( __CLASS__, 'remove_discovery_links' ), 11 );
	}

	/**
	 * Whether XML-RPC (except stripped methods) should run.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		/**
		 * Enable WordPress XML-RPC. Default false.
		 *
		 * @param bool $enabled Whether XML-RPC requests should be processed.
		 */
		return (bool) apply_filters( 'remember_xmlrpc_enabled', false );
	}

	/**
	 * Refuse xmlrpc.php before the server parses the payload.
	 *
	 * @return void
	 */
	public static function maybe_forbid_xmlrpc_request() {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}
		if ( self::is_enabled() ) {
			return;
		}

		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html__( 'XML-RPC is disabled.', 'remember' );
		exit;
	}

	/**
	 * Tell WordPress XML-RPC is off (covers code paths that never define XMLRPC_REQUEST).
	 *
	 * @param bool $enabled Incoming value.
	 * @return bool
	 */
	public static function filter_xmlrpc_enabled( $enabled ) {
		unset( $enabled );
		return self::is_enabled();
	}

	/**
	 * Drop the multicall brute-force amplifier and pingback methods even if XML-RPC is re-enabled.
	 *
	 * @param array<string,callable> $methods Registered methods.
	 * @return array<string,callable>
	 */
	public static function filter_xmlrpc_methods( $methods ) {
		unset( $methods['system.multicall'] );
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Stop advertising xmlrpc.php via the Pingback header.
	 *
	 * @param array<string,string> $headers Response headers.
	 * @return array<string,string>
	 */
	public static function strip_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Close pingbacks/trackbacks on posts.
	 *
	 * @param bool $open Whether pings are open.
	 * @return bool
	 */
	public static function close_pings( $open ) {
		unset( $open );
		return false;
	}

	/**
	 * Remove RSD / Windows Live Writer links that point at xmlrpc.php.
	 *
	 * @return void
	 */
	public static function remove_discovery_links() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}
}
