<?php
/**
 * Import/Export page view
 *
 * @package    reMember
 * @subpackage reMember/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-profile-questions.php';
require_once plugin_dir_path( __FILE__ ) . '../../includes/utilities/class-remember-backup.php';
$remember_custom_field_keys = Remember_Profile_Questions::export_field_keys();
$remember_can_backup        = Remember_Backup::current_user_can_backup();

// Handle form submissions (imports only; exports run on admin_init).
if ( isset( $_POST['remember_import_export_action'] ) ) {
	check_admin_referer( 'remember_import_export_action', 'remember_import_export_nonce' );

	if ( ! current_user_can( 'remember_import_export' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to import data.', 'remember' ) );
	}

	$action = sanitize_text_field( wp_unslash( $_POST['remember_import_export_action'] ) );

	if ( in_array( $action, array( 'import_members', 'import_events', 'import_locations', 'import_profile_questions' ), true ) ) {
		if ( ! isset( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== $_FILES['import_file']['error'] ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error uploading file. Please try again.', 'remember' ) . '</p></div>';
		} else {
			$file      = $_FILES['import_file'];
			$file_type = wp_check_filetype( $file['name'] );

			if ( ! in_array( $file_type['ext'], array( 'csv' ), true ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid file type. Please upload a CSV file.', 'remember' ) . '</p></div>';
			} else {
				$upload_dir = wp_upload_dir();
				$temp_file  = $upload_dir['path'] . '/' . sanitize_file_name( $file['name'] );

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

					if ( $results['success'] > 0 ) {
						echo '<div class="notice notice-success is-dismissible"><p>' .
							esc_html(
								sprintf(
									_n(
										'Successfully imported %d record.',
										'Successfully imported %d records.',
										$results['success'],
										'remember'
									),
									$results['success']
								)
							) .
							'</p></div>';
					}

					if ( $results['error'] > 0 ) {
						echo '<div class="notice notice-warning is-dismissible"><p>' .
							esc_html(
								sprintf(
									_n(
										'%d record failed to import.',
										'%d records failed to import.',
										$results['error'],
										'remember'
									),
									$results['error']
								)
							) .
							'</p></div>';

						if ( ! empty( $results['errors'] ) && count( $results['errors'] ) <= 20 ) {
							echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Errors:', 'remember' ) . '</strong></p><ul>';
							foreach ( $results['errors'] as $error ) {
								echo '<li>' . esc_html( $error ) . '</li>';
							}
							echo '</ul></div>';
						} elseif ( ! empty( $results['errors'] ) ) {
							echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Too many errors to display. Please check your CSV file format.', 'remember' ) . '</p></div>';
						}
					}

					@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save uploaded file.', 'remember' ) . '</p></div>';
				}
			}
		}
	} elseif ( 'import_backup' === $action && $remember_can_backup ) {
		$phrase   = isset( $_POST['remember_restore_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['remember_restore_phrase'] ) ) : '';
		$confirm  = ! empty( $_POST['remember_restore_confirm'] );
		if ( ! $confirm || 'RESTORE' !== $phrase ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Restore cancelled. Check the confirmation box and type RESTORE.', 'remember' ) . '</p></div>';
		} elseif ( ! isset( $_FILES['backup_file'] ) || UPLOAD_ERR_OK !== $_FILES['backup_file']['error'] ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error uploading file. Please try again.', 'remember' ) . '</p></div>';
		} else {
			$file = $_FILES['backup_file'];
			$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( 'json' !== $ext ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Upload a .json backup file.', 'remember' ) . '</p></div>';
			} else {
				$upload_dir = wp_upload_dir();
				$temp_file  = $upload_dir['path'] . '/' . sanitize_file_name( $file['name'] );
				if ( move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
					$result = Remember_Backup::restore_from_file( $temp_file );
					@unlink( $temp_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( is_wp_error( $result ) ) {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
					} else {
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
							sprintf(
								/* translators: 1: tables, 2: rows, 3: matched users, 4: created users */
								__( 'Restore complete: %1$d tables, %2$d rows. Matched %3$d WordPress users and created %4$d. New accounts have random passwords until those people reset them.', 'remember' ),
								(int) $result['tables'],
								(int) $result['rows'],
								(int) $result['users_matched'],
								(int) $result['users_created']
							)
						) . '</p></div>';
					}
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not save uploaded file.', 'remember' ) . '</p></div>';
				}
			}
		}
	}
}

$remember_ie_page  = admin_url( 'admin.php?page=remember-import-export' );

