<?php
/**
 * Vetting workflow utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Vetting workflow utility class.
 *
 * Handles configurable vetting workflow logic.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Vetting_Workflow {

	/**
	 * Get the configured vetting workflow.
	 *
	 * @return string 'on_join' or 'first_application'
	 */
	public static function get_workflow() {
		$options = get_option( 'remember_options', array() );
		return isset( $options['vetting_workflow'] ) ? $options['vetting_workflow'] : 'on_join';
	}

	/**
	 * Check if vetting should be created on member join.
	 *
	 * @return bool
	 */
	public static function should_vet_on_join() {
		return self::get_workflow() === 'on_join';
	}

	/**
	 * Check if vetting should be created on first application.
	 *
	 * @return bool
	 */
	public static function should_vet_on_first_application() {
		return self::get_workflow() === 'first_application';
	}

	/**
	 * Check if a member has any applications.
	 *
	 * @param int $member_id Member ID.
	 * @return bool
	 */
	public static function member_has_applications( $member_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}remember_event_applications WHERE member_id = %d",
			$member_id
		) );
		return $count > 0;
	}

	/**
	 * Check if this is the member's first application.
	 *
	 * @param int $member_id Member ID.
	 * @return bool
	 */
	public static function is_first_application( $member_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}remember_event_applications WHERE member_id = %d",
			$member_id
		) );
		return $count === 0; // This will be the first one after it's created
	}

	/**
	 * Create vetting case for a member.
	 *
	 * @param int $member_id Member ID.
	 * @return int|false Vetting ID or false on error.
	 */
	public static function create_vetting_case( $member_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-vetting.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';
		
		// Check if vetting already exists
		$vetting_model = new Remember_Vetting();
		$existing = $vetting_model->get_by_member( $member_id );
		if ( $existing ) {
			return $existing->vetting_id; // Already has a vetting case
		}
		
		// Get default vetter (first user with vetting capability, or current user, or 0 if none)
		$vetters = get_users( array(
			'capability__in' => array( 'remember_create_vetting', 'remember_update_vetting' ),
			'number' => 1,
		) );
		
		$primary_vetter_id = 0; // Default to no vetter assigned
		if ( ! empty( $vetters ) ) {
			$primary_vetter_id = $vetters[0]->ID;
		} elseif ( get_current_user_id() > 0 ) {
			$current_user = wp_get_current_user();
			if ( $current_user->has_cap( 'remember_create_vetting' ) || $current_user->has_cap( 'remember_update_vetting' ) ) {
				$primary_vetter_id = get_current_user_id();
			}
		}
		
		// Create vetting case (primary_vetter_id can be 0 if no vetter available)
		$vetting_id = $vetting_model->create( $member_id, $primary_vetter_id, 'pending' );
		
		if ( $vetting_id ) {
			// Update member status to in_vetting
			require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';
			$member_model = new Remember_Member();
			$member = $member_model->get( $member_id );
			if ( $member ) {
				// Update status if member is pending_vetting or unvetted
				if ( in_array( $member->status, array( 'pending_vetting', 'unvetted' ), true ) ) {
					$member_model->update_status( $member_id, 'in_vetting' );
				}
			}
			
			// Add system note for automatic case creation
			$system_note = __( 'SYSTEM: Vetting case created automatically', 'remember' );
			if ( $primary_vetter_id > 0 ) {
				$vetter_user = get_user_by( 'ID', $primary_vetter_id );
				if ( $vetter_user ) {
					$system_note .= sprintf( __( ' with primary vetter %s', 'remember' ), $vetter_user->display_name );
				}
			}
			$vetting_model->add_note( $vetting_id, get_current_user_id() > 0 ? get_current_user_id() : 1, $system_note, true );
			
			Remember_Logger::info( 'Vetting case created', array( 'member_id' => $member_id, 'vetting_id' => $vetting_id ) );
		}
		
		return $vetting_id;
	}
}
