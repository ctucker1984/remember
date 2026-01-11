<?php
/**
 * Plugin Name: reMember
 * Plugin URI: https://github.com/ctucker1984/remember
 * Description: A custom WordPress plugin for reMember functionality.
 * Version: 1.0.0
 * Author: ctucker1984
 * Author URI: https://github.com/ctucker1984
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: remember
 * Domain Path: /languages
 *
 * @package reMember
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'REMEMBER_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'REMEMBER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'REMEMBER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_remember() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-remember-activator.php';
	Remember_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_remember() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-remember-deactivator.php';
	Remember_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_remember' );
register_deactivation_hook( __FILE__, 'deactivate_remember' );

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function run_remember() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-remember.php';
	$plugin = new Remember();
	$plugin->run();
}

// Run the plugin after WordPress is fully loaded
add_action( 'plugins_loaded', 'run_remember' );
