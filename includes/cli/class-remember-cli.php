<?php
/**
 * WP-CLI commands for backup, import, export, billing sync, and duplicate scan.
 *
 * @package    reMember
 * @subpackage reMember/includes/cli
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * reMember command.
 */
class Remember_CLI {

	/**
	 * Write a full reMember backup to a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Destination path. Defaults to remember-backup-*.json in the current directory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember backup --file=/var/backups/remember.json --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function backup( $args, $assoc_args ) {
		$this->require_user();
		$this->require_cap( array( 'remember_access_settings', 'remember_import_export' ), __( 'You cannot download a full reMember backup.', 'remember' ) );
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-backup.php';

		$file = \WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );
		if ( '' === $file ) {
			$file = trailingslashit( getcwd() ) . 'remember-backup-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
		}
		$result = $this->with_progress(
			__( 'Backing up', 'remember' ),
			static function () use ( $file ) {
				return Remember_Backup::write_to_path( $file );
			}
		);
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Wrote %s', $file ) );
	}

	/**
	 * Replace reMember data from a backup file.
	 *
	 * Existing WordPress users are kept. The restore does not run unless --yes is passed.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a reMember backup JSON file.
	 *
	 * [--yes]
	 * : Confirm replacing reMember data.
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember restore /var/backups/remember.json --yes --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function restore( $args, $assoc_args ) {
		$this->require_user();
		$file = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $file ) {
			WP_CLI::error( 'Pass the backup file path.' );
		}
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false ) ) {
			WP_CLI::error( 'Restoring replaces reMember data. Pass --yes to continue.' );
		}
		$this->require_cap( array( 'remember_access_settings', 'remember_import_export' ), __( 'You cannot restore a full reMember backup.', 'remember' ) );
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-backup.php';

		$result = $this->with_progress(
			__( 'Restoring', 'remember' ),
			static function () use ( $file ) {
				return Remember_Backup::restore_from_file( $file );
			}
		);
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success(
			sprintf(
				'Restored %1$d tables (%2$d rows). Matched %3$d users and created %4$d.',
				(int) $result['tables'],
				(int) $result['rows'],
				(int) $result['users_matched'],
				(int) $result['users_created']
			)
		);
	}

	/**
	 * Import a CSV.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : members, events, locations, or profile-questions.
	 *
	 * <file>
	 * : CSV path.
	 *
	 * [--dry-run]
	 * : Check rows and save nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember import members members.csv --dry-run --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$this->require_user();
		$this->require_cap( 'remember_import_export', __( 'You cannot import reMember data.', 'remember' ) );
		$type = isset( $args[0] ) ? (string) $args[0] : '';
		$file = isset( $args[1] ) ? (string) $args[1] : '';
		$map  = array(
			'members'           => 'import_members',
			'events'            => 'import_events',
			'locations'         => 'import_locations',
			'profile-questions' => 'import_profile_questions',
		);
		if ( ! isset( $map[ $type ] ) || '' === $file ) {
			WP_CLI::error( 'Use: wp remember import members|events|locations|profile-questions <file.csv>' );
		}
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-import-export.php';
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$method  = $map[ $type ];
		$results = $this->with_progress(
			$dry_run ? __( 'Checking', 'remember' ) : __( 'Importing', 'remember' ),
			static function () use ( $method, $file, $dry_run ) {
				return Remember_Import_Export::$method( $file, $dry_run );
			}
		);
		$this->report_import( $results, $dry_run );
	}

	/**
	 * Export members, events, or locations to a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : members, events, or locations.
	 *
	 * [--file=<path>]
	 * : Destination path. Defaults to a timestamped CSV in the current directory.
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember export members --file=/tmp/members.csv --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$this->require_user();
		$type = isset( $args[0] ) ? (string) $args[0] : '';
		$map  = array(
			'members'   => 'export_members',
			'events'    => 'export_events',
			'locations' => 'export_locations',
		);
		if ( ! isset( $map[ $type ] ) ) {
			WP_CLI::error( 'Use: wp remember export members|events|locations [--file=<path>]' );
		}
		if ( 'members' === $type ) {
			$this->require_cap( array( 'remember_read_members', 'remember_read_attendees' ), __( 'You cannot export members.', 'remember' ), false );
		} else {
			$this->require_cap( 'remember_import_export', __( 'You cannot export that data.', 'remember' ) );
		}
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-import-export.php';
		$file = \WP_CLI\Utils\get_flag_value( $assoc_args, 'file', '' );
		if ( '' === $file ) {
			$file = trailingslashit( getcwd() ) . $type . '-export-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';
		}
		$method = $map[ $type ];
		$ok     = $this->with_progress(
			__( 'Exporting', 'remember' ),
			static function () use ( $method, $file ) {
				return Remember_Import_Export::$method( $file );
			}
		);
		if ( ! $ok ) {
			WP_CLI::error( 'Could not write that file.' );
		}
		WP_CLI::success( sprintf( 'Wrote %s', $file ) );
	}

	/**
	 * Pull payment status from the connected bookkeeping provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : quickbooks or xero. Must be the provider this site is using.
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember sync quickbooks --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function sync( $args, $assoc_args ) {
		unset( $assoc_args );
		$this->require_user();
		$this->require_cap( 'remember_update_billing', __( 'You cannot sync billing.', 'remember' ) );
		$provider = isset( $args[0] ) ? (string) $args[0] : '';
		if ( ! in_array( $provider, array( 'quickbooks', 'xero' ), true ) ) {
			WP_CLI::error( 'Use: wp remember sync quickbooks|xero' );
		}
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-billing-provider.php';
		$matches = ( 'quickbooks' === $provider ) ? Remember_Billing_Provider::is_quickbooks() : Remember_Billing_Provider::is_xero();
		if ( ! $matches ) {
			WP_CLI::error( 'That is not the bookkeeping provider configured for this site.' );
		}
		$results = $this->with_progress(
			__( 'Syncing payments', 'remember' ),
			static function () {
				return Remember_Billing_Provider::sync_all_payments( 0 );
			}
		);
		if ( ! empty( $results['skipped'] ) ) {
			WP_CLI::error( 'The provider is not connected.' );
		}
		if ( ! empty( $results['error'] ) ) {
			WP_CLI::error( sprintf( 'Synced %1$d payments. %2$d failed.', (int) $results['success'], (int) $results['error'] ) );
		}
		WP_CLI::success( sprintf( 'Synced %d payments.', (int) $results['success'] ) );
	}

	/**
	 * Scan members for possible duplicate profiles.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : scan
	 *
	 * ## EXAMPLES
	 *
	 *     wp remember duplicates scan --user=1
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function duplicates( $args, $assoc_args ) {
		unset( $assoc_args );
		$this->require_user();
		$action = isset( $args[0] ) ? (string) $args[0] : '';
		if ( 'scan' !== $action ) {
			WP_CLI::error( 'Use: wp remember duplicates scan' );
		}
		require_once plugin_dir_path( __FILE__ ) . '../utilities/class-remember-profile-duplicates.php';
		$this->require_cap( Remember_Profile_Duplicates::MERGE_CAP, __( 'You cannot scan for duplicate profiles.', 'remember' ) );
		$created = $this->with_progress(
			__( 'Scanning', 'remember' ),
			static function () {
				return Remember_Profile_Duplicates::scan();
			}
		);
		WP_CLI::success( sprintf( 'Found %d new possible duplicates.', (int) $created ) );
	}

	/**
	 * Require a logged-in WP-CLI user so capability checks apply.
	 *
	 * @return void
	 */
	private function require_user() {
		if ( get_current_user_id() < 1 ) {
			WP_CLI::error( 'Pass --user=<id> so the command runs with that account\'s access.' );
		}
	}

