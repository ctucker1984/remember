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

		if ( empty( $settings['access_token'] ) ) {
			return new WP_Error( 'no_token', __( 'No access token available. Please reconnect QuickBooks.', 'remember' ) );
		}

		// Check if token is expired (with 5 minute buffer)
		$expires_at = isset( $settings['expires_at'] ) ? $settings['expires_at'] : 0;
		if ( time() >= ( $expires_at - 300 ) ) {
			// Token expired or expiring soon, refresh it
			$refresh_result = Remember_QuickBooks_OAuth::refresh_token(
				$settings['refresh_token'],
				$settings['client_id'],
				$settings['client_secret']
			);

			if ( is_wp_error( $refresh_result ) ) {
				return $refresh_result;
			}

			// Update settings with new tokens
			$settings['access_token'] = $refresh_result['access_token'];
			$settings['refresh_token'] = isset( $refresh_result['refresh_token'] ) ? $refresh_result['refresh_token'] : $settings['refresh_token'];
			$settings['expires_at'] = time() + $refresh_result['expires_in'];
			Remember_QuickBooks_OAuth::save_settings( $settings );
		}

		return $settings['access_token'];
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
			return new WP_Error( 'no_company_id', __( 'No company ID available. Please reconnect QuickBooks.', 'remember' ) );
		}

		$url = self::get_api_base() . $company_id . '/' . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$error_message = isset( $body['Fault']['Error'][0]['Message'] ) 
				? $body['Fault']['Error'][0]['Message'] 
				: __( 'QuickBooks API error.', 'remember' );
			
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
			$data['BillAddr'] = array(
				'Line1'                  => $customer_data['address']['street'],
				'City'                   => $customer_data['address']['city'],
				'CountrySubDivisionCode' => $customer_data['address']['state'],
				'PostalCode'             => $customer_data['address']['postal'],
				'Country'                => $customer_data['address']['country'],
			);
		}

		// If customer ID exists, update instead of create
		if ( ! empty( $customer_data['qb_customer_id'] ) ) {
			$data['Id'] = $customer_data['qb_customer_id'];
			$data['SyncToken'] = $customer_data['sync_token'] ?? '0';
			$endpoint = 'customer';
			$method = 'POST'; // QuickBooks uses POST for both create and update
		} else {
			$endpoint = 'customer';
			$method = 'POST';
		}

		$response = self::api_request( $method, $endpoint, array( 'Customer' => $data ) );

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

		$response = self::api_request( 'POST', 'invoice', array( 'Invoice' => $data ) );

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
	 * @param string $invoice_id Invoice ID.
	 * @return array|WP_Error Payment data or error.
	 */
	public static function get_invoice_payment( $invoice_id ) {
		$response = self::api_request( 'GET', 'payment?query=SELECT * FROM Payment WHERE Line[0].LinkedTxn[0].TxnId = \'' . $invoice_id . '\'' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['QueryResponse']['Payment'] ) 
			? $response['QueryResponse']['Payment'] 
			: array();
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

		return isset( $response['QueryResponse']['Item'] ) 
			? $response['QueryResponse']['Item'] 
			: array();
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
