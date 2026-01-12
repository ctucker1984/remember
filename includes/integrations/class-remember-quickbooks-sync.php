<?php
/**
 * QuickBooks sync utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-quickbooks-api.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * QuickBooks sync utility class.
 *
 * Handles syncing data between WordPress and QuickBooks Online.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_QuickBooks_Sync {

	/**
	 * Sync member to QuickBooks customer.
	 *
	 * @param int $member_id Member ID.
	 * @return array|WP_Error Customer data or error.
	 */
	public static function sync_member_to_customer( $member_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';
		
		$member_model = new Remember_Member();
		$member = $member_model->get( $member_id );
		
		if ( ! $member ) {
			return new WP_Error( 'member_not_found', __( 'Member not found.', 'remember' ) );
		}

		$user = get_user_by( 'ID', $member_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'WordPress user not found.', 'remember' ) );
		}

		// Get member profile
		global $wpdb;
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
				$member_id
			)
		);

		// Prepare customer data
		$customer_data = array(
			'display_name' => $user->display_name,
			'first_name'    => ! empty( $profile->legal_first_name ) ? $profile->legal_first_name : $user->first_name,
			'last_name'     => ! empty( $profile->legal_last_name ) ? $profile->legal_last_name : $user->last_name,
			'email'         => $user->user_email,
			'phone'         => ! empty( $profile->cell_phone ) ? $profile->cell_phone : '',
		);

		// Add address if available
		if ( $profile && ( ! empty( $profile->address_street ) || ! empty( $profile->address_city ) ) ) {
			$customer_data['address'] = array(
				'street'  => $profile->address_street ?? '',
				'city'    => $profile->address_city ?? '',
				'state'   => $profile->address_state ?? '',
				'postal'  => $profile->address_postal ?? '',
				'country' => $profile->address_country ?? 'US',
			);
		}

		// Check if customer already exists in QB
		$qb_customer_id = get_user_meta( $member_id, 'remember_qb_customer_id', true );
		$qb_sync_token = get_user_meta( $member_id, 'remember_qb_sync_token', true );

		if ( $qb_customer_id ) {
			$customer_data['qb_customer_id'] = $qb_customer_id;
			$customer_data['sync_token'] = $qb_sync_token;
		}

		// Create or update customer in QuickBooks
		$result = Remember_QuickBooks_API::create_customer( $customer_data );

		if ( is_wp_error( $result ) ) {
			Remember_Logger::error( 'Failed to sync member to QuickBooks customer', array(
				'member_id' => $member_id,
				'error'     => $result->get_error_message(),
			) );
			return $result;
		}

		// Store QB customer ID and sync token
		update_user_meta( $member_id, 'remember_qb_customer_id', $result['Id'] );
		update_user_meta( $member_id, 'remember_qb_sync_token', $result['SyncToken'] );

		Remember_Logger::info( 'Member synced to QuickBooks customer', array(
			'member_id'      => $member_id,
			'qb_customer_id' => $result['Id'],
		) );

		return $result;
	}

	/**
	 * Create invoice in QuickBooks for accepted application.
	 *
	 * @param int $application_id Application ID.
	 * @return array|WP_Error Invoice data or error.
	 */
	public static function create_invoice_for_application( $application_id ) {
		$application_model = new Remember_Application();
		$application = $application_model->get( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'application_not_found', __( 'Application not found.', 'remember' ) );
		}

		// Only create invoice for accepted applications
		if ( 'accepted' !== $application->status ) {
			return new WP_Error( 'application_not_accepted', __( 'Application is not accepted.', 'remember' ) );
		}

		// Check if invoice already exists
		$payment_model = new Remember_Payment();
		$payment = $payment_model->get_by_application( $application_id );
		
		if ( $payment && ! empty( $payment->quickbooks_invoice_id ) ) {
			return new WP_Error( 'invoice_exists', __( 'Invoice already exists for this application.', 'remember' ) );
		}

		// Get member and ensure they're synced to QB
		$member_id = $application->member_id;
		$qb_customer_id = get_user_meta( $member_id, 'remember_qb_customer_id', true );

		if ( ! $qb_customer_id ) {
			// Sync member to QB first
			$sync_result = self::sync_member_to_customer( $member_id );
			if ( is_wp_error( $sync_result ) ) {
				return $sync_result;
			}
			$qb_customer_id = $sync_result['Id'];
		}

		// Get event and event role
		$event_model = new Remember_Event();
		$event = $event_model->get( $application->event_id );

		global $wpdb;
		$event_role = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT er.*, r.role_name FROM {$wpdb->prefix}remember_event_roles er 
				JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id 
				WHERE er.event_role_id = %d",
				$application->event_role_id
			)
		);

		// Build line items
		$line_items = array();

		// Add role cost as line item
		if ( $event_role && $event_role->cost > 0 ) {
			// Get or create product for role cost
			$role_product_id = self::get_or_create_role_product( $event_role->role_id, $event_role->role_name, $event_role->cost );
			if ( ! is_wp_error( $role_product_id ) ) {
				$line_items[] = array(
					'product_id'  => $role_product_id,
					'quantity'    => 1,
					'amount'      => floatval( $event_role->cost ),
					'description' => sprintf( __( '%s - %s', 'remember' ), $event_role->role_name, $event->event_name ),
				);
			} else {
				Remember_Logger::warning( 'Skipping role cost line item - product not mapped', array(
					'role_name' => $event_role->role_name,
					'error'     => $role_product_id->get_error_message(),
				) );
			}
		}

		// Add merchandise as line items
		$merchandise_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT am.*, m.merchandise_name, m.unit_cost 
				FROM {$wpdb->prefix}remember_application_merchandise am
				JOIN {$wpdb->prefix}remember_event_merchandise em ON am.merchandise_id = em.merchandise_id
				JOIN {$wpdb->prefix}remember_merchandise m ON em.merchandise_id = m.merchandise_id
				WHERE am.event_application_id = %d",
				$application_id
			)
		);

		foreach ( $merchandise_items as $item ) {
			$product_id = self::get_or_create_merchandise_product( $item->merchandise_id, $item->merchandise_name, $item->unit_cost );
			if ( ! is_wp_error( $product_id ) ) {
				$line_items[] = array(
					'product_id'  => $product_id,
					'quantity'    => intval( $item->quantity ),
					'amount'      => floatval( $item->total_cost ),
					'description' => $item->merchandise_name,
				);
			} else {
				Remember_Logger::warning( 'Skipping merchandise line item - product not mapped', array(
					'merchandise_name' => $item->merchandise_name,
					'error'            => $product_id->get_error_message(),
				) );
			}
		}

		if ( empty( $line_items ) ) {
			return new WP_Error( 'no_line_items', __( 'No line items available. Please ensure products are mapped to QuickBooks in the Product Mapping section.', 'remember' ) );
		}

		// Create invoice in QuickBooks
		$invoice_data = array(
			'customer_id' => $qb_customer_id,
			'line_items'  => $line_items,
		);

		$invoice_result = Remember_QuickBooks_API::create_invoice( $invoice_data );

		if ( is_wp_error( $invoice_result ) ) {
			Remember_Logger::error( 'Failed to create QuickBooks invoice', array(
				'application_id' => $application_id,
				'error'          => $invoice_result->get_error_message(),
			) );
			return $invoice_result;
		}

		// Create or update payment record with invoice ID
		if ( ! $payment ) {
			// Calculate totals
			$total_amount = 0;
			foreach ( $line_items as $item ) {
				$total_amount += $item['amount'];
			}

			$payment_data = array(
				'event_application_id'    => $application_id,
				'member_id'               => $member_id,
				'role_cost'               => $event_role ? floatval( $event_role->cost ) : 0,
				'merchandise_cost'        => 0, // Calculate from merchandise items
				'total_amount'           => $total_amount,
				'amount_paid'            => 0,
				'amount_due'             => $total_amount,
				'payment_status'         => 'pending',
				'quickbooks_invoice_id'  => $invoice_result['Id'],
			);

			$payment_model->create( $payment_data );
		} else {
			$payment_model->update( $payment->payment_id, array(
				'quickbooks_invoice_id' => $invoice_result['Id'],
			) );
		}

		Remember_Logger::info( 'QuickBooks invoice created', array(
			'application_id'    => $application_id,
			'invoice_id'        => $invoice_result['Id'],
			'qb_customer_id'    => $qb_customer_id,
		) );

		return $invoice_result;
	}

	/**
	 * Sync payment status from QuickBooks.
	 *
	 * @param int $payment_id Payment ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function sync_payment_status( $payment_id ) {
		$payment_model = new Remember_Payment();
		$payment = $payment_model->get( $payment_id );

		if ( ! $payment || empty( $payment->quickbooks_invoice_id ) ) {
			return new WP_Error( 'no_invoice', __( 'No QuickBooks invoice ID found for this payment.', 'remember' ) );
		}

		// Get invoice from QuickBooks
		$invoice = Remember_QuickBooks_API::get_invoice( $payment->quickbooks_invoice_id );

		if ( is_wp_error( $invoice ) ) {
			return $invoice;
		}

		// Get payments for this invoice
		$payments = Remember_QuickBooks_API::get_invoice_payment( $payment->quickbooks_invoice_id );

		$total_paid = 0;
		if ( ! is_wp_error( $payments ) && ! empty( $payments ) ) {
			foreach ( $payments as $qb_payment ) {
				$total_paid += floatval( $qb_payment['TotalAmt'] ?? 0 );
			}
		}

		// Update payment record
		$amount_due = floatval( $payment->total_amount ) - $total_paid;
		$payment_status = 'pending';
		
		if ( $total_paid >= floatval( $payment->total_amount ) ) {
			$payment_status = 'paid';
			$amount_due = 0;
		} elseif ( $total_paid > 0 ) {
			$payment_status = 'partial';
		}

		$payment_model->update( $payment_id, array(
			'amount_paid'    => $total_paid,
			'amount_due'     => $amount_due,
			'payment_status' => $payment_status,
			'payment_date'   => $total_paid > 0 ? current_time( 'mysql' ) : null,
		) );

		Remember_Logger::info( 'Payment status synced from QuickBooks', array(
			'payment_id'     => $payment_id,
			'amount_paid'    => $total_paid,
			'payment_status' => $payment_status,
		) );

		return true;
	}

	/**
	 * Sync all pending payments.
	 *
	 * @return array Results array with success/error counts.
	 */
	public static function sync_all_payments() {
		global $wpdb;
		
		$payments = $wpdb->get_results(
			"SELECT payment_id FROM {$wpdb->prefix}remember_payments 
			WHERE quickbooks_invoice_id IS NOT NULL 
			AND quickbooks_invoice_id != '' 
			AND payment_status IN ('pending', 'partial')
			ORDER BY payment_id ASC"
		);

		$results = array(
			'success' => 0,
			'error'   => 0,
			'errors'  => array(),
		);

		foreach ( $payments as $payment ) {
			$result = self::sync_payment_status( $payment->payment_id );
			if ( is_wp_error( $result ) ) {
				$results['error']++;
				$results['errors'][] = array(
					'payment_id' => $payment->payment_id,
					'error'      => $result->get_error_message(),
				);
			} else {
				$results['success']++;
			}
		}

		// Update last sync time
		$wpdb->update(
			$wpdb->prefix . 'remember_payment_processors',
			array( 'last_sync_at' => current_time( 'mysql' ) ),
			array( 'processor_type' => 'quickbooks' ),
			array( '%s' ),
			array( '%s' )
		);

		return $results;
	}

	/**
	 * Get or create product for role.
	 *
	 * @param int    $role_id Role ID.
	 * @param string $role_name Role name.
	 * @param float  $cost Role cost.
	 * @return string|WP_Error Product ID or error.
	 */
	private static function get_or_create_role_product( $role_id, $role_name, $cost ) {
		global $wpdb;
		
		// Check if product exists in our mapping table
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_products 
				WHERE product_name = %s",
				$role_name
			)
		);

		if ( $product && ! empty( $product->quickbooks_product_id ) ) {
			return $product->quickbooks_product_id;
		}

		// Try to find product in QuickBooks by name
		$qb_items = Remember_QuickBooks_API::query_items( "SELECT * FROM Item WHERE Name = '" . esc_sql( $role_name ) . "'" );
		
		if ( ! is_wp_error( $qb_items ) && ! empty( $qb_items ) ) {
			$qb_product = $qb_items[0];
			$qb_product_id = $qb_product['Id'];
			
			// Store mapping
			if ( ! $product ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_products',
					array(
						'product_name'            => $role_name,
						'description'            => sprintf( __( 'Event role: %s', 'remember' ), $role_name ),
						'quickbooks_product_id'  => $qb_product_id,
						'quickbooks_product_name' => $qb_product['Name'] ?? $role_name,
						'product_type'          => 'Service',
						'is_active'             => 1,
						'created_at'            => current_time( 'mysql' ),
						'updated_at'            => current_time( 'mysql' ),
					)
				);
			} else {
				$wpdb->update(
					$wpdb->prefix . 'remember_products',
					array(
						'quickbooks_product_id'  => $qb_product_id,
						'quickbooks_product_name' => $qb_product['Name'] ?? $role_name,
						'last_sync_at'           => current_time( 'mysql' ),
						'updated_at'             => current_time( 'mysql' ),
					),
					array( 'product_id' => $product->product_id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
			
			return $qb_product_id;
		}

		// Product not found - user needs to map it manually
		// For now, we'll skip this line item and log a warning
		Remember_Logger::warning( 'QuickBooks product not found for role', array(
			'role_name' => $role_name,
			'role_id'   => $role_id,
		) );
		
		return new WP_Error( 'product_not_mapped', sprintf( __( 'Product "%s" not mapped to QuickBooks. Please map it in the Product Mapping section.', 'remember' ), $role_name ) );
	}

	/**
	 * Get or create product for merchandise.
	 *
	 * @param int    $merchandise_id Merchandise ID.
	 * @param string $merchandise_name Merchandise name.
	 * @param float  $cost Unit cost.
	 * @return string|WP_Error Product ID or error.
	 */
	private static function get_or_create_merchandise_product( $merchandise_id, $merchandise_name, $cost ) {
		global $wpdb;
		
		// Check if product exists in our mapping table
		$product = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_products 
				WHERE product_name = %s",
				$merchandise_name
			)
		);

		if ( $product && ! empty( $product->quickbooks_product_id ) ) {
			return $product->quickbooks_product_id;
		}

		// Try to find product in QuickBooks by name
		$qb_items = Remember_QuickBooks_API::query_items( "SELECT * FROM Item WHERE Name = '" . esc_sql( $merchandise_name ) . "'" );
		
		if ( ! is_wp_error( $qb_items ) && ! empty( $qb_items ) ) {
			$qb_product = $qb_items[0];
			$qb_product_id = $qb_product['Id'];
			
			// Store mapping
			if ( ! $product ) {
				$wpdb->insert(
					$wpdb->prefix . 'remember_products',
					array(
						'product_name'            => $merchandise_name,
						'description'            => sprintf( __( 'Merchandise: %s', 'remember' ), $merchandise_name ),
						'quickbooks_product_id'  => $qb_product_id,
						'quickbooks_product_name' => $qb_product['Name'] ?? $merchandise_name,
						'product_type'          => 'Inventory',
						'is_active'             => 1,
						'created_at'            => current_time( 'mysql' ),
						'updated_at'            => current_time( 'mysql' ),
					)
				);
			} else {
				$wpdb->update(
					$wpdb->prefix . 'remember_products',
					array(
						'quickbooks_product_id'  => $qb_product_id,
						'quickbooks_product_name' => $qb_product['Name'] ?? $merchandise_name,
						'last_sync_at'           => current_time( 'mysql' ),
						'updated_at'             => current_time( 'mysql' ),
					),
					array( 'product_id' => $product->product_id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
			
			return $qb_product_id;
		}

		// Product not found - user needs to map it manually
		Remember_Logger::warning( 'QuickBooks product not found for merchandise', array(
			'merchandise_name' => $merchandise_name,
			'merchandise_id'   => $merchandise_id,
		) );
		
		return new WP_Error( 'product_not_mapped', sprintf( __( 'Product "%s" not mapped to QuickBooks. Please map it in the Product Mapping section.', 'remember' ), $merchandise_name ) );
	}
}
