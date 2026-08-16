<?php
/**
 * Import/Export page view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Template downloads and CSV exports are handled in Remember_Admin::handle_import_export_requests() on admin_init.

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-profile-questions.php';
$remember_custom_field_keys = Remember_Profile_Questions::export_field_keys();

// Handle form submissions (imports only; exports run on admin_init).
if ( isset( $_POST['remember_import_export_action'] ) ) {
	check_admin_referer( 'remember_import_export_action', 'remember_import_export_nonce' );

	if ( ! current_user_can( 'remember_import_export' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to import data.', 'remember' ) );
	}
	
	$action = sanitize_text_field( wp_unslash( $_POST['remember_import_export_action'] ) );
	
	// Handle imports
	if ( in_array( $action, array( 'import_members', 'import_events', 'import_locations', 'import_profile_questions' ), true ) ) {
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error uploading file. Please try again.', 'remember' ) . '</p></div>';
		} else {
			$file = $_FILES['import_file'];
			$file_type = wp_check_filetype( $file['name'] );
			
			if ( ! in_array( $file_type['ext'], array( 'csv' ), true ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid file type. Please upload a CSV file.', 'remember' ) . '</p></div>';
			} else {
				// Move uploaded file to temp location
				$upload_dir = wp_upload_dir();
				$temp_file = $upload_dir['path'] . '/' . sanitize_file_name( $file['name'] );
				
				if ( move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
					$results = array();
					
					if ( 'import_members' === $action ) {
						$results = Remember_Import_Export::import_members( $temp_file );
					} elseif ( 'import_events' === $action ) {
						$results = Remember_Import_Export::import_events( $temp_file );
					} elseif ( 'import_locations' === $action ) {
						$results = Remember_Import_Export::import_locations( $temp_file );
					} else {
						$results = Remember_Import_Export::import_profile_questions( $temp_file );
					}
					
					// Display results
					if ( $results['success'] > 0 ) {
						echo '<div class="notice notice-success is-dismissible"><p>' . 
							esc_html( sprintf( 
								_n( 
									'Successfully imported %d record.', 
									'Successfully imported %d records.', 
									$results['success'], 
									'remember' 
								), 
								$results['success'] 
							) ) . 
							'</p></div>';
					}
					
					if ( $results['error'] > 0 ) {
						echo '<div class="notice notice-warning is-dismissible"><p>' . 
							esc_html( sprintf( 
								_n( 
									'%d record failed to import.', 
									'%d records failed to import.', 
									$results['error'], 
									'remember' 
								), 
								$results['error'] 
							) ) . 
							'</p></div>';
						
						if ( ! empty( $results['errors'] ) && count( $results['errors'] ) <= 20 ) {
							echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Errors:', 'remember' ) . '</strong></p><ul style="margin-left: 20px;">';
							foreach ( $results['errors'] as $error ) {
								echo '<li>' . esc_html( $error ) . '</li>';
							}
							echo '</ul></div>';
						} elseif ( ! empty( $results['errors'] ) ) {
							echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Too many errors to display. Please check your CSV file format.', 'remember' ) . '</p></div>';
						}
					}
					
					// Clean up temp file
					@unlink( $temp_file );
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save uploaded file.', 'remember' ) . '</p></div>';
				}
			}
		}
	}
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p class="description"><?php esc_html_e( 'Requires the Import / Export Data capability. Assign it on Roles when someone besides System Administrators should move bulk data.', 'remember' ); ?></p>
	
	<div class="remember-import-export-container" style="margin-top: 20px;">
		
		<!-- Members Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Members', 'remember' ); ?></h2>
			
			<h3><?php esc_html_e( 'Export', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Export all members to a CSV file, including clothing sizes, dietary/allergy/medical lists, and custom field answers (columns use each field’s Short name).', 'remember' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_members">
				<?php submit_button( __( 'Export Members', 'remember' ), 'secondary', 'submit', false ); ?>
			</form>
			
			<hr style="margin: 20px 0;">
			
			<h3><?php esc_html_e( 'Import', 'remember' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Required: Email. Also supported: Display Name, Nickname, First/Last Name, Status, Legal First/Last, address fields, Cell Phone, Timezone, IM Handle/Type, Shirt/Pants/Shoe Size, Interests, Emergency Contact fields, Dietary Restrictions, Allergies, Medical Accommodations (comma-separated catalog names), plus any custom Short name columns.', 'remember' ); ?>
			</p>
			<?php if ( ! empty( $remember_custom_field_keys ) ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: comma-separated short names */
							__( 'Custom columns currently defined: %s', 'remember' ),
							implode( ', ', $remember_custom_field_keys )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-import-export&remember_import_export_action=download_members_template' ), 'remember_import_export_action', 'remember_import_export_nonce' ) ); ?>" class="button button-secondary" style="margin-bottom: 10px;">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Download Template', 'remember' ); ?>
				</a>
			</p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="import_members">
				<p>
					<input type="file" name="import_file" accept=".csv" required>
				</p>
				<?php submit_button( __( 'Import Members', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>

		<!-- Custom Fields definitions -->
		<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Custom Fields', 'remember' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Move the field definitions (Short name, question text, type, options) between sites before importing member answers that use those Short names.', 'remember' ); ?></p>

			<h3><?php esc_html_e( 'Export', 'remember' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_profile_questions">
				<?php submit_button( __( 'Export Custom Fields', 'remember' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr style="margin: 20px 0;">

			<h3><?php esc_html_e( 'Import', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Upserts by Short Name. Columns: Short Name, Question, Type (text|select|multiselect), Options (key|Label;key|Label), Required, Active, Order.', 'remember' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-import-export&remember_import_export_action=download_profile_questions_template' ), 'remember_import_export_action', 'remember_import_export_nonce' ) ); ?>" class="button button-secondary" style="margin-bottom: 10px;">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Download Template', 'remember' ); ?>
				</a>
			</p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="import_profile_questions">
				<p>
					<input type="file" name="import_file" accept=".csv" required>
				</p>
				<?php submit_button( __( 'Import Custom Fields', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		
		<!-- Events Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Events', 'remember' ); ?></h2>
			
			<h3><?php esc_html_e( 'Export', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Export all events to a CSV file.', 'remember' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_events">
				<?php submit_button( __( 'Export Events', 'remember' ), 'secondary', 'submit', false ); ?>
			</form>
			
			<hr style="margin: 20px 0;">
			
			<h3><?php esc_html_e( 'Import', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Import events from a CSV file. Required columns: Event Name, Start Date. Optional columns: Description, End Date, Status, Is Private, Location ID, Location Name.', 'remember' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-import-export&remember_import_export_action=download_events_template' ), 'remember_import_export_action', 'remember_import_export_nonce' ) ); ?>" class="button button-secondary" style="margin-bottom: 10px;">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Download Template', 'remember' ); ?>
				</a>
			</p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="import_events">
				<p>
					<input type="file" name="import_file" accept=".csv" required>
				</p>
				<?php submit_button( __( 'Import Events', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		
		<!-- Locations Section -->
		<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Locations', 'remember' ); ?></h2>
			
			<h3><?php esc_html_e( 'Export', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Export all locations to a CSV file.', 'remember' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_locations">
				<?php submit_button( __( 'Export Locations', 'remember' ), 'secondary', 'submit', false ); ?>
			</form>
			
			<hr style="margin: 20px 0;">
			
			<h3><?php esc_html_e( 'Import', 'remember' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Import locations from a CSV file. Required columns: Location Name. Optional columns: Street Address, City, State, Postal Code, Country, Details, Is Active.', 'remember' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=remember-import-export&remember_import_export_action=download_locations_template' ), 'remember_import_export_action', 'remember_import_export_nonce' ) ); ?>" class="button button-secondary" style="margin-bottom: 10px;">
					<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Download Template', 'remember' ); ?>
				</a>
			</p>
			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="import_locations">
				<p>
					<input type="file" name="import_file" accept=".csv" required>
				</p>
				<?php submit_button( __( 'Import Locations', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		
	</div>
	
	<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 20px;">
		<h2><?php esc_html_e( 'Import/Export Tips', 'remember' ); ?></h2>
		<ul style="margin-left: 20px;">
			<li><?php esc_html_e( 'CSV files should be UTF-8 encoded for best compatibility.', 'remember' ); ?></li>
			<li><?php esc_html_e( 'Demo → production: export Custom Fields first, import them on the target site, then export/import Members so Short name columns match.', 'remember' ); ?></li>
			<li><?php esc_html_e( 'When importing members, existing users are matched by email and updated; missing users are created with auto-generated passwords.', 'remember' ); ?></li>
			<li><?php esc_html_e( 'Custom field answers use option keys (e.g. yes or key|key for multi-select), not display labels.', 'remember' ); ?></li>
			<li><?php esc_html_e( 'Date formats supported: YYYY-MM-DD, MM/DD/YYYY, DD/MM/YYYY, and other common formats.', 'remember' ); ?></li>
			<li><?php esc_html_e( 'For Yes/No columns, use Yes or No (case-insensitive).', 'remember' ); ?></li>
			<li><?php esc_html_e( 'Export a file first to see the exact column format expected.', 'remember' ); ?></li>
		</ul>
	</div>
	
</div>
