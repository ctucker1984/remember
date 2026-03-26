<?php
/**
 * QuickBooks API wrapper class
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'class-remember-quickbooks-oauth.php';
require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-logger.php';

/**
 * QuickBooks API wrapper class.
 *
 * Handles all QuickBooks Online API calls.
 *
 * @package    reMember
 * @subpackage reMember/includes/integrations
 */
class Remember_QuickBooks_API {

	/**
	 * QuickBooks API base URL.
	 */
	const QB_API_BASE = 'https://sandbox-quickbooks.api.intuit.com/v3/company/';

	/**
	 * Minor version for v3 API requests (Intuit recommends always sending this).
	 *
	 * @link https://developer.intuit.com/app/developer/qbo/docs/learn/rest-api-features#minor-versions
	 */
	const QB_MINOR_VERSION = 73;

	/**
	 * Get API base URL (sandbox or production).
	 *
	 * @return string API base URL.
	 */
	private static function get_api_base() {
		$settings = Remember_QuickBooks_OAuth::get_settings();
		$environment = isset( $settings['environment'] ) ? $settings['environment'] : 'sandbox';

		if ( 'production' === $environment ) {
			return 'https://quickbooks.api.intuit.com/v3/company/';
		}

		return self::QB_API_BASE;
	}

	/**
	 * Get company ID from settings.
	 *
	 * @return string|false Company ID or false if not set.
	 */
	private static function get_company_id() {
		$settings = Remember_QuickBooks_OAuth::get_settings();
		return isset( $settings['realm_id'] ) ? $settings['realm_id'] : false;
	}

	/**
	 * Get valid access token (refresh if needed).
	 *
	 * @return string|WP_Error Access token or error.
	 */
	private static function get_access_token() {
		$settings = Remember_QuickBooks_OAuth::get_settings();

		$access = isset( $settings['access_token'] ) ? trim( (string) $settings['access_token'] ) : '';
		if ( ! is_string( $access ) || strlen( $access ) < 20 ) {
			Remember_Logger::error(
				'QuickBooks API: access token missing or invalid after decrypt',
				array( 'code' => 'no_token' )
			);
			return new WP_Error( 'no_token', __( 'No access token available. Please reconnect QuickBooks.', 'remember' ) );
		}

		// Check if token is expired (with 5 minute buffer). Missing expires_at (0) means unknown — refresh if possible.
		$expires_at    = isset( $settings['expires_at'] ) ? (int) $settings['expires_at'] : 0;
		$needs_refresh = ( $expires_at <= 0 ) || ( time() >= ( $expires_at - 300 ) );

		if ( $needs_refresh ) {
			$refresh_token = isset( $settings['refresh_token'] ) ? trim( (string) $settings['refresh_token'] ) : '';
			if ( strlen( $refresh_token ) < 10 ) {
				Remember_Logger::error( 'QuickBooks API: refresh token missing or invalid', array( 'code' => 'no_refresh_token' ) );
				return new WP_Error( 'no_refresh_token', __( 'QuickBooks session expired. Please reconnect QuickBooks.', 'remember' ) );
			}
			$refresh_result = Remember_QuickBooks_OAuth::refresh_token(
				$refresh_token,
				$settings['client_id'],
				$settings['client_secret']
			);

			if ( is_wp_error( $refresh_result ) ) {
				Remember_Logger::error(
					'QuickBooks API: token refresh failed',
					array(
						'code'    => $refresh_result->get_error_code(),
						'message' => $refresh_result->get_error_message(),
					)
				);
				return $refresh_result;
			}

			// Update settings with new tokens
			$settings['access_token'] = $refresh_result['access_token'];
			$settings['refresh_token'] = isset( $refresh_result['refresh_token'] ) ? $refresh_result['refresh_token'] : $settings['refresh_token'];
			$rin = isset( $refresh_result['expires_in'] ) ? (int) $refresh_result['expires_in'] : 3600;
			if ( $rin < 60 ) {
				$rin = 3600;
			}
			$settings['expires_at'] = time() + $rin;
			Remember_QuickBooks_OAuth::save_settings( $settings );

			return trim( (string) $settings['access_token'] );
		}

		return $access;
	}

