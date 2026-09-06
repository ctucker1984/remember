<?php
/**
 * Reports AJAX, export, and hooks.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Staff reporting endpoints.
 */
class Remember_Reports {

	const CAP = 'remember_view_reports';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_remember_report_catalog', array( __CLASS__, 'ajax_catalog' ) );
		add_action( 'wp_ajax_remember_report_run', array( __CLASS__, 'ajax_run' ) );
		add_action( 'wp_ajax_remember_report_list', array( __CLASS__, 'ajax_list' ) );
		add_action( 'wp_ajax_remember_report_get', array( __CLASS__, 'ajax_get' ) );
		add_action( 'wp_ajax_remember_report_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_remember_report_delete', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'admin_post_remember_report_export', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Guard AJAX.
	 *
	 * @return void
	 */
	private static function require_ajax() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot view reports.', 'remember' ) ), 403 );
		}
		check_ajax_referer( 'remember_reports', 'nonce' );
	}

	/**
	 * Catalog JSON.
	 *
	 * @return void
	 */
	public static function ajax_catalog() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-report-catalog.php';
		wp_send_json_success( Remember_Report_Catalog::payload_for_current_user() );
	}

	/**
	 * Run preview.
	 *
	 * @return void
	 */
	public static function ajax_run() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-report-engine.php';
		$definition = self::definition_from_request();
		$page       = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$result     = Remember_Report_Engine::run( $definition, $page, Remember_Report_Engine::PAGE_SIZE, false );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * List saved reports.
	 *
	 * @return void
	 */
	public static function ajax_list() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . '../models/class-saved-report.php';
		$rows = Remember_Saved_Report::list_for_owner( get_current_user_id() );
		$list = array();
		foreach ( $rows as $row ) {
			$list[] = array(
				'report_id'  => (int) $row->report_id,
				'name'       => (string) $row->name,
				'subject'    => (string) $row->subject,
				'updated_at' => (string) $row->updated_at,
			);
		}
		wp_send_json_success( array( 'reports' => $list ) );
	}

	/**
	 * Load one saved report.
	 *
	 * @return void
	 */
	public static function ajax_get() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . '../models/class-saved-report.php';
		$id  = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0;
		$row = Remember_Saved_Report::get_owned( $id, get_current_user_id() );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'That report was not found.', 'remember' ) ), 404 );
		}
		$definition = json_decode( (string) $row->definition, true );
		if ( ! is_array( $definition ) ) {
			$definition = array();
		}
		wp_send_json_success(
			array(
				'report_id'  => (int) $row->report_id,
				'name'       => (string) $row->name,
				'subject'    => (string) $row->subject,
				'definition' => $definition,
			)
		);
	}

	/**
	 * Save report.
	 *
	 * @return void
	 */
	public static function ajax_save() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . '../models/class-saved-report.php';
		$definition = self::definition_from_request();
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$subject    = isset( $definition['subject'] ) ? sanitize_key( $definition['subject'] ) : '';
		$report_id  = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0;
		$result     = Remember_Saved_Report::save( get_current_user_id(), $name, $subject, $definition, $report_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'report_id' => (int) $result ) );
	}

	/**
	 * Delete report.
	 *
	 * @return void
	 */
	public static function ajax_delete() {
		self::require_ajax();
		require_once plugin_dir_path( __FILE__ ) . '../models/class-saved-report.php';
		$id = isset( $_POST['report_id'] ) ? absint( wp_unslash( $_POST['report_id'] ) ) : 0;
		if ( ! Remember_Saved_Report::delete_owned( $id, get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete that report.', 'remember' ) ), 400 );
		}
		wp_send_json_success();
	}

	/**
	 * Stream CSV.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You cannot view reports.', 'remember' ), esc_html__( 'Access Denied', 'remember' ), array( 'response' => 403 ) );
		}
		check_admin_referer( 'remember_reports', 'nonce' );
		require_once plugin_dir_path( __FILE__ ) . 'class-remember-report-engine.php';
		$raw = isset( $_POST['definition'] ) ? wp_unslash( $_POST['definition'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$definition = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $definition ) ) {
			$definition = array();
		}
		$result = Remember_Report_Engine::run( $definition, 1, Remember_Report_Engine::PAGE_SIZE, true );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		$filename = 'remember-report-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		$headers = array();
		foreach ( $result['columns'] as $col ) {
			$headers[] = Remember_Report_Engine::csv_cell( $col['label'] );
		}
		fputcsv( $out, $headers );
		foreach ( $result['rows'] as $row ) {
			$line = array();
			foreach ( $result['columns'] as $col ) {
				$id     = $col['id'];
				$line[] = Remember_Report_Engine::csv_cell( isset( $row[ $id ] ) ? $row[ $id ] : '' );
			}
			fputcsv( $out, $line );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Definition from POST.
	 *
	 * @return array
	 */
	private static function definition_from_request() {
		$raw = isset( $_POST['definition'] ) ? wp_unslash( $_POST['definition'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
