<?php
/**
 * Logger utility class
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Logger utility class.
 *
 * @package    reMember
 * @subpackage reMember/includes/utilities
 */
class Remember_Logger {

	/**
	 * Microtime when activation tracing started (for elapsed_ms).
	 *
	 * @var float|null
	 */
	private static $activation_start_time = null;

	/**
	 * Mark the start of plugin activation (call once at the beginning of Remember_Activator::activate).
	 *
	 * @return void
	 */
	public static function mark_activation_start() {
		self::$activation_start_time = microtime( true );
	}

	/**
	 * Log activation progress to wp-content/debug.log when WP_DEBUG_LOG is true.
	 *
	 * Ignores remember_options log_level so DEBUG lines always appear during activation troubleshooting.
	 *
	 * @param string $label   Short step name.
	 * @param array  $context Extra context (merged with elapsed_ms, peak_memory_mb).
	 * @return void
	 */
	public static function activation_debug( $label, $context = array() ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		if ( self::$activation_start_time === null ) {
			self::$activation_start_time = microtime( true );
		}

		$elapsed_ms = round( ( microtime( true ) - self::$activation_start_time ) * 1000, 1 );
		$peak_mb    = function_exists( 'memory_get_peak_usage' )
			? round( memory_get_peak_usage( true ) / 1048576, 2 )
			: 0.0;

		$ctx = array_merge(
			array(
				'elapsed_ms'     => $elapsed_ms,
				'peak_memory_mb' => $peak_mb,
			),
			$context
		);

		$timestamp = current_time( 'mysql' );
		$log_entry   = sprintf(
			'[%s] [reMember] [ACTIVATION] %s | %s' . "\n",
			$timestamp,
			$label,
			wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);

		$log_file = self::get_log_file_path();
		if ( false === @error_log( $log_entry, 3, $log_file ) ) {
			@file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Get the configured log level.
	 *
	 * @return string Log level (NONE, ERROR, WARNING, INFO, DEBUG).
	 */
	private static function get_log_level() {
		$options = get_option( 'remember_options', array() );
		$log_level = isset( $options['log_level'] ) ? $options['log_level'] : 'ERROR';
		
		// If WP_DEBUG_LOG is not enabled, don't log anything
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return 'NONE';
		}
		
		return $log_level;
	}

	/**
	 * Check if a log level should be logged.
	 *
	 * @param string $level Log level to check.
	 * @return bool True if should log, false otherwise.
	 */
	private static function should_log( $level ) {
		$configured_level = self::get_log_level();
		
		if ( 'NONE' === $configured_level ) {
			return false;
		}
		
		$levels = array( 'NONE' => 0, 'ERROR' => 1, 'WARNING' => 2, 'INFO' => 3, 'DEBUG' => 4 );
		$level_priority = isset( $levels[ $level ] ) ? $levels[ $level ] : 0;
		$configured_priority = isset( $levels[ $configured_level ] ) ? $levels[ $configured_level ] : 0;
		
		return $level_priority <= $configured_priority;
	}

	/**
	 * Get the log file path.
	 *
	 * @return string Log file path.
	 */
	private static function get_log_file_path() {
		// Use WordPress standard debug.log location
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && is_string( WP_DEBUG_LOG ) ) {
			// If WP_DEBUG_LOG is a custom path, use it
			return WP_DEBUG_LOG;
		}
		
		// Default to wp-content/debug.log
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public static function debug( $message, $context = array() ) {
		if ( self::should_log( 'DEBUG' ) ) {
			self::log( 'DEBUG', $message, $context );
		}
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public static function info( $message, $context = array() ) {
		if ( self::should_log( 'INFO' ) ) {
			self::log( 'INFO', $message, $context );
		}
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public static function warning( $message, $context = array() ) {
		if ( self::should_log( 'WARNING' ) ) {
			self::log( 'WARNING', $message, $context );
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public static function error( $message, $context = array() ) {
		if ( self::should_log( 'ERROR' ) ) {
			self::log( 'ERROR', $message, $context );
		}
	}

	/**
	 * Write log entry.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	private static function log( $level, $message, $context = array() ) {
		$timestamp = current_time( 'mysql' );
		$log_entry = sprintf(
			'[%s] [reMember] [%s] %s',
			$timestamp,
			$level,
			$message
		);

		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$log_entry .= "\n";

		$log_file = self::get_log_file_path();

		// Append to the log file explicitly. A bare error_log( $msg ) follows PHP's error_log
		// ini setting, which on many hosts (e.g. Laravel Valet) is not wp-content/debug.log.
		if ( false === @error_log( $log_entry, 3, $log_file ) ) {
			@file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
		}
	}
}