	/**
	 * Human-readable message from QuickBooks JSON error body (handles Intuit lowercase fault/error shape).
	 *
	 * @param array|null $body Decoded JSON body.
	 * @return string
	 */
	private static function parse_qb_error_message( $body ) {
		if ( ! is_array( $body ) ) {
			return __( 'QuickBooks API error.', 'remember' );
		}

		// Legacy XML-style JSON (Fault / Error / Message).
		if ( ! empty( $body['Fault']['Error'][0]['Message'] ) ) {
			return (string) $body['Fault']['Error'][0]['Message'];
		}

		// REST JSON (fault / error[] with detail or message; keys may be mixed case).
		$fault = isset( $body['fault'] ) ? $body['fault'] : ( isset( $body['Fault'] ) ? $body['Fault'] : null );
		if ( is_array( $fault ) ) {
			$errors = isset( $fault['error'] ) ? $fault['error'] : ( isset( $fault['Error'] ) ? $fault['Error'] : null );
			if ( is_array( $errors ) && ! empty( $errors[0] ) && is_array( $errors[0] ) ) {
				$err = $errors[0];
				if ( ! empty( $err['Detail'] ) ) {
					return (string) $err['Detail'];
				}
				if ( ! empty( $err['detail'] ) ) {
					return (string) $err['detail'];
				}
				if ( ! empty( $err['Message'] ) ) {
					return (string) $err['Message'];
				}
				if ( ! empty( $err['message'] ) ) {
					return (string) $err['message'];
				}
			}
		}

		return __( 'QuickBooks API error.', 'remember' );
	}

