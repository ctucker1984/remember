<?php
/**
 * Xero sync orchestration.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-xero-api.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-application.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-payment.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-product.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-remember-xero-item-mapping.php';
require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * Xero sync — mirrors Remember_QuickBooks_Sync for the active Xero provider.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_Xero_Sync {

	/**
	 * Sync a member to a Xero Contact.
	 *
	 * @param int $member_id Member ID (WordPress user ID).
	 * @return array|WP_Error Contact data or error.
	 */
	public static function sync_member_to_contact( $member_id ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';

		$member_model = new Remember_Member();
		$member       = $member_model->get( $member_id );
		if ( ! $member ) {
			return new WP_Error( 'member_not_found', __( 'Member not found.', 'remember' ) );
		}

		$user = get_user_by( 'ID', $member_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'WordPress user not found.', 'remember' ) );
		}

		global $wpdb;
		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
				$member_id
			)
		);

		$first_name = ! empty( $profile->legal_first_name ) ? $profile->legal_first_name : $user->first_name;
		$last_name  = ! empty( $profile->legal_last_name ) ? $profile->legal_last_name : $user->last_name;
		$full_name  = trim( $first_name . ' ' . $last_name );
		if ( '' === $full_name ) {
			$full_name = $user->display_name;
		}

		$contact_data = array(
			'name'       => $full_name,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $user->user_email,
			'phone'      => ( $profile && ! empty( $profile->cell_phone ) ) ? $profile->cell_phone : '',
		);

		if ( $profile && ( ! empty( $profile->address_street ) || ! empty( $profile->address_city ) ) ) {
			$contact_data['address'] = array(
				'street'  => $profile->address_street ?? '',
				'city'    => $profile->address_city ?? '',
				'state'   => $profile->address_state ?? '',
				'postal'  => $profile->address_postal ?? '',
				'country' => $profile->address_country ?? 'US',
			);
		}

		$xero_contact_id = get_user_meta( $member_id, 'remember_xero_contact_id', true );
		if ( ! $xero_contact_id ) {
			$by_email = Remember_Xero_API::find_contact_by_email( $user->user_email );
			if ( is_array( $by_email ) && ! empty( $by_email['ContactID'] ) ) {
				$xero_contact_id = $by_email['ContactID'];
				update_user_meta( $member_id, 'remember_xero_contact_id', $xero_contact_id );
			}
		}

		if ( $xero_contact_id ) {
			$contact_data['contact_id'] = $xero_contact_id;
		}

		$result = Remember_Xero_API::create_or_update_contact( $contact_data );

		// Stale ContactID: clear and retry once (match-by-email or create).
		if ( is_wp_error( $result ) && $xero_contact_id ) {
			delete_user_meta( $member_id, 'remember_xero_contact_id' );
			unset( $contact_data['contact_id'] );

			$by_email = Remember_Xero_API::find_contact_by_email( $user->user_email );
			if ( is_array( $by_email ) && ! empty( $by_email['ContactID'] ) ) {
				$contact_data['contact_id'] = $by_email['ContactID'];
				update_user_meta( $member_id, 'remember_xero_contact_id', $by_email['ContactID'] );
			}
			$result = Remember_Xero_API::create_or_update_contact( $contact_data );
		}

		if ( is_wp_error( $result ) ) {
			Remember_Logger::error(
				'Failed to sync member to Xero contact',
				array(
					'member_id' => $member_id,
					'error'     => $result->get_error_message(),
				)
			);
			return $result;
		}

		if ( ! empty( $result['ContactID'] ) ) {
			update_user_meta( $member_id, 'remember_xero_contact_id', $result['ContactID'] );
		}

		Remember_Logger::info(
			'Member synced to Xero contact',
			array(
				'member_id'        => $member_id,
				'xero_contact_id'  => isset( $result['ContactID'] ) ? $result['ContactID'] : '',
			)
		);

		return $result;
	}

	/**
	 * Resolve Xero ItemID for an event role (by role_id).
	 *
	 * @param int    $role_id   Role ID.
	 * @param string $role_name Role display name (auto-match by Name when unmapped).
	 * @return string|WP_Error Xero ItemID, or error.
	 */
	public static function resolve_xero_item_id_for_role( $role_id, $role_name ) {
		return self::resolve_xero_item_id( 'role', absint( $role_id ), $role_name );
	}

	/**
	 * Resolve Xero ItemID for a catalog product (add-on) by merchandise name.
	 *
	 * @param string $merchandise_name Must match an active remember_products row.
	 * @return string|WP_Error Xero ItemID, or error.
	 */
	public static function resolve_xero_item_id_for_catalog_product( $merchandise_name ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-product.php';
		$product_model = new Remember_Product();
		$catalog       = $product_model->get_by_name( $merchandise_name );
		if ( ! $catalog || (int) $catalog->is_active !== 1 ) {
			return new WP_Error(
				'catalog_product_missing',
				sprintf(
					/* translators: %s: product/add-on name */
					__( 'Add-on "%s" is not an active catalog product. Add it under Products first.', 'remember' ),
					$merchandise_name
				)
			);
		}
		return self::resolve_xero_item_id( 'product', absint( $catalog->product_id ), $merchandise_name );
	}

	/**
	 * Resolve Xero ItemID via remember_xero_item_mappings; optional auto-match by Item Name.
	 *
	 * @param string $entity_type   'role' or 'product'.
	 * @param int    $entity_id     role_id or product_id.
	 * @param string $fallback_name Name to match in Xero when no mapping exists.
	 * @return string|WP_Error Xero ItemID, or error.
	 */
	private static function resolve_xero_item_id( $entity_type, $entity_id, $fallback_name ) {
		require_once plugin_dir_path( __FILE__ ) . '../models/class-remember-xero-item-mapping.php';

		$mapping_model = new Remember_Xero_Item_Mapping();
		$row           = $mapping_model->get_by_entity( $entity_type, $entity_id );
		if ( $row && ! empty( $row->xero_item_id ) ) {
			return $row->xero_item_id;
		}

		$matched = Remember_Xero_API::find_item_by_name( $fallback_name );
		if ( is_array( $matched ) && ! empty( $matched['ItemID'] ) ) {
			$item_id   = $matched['ItemID'];
			$item_name = isset( $matched['Name'] ) ? $matched['Name'] : $fallback_name;
			$mapping_model->upsert( $entity_type, $entity_id, $item_id, $item_name );
			return $item_id;
		}

		Remember_Logger::warning(
			'Xero Item not mapped and no name match',
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'name'        => $fallback_name,
			)
		);

		return new WP_Error(
			'xero_item_not_mapped',
			sprintf(
				/* translators: %s: role or product name */
				__( 'Xero has no mapping for "%s". Set it under Settings → Xero (Event roles + Products).', 'remember' ),
				$fallback_name
			)
		);
	}

	/**
	 * Create a Xero invoice for an accepted application.
	 *
	 * @param int $application_id Application ID.
	 * @return array|WP_Error Invoice data or error.
	 */
	public static function create_invoice_for_application( $application_id ) {
		$application_model = new Remember_Application();
		$application       = $application_model->get( $application_id );

		if ( ! $application ) {
			return new WP_Error( 'application_not_found', __( 'Application not found.', 'remember' ) );
		}

		if ( 'accepted' !== $application->status ) {
			return new WP_Error( 'application_not_accepted', __( 'Application is not accepted.', 'remember' ) );
		}

		$payment_model = new Remember_Payment();
		$payment       = $payment_model->get_by_application( $application_id );

		if ( $payment && ! empty( $payment->xero_invoice_id ) ) {
			return new WP_Error( 'invoice_exists', __( 'Invoice already exists for this application.', 'remember' ) );
		}

		$member_id   = $application->member_id;
		$sync_result = self::sync_member_to_contact( $member_id );
		if ( is_wp_error( $sync_result ) ) {
			return $sync_result;
		}
		$xero_contact_id = isset( $sync_result['ContactID'] ) ? $sync_result['ContactID'] : get_user_meta( $member_id, 'remember_xero_contact_id', true );
		if ( ! $xero_contact_id ) {
			return new WP_Error( 'no_xero_contact', __( 'Could not create or resolve Xero contact for this member.', 'remember' ) );
		}

		$event_model = new Remember_Event();
		$event       = $event_model->get( $application->event_id );

		global $wpdb;
		$event_role = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT er.*, r.role_name FROM {$wpdb->prefix}remember_event_roles er
				JOIN {$wpdb->prefix}remember_roles r ON er.role_id = r.role_id
				WHERE er.event_role_id = %d",
				$application->event_role_id
			)
		);

		$line_items = array();

		if ( $event_role && $event_role->cost > 0 ) {
			$role_item_id = self::resolve_xero_item_id_for_role( $event_role->role_id, $event_role->role_name );
			if ( ! is_wp_error( $role_item_id ) ) {
				$line = self::build_line_item_from_xero_item(
					$role_item_id,
					1,
					floatval( $event_role->cost ),
					sprintf(
						/* translators: 1: role name, 2: event name */
						__( '%1$s - %2$s', 'remember' ),
						$event_role->role_name,
						$event ? $event->event_name : ''
					)
				);
				if ( ! is_wp_error( $line ) ) {
					$line_items[] = $line;
				} else {
					Remember_Logger::warning(
						'Skipping role cost line item - Xero item details unavailable',
						array(
							'role_name' => $event_role->role_name,
							'error'     => $line->get_error_message(),
						)
					);
				}
			} else {
				Remember_Logger::warning(
					'Skipping role cost line item - product not mapped',
					array(
						'role_name' => $event_role->role_name,
						'error'     => $role_item_id->get_error_message(),
					)
				);
			}
		}

		$merchandise_items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT am.*, em.merchandise_name
				FROM {$wpdb->prefix}remember_application_merchandise am
				JOIN {$wpdb->prefix}remember_event_merchandise em ON am.merchandise_id = em.merchandise_id
				WHERE am.event_application_id = %d",
				$application_id
			)
		);

		foreach ( $merchandise_items as $item ) {
			$product_item_id = self::resolve_xero_item_id_for_catalog_product( $item->merchandise_name );
			if ( ! is_wp_error( $product_item_id ) ) {
				$qty = intval( $item->quantity );
				if ( $qty <= 0 ) {
					$qty = 1;
				}
				$line = self::build_line_item_from_xero_item(
					$product_item_id,
					$qty,
					floatval( $item->total_cost ),
					$item->merchandise_name
				);
				if ( ! is_wp_error( $line ) ) {
					$line_items[] = $line;
				} else {
					Remember_Logger::warning(
						'Skipping merchandise line item - Xero item details unavailable',
						array(
							'merchandise_name' => $item->merchandise_name,
							'error'            => $line->get_error_message(),
						)
					);
				}
			} else {
				Remember_Logger::warning(
					'Skipping merchandise line item - product not mapped',
					array(
						'merchandise_name' => $item->merchandise_name,
						'error'            => $product_item_id->get_error_message(),
					)
				);
			}
		}

		if ( empty( $line_items ) ) {
			return new WP_Error( 'no_line_items', __( 'No line items available. Map event roles and catalog products to Xero under Settings → Xero.', 'remember' ) );
		}

		$invoice_result = Remember_Xero_API::create_invoice(
			array(
				'contact_id' => $xero_contact_id,
				'line_items' => $line_items,
				'reference'  => sprintf( 'reMember app #%d', absint( $application_id ) ),
			)
		);

		if ( is_wp_error( $invoice_result ) ) {
			Remember_Logger::error(
				'Failed to create Xero invoice',
				array(
					'application_id' => $application_id,
					'error'          => $invoice_result->get_error_message(),
				)
			);
			return $invoice_result;
		}

		$total_amount = 0;
		foreach ( $line_items as $li ) {
			$total_amount += floatval( $li['unit_amount'] ) * floatval( $li['quantity'] );
		}

		$xero_invoice_id     = isset( $invoice_result['InvoiceID'] ) ? $invoice_result['InvoiceID'] : '';
		$xero_invoice_number = isset( $invoice_result['InvoiceNumber'] ) ? sanitize_text_field( (string) $invoice_result['InvoiceNumber'] ) : null;

		if ( ! $payment ) {
			$payment_data = array(
				'event_application_id' => $application_id,
				'member_id'            => $member_id,
				'role_cost'            => $event_role ? floatval( $event_role->cost ) : 0,
				'merchandise_cost'     => 0,
				'total_amount'         => $total_amount,
				'amount_paid'          => 0,
				'amount_due'           => $total_amount,
				'payment_status'       => 'pending',
				'xero_invoice_id'      => $xero_invoice_id,
				'xero_invoice_number'  => $xero_invoice_number,
			);
			$payment_model->create( $payment_data );
		} else {
			$update_invoice = array(
				'xero_invoice_id' => $xero_invoice_id,
			);
			if ( null !== $xero_invoice_number ) {
				$update_invoice['xero_invoice_number'] = $xero_invoice_number;
			}
			$payment_model->update( $payment->payment_id, $update_invoice );
		}

		Remember_Logger::info(
			'Xero invoice created',
			array(
				'application_id'   => $application_id,
				'invoice_id'       => $xero_invoice_id,
				'xero_contact_id'  => $xero_contact_id,
			)
		);

		return $invoice_result;
	}

	/**
	 * Build a create_invoice line payload from a mapped Xero ItemID.
	 *
	 * @param string $item_id     Xero ItemID.
	 * @param int    $quantity    Quantity.
	 * @param float  $line_total  Line total (UnitAmount = total / qty).
	 * @param string $description Line description.
	 * @return array|WP_Error
	 */
	private static function build_line_item_from_xero_item( $item_id, $quantity, $line_total, $description ) {
		$quantity = max( 1, intval( $quantity ) );
		$item     = Remember_Xero_API::get_item( $item_id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		if ( empty( $item['Code'] ) ) {
			return new WP_Error( 'xero_item_no_code', __( 'Xero item is missing a Code.', 'remember' ) );
		}

		$unit_amount = floatval( $line_total ) / $quantity;
		$line        = array(
			'item_code'   => (string) $item['Code'],
			'quantity'    => $quantity,
			'unit_amount' => $unit_amount,
			'description' => $description,
		);

		if ( ! empty( $item['SalesDetails']['AccountCode'] ) ) {
			$line['account_code'] = (string) $item['SalesDetails']['AccountCode'];
		}
		if ( ! empty( $item['SalesDetails']['TaxType'] ) ) {
			$line['tax_type'] = (string) $item['SalesDetails']['TaxType'];
		}

		return $line;
	}

	/**
	 * Sync payment/refund status for one payment row from Xero.
	 *
	 * @param int $payment_id Payment ID.
	 * @return true|WP_Error
	 */
	public static function sync_payment_status( $payment_id ) {
		return new WP_Error(
			'xero_not_implemented',
			__( 'Xero payment sync is not implemented yet.', 'remember' )
		);
	}

	/**
	 * Sync all open Xero-linked payments.
	 *
	 * @return array{success:int,error:int}
	 */
	public static function sync_all_payments() {
		return array(
			'success' => 0,
			'error'   => 0,
		);
	}
}