$remember_ie_tools = array(
	array(
		'title'         => __( 'Members', 'remember' ),
		'blurb'         => __( 'CSV of profiles. Existing emails are updated; new emails create WordPress users.', 'remember' ),
		'export'        => 'export_members',
		'export_label'  => __( 'Export', 'remember' ),
		'import'        => 'import_members',
		'import_label'  => __( 'Import', 'remember' ),
		'template'      => 'download_members_template',
		'columns'       => __( 'Required: Email. Also: names, status, address, phone, timezone, IM, clothing sizes, interests, emergency contact, dietary / allergies / medical (comma-separated catalog names), and custom Short name columns. Answers use option keys, not labels.', 'remember' ),
		'extra_columns' => $remember_custom_field_keys,
	),
	array(
		'title'        => __( 'Custom Fields', 'remember' ),
		'blurb'        => __( 'Field definitions. Import these before member answers that use those Short names.', 'remember' ),
		'export'       => 'export_profile_questions',
		'export_label' => __( 'Export', 'remember' ),
		'import'       => 'import_profile_questions',
		'import_label' => __( 'Import', 'remember' ),
		'template'     => 'download_profile_questions_template',
		'columns'      => __( 'Upserts by Short Name. Columns: Short Name, Question, Type (text|select|multiselect), Options (key|Label;key|Label), Required, Active, Order.', 'remember' ),
	),
	array(
		'title'        => __( 'Events', 'remember' ),
		'blurb'        => __( 'Event records. Location can be an ID or name.', 'remember' ),
		'export'       => 'export_events',
		'export_label' => __( 'Export', 'remember' ),
		'import'       => 'import_events',
		'import_label' => __( 'Import', 'remember' ),
		'template'     => 'download_events_template',
		'columns'      => __( 'Required: Event Name, Start Date. Optional: Description, End Date, Status, Is Private, Location ID, Location Name.', 'remember' ),
	),
	array(
		'title'        => __( 'Locations', 'remember' ),
		'blurb'        => __( 'Venues used by events.', 'remember' ),
		'export'       => 'export_locations',
		'export_label' => __( 'Export', 'remember' ),
		'import'       => 'import_locations',
		'import_label' => __( 'Import', 'remember' ),
		'template'     => 'download_locations_template',
		'columns'      => __( 'Required: Location Name. Optional: Street Address, City, State, Postal Code, Country, Details, Is Active.', 'remember' ),
	),
);
?>
<div class="wrap remember-import-export">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p class="description"><?php esc_html_e( 'CSV tools need Import / Export Data. A full backup also needs Access Settings. UTF-8 CSV. Yes/No columns accept Yes or No. Dates: YYYY-MM-DD or common US/EU formats.', 'remember' ); ?></p>

	<?php if ( $remember_can_backup ) : ?>
		<div class="remember-ie-backup">
			<div>
				<h2><?php esc_html_e( 'Full backup', 'remember' ); ?></h2>
				<p><?php esc_html_e( 'JSON of every reMember table and plugin setting. Billing secrets, OAuth tokens, and encryption keys are omitted. Payment rows keep invoice IDs (not downloaded ledgers) and the user index keeps QuickBooks/Xero account IDs so a restored site can reconnect and redownload. WordPress users and passwords are not stored; a login/email index is, so restore can match or create accounts. Profile photos are URLs only. Store the file privately.', 'remember' ); ?></p>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="export_backup">
				<?php submit_button( __( 'Download backup', 'remember' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<div class="remember-ie-backup remember-ie-restore">
			<div>
				<h2><?php esc_html_e( 'Restore backup', 'remember' ); ?></h2>
				<p><?php esc_html_e( 'Replaces all current reMember data with this file. Use it to bring a backup back, or to load a backup onto a fresh install (migration). This site must be the same plugin and database version as the backup, or newer — a 2.1 backup will not load into 1.4. Existing WordPress users are matched by email, then username; anyone missing is created as a Subscriber with a random password. WordPress users are never deleted. Setup-wizard pages and this site’s plugin version number are left as they are. After restore, enter the billing client secret and reconnect QuickBooks or Xero so invoices and payments refill from the provider. Large files may need a higher PHP upload limit.', 'remember' ); ?></p>
			</div>
			<form method="post" action="" enctype="multipart/form-data" class="remember-ie-restore-form">
				<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
				<input type="hidden" name="remember_import_export_action" value="import_backup">
				<p>
					<input type="file" name="backup_file" accept=".json,application/json" required>
				</p>
				<p>
					<label>
						<input type="checkbox" name="remember_restore_confirm" value="1" required>
						<?php esc_html_e( 'I understand this overwrites all current reMember data and cannot be undone except by restoring another backup.', 'remember' ); ?>
					</label>
				</p>
				<p>
					<label>
						<?php esc_html_e( 'Type RESTORE', 'remember' ); ?>
						<input type="text" name="remember_restore_phrase" value="" autocomplete="off" class="regular-text" placeholder="RESTORE" required>
					</label>
				</p>
				<?php submit_button( __( 'Restore backup', 'remember' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	<?php endif; ?>

	<div class="remember-import-export-container">
		<?php foreach ( $remember_ie_tools as $tool ) : ?>
			<div class="remember-ie-card">
				<h2><?php echo esc_html( $tool['title'] ); ?></h2>
				<p class="description"><?php echo esc_html( $tool['blurb'] ); ?></p>
				<div class="remember-ie-actions">
					<form method="post" action="">
						<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
						<input type="hidden" name="remember_import_export_action" value="<?php echo esc_attr( $tool['export'] ); ?>">
						<?php submit_button( $tool['export_label'], 'secondary', 'submit', false ); ?>
					</form>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'remember_import_export_action', $tool['template'], $remember_ie_page ), 'remember_import_export_action', 'remember_import_export_nonce' ) ); ?>">
						<?php esc_html_e( 'Template', 'remember' ); ?>
					</a>
				</div>
				<form class="remember-ie-import" method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'remember_import_export_action', 'remember_import_export_nonce' ); ?>
					<input type="hidden" name="remember_import_export_action" value="<?php echo esc_attr( $tool['import'] ); ?>">
					<input type="file" name="import_file" accept=".csv" required>
					<?php submit_button( $tool['import_label'], 'primary', 'submit', false ); ?>
				</form>
				<details class="remember-ie-columns">
					<summary><?php esc_html_e( 'Columns', 'remember' ); ?></summary>
					<p><?php echo esc_html( $tool['columns'] ); ?></p>
					<?php if ( ! empty( $tool['extra_columns'] ) ) : ?>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated short names */
									__( 'Custom columns currently defined: %s', 'remember' ),
									implode( ', ', $tool['extra_columns'] )
								)
							);
							?>
						</p>
					<?php endif; ?>
				</details>
			</div>
		<?php endforeach; ?>
	</div>
</div>
