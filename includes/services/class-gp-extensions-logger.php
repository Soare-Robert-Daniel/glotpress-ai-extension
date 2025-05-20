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
	 * The custom post type name.
	 *
	 * @since 1.0.0
	 */
	public const POST_TYPE = 'gl_ai_ext_log';

	/**
	 * Maximum number of logs to keep.
	 *
	 * @since 1.0.0
	 */
	public const MAX_LOGS = 50;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

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
	 * Register the custom post type
	 *
	 * @since 1.0.0
	 */
	public function register_post_type() {
		add_action( 'init', array( $this, 'register_cpt' ) );
	}

	/**
	 * Register custom post type callback
	 *
	 * @since 1.0.0
	 */
	public function register_cpt() {
		$args = array(
			'labels'             => array(
				'name'               => __( 'Logs', 'glotpress-ai-extension' ),
				'singular_name'      => __( 'Log', 'glotpress-ai-extension' ),
				'menu_name'          => __( 'Logs', 'glotpress-ai-extension' ),
				'all_items'          => __( 'All Logs', 'glotpress-ai-extension' ),
				'add_new'            => __( 'Add New', 'glotpress-ai-extension' ),
				'add_new_item'       => __( 'Add New Log', 'glotpress-ai-extension' ),
				'edit_item'          => __( 'Edit Log', 'glotpress-ai-extension' ),
				'new_item'           => __( 'New Log', 'glotpress-ai-extension' ),
				'view_item'          => __( 'View Log', 'glotpress-ai-extension' ),
				'search_items'       => __( 'Search Logs', 'glotpress-ai-extension' ),
				'not_found'          => __( 'No logs found', 'glotpress-ai-extension' ),
				'not_found_in_trash' => __( 'No logs found in Trash', 'glotpress-ai-extension' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'gp-ai-extensions-dashboard',
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title' ),
			'show_in_rest'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add a new log entry
	 *
	 * @param string                                                   $title      The log entry ID.
	 * @param array{}|array{array{message: string, code?: int|string}} $errors  Array of error messages.
	 * @param array<array<string, mixed>>                              $info    Array of information messages.
	 * @param array<string, mixed>                                     $metadata Various Information (e.g.: duration ).
	 *
	 * @since 1.0.0
	 *
	 * @return int|bool Whether the log was successfully added. Return the log id on success.
	 */
	public function add_log( $title, array $errors = array(), array $info = array(), array $metadata = array() ) {
		// Remove oldest logs if we're at the limit
		$this->cleanup_old_logs();

		$post_data = array(
			'post_title'  => $title,
			'post_status' => 'private',
			'post_type'   => self::POST_TYPE,
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Store all data as post meta
		update_post_meta( $post_id, '_log_errors', $errors );
		update_post_meta( $post_id, '_log_api_info', $info );
		update_post_meta( $post_id, '_log_metadata', $metadata );

		if ( ! empty( $medata['project_id'] ) ) {
			update_post_meta( $post_id, '_glotpress_project_id', $medata['project_id'] );
		}
		if ( ! empty( $medata['set_id'] ) ) {
			update_post_meta( $post_id, '_glotpress_translation_set_id', $medata['set_id'] );
		}

		$total_used_tokens = 0;
		foreach ( $info as $api_run ) {
			if ( ! empty( $api_run['tokens_used'] ) ) {
				$total_used_tokens += $api_run['tokens_used'];
			}
		}

		update_post_meta( $post_id, '_api_used_tokens', $total_used_tokens );

		return $post_id;
	}

	/**
	 * Clear all logs
	 *
	 * @return bool Whether the logs were successfully cleared
	 */
	public function clear_logs() {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return true;
		}

		$success = true;
		foreach ( $posts as $post_id ) {
			$result = wp_delete_post( $post_id, true );
			if ( ! $result ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get the most recent log entry
	 *
	 * @return array{
	 *   id: string,
	 *   errors: array<int|string, array{message: string, code?: string}>,
	 *   info: array<string, mixed>,
	 *   logged_at: string,
	 *   metadata: array<string, mixed>
	 * }|null The most recent log entry or null if no logs exist
	 */
	public function get_latest_log(): ?array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->get_log_from_post( $posts[0] );
	}

	/**
	 * Clean up old logs - deletes logs older than 6 months
	 *
	 * @since 1.0.0
	 */
	private function cleanup_old_logs() {
		// Calculate the date 6 months ago
		$six_months_ago = current_datetime()->modify( '-6 months' );

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'date_query'     => array(
				array(
					'before' => $six_months_ago->format( 'Y-m-d H:i:s' ),
				),
			),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$posts_to_delete = get_posts( $args );

		if ( ! empty( $posts_to_delete ) ) {
			foreach ( $posts_to_delete as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	/**
	 * Extract log data from a post
	 *
	 * @param WP_Post $post The post object
	 *
	 * @return array{
	 *   id: string,
	 *   errors: array<int|string, array{message: string, code?: string}>,
	 *   info: array<string, mixed>,
	 *   logged_at: string,
	 *   metadata: array<string, mixed>
	 * }|null The log data or null if invalid
	 *
	 * @since 1.0.0
	 */
	private function get_log_from_post( $post ): ?array {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$id        = get_post_meta( $post->ID, '_log_id', true );
		$errors    = get_post_meta( $post->ID, '_log_errors', true );
		$info      = get_post_meta( $post->ID, '_log_info', true );
		$metadata  = get_post_meta( $post->ID, '_log_metadata', true );
		$logged_at = get_post_meta( $post->ID, '_log_logged_at', true );

		// Ensure we have valid data
		if ( empty( $id ) || empty( $logged_at ) ) {
			return null;
		}

		return array(
			'id'        => $id,
			'errors'    => is_array( $errors ) ? $errors : array(),
			'info'      => is_array( $info ) ? $info : array(),
			'logged_at' => $logged_at,
			'metadata'  => is_array( $metadata ) ? $metadata : array(),
		);
	}
}