	/**
	 * Stop when the current user lacks a capability.
	 *
	 * @param string|string[] $caps    One required cap, or a list where any one is enough when $all is false.
	 * @param string          $message Failure message.
	 * @param bool            $all     Require every cap.
	 * @return void
	 */
	private function require_cap( $caps, $message, $all = true ) {
		$caps = (array) $caps;
		$ok   = $all;
		foreach ( $caps as $cap ) {
			$has = current_user_can( $cap );
			$ok  = $all ? ( $ok && $has ) : ( $ok || $has );
		}
		if ( ! $ok ) {
			WP_CLI::error( $message );
		}
	}

	/**
	 * Show a one-step progress bar around a blocking call.
	 *
	 * @param string   $label    Bar label.
	 * @param callable $callback Work.
	 * @return mixed
	 */
	private function with_progress( $label, $callback ) {
		$bar = null;
		if ( function_exists( '\WP_CLI\Utils\make_progress_bar' ) ) {
			$bar = \WP_CLI\Utils\make_progress_bar( $label, 1 );
		} else {
			WP_CLI::log( $label );
		}
		$result = call_user_func( $callback );
		if ( $bar ) {
			$bar->tick();
			$bar->finish();
		}
		return $result;
	}

	/**
	 * Print import counts and exit non-zero when a row failed.
	 *
	 * @param array $results Import result.
	 * @param bool  $dry_run Whether nothing was saved.
	 * @return void
	 */
	private function report_import( $results, $dry_run ) {
		if ( ! is_array( $results ) ) {
			WP_CLI::error( 'Import did not finish.' );
		}
		$errors = isset( $results['errors'] ) && is_array( $results['errors'] ) ? $results['errors'] : array();
		foreach ( array_slice( $errors, 0, 20 ) as $message ) {
			WP_CLI::warning( (string) $message );
		}
		$success = isset( $results['success'] ) ? (int) $results['success'] : 0;
		$failed  = isset( $results['error'] ) ? (int) $results['error'] : 0;
		if ( $failed > 0 || ( $errors && $success < 1 ) ) {
			WP_CLI::error( sprintf( '%1$d rows ready, %2$d failed.', $success, max( $failed, count( $errors ) ) ) );
		}
		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run: %d rows would be imported. Nothing was saved.', $success ) );
			return;
		}
		WP_CLI::success( sprintf( 'Imported %d rows.', $success ) );
	}
}
