<?php
/**
 * GlotPress AI Extension Logger Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Logger
 *
 * Handles logging functionality for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Logger {

	/**
	 * The single instance of the class.
	 *
	 * @var GP_Extensions_Logger|null
	 * @since 1.0.0
	 */
	protected static ?GP_Extensions_Logger $instance = null;

	/**
	 * The option key for storing logs.
	 *
	 * @since 1.0.0
	 */
	public const LOGS_KEY = 'gb-extensions-logs';

	/**
	 * Maximum number of logs to keep.
	 *
	 * @since 1.0.0
	 */
	public const MAX_LOGS = 5;

	/**
	 * Main GP_Extensions_Logger Instance.
	 *
	 * Ensures only one instance of GP_Extensions_Logger is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return GP_Extensions_Logger - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add a new log entry
	 *
	 * @param string                                                   $id      The log entry ID.
	 * @param array{}|array{array{message: string, code?: int|string}} $errors  Array of error messages.
	 * @param array<array<string, mixed>>                              $info    Array of information messages.
	 * @param array<string, mixed>                                     $metadata Various Information (e.g.: duration ).
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the log was successfully added
	 */
	public function add_log( $id, array $errors = array(), array $info = array(), array $metadata = array() ) {
		$current_logs = $this->get_logs();

		$new_log = array(
			'id'         => $id,
			'errors'     => $errors,
			'info'       => $info,
			'created_at' => gmdate( 'Y-m-d H:i:s T' ),
			'metadata'   => $metadata,
		);

		array_unshift( $current_logs, $new_log );

		$current_logs = array_slice( $current_logs, 0, self::MAX_LOGS );

		return update_option( self::LOGS_KEY, $current_logs );
	}

	/**
	 * Get all stored logs
	 *
	 * @return array<int, array{
	 *   id: string,
	 *   errors: array<int|string, array{message: string, code?: string}>,
	 *   info: array<string, mixed>,
	 *   created_at: string
	 * }>
	 */
	public function get_logs(): array {
		return get_option( self::LOGS_KEY, array() );
	}

	/**
	 * Clear all logs
	 *
	 * @return bool Whether the logs were successfully cleared
	 */
	public function clear_logs() {
		return delete_option( self::LOGS_KEY );
	}

	/**
	 * Get the most recent log entry
	 *
	 * @return array{
	 *   id: string,
	 *   errors: array<int|string, array{message: string, code?: string}>,
	 *   info: array<string, mixed>,
	 *   created_at: string
	 * }|null The most recent log entry or null if no logs exist
	 */
	public function get_latest_log(): ?array {
		$logs = $this->get_logs();
		return ! empty( $logs ) ? $logs[0] : null;
	}
}
