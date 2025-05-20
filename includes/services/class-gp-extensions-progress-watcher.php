<?php
/**
 * GlotPress AI Extension Progress Watcher Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Progress_Watcher
 *
 * Handles progress tracking functionality for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Progress_Watcher {

	/**
	 * The single instance of the class.
	 *
	 * @var GP_Extensions_Progress_Watcher|null
	 */
	protected static ?GP_Extensions_Progress_Watcher $instance = null;

	/**
	 * Main GP_Extensions_Progress_Watcher Instance.
	 *
	 * Ensures only one instance of GP_Extensions_Progress_Watcher is loaded or can be loaded.
	 *
	 * @return GP_Extensions_Progress_Watcher - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {}

	/**
	 * Initialize the progress watcher.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_gp_ai_translation_progress', array( $this, 'handle_progress_request' ) );
	}

	/**
	 * Update translation progress using a transient.
	 *
	 * The transient key is composed of the project ID and translation set ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $project_id The project ID.
	 * @param int  $set_id The translation set ID.
	 * @param int  $translated_rows Number of translated rows.
	 * @param int  $total_rows Total number of rows.
	 * @param bool $completed If the process has finished.
	 * @param int  $log_id The log id.
	 *
	 * @return void
	 */
	public function update_progress( int $project_id, int $set_id, int $translated_rows, int $total_rows, bool $completed = false, int $log_id = -1 ): void {
		$transient_key = $this->get_transient_key( $project_id, $set_id );
		$progress_data = array(
			'translated' => $translated_rows,
			'total'      => $total_rows,
			'completed'  => $completed,
		);

		if ( -1 !== $log_id ) {
			$progress_data['log_id'] = $log_id;
		}

		set_transient( $transient_key, $progress_data, HOUR_IN_SECONDS );
	}

	/**
	 * Delete transient for translation.
	 *
	 * The transient key is composed of the project ID and translation set ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $project_id The project ID.
	 * @param int $set_id The translation set ID.
	 * @return void
	 */
	public function delete_progress( int $project_id, int $set_id ): void {
		$transient_key = $this->get_transient_key( $project_id, $set_id );
		delete_transient( $transient_key );
	}

	/**
	 * Get progress data for a specific translation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $project_id The project ID.
	 * @param int $set_id The translation set ID.
	 * @return array{translated: int, total: int, completed: bool} Progress data.
	 */
	public function get_progress( int $project_id, int $set_id ): array {
		$transient_key = $this->get_transient_key( $project_id, $set_id );
		$progress_data = get_transient( $transient_key );

		if ( false === $progress_data ) {
			return array(
				'translated' => 0,
				'total'      => 0,
				'completed'  => false
			);
		}

		if (
			isset( $progress_data['completed'] ) && $progress_data['completed']
		) {
			$errors     = get_post_meta( $log_id, '_log_errors', true );
			$is_success = is_array( $errors ) ? 0 === count( $errors ) : false;

			if ( isset( $progress_data['log_id'] ) && -1 !== $progress_data['log_id'] ) {
				$log_id  = $progress_data['log_id'];
				$log_url = add_query_arg(
					array(
						'action' => 'edit',
						'post'   => $log_id,
					),
					admin_url( 'post.php' )
				);

				$progress_data['logUrl'] = esc_url_raw( $log_url );
			}

			$progress_data['success'] = $is_success;
		}

		return $progress_data;
	}

	/**
	 * AJAX handler: Get translation progress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_progress_request(): void {
		check_ajax_referer( 'gp-ai-translate-progress', 'nonce' );

		$project_id = ! empty( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
		$set_id     = ! empty( $_GET['set_id'] ) ? absint( $_GET['set_id'] ) : 0;

		if ( ! $project_id || ! $set_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'glotpress-ai-extension' ) ) );
		}

		$progress_data = $this->get_progress( $project_id, $set_id );

		if ( $progress_data['completed'] ) {
			$this->delete_progress( $project_id, $set_id );
		}

		wp_send_json_success( $progress_data );
	}

	/**
	 * Generate the transient key for a specific translation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $project_id The project ID.
	 * @param int $set_id The translation set ID.
	 * @return string The transient key.
	 */
	public function get_transient_key( int $project_id, int $set_id ): string {
		return 'gp_translation_progress_' . $project_id . '_' . $set_id;
	}
}
