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

require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';

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
			'survey' => array(
				'title'       => __( 'Survey', 'remember' ),
				'slug'        => 'survey',
				'content'     => '<!-- wp:shortcode -->[remember_survey]<!-- /wp:shortcode -->',
				'shortcode'   => '[remember_survey]',
				'description' => __( 'Follow-on event survey. Opened from the link in the survey email (?survey_id=).', 'remember' ),
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
				Remember_Logger::warning( 'Front-end page not created: unknown page', array( 'page' => (string) $page_key ) );
				continue;
			}

			$page_data = $default_pages[ $page_key ];

			// Check if page already exists
			$existing_page = get_page_by_path( $page_data['slug'] );
			if ( $existing_page ) {
				$created_pages[ $page_key ] = $existing_page->ID;
				Remember_Logger::debug(
					'Front-end page already exists; linked instead of created',
					array(
						'page'    => $page_key,
						'post_id' => (int) $existing_page->ID,
					)
				);
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
				),
				true
			);

			if ( is_wp_error( $page_id ) || ! $page_id ) {
				Remember_Logger::error(
					'Front-end page could not be created',
					array(
						'page'  => $page_key,
						'error' => is_wp_error( $page_id ) ? $page_id->get_error_message() : '',
					)
				);
				continue;
			}
			$created_pages[ $page_key ] = $page_id;
			Remember_Logger::info(
				'Front-end page created',
				array(
					'page'    => $page_key,
					'post_id' => (int) $page_id,
				)
			);
		}

		// Store created page IDs in options
		$existing_pages = get_option( 'remember_created_pages', array() );
		$updated_pages  = array_merge( $existing_pages, $created_pages );
		if ( $updated_pages !== $existing_pages && ! update_option( 'remember_created_pages', $updated_pages ) ) {
			Remember_Logger::error(
				'Front-end pages were created, but reMember could not record them; links to those pages will not work until they are set up again',
				array( 'pages' => $created_pages )
			);
		}

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

		foreach ( $created_pages as $page_key => $page_id ) {
			if ( wp_delete_post( $page_id, $force ) ) {
				$deleted_count++;
			} else {
				Remember_Logger::warning(
					'Front-end page could not be deleted',
					array(
						'page'    => (string) $page_key,
						'post_id' => (int) $page_id,
					)
				);
			}
		}

		// Clear the option
		delete_option( 'remember_created_pages' );

		$summary = array(
			'deleted' => $deleted_count,
			'listed'  => count( $created_pages ),
			'force'   => (bool) $force,
		);
		if ( $deleted_count < count( $created_pages ) ) {
			Remember_Logger::warning( 'Front-end pages deleted with failures; the pages left over can be deleted under Pages', $summary );
		} else {
			Remember_Logger::info( 'Front-end pages deleted', $summary );
		}

		return $deleted_count;
	}
}
