<?php
/**
 * Page creation utility for reMember plugin
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Page creation utility class.
 *
 * Creates pages with shortcodes and manages them.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Page_Creator {

	/**
	 * Default pages to create.
	 *
	 * @return array
	 */
	public static function get_default_pages() {
		return array(
			'member_register' => array(
				'title'       => __( 'Member Registration', 'remember' ),
				'slug'        => 'member-register',
				'content'     => '<!-- wp:shortcode -->[remember_register]<!-- /wp:shortcode -->',
				'shortcode'   => '[remember_register]',
				'description' => __( 'Sign up for a member account (username, display name, legal name, email, cell phone, time zone, password). Works when Settings → General → Membership is off.', 'remember' ),
			),
			'member_dashboard' => array(
				'title'   => __( 'Member Dashboard', 'remember' ),
				'slug'    => 'member-dashboard',
				'content' => '<!-- wp:shortcode -->[remember_dashboard]<!-- /wp:shortcode -->',
				'shortcode' => '[remember_dashboard]',
				'description' => __( 'Member dashboard showing profile summary, accepted events, and applications.', 'remember' ),
			),
			'events' => array(
				'title'   => __( 'Events', 'remember' ),
				'slug'    => 'events',
				'content' => '<!-- wp:heading {"level":2} --><h2>' . __( 'Available Events', 'remember' ) . '</h2><!-- /wp:heading --><!-- wp:shortcode -->[remember_events status="open"]<!-- /wp:shortcode -->',
				'shortcode' => '[remember_events status="open"]',
				'description' => __( 'Lists all open events available for applications.', 'remember' ),
			),
			'apply' => array(
				'title'   => __( 'Apply for Event', 'remember' ),
				'slug'    => 'apply',
				'content' => '<!-- wp:shortcode -->[remember_apply]<!-- /wp:shortcode -->',
				'shortcode' => '[remember_apply]',
				'description' => __( 'Application form for members to apply for events. Can accept event_id parameter.', 'remember' ),
			),
			'profile' => array(
				'title'   => __( 'My Profile', 'remember' ),
				'slug'    => 'profile',
				'content' => '<!-- wp:shortcode -->[remember_profile]<!-- /wp:shortcode -->',
				'shortcode' => '[remember_profile]',
				'description' => __( 'Member profile view and edit page. Supports ?edit=1 parameter for edit mode.', 'remember' ),
			),
			'event_detail' => array(
				'title'   => __( 'Event Detail', 'remember' ),
				'slug'    => 'event-detail',
				'content' => '<!-- wp:shortcode -->[remember_event_detail]<!-- /wp:shortcode -->',
				'shortcode' => '[remember_event_detail]',
				'description' => __( 'Event detail page showing event information and attendee directory. Requires ?event=ID parameter.', 'remember' ),
			),
		);
	}

	/**
	 * Create pages.
	 *
	 * @param array $pages_to_create Array of page keys to create.
	 * @return array Created page IDs.
	 */
	public static function create_pages( $pages_to_create = array() ) {
		if ( empty( $pages_to_create ) ) {
			$pages_to_create = array_keys( self::get_default_pages() );
		}

		$created_pages = array();
		$default_pages = self::get_default_pages();

		foreach ( $pages_to_create as $page_key ) {
			if ( ! isset( $default_pages[ $page_key ] ) ) {
				continue;
			}

			$page_data = $default_pages[ $page_key ];

			// Check if page already exists
			$existing_page = get_page_by_path( $page_data['slug'] );
			if ( $existing_page ) {
				$created_pages[ $page_key ] = $existing_page->ID;
				continue;
			}

			// Create the page
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page_data['title'],
					'post_name'    => $page_data['slug'],
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_author'  => get_current_user_id(),
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$created_pages[ $page_key ] = $page_id;
			}
		}

		// Store created page IDs in options
		$existing_pages = get_option( 'remember_created_pages', array() );
		$updated_pages = array_merge( $existing_pages, $created_pages );
		update_option( 'remember_created_pages', $updated_pages );

		return $created_pages;
	}

	/**
	 * Get created page IDs.
	 *
	 * @return array
	 */
	public static function get_created_pages() {
		return get_option( 'remember_created_pages', array() );
	}

	/**
	 * Delete created pages.
	 *
	 * @param bool $force Force delete (bypass trash).
	 * @return int Number of pages deleted.
	 */
	public static function delete_pages( $force = false ) {
		$created_pages = self::get_created_pages();
		$deleted_count = 0;

		foreach ( $created_pages as $page_id ) {
			if ( wp_delete_post( $page_id, $force ) ) {
				$deleted_count++;
			}
		}

		// Clear the option
		delete_option( 'remember_created_pages' );

		return $deleted_count;
	}
}
