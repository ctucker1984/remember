<?php
/**
 * Drop XML-RPC pingbacks; leave the endpoint up for Jetpack and the WP app.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * XML-RPC pingback hardening.
 *
 * xmlrpc.php must stay reachable: Jetpack and the WordPress mobile app use it,
 * including system.multicall. Brute-force via multicall is a server concern
 * (fail2ban / rate-limit / WAF), not a plugin 403.
 *
 * Pingbacks are unused and are an SSRF vector, so those methods and the
 * X-Pingback header are removed.
 */
class Remember_Xmlrpc {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'xmlrpc_methods', array( __CLASS__, 'filter_xmlrpc_methods' ) );
		add_filter( 'wp_headers', array( __CLASS__, 'strip_pingback_header' ) );
		add_filter( 'pings_open', array( __CLASS__, 'close_pings' ), 999 );
	}

	/**
	 * Unregister pingback methods only. system.multicall stays.
	 *
	 * @param array<string,callable> $methods Registered methods.
	 * @return array<string,callable>
	 */
	public static function filter_xmlrpc_methods( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Stop advertising xmlrpc.php as a pingback target.
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
}
