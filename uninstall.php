<?php
/**
 * Fired when the plugin is deleted from Plugins → Delete.
 *
 * Deactivate does not run this. WordPress users are not deleted.
 *
 * @package reMember
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/utilities/class-remember-uninstall.php';
Remember_Uninstall::wipe();