	/**
	 * Make API request.
	 *
	 * @param string $method HTTP method (GET, POST, etc.).
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response data or error.
	 */
	private static function api_request( $method, $endpoint, $data = array() ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$company_id = self::get_company_id();
		if ( ! $company_id ) {
			Remember_Logger::error(
				'QuickBooks API: no company (realm) ID',
				array( 'code' => 'no_company_id' )
			);
			return new WP_Error( 'no_company_id', __( 'No company ID available. Please reconnect QuickBooks.', 'remember' ) );
		}

		$url = self::get_api_base() . $company_id . '/' . $endpoint;
		$url .= ( strpos( $url, '?' ) !== false ? '&' : '?' ) . 'minorversion=' . self::QB_MINOR_VERSION;

		$qb_opts = Remember_QuickBooks_OAuth::get_settings();
		$qb_env  = isset( $qb_opts['environment'] ) ? $qb_opts['environment'] : 'unknown';

		$token = trim( (string) $token );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'          => 'application/json',
			),
			'timeout' => 60,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Remember_Logger::error(
				'QuickBooks API: HTTP transport error',
				array(
					'method'   => $method,
					'endpoint' => $endpoint,
					'code'     => $response->get_error_code(),
					'message'  => $response->get_error_message(),
				)
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( $status_code >= 400 ) {
			$error_message = self::parse_qb_error_message( $body );

			$body_for_log = is_array( $body ) ? $body : $raw_body;
			$encoded      = is_string( $body_for_log ) ? $body_for_log : wp_json_encode( $body_for_log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( is_string( $encoded ) && strlen( $encoded ) > 6000 ) {
				$encoded = substr( $encoded, 0, 6000 ) . '...<truncated>';
			}

			Remember_Logger::error(
				'QuickBooks API: error response',
				array(
					'method'        => $method,
					'endpoint'      => $endpoint,
					'status'        => $status_code,
					'qb_message'    => $error_message,
					'response_body' => $encoded,
					'environment'   => $qb_env,
					'api_base'      => self::get_api_base(),
					'realm_id'      => substr( (string) $company_id, 0, 6 ) . '…',
				)
			);

			return new WP_Error(
				'qb_api_error',
				$error_message,
				array(
					'status' => $status_code,
					'body'   => $body,
				)
			);
		}

		return $body;
	}

	/**
	 * Normalize QueryResponse Customer list to a numeric array of customer rows.
	 *
	 * @param array $response Decoded API response.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_customer_query_response( $response ) {
		if ( ! isset( $response['QueryResponse']['Customer'] ) ) {
			return array();
		}
		$raw = $response['QueryResponse']['Customer'];
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( isset( $raw['Id'] ) ) {
			return array( $raw );
		}
		return $raw;
	}

	/**
	 * Fetch a single Customer by Id (includes current SyncToken).
	 *
	 * @param string $customer_id QuickBooks Customer Id.
	 * @return array|WP_Error Customer entity or error.
	 */
	public static function get_customer( $customer_id ) {
		$customer_id = trim( (string) $customer_id );
		if ( '' === $customer_id ) {
			return new WP_Error( 'invalid_customer_id', __( 'Invalid QuickBooks customer ID.', 'remember' ) );
		}
		$response = self::api_request( 'GET', 'customer/' . rawurlencode( $customer_id ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return isset( $response['Customer'] ) ? $response['Customer'] : $response;
	}

	/**
	 * Find customers whose PrimaryEmailAddr matches (first match wins).
	 *
	 * @param string $email Email address.
	 * @return array|null Customer row or null if none.
	 */
	public static function find_customer_by_primary_email( $email ) {
		$email = trim( (string) $email );
		if ( '' === $email || ! is_email( $email ) ) {
			return null;
		}
		$escaped = str_replace( "'", "''", $email );
		$query     = "SELECT * FROM Customer WHERE PrimaryEmailAddr = '" . $escaped . "'";
		$response  = self::api_request( 'GET', 'query?query=' . rawurlencode( $query ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$rows = self::normalize_customer_query_response( $response );
		if ( empty( $rows ) ) {
			return null;
		}
		return $rows[0];
	}

	/**
	 * Create or update customer in QuickBooks.
	 *
	 * @param array $customer_data Customer data.
	 * @return array|WP_Error Customer data or error.
	 */
	public static function create_customer( $customer_data ) {
		$data = array(
			'DisplayName' => $customer_data['display_name'],
			'GivenName'    => $customer_data['first_name'],
			'FamilyName'   => $customer_data['last_name'],
		);

		if ( ! empty( $customer_data['email'] ) ) {
			$data['PrimaryEmailAddr'] = array( 'Address' => $customer_data['email'] );
		}

		if ( ! empty( $customer_data['phone'] ) ) {
			$data['PrimaryPhone'] = array( 'FreeFormNumber' => $customer_data['phone'] );
		}

		if ( ! empty( $customer_data['address'] ) ) {
			$bill_addr = array(
				'Line1'                  => isset( $customer_data['address']['street'] ) ? $customer_data['address']['street'] : '',
				'City'                   => isset( $customer_data['address']['city'] ) ? $customer_data['address']['city'] : '',
				'CountrySubDivisionCode' => isset( $customer_data['address']['state'] ) ? $customer_data['address']['state'] : '',
				'PostalCode'             => isset( $customer_data['address']['postal'] ) ? $customer_data['address']['postal'] : '',
				'Country'                => isset( $customer_data['address']['country'] ) ? $customer_data['address']['country'] : '',
			);
			$bill_addr = array_filter(
				$bill_addr,
				function( $value ) {
					return '' !== trim( (string) $value );
				}
			);
			if ( ! empty( $bill_addr ) ) {
				$data['BillAddr'] = $bill_addr;
				$data['ShipAddr'] = $bill_addr;
			}
		}

		// Updates require a current SyncToken from QBO (stored tokens go stale after edits in QBO or missing meta).
		if ( ! empty( $customer_data['qb_customer_id'] ) ) {
			$existing = self::get_customer( $customer_data['qb_customer_id'] );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$data['Id']         = $customer_data['qb_customer_id'];
			$data['SyncToken']  = isset( $existing['SyncToken'] ) ? (string) $existing['SyncToken'] : '0';
			$endpoint           = 'customer';
			$method               = 'POST';
		} else {
			$endpoint = 'customer';
			$method   = 'POST';
		}

		// QBO v3 expects the Customer object as the top-level payload.
		$response = self::api_request( $method, $endpoint, $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['Customer'] ) ? $response['Customer'] : $response;
	}

	/**
	 * Create invoice in QuickBooks.
	 *
	 * @param array $invoice_data Invoice data.
	 * @return array|WP_Error Invoice data or error.
	 */
	public static function create_invoice( $invoice_data ) {
		$data = array(
			'CustomerRef' => array(
				'value' => $invoice_data['customer_id'],
			),
			'Line' => array(),
		);

		// Add line items
		foreach ( $invoice_data['line_items'] as $item ) {
			$line_item = array(
				'Amount'      => $item['amount'],
				'DetailType'  => 'SalesItemLineDetail',
				'SalesItemLineDetail' => array(
					'ItemRef' => array(
						'value' => $item['product_id'],
					),
					'Qty'     => $item['quantity'],
				),
			);

			if ( ! empty( $item['description'] ) ) {
				$line_item['Description'] = $item['description'];
			}

			$data['Line'][] = $line_item;
		}

		// QBO v3 expects the Invoice object as the top-level payload.
		$response = self::api_request( 'POST', 'invoice', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['Invoice'] ) ? $response['Invoice'] : $response;
	}

	/**
	 * Get invoice by ID.
	 *
	 * @param string $invoice_id Invoice ID.
	 * @return array|WP_Error Invoice data or error.
	 */
	public static function get_invoice( $invoice_id ) {
		$response = self::api_request( 'GET', 'invoice/' . $invoice_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['QueryResponse']['Invoice'][0] ) 
			? $response['QueryResponse']['Invoice'][0] 
			: $response['Invoice'];
	}

	/**
	 * Get payment for invoice.
	 *
	 * @param string      $invoice_id  Invoice ID.
	 * @param string|null $customer_id Optional QuickBooks Customer Id to narrow the search.
	 * @return array|WP_Error Payment data or error.
	 */
	public static function get_invoice_payment( $invoice_id, $customer_id = null ) {
		$invoice_id = trim( (string) $invoice_id );
		if ( '' === $invoice_id ) {
			return new WP_Error( 'invalid_invoice_id', __( 'Invalid QuickBooks invoice ID.', 'remember' ) );
		}

		// First, pull a reasonably-scoped set of payments from QBO. We can't reliably filter on
		// Line.LinkedTxn in the SQL WHERE clause across all environments, so we:
		// 1) Optionally narrow by CustomerRef when a customer_id is available.
		// 2) Filter by LinkedTxn.TxnId = invoice_id in PHP.
		$customer_id = $customer_id ? trim( (string) $customer_id ) : '';
		$where_parts = array();
		if ( '' !== $customer_id ) {
			$where_parts[] = "CustomerRef = '" . $customer_id . "'";
		}

		$query = 'SELECT * FROM Payment';
		if ( ! empty( $where_parts ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where_parts );
		}

		$response = self::api_request( 'GET', 'query?query=' . rawurlencode( $query ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['QueryResponse']['Payment'] ) ) {
			return array();
		}

		$raw = $response['QueryResponse']['Payment'];
		if ( ! is_array( $raw ) ) {
			return array();
		}

		// Normalize single Payment → list of one.
		$payments = isset( $raw['Id'] ) ? array( $raw ) : $raw;

		$matches = array();
		foreach ( $payments as $payment ) {
			if ( ! is_array( $payment ) || ! isset( $payment['Line'] ) ) {
				continue;
			}
			$lines = $payment['Line'];
			if ( isset( $lines['Amount'] ) ) {
				$lines = array( $lines );
			}
			if ( ! is_array( $lines ) ) {
				continue;
			}
			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) || ! isset( $line['LinkedTxn'] ) ) {
					continue;
				}
				$linked = $line['LinkedTxn'];
				if ( isset( $linked['TxnId'] ) ) {
					$linked = array( $linked );
				}
				if ( ! is_array( $linked ) ) {
					continue;
				}
				foreach ( $linked as $lt ) {
					if ( isset( $lt['TxnId'] ) && (string) $lt['TxnId'] === $invoice_id ) {
						$matches[] = $payment;
						// Once matched, no need to scan remaining linked Txn for this payment.
						break 3;
					}
				}
			}
		}

		return $matches;
	}

	/**
	 * Normalize QueryResponse Item payload to a list of item arrays.
	 *
	 * @param array $response Decoded API response.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_item_query_response( $response ) {
		if ( ! isset( $response['QueryResponse']['Item'] ) ) {
			return array();
		}
		$raw = $response['QueryResponse']['Item'];
		if ( ! is_array( $raw ) ) {
			return array();
		}
		// Single item is returned as one associative array with an "Id" key, not 0..n.
		if ( isset( $raw['Id'] ) ) {
			return array( $raw );
		}
		return $raw;
	}

	/**
	 * Remove QuickBooks Category rows (folder/grouping only; not invoice line items).
	 *
	 * @param array $items List of Item entities.
	 * @return array<int, array<string, mixed>>
	 */
	private static function exclude_category_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$excluded_exact_names = array( 'Hours', 'Services' );
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['Type'] ) ? (string) $item['Type'] : '';
			if ( strcasecmp( $type, 'Category' ) === 0 ) {
				continue;
			}
			$name = isset( $item['Name'] ) ? trim( (string) $item['Name'] ) : '';
			if ( in_array( $name, $excluded_exact_names, true ) ) {
				continue;
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Query items/products.
	 *
	 * @param string $query SQL-like query.
	 * @return array|WP_Error Items or error.
	 */
	public static function query_items( $query = "SELECT * FROM Item" ) {
		$response = self::api_request( 'GET', 'query?query=' . urlencode( $query ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = self::normalize_item_query_response( $response );
		return self::exclude_category_items( $items );
	}

	/**
	 * Get company info.
	 *
	 * @return array|WP_Error Company info or error.
	 */
	public static function get_company_info() {
		$response = self::api_request( 'GET', 'companyinfo/' . self::get_company_id() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['CompanyInfo'] ) ? $response['CompanyInfo'] : array();
	}
}
