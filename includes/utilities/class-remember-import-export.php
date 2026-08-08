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
		
		$headers = self::member_csv_headers();
		fputcsv( $output, $headers );

		// Add example row (core columns only; custom field keys are empty).
		$example = array(
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
		);
		while ( count( $example ) < count( $headers ) ) {
			$example[] = '';
		}
		fputcsv( $output, $example );
		
		fclose( $output );
		exit;
	}

	/**
	 * Export members to CSV.
	 *
	 * @return void
	 */
	/**
	 * Member CSV column headers (core + custom profile question field keys).
	 *
	 * @return array<int, string>
	 */
	public static function member_csv_headers() {
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		$headers = array(
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
		);
		return array_merge( $headers, Remember_Profile_Questions::export_field_keys() );
	}

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

		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
		$headers    = self::member_csv_headers();
		$field_keys = Remember_Profile_Questions::export_field_keys();
		fputcsv( $output, $headers );
		
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

			$custom = Remember_Profile_Questions::get_responses_by_field_key( (int) $member->member_id );
			$row    = array(
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
			);
			foreach ( $field_keys as $fkey ) {
				$row[] = isset( $custom[ $fkey ] ) ? $custom[ $fkey ] : '';
			}
			fputcsv( $output, $row );
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
	 * True if a name field should be ignored (empty, Excel zero, etc.).
	 *
	 * @param mixed $value Field value.
	 * @return bool
	 */
	private static function member_import_is_placeholder_name( $value ) {
		$s = trim( (string) $value );
		if ( '' === $s ) {
			return true;
		}
		if ( '0' === $s ) {
			return true;
		}
		if ( is_numeric( $s ) && (float) $s === 0.0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Trim; empty string if value is a placeholder.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	private static function member_import_normalize_csv_name( $value ) {
		$s = trim( (string) $value );
		if ( self::member_import_is_placeholder_name( $s ) ) {
			return '';
		}
		return $s;
	}

	/**
	 * Legal names from CSV + WordPress user when placeholders appear in the sheet.
	 *
	 * @param array    $row_data Row keyed by CSV header.
	 * @param int|null $user_id  WordPress user ID after create/update, or null.
	 * @return array With keys legal_first_name and legal_last_name.
	 */
	private static function member_import_legal_names( $row_data, $user_id ) {
		$legal_first = self::member_import_normalize_csv_name( $row_data['Legal First Name'] ?? '' );
		$legal_last  = self::member_import_normalize_csv_name( $row_data['Legal Last Name'] ?? '' );
		$first       = self::member_import_normalize_csv_name( $row_data['First Name'] ?? '' );
		$last        = self::member_import_normalize_csv_name( $row_data['Last Name'] ?? '' );

		if ( self::member_import_is_placeholder_name( $legal_first ) ) {
			$legal_first = $first;
		}
		if ( self::member_import_is_placeholder_name( $legal_last ) ) {
			$legal_last = $last;
		}

		if ( $user_id ) {
			if ( self::member_import_is_placeholder_name( $legal_first ) ) {
				$meta_first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
				if ( ! self::member_import_is_placeholder_name( $meta_first ) ) {
					$legal_first = $meta_first;
				}
			}
			if ( self::member_import_is_placeholder_name( $legal_last ) ) {
				$meta_last = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
				if ( ! self::member_import_is_placeholder_name( $meta_last ) ) {
					$legal_last = $meta_last;
				}
			}
		}

		// Never derive legal name from display_name — public identity is separate.
		if ( self::member_import_is_placeholder_name( $legal_first ) ) {
			$legal_first = '';
		}
		if ( self::member_import_is_placeholder_name( $legal_last ) ) {
			$legal_last = '';
		}

		return array(
			'legal_first_name' => $legal_first,
			'legal_last_name'  => $legal_last,
		);
	}

	/**
	 * Resolve legal first/last for display when DB has placeholders (e.g. "0") or one side is missing.
	 *
	 * @param object|null $profile Profile row or null.
	 * @param int         $user_id WordPress user ID.
	 * @return array Two strings: first name, last name.
	 */
	public static function member_resolve_legal_name_parts( $profile, $user_id ) {
		if ( ! $profile ) {
			return array( '', '' );
		}
		$first = trim( (string) $profile->legal_first_name );
		$last  = trim( (string) $profile->legal_last_name );

		if ( self::member_import_is_placeholder_name( $first ) ) {
			$first = trim( (string) get_user_meta( $user_id, 'first_name', true ) );
		}
		if ( self::member_import_is_placeholder_name( $last ) ) {
			$last = trim( (string) get_user_meta( $user_id, 'last_name', true ) );
		}

		// Do not fall back to display_name: that field is the member's public identity
		// and must not be treated as a legal-name substitute.
		if ( self::member_import_is_placeholder_name( $first ) ) {
			$first = '';
		}
		if ( self::member_import_is_placeholder_name( $last ) ) {
			$last = '';
		}

		return array( $first, $last );
	}

	/**
	 * Human-readable legal name line for admin lists when profile rows still hold placeholders.
	 *
	 * @param object|null $profile Profile row or null.
	 * @param int         $user_id WordPress user ID.
	 * @return string
	 */
	public static function member_list_legal_name_line( $profile, $user_id ) {
		list( $first, $last ) = self::member_resolve_legal_name_parts( $profile, $user_id );
		$line = trim( $first . ' ' . $last );
		// Legacy bad imports stored literal "0" as first name; show surname only for display.
		if ( preg_match( '/^0\s+(.+)/u', $line, $matches ) ) {
			return trim( $matches[1] );
		}
		return $line;
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
		$headers = self::read_csv_line( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$member_model = new Remember_Member();
		$row_number = 1;
		
		while ( ( $data = self::read_csv_line( $handle ) ) !== false ) {
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
				
				// Update user data (do not store Excel "0" placeholders in user meta).
				wp_update_user( array(
					'ID'           => $user_id,
					'display_name' => $display_name,
					'first_name'   => self::member_import_normalize_csv_name( $row_data['First Name'] ?? '' ),
					'last_name'    => self::member_import_normalize_csv_name( $row_data['Last Name'] ?? '' ),
				) );
			} else {
				$user_id = $user->ID;
				// Sync WP name fields from CSV on re-import (clears Excel "0" placeholders).
				$user_update = array( 'ID' => $user_id );
				if ( ! empty( trim( (string) ( $row_data['Display Name'] ?? '' ) ) ) ) {
					$user_update['display_name'] = trim( (string) $row_data['Display Name'] );
				}
				if ( array_key_exists( 'First Name', $row_data ) ) {
					$user_update['first_name'] = self::member_import_normalize_csv_name( $row_data['First Name'] );
				}
				if ( array_key_exists( 'Last Name', $row_data ) ) {
					$user_update['last_name'] = self::member_import_normalize_csv_name( $row_data['Last Name'] );
				}
				if ( count( $user_update ) > 1 ) {
					wp_update_user( $user_update );
				}
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
			
			$profile_data = array_merge(
				self::member_import_legal_names( $row_data, $user_id ),
				array(
					'address_street'                 => $row_data['Street Address'] ?? '',
					'address_city'                   => $row_data['City'] ?? '',
					'address_state'                  => $row_data['State'] ?? '',
					'address_postal'                 => $row_data['Postal Code'] ?? '',
					'address_country'                => $row_data['Country'] ?? 'US',
					'cell_phone'                     => $row_data['Cell Phone'] ?? '',
					'timezone'                       => $row_data['Timezone'] ?? '',
					'im_handle'                      => $row_data['IM Handle'] ?? '',
					'im_type'                        => $row_data['IM Type'] ?? 'telegram',
					'interests'                      => $row_data['Interests'] ?? '',
					'emergency_contact_first'        => $row_data['Emergency Contact First'] ?? '',
					'emergency_contact_last'         => $row_data['Emergency Contact Last'] ?? '',
					'emergency_contact_phone'        => $row_data['Emergency Contact Phone'] ?? '',
					'emergency_contact_relationship' => $row_data['Emergency Contact Relationship'] ?? '',
					'updated_at'                     => current_time( 'mysql' ),
				)
			);
			
			if ( $profile ) {
				$wpdb->update(
					$wpdb->prefix . 'remember_member_profiles',
					$profile_data,
					array( 'member_id' => $user_id ),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
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

			require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';
			foreach ( Remember_Profile_Questions::export_field_keys() as $fkey ) {
				if ( ! array_key_exists( $fkey, $row_data ) ) {
					continue;
				}
				$raw = trim( (string) $row_data[ $fkey ] );
				if ( '' === $raw ) {
					continue;
				}
				Remember_Profile_Questions::save_by_field_key( (int) $user_id, $fkey, $raw );
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
		$headers = self::read_csv_line( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$event_model = new Remember_Event();
		$row_number = 1;
		
		while ( ( $data = self::read_csv_line( $handle ) ) !== false ) {
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
		$headers = self::read_csv_line( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			$results['errors'][] = __( 'Could not read CSV headers.', 'remember' );
			return $results;
		}
		
		$location_model = new Remember_Location();
		$row_number = 1;
		
		while ( ( $data = self::read_csv_line( $handle ) ) !== false ) {
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

	/**
	 * Read one CSV row (PHP 8.4+ requires explicit $escape for fgetcsv()).
	 *
	 * @param resource $handle Open read handle.
	 * @return array<int, string>|false|null
	 */
	private static function read_csv_line( $handle ) {
		return fgetcsv( $handle, 0, ',', '"', '\\' );
	}

	/**
	 * Export accepted participants for one event (organizer roster dump).
	 *
	 * @param int $event_id Event ID.
	 * @return void Exits after download, or returns on invalid event.
	 */
	public static function export_event_participants( $event_id ) {
		global $wpdb;

		$event_id = absint( $event_id );
		if ( $event_id <= 0 ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '../models/class-event.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-profile-questions.php';

		$event_model = new Remember_Event();
		$event       = $event_model->get( $event_id );
		if ( ! $event ) {
			return;
		}

		$apps_table  = $wpdb->prefix . 'remember_event_applications';
		$er_table    = $wpdb->prefix . 'remember_event_roles';
		$roles_table = $wpdb->prefix . 'remember_roles';
		$merch_table = $wpdb->prefix . 'remember_event_merchandise';
		$app_merch   = $wpdb->prefix . 'remember_application_merchandise';

		$addons = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT merchandise_id, merchandise_name FROM {$merch_table}
				WHERE event_id = %d
				ORDER BY merchandise_name ASC, merchandise_id ASC",
				$event_id
			)
		);
		if ( ! is_array( $addons ) ) {
			$addons = array();
		}

		$field_keys = Remember_Profile_Questions::active_export_field_keys();

		$headers = array(
			'Application ID',
			'Event ID',
			'Event Name',
			'Event Role',
			'Applied At',
			'Status',
			'Ticket Voided',
			'Ticket Ready Emailed At',
			'User ID',
			'Email',
			'Display Name',
			'Nickname',
			'First Name',
			'Last Name',
			'Legal First Name',
			'Legal Last Name',
			'Street Address',
			'City',
			'State',
			'Postal Code',
			'Country',
			'Cell Phone',
			'Timezone',
			'IM Type',
			'IM Handle',
			'Shirt Size',
			'Pants Size',
			'Shoe Size',
			'Emergency Contact First',
			'Emergency Contact Last',
			'Emergency Contact Phone',
			'Emergency Contact Relationship',
			'Dietary Restrictions',
			'Allergies',
			'Medical Accommodations',
		);
		$headers = array_merge( $headers, $field_keys );
		foreach ( $addons as $addon ) {
			$headers[] = (string) $addon->merchandise_name;
		}
		$headers[] = 'Add-ons Summary';

		$applications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, r.role_name
				FROM {$apps_table} a
				LEFT JOIN {$er_table} er ON a.event_role_id = er.event_role_id
				LEFT JOIN {$roles_table} r ON er.role_id = r.role_id
				WHERE a.event_id = %d
					AND a.status = 'accepted'
					AND a.superseded_at IS NULL
				ORDER BY r.role_name ASC, a.applied_at ASC, a.application_id ASC",
				$event_id
			)
		);
		if ( ! is_array( $applications ) ) {
			$applications = array();
		}

		$filename = 'event-' . $event_id . '-participants-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, $headers );

		foreach ( $applications as $app ) {
			$user = get_user_by( 'ID', (int) $app->member_id );
			if ( ! $user ) {
				continue;
			}

			$profile = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}remember_member_profiles WHERE member_id = %d",
					(int) $app->member_id
				)
			);

			$qty_by_merch = array();
			$merch_rows   = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT merchandise_id, quantity FROM {$app_merch} WHERE event_application_id = %d",
					(int) $app->application_id
				)
			);
			if ( is_array( $merch_rows ) ) {
				foreach ( $merch_rows as $mr ) {
					$qty_by_merch[ (int) $mr->merchandise_id ] = (int) $mr->quantity;
				}
			}

			$summary_parts = array();
			foreach ( $addons as $addon ) {
				$mid = (int) $addon->merchandise_id;
				if ( isset( $qty_by_merch[ $mid ] ) && $qty_by_merch[ $mid ] > 0 ) {
					$summary_parts[] = $addon->merchandise_name . '×' . $qty_by_merch[ $mid ];
				}
			}

			$custom = Remember_Profile_Questions::get_responses_by_field_key( (int) $app->member_id );

			$row = array(
				(int) $app->application_id,
				$event_id,
				$event->event_name,
				isset( $app->role_name ) ? $app->role_name : '',
				isset( $app->applied_at ) ? $app->applied_at : '',
				'accepted',
				! empty( $app->ticket_voided ) ? 'Yes' : 'No',
				isset( $app->ticket_ready_emailed_at ) ? (string) $app->ticket_ready_emailed_at : '',
				(int) $app->member_id,
				$user->user_email,
				$user->display_name,
				get_user_meta( $user->ID, 'nickname', true ),
				$user->first_name,
				$user->last_name,
				$profile->legal_first_name ?? '',
				$profile->legal_last_name ?? '',
				$profile->address_street ?? '',
				$profile->address_city ?? '',
				$profile->address_state ?? '',
				$profile->address_postal ?? '',
				$profile->address_country ?? '',
				$profile->cell_phone ?? '',
				get_user_meta( $user->ID, 'timezone_string', true ),
				$profile->im_type ?? '',
				$profile->im_handle ?? '',
				$profile->shirt_size ?? '',
				$profile->pants_size ?? '',
				$profile->shoe_size ?? '',
				$profile->emergency_contact_first ?? '',
				$profile->emergency_contact_last ?? '',
				$profile->emergency_contact_phone ?? '',
				$profile->emergency_contact_relationship ?? '',
				self::member_list_labels( (int) $app->member_id, 'dietary' ),
				self::member_list_labels( (int) $app->member_id, 'allergies' ),
				self::member_list_labels( (int) $app->member_id, 'medical' ),
			);

			foreach ( $field_keys as $fkey ) {
				$row[] = isset( $custom[ $fkey ] ) ? $custom[ $fkey ] : '';
			}
			foreach ( $addons as $addon ) {
				$mid = (int) $addon->merchandise_id;
				$row[] = ( isset( $qty_by_merch[ $mid ] ) && $qty_by_merch[ $mid ] > 0 ) ? (string) $qty_by_merch[ $mid ] : '';
			}
			$row[] = implode( '; ', $summary_parts );

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Comma-separated labels for a member's dietary / allergy / medical selections.
	 *
	 * @param int    $member_id Member ID.
	 * @param string $type      dietary|allergies|medical.
	 * @return string
	 */
	private static function member_list_labels( $member_id, $type ) {
		global $wpdb;
		$member_id = absint( $member_id );
		if ( $member_id <= 0 ) {
			return '';
		}

		if ( 'dietary' === $type ) {
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT dr.restriction_name
					FROM {$wpdb->prefix}remember_dietary_restrictions dr
					INNER JOIN {$wpdb->prefix}remember_member_dietary_restrictions mdr ON dr.restriction_id = mdr.restriction_id
					WHERE mdr.member_id = %d
					ORDER BY dr.sort_order ASC, dr.restriction_name ASC",
					$member_id
				)
			);
		} elseif ( 'allergies' === $type ) {
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT a.allergy_name
					FROM {$wpdb->prefix}remember_allergies a
					INNER JOIN {$wpdb->prefix}remember_member_allergies ma ON a.allergy_id = ma.allergy_id
					WHERE ma.member_id = %d
					ORDER BY a.sort_order ASC, a.allergy_name ASC",
					$member_id
				)
			);
		} else {
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ma.accommodation_name
					FROM {$wpdb->prefix}remember_medical_accommodations ma
					INNER JOIN {$wpdb->prefix}remember_member_medical_accommodations mma ON ma.accommodation_id = mma.accommodation_id
					WHERE mma.member_id = %d
					ORDER BY ma.sort_order ASC, ma.accommodation_name ASC",
					$member_id
				)
			);
		}

		if ( ! is_array( $names ) || empty( $names ) ) {
			return '';
		}
		return implode( ', ', $names );
	}
}
