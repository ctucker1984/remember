<?php
/**
 * Restrict unauthenticated WordPress REST user listing.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stop GET /wp-json/wp/v2/users from handing out login slugs to anyone.
 *
 * Editors and admins keep access (block editor author pickers). Logged-in
 * users can still read themselves (/users/me and their own id).
 */
class Remember_Rest_Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_endpoints', array( __CLASS__, 'filter_endpoints' ) );
	}

	/**
	 * Wrap /wp/v2/users* permission callbacks.
	 *
	 * @param array<string,mixed> $endpoints Registered routes.
	 * @return array<string,mixed>
	 */
	public static function filter_endpoints( $endpoints ) {
		foreach ( $endpoints as $route => $handlers ) {
			if ( 0 !== strpos( $route, '/wp/v2/users' ) ) {
				continue;
			}
			if ( ! is_array( $handlers ) ) {
				continue;
			}
			foreach ( $handlers as $i => $handler ) {
				if ( ! is_array( $handler ) ) {
					continue;
				}
				$orig = isset( $handler['permission_callback'] ) ? $handler['permission_callback'] : null;
				$endpoints[ $route ][ $i ]['permission_callback'] = static function ( $request ) use ( $orig, $route ) {
					return Remember_Rest_Privacy::users_permission( $request, $orig, $route );
				};
			}
		}
		return $endpoints;
	}

	/**
	 * Whether this REST users request is allowed.
	 *
	 * @param mixed                $request Request.
	 * @param callable|string|null $orig    Original permission callback.
	 * @param string               $route   Route pattern.
	 * @return bool|WP_Error
	 */
	public static function users_permission( $request, $orig, $route ) {
		if ( current_user_can( 'list_users' ) || current_user_can( 'edit_posts' ) ) {
			return is_callable( $orig ) ? $orig( $request ) : true;
		}

		if ( is_user_logged_in() && false !== strpos( $route, '/me' ) ) {
			return is_callable( $orig ) ? $orig( $request ) : true;
		}

		if ( is_user_logged_in() && $request instanceof WP_REST_Request ) {
			$id = absint( $request['id'] );
			if ( $id > 0 && $id === get_current_user_id() ) {
				return is_callable( $orig ) ? $orig( $request ) : true;
			}
		}

		return new WP_Error(
			'rest_user_cannot_view',
			__( 'Sorry, you are not allowed to list users.', 'remember' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
