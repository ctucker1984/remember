<?php
/**
 * Import/Export utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../models/class-member.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-event.php';
require_once plugin_dir_path( __FILE__ ) . '../models/class-location.php';
require_once plugin_dir_path( __FILE__ ) . 'class-remember-logger.php';

/**
 * Import/Export utility class.
 *
 * Handles CSV import/export for Members, Events, and Locations.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Import_Export {

	/**
	 * Download template CSV for members.
	 *
	 * @return void
	 */
	public static function download_members_template() {
		$filename = 'members-template.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers only
		fputcsv( $output, array(
			'User ID',
			'Email',
			'Display Name',
			'First Name',
			'Last Name',
			'Status',
			'Legal First Name',
			'Legal Last Name',
			'Street Address',
			'City',
			'State',
			'Postal Code',
			'Country',
			'Cell Phone',
			'Timezone',
			'IM Handle',
			'IM Type',
			'Interests',
			'Emergency Contact First',
			'Emergency Contact Last',
			'Emergency Contact Phone',
			'Emergency Contact Relationship',
		) );
		
		// Add example row
		fputcsv( $output, array(
			'', // User ID - leave blank for new users
			'john.doe@example.com',
			'John Doe',
			'John',
			'Doe',
			'pending_vetting',
			'John',
			'Doe',
			'123 Main St',
			'Anytown',
			'CA',
			'12345',
			'US',
			'+1-555-123-4567',
			'America/Los_Angeles',
			'@johndoe',
			'telegram',
			'Gaming, Hiking',
			'Jane',
			'Doe',
			'+1-555-987-6543',
			'Spouse',
		) );
		
		fclose( $output );
		exit;
	}

	/**
	 * Export members to CSV.
	 *
	 * @return void
	 */
	public static function export_members() {
		$member_model = new Remember_Member();
		$members = $member_model->get_all();
		
		$filename = 'members-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers
		fputcsv( $output, array(
			'User ID',
			'Email',
			'Display Name',
			'First Name',
			'Last Name',
			'Status',
			'Legal First Name',
			'Legal Last Name',
			'Street Address',
			'City',
			'State',
			'Postal Code',
			'Country',
			'Cell Phone',
			'Timezone',
			'IM Handle',
			'IM Type',
			'Interests',
			'Emergency Contact First',
			'Emergency Contact Last',
			'Emergency Contact Phone',
			'Emergency Contact Relationship',
		) );
		
		// Data rows
		foreach ( $members as $member ) {
			$user = get_user_by( 'ID', $member->member_id );
			if ( ! $user ) {
				continue;
			}
			
			// Get profile
			global $wpdb;
			$profile = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
					$member->member_id
				)
			);
			
			fputcsv( $output, array(
				$member->member_id,
				$user->user_email,
				$user->display_name,
				$user->first_name,
				$user->last_name,
				$member->status,
				$profile->legal_first_name ?? '',
				$profile->legal_last_name ?? '',
				$profile->address_street ?? '',
				$profile->address_city ?? '',
				$profile->address_state ?? '',
				$profile->address_postal ?? '',
				$profile->address_country ?? '',
				$profile->cell_phone ?? '',
				$profile->timezone ?? '',
				$profile->im_handle ?? '',
				$profile->im_type ?? '',
				$profile->interests ?? '',
				$profile->emergency_contact_first ?? '',
				$profile->emergency_contact_last ?? '',
				$profile->emergency_contact_phone ?? '',
				$profile->emergency_contact_relationship ?? '',
			) );
		}
		
		fclose( $output );
		exit;
	}

	/**
	 * Download template CSV for events.
	 *
	 * @return void
	 */
	public static function download_events_template() {
		$filename = 'events-template.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers only
		fputcsv( $output, array(
			'Event ID',
			'Event Name',
			'Description',
			'Start Date',
			'End Date',
			'Status',
			'Is Private',
			'Location ID',
			'Location Name',
		) );
		
		// Add example row
		fputcsv( $output, array(
			'', // Event ID - leave blank for new events
			'Summer Retreat 2024',
			'Annual summer gathering',
			'2024-07-15',
			'2024-07-20',
			'open',
			'No',
			'', // Location ID - leave blank if using Location Name
			'Main Campus', // Or use Location ID above
		) );
		
		fclose( $output );
		exit;
	}

	/**
	 * Export events to CSV.
	 *
	 * @return void
	 */
	public static function export_events() {
		$event_model = new Remember_Event();
		$events = $event_model->get_all();
		
		$filename = 'events-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers
		fputcsv( $output, array(
			'Event ID',
			'Event Name',
			'Description',
			'Start Date',
			'End Date',
			'Status',
			'Is Private',
			'Location ID',
			'Location Name',
		) );
		
		// Data rows
		foreach ( $events as $event ) {
			$location_name = '';
			if ( $event->location_id ) {
				$location_model = new Remember_Location();
				$location = $location_model->get( $event->location_id );
				$location_name = $location ? $location->location_name : '';
			}
			
			fputcsv( $output, array(
				$event->event_id,
				$event->event_name,
				$event->event_description ?? '',
				$event->start_date,
				$event->end_date,
				$event->status,
				$event->is_private ? 'Yes' : 'No',
				$event->location_id ?? '',
				$location_name,
			) );
		}
		
		fclose( $output );
		exit;
	}

	/**
	 * Download template CSV for locations.
	 *
	 * @return void
	 */
	public static function download_locations_template() {
		$filename = 'locations-template.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers only
		fputcsv( $output, array(
			'Location ID',
			'Location Name',
			'Street Address',
			'City',
			'State',
			'Postal Code',
			'Country',
			'Details',
			'Is Active',
		) );
		
		// Add example row
		fputcsv( $output, array(
			'', // Location ID - leave blank for new locations
			'Main Campus',
			'456 University Ave',
			'Springfield',
			'IL',
			'62701',
			'US',
			'Main event location with parking',
			'Yes',
		) );
		
		fclose( $output );
		exit;
	}

	/**
	 * Export locations to CSV.
	 *
	 * @return void
	 */
	public static function export_locations() {
		$location_model = new Remember_Location();
		$locations = $location_model->get_all();
		
		$filename = 'locations-export-' . date( 'Y-m-d-H-i-s' ) . '.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel compatibility
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		
		// Headers
		fputcsv( $output, array(
			'Location ID',
			'Location Name',
			'Street Address',
			'City',
			'State',
			'Postal Code',
			'Country',
			'Details',
			'Is Active',
		) );
		
		// Data rows
		foreach ( $locations as $location ) {
			fputcsv( $output, array(
				$location->location_id,
				$location->location_name,
				$location->address_street ?? '',
				$location->address_city ?? '',
				$location->address_state ?? '',
				$location->address_postal ?? '',
				$location->address_country ?? '',
				$location->details ?? '',
				$location->is_active ? 'Yes' : 'No',
			) );
		}
		
		fclose( $output );
		exit;
	}

	/**
	 * Import members from CSV.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array Results array with success/error counts.
	 */
	public static function import_members( $file_path ) {
		$results = array(
			'success' => 0,
			'error'   => 0,
			'errors'  => array(),
		);
		
		if ( ! file_exists( $file_path ) ) {
			$results['errors'][] = __( 'File not found.', 'remember' );
			return $results;
		}
		
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			$results['errors'][] = __( 'Could not open file for reading.', 'remember' );
			return $results;
		}
		
		// Skip BOM if present
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}
		
		// Read headers
		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$member_model = new Remember_Member();
		$row_number = 1;
		
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;
			
			if ( count( $data ) < count( $headers ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Insufficient columns.', 'remember' ), $row_number );
				continue;
			}
			
			$row_data = array_combine( $headers, $data );
			
			// Required fields
			if ( empty( $row_data['Email'] ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Email is required.', 'remember' ), $row_number );
				continue;
			}
			
			// Check if user exists
			$user = get_user_by( 'email', $row_data['Email'] );
			$user_id = null;
			
			if ( ! $user ) {
				// Create WordPress user
				$username = sanitize_user( $row_data['Email'] );
				$password = wp_generate_password( 12, false );
				$display_name = ! empty( $row_data['Display Name'] ) ? $row_data['Display Name'] : $row_data['Email'];
				
				$user_id = wp_create_user( $username, $password, $row_data['Email'] );
				
				if ( is_wp_error( $user_id ) ) {
					$results['error']++;
					$results['errors'][] = sprintf( __( 'Row %d: Could not create user - %s', 'remember' ), $row_number, $user_id->get_error_message() );
					continue;
				}
				
				// Update user data
				wp_update_user( array(
					'ID'           => $user_id,
					'display_name' => $display_name,
					'first_name'   => $row_data['First Name'] ?? '',
					'last_name'    => $row_data['Last Name'] ?? '',
				) );
			} else {
				$user_id = $user->ID;
			}
			
			// Check if member record exists
			$member = $member_model->get( $user_id );
			if ( ! $member ) {
				$status = ! empty( $row_data['Status'] ) ? $row_data['Status'] : 'pending_vetting';
				$member_id = $member_model->create( $user_id, $status );
				
				if ( ! $member_id ) {
					$results['error']++;
					$results['errors'][] = sprintf( __( 'Row %d: Could not create member record.', 'remember' ), $row_number );
					continue;
				}
			}
			
			// Create or update profile
			global $wpdb;
			$profile = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
					$user_id
				)
			);
			
			$profile_data = array(
				'legal_first_name'            => $row_data['Legal First Name'] ?? '',
				'legal_last_name'             => $row_data['Legal Last Name'] ?? '',
				'address_street'              => $row_data['Street Address'] ?? '',
				'address_city'                => $row_data['City'] ?? '',
				'address_state'               => $row_data['State'] ?? '',
				'address_postal'              => $row_data['Postal Code'] ?? '',
				'address_country'             => $row_data['Country'] ?? 'US',
				'cell_phone'                  => $row_data['Cell Phone'] ?? '',
				'timezone'                    => $row_data['Timezone'] ?? '',
				'im_handle'                   => $row_data['IM Handle'] ?? '',
				'im_type'                     => $row_data['IM Type'] ?? 'telegram',
				'interests'                   => $row_data['Interests'] ?? '',
				'emergency_contact_first'     => $row_data['Emergency Contact First'] ?? '',
				'emergency_contact_last'      => $row_data['Emergency Contact Last'] ?? '',
				'emergency_contact_phone'     => $row_data['Emergency Contact Phone'] ?? '',
				'emergency_contact_relationship' => $row_data['Emergency Contact Relationship'] ?? '',
				'updated_at'                  => current_time( 'mysql' ),
			);
			
			if ( $profile ) {
				$wpdb->update(
					$wpdb->prefix . 'remember_member_profiles',
					$profile_data,
					array( 'member_id' => $user_id ),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$profile_data['member_id'] = $user_id;
				$profile_data['created_at'] = current_time( 'mysql' );
				$wpdb->insert(
					$wpdb->prefix . 'remember_member_profiles',
					$profile_data,
					array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
			
			$results['success']++;
		}
		
		fclose( $handle );
		return $results;
	}

	/**
	 * Import events from CSV.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array Results array with success/error counts.
	 */
	public static function import_events( $file_path ) {
		$results = array(
			'success' => 0,
			'error'   => 0,
			'errors'  => array(),
		);
		
		if ( ! file_exists( $file_path ) ) {
			$results['errors'][] = __( 'File not found.', 'remember' );
			return $results;
		}
		
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			$results['errors'][] = __( 'Could not open file for reading.', 'remember' );
			return $results;
		}
		
		// Skip BOM if present
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}
		
		// Read headers
		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$event_model = new Remember_Event();
		$row_number = 1;
		
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;
			
			if ( count( $data ) < count( $headers ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Insufficient columns.', 'remember' ), $row_number );
				continue;
			}
			
			$row_data = array_combine( $headers, $data );
			
			// Required fields
			if ( empty( $row_data['Event Name'] ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Event Name is required.', 'remember' ), $row_number );
				continue;
			}
			
			if ( empty( $row_data['Start Date'] ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Start Date is required.', 'remember' ), $row_number );
				continue;
			}
			
			// Parse dates
			$start_date = self::parse_date( $row_data['Start Date'] );
			$end_date = ! empty( $row_data['End Date'] ) ? self::parse_date( $row_data['End Date'] ) : $start_date;
			
			if ( ! $start_date ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Invalid Start Date format.', 'remember' ), $row_number );
				continue;
			}
			
			// Get location ID if location name or ID provided
			$location_id = null;
			if ( ! empty( $row_data['Location Name'] ) ) {
				$location_model = new Remember_Location();
				$location = $location_model->get_by_name( $row_data['Location Name'] );
				if ( $location ) {
					$location_id = $location->location_id;
				} else {
					// Don't fail if location not found, just log a warning
					$results['errors'][] = sprintf( __( 'Row %d: Location "%s" not found. Event will be created without location.', 'remember' ), $row_number, $row_data['Location Name'] );
				}
			} elseif ( ! empty( $row_data['Location ID'] ) ) {
				$location_id = absint( $row_data['Location ID'] );
			}
			
			$event_data = array(
				'event_name'        => $row_data['Event Name'],
				'event_description' => $row_data['Description'] ?? '',
				'start_date'        => $start_date,
				'end_date'          => $end_date,
				'status'            => ! empty( $row_data['Status'] ) ? $row_data['Status'] : 'draft',
				'is_private'        => ( ! empty( $row_data['Is Private'] ) && strtolower( $row_data['Is Private'] ) === 'yes' ) ? 1 : 0,
				'location_id'       => $location_id,
			);
			
			$event_id = $event_model->create( $event_data );
			
			if ( ! $event_id ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Could not create event.', 'remember' ), $row_number );
				continue;
			}
			
			$results['success']++;
		}
		
		fclose( $handle );
		return $results;
	}

	/**
	 * Import locations from CSV.
	 *
	 * @param string $file_path Path to CSV file.
	 * @return array Results array with success/error counts.
	 */
	public static function import_locations( $file_path ) {
		$results = array(
			'success' => 0,
			'error'   => 0,
			'errors'  => array(),
		);
		
		if ( ! file_exists( $file_path ) ) {
			$results['errors'][] = __( 'File not found.', 'remember' );
			return $results;
		}
		
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			$results['errors'][] = __( 'Could not open file for reading.', 'remember' );
			return $results;
		}
		
		// Skip BOM if present
		$bom = fread( $handle, 3 );
		if ( $bom !== chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) {
			rewind( $handle );
		}
		
		// Read headers
		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$location_model = new Remember_Location();
		$row_number = 1;
		
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$row_number++;
			
			if ( count( $data ) < count( $headers ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Insufficient columns.', 'remember' ), $row_number );
				continue;
			}
			
			$row_data = array_combine( $headers, $data );
			
			// Required fields
			if ( empty( $row_data['Location Name'] ) ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Location Name is required.', 'remember' ), $row_number );
				continue;
			}
			
			// Check if location already exists
			$existing = $location_model->get_by_name( $row_data['Location Name'] );
			if ( $existing ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Location "%s" already exists.', 'remember' ), $row_number, $row_data['Location Name'] );
				continue;
			}
			
			$location_data = array(
				'location_name'  => $row_data['Location Name'],
				'address_street' => $row_data['Street Address'] ?? '',
				'address_city'   => $row_data['City'] ?? '',
				'address_state'  => $row_data['State'] ?? '',
				'address_postal' => $row_data['Postal Code'] ?? '',
				'address_country' => $row_data['Country'] ?? 'US',
				'details'        => $row_data['Details'] ?? '',
				'is_active'      => ( ! empty( $row_data['Is Active'] ) && strtolower( $row_data['Is Active'] ) === 'yes' ) ? 1 : 0,
			);
			
			$location_id = $location_model->create( $location_data );
			
			if ( ! $location_id ) {
				$results['error']++;
				$results['errors'][] = sprintf( __( 'Row %d: Could not create location.', 'remember' ), $row_number );
				continue;
			}
			
			$results['success']++;
		}
		
		fclose( $handle );
		return $results;
	}

	/**
	 * Parse date string to MySQL format.
	 *
	 * @param string $date_string Date string in various formats.
	 * @return string|false MySQL date string or false on failure.
	 */
	private static function parse_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return false;
		}
		
		$date_string = trim( $date_string );
		
		// Try common date formats
		$formats = array(
			'Y-m-d',
			'm/d/Y',
			'd/m/Y',
			'Y/m/d',
			'm-d-Y',
			'd-m-Y',
		);
		
		foreach ( $formats as $format ) {
			$date = DateTime::createFromFormat( $format, $date_string );
			if ( $date !== false ) {
				// Check if the date was parsed correctly
				$errors = DateTime::getLastErrors();
				if ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
					continue;
				}
				return $date->format( 'Y-m-d' );
			}
		}
		
		// Try strtotime as fallback
		$timestamp = strtotime( $date_string );
		if ( $timestamp !== false ) {
			$parsed = date( 'Y-m-d', $timestamp );
			// Validate the parsed date
			if ( $parsed && $parsed !== '1970-01-01' ) {
				return $parsed;
			}
		}
		
		return false;
	}
}
