<?php
/**
 * FSE (Full Site Editing) support class
 *
 * @package    reMember
 * @subpackage reMember/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * FSE support class.
 *
 * Handles Full Site Editing block patterns and templates.
 *
 * @package    reMember
 * @subpackage reMember/includes
 */
class Remember_FSE {

	/**
	 * Register block patterns.
	 *
	 * @since    1.0.0
	 */
	public static function register_block_patterns() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		// Member Dashboard Pattern
		register_block_pattern(
			'remember/member-dashboard',
			array(
				'title'       => __( 'Member Dashboard', 'remember' ),
				'description' => __( 'A dashboard for members to view their profile, accepted events, and applications.', 'remember' ),
				'categories'  => array( 'remember' ),
				'content'     => '<!-- wp:group {"className":"remember-dashboard-container"} -->
<div class="wp-block-group remember-dashboard-container"><!-- wp:shortcode -->
[remember_dashboard]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->',
			)
		);

		// Events List Pattern
		register_block_pattern(
			'remember/events-list',
			array(
				'title'       => __( 'Events List', 'remember' ),
				'description' => __( 'Display a list of events available for applications.', 'remember' ),
				'categories'  => array( 'remember' ),
				'content'     => '<!-- wp:group {"className":"remember-events-container"} -->
<div class="wp-block-group remember-events-container"><!-- wp:heading {"level":2} -->
<h2>' . __( 'Available Events', 'remember' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[remember_events status="open"]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->',
			)
		);

		// Application Form Pattern
		register_block_pattern(
			'remember/application-form',
			array(
				'title'       => __( 'Application Form', 'remember' ),
				'description' => __( 'A form for members to apply for events.', 'remember' ),
				'categories'  => array( 'remember' ),
				'content'     => '<!-- wp:group {"className":"remember-apply-container"} -->
<div class="wp-block-group remember-apply-container"><!-- wp:shortcode -->
[remember_apply]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->',
			)
		);

		// Profile View Pattern
		register_block_pattern(
			'remember/member-profile',
			array(
				'title'       => __( 'Member Profile', 'remember' ),
				'description' => __( 'Display and edit member profile information.', 'remember' ),
				'categories'  => array( 'remember' ),
				'content'     => '<!-- wp:group {"className":"remember-profile-container"} -->
<div class="wp-block-group remember-profile-container"><!-- wp:shortcode -->
[remember_profile]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->',
			)
		);

		// Member registration (minimal signup; works when Settings → General → Membership is off).
		register_block_pattern(
			'remember/member-register',
			array(
				'title'       => __( 'Member Registration', 'remember' ),
				'description' => __( 'Minimal form: username, display name, legal name, email, cell phone, time zone, password. Creates a WordPress user and reMember member record.', 'remember' ),
				'categories'  => array( 'remember' ),
				'content'     => '<!-- wp:group {"className":"remember-register-container"} -->
<div class="wp-block-group remember-register-container"><!-- wp:heading {"level":2} -->
<h2>' . __( 'Become a member', 'remember' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[remember_register]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->',
			)
		);
	}

	/**
	 * Register block pattern category.
	 *
	 * @since    1.0.0
	 */
	public static function register_block_pattern_category() {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'remember',
			array( 'label' => __( 'reMember', 'remember' ) )
		);
	}

	/**
	 * Register template parts.
	 *
	 * @since    1.0.0
	 */
	public static function register_template_parts() {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		// Template parts are registered via theme.json or block templates
		// For now, we provide shortcodes that can be used in any template
	}
}
