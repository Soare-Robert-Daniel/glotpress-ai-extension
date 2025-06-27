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
		$this->init_custom_columns();
		$this->init_meta_boxes();
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

	/**
	 * Initialize custom columns
	 *
	 * @since 1.0.0
	 */
	public function init_custom_columns() {
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'make_columns_sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );
	}

	/**
	 * Add custom columns to the post list
	 *
	 * @param array $columns The existing columns
	 * @return array Modified columns array
	 *
	 * @since 1.0.0
	 */
	public function add_custom_columns( $columns ) {
		// Remove the default date column to reposition it
		$date = $columns['date'];
		unset( $columns['date'] );

		// Add our custom columns
		$columns['tokens_used']  = __( 'Tokens Used', 'glotpress-ai-extension' );
		$columns['errors']       = __( 'Errors', 'glotpress-ai-extension' );
		$columns['translations'] = __( 'Translations', 'glotpress-ai-extension' );
		$columns['duration']     = __( 'Duration', 'glotpress-ai-extension' );

		// Re-add date at the end
		$columns['date'] = $date;

		return $columns;
	}

	/**
	 * Render custom column content
	 *
	 * @param string $column The column name
	 * @param int    $post_id The post ID
	 *
	 * @since 1.0.0
	 */
	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'tokens_used':
				$tokens   = get_post_meta( $post_id, '_api_used_tokens', true );
				$api_info = get_post_meta( $post_id, '_log_api_info', true );

				if ( $tokens ) {
					echo '<strong>' . esc_html( number_format( intval( $tokens ) ) ) . '</strong>';

					// Show number of API calls
					if ( is_array( $api_info ) && ! empty( $api_info ) ) {
						$runs = count( $api_info );
						echo '<br><small>' . sprintf(
							/* translators: %d: Number of API runs */
							_n( '%d run', '%d runs', $runs, 'glotpress-ai-extension' ),
							$runs
						) . '</small>';
					}
				} else {
					echo '—';
				}
				break;

			case 'errors':
				$errors = get_post_meta( $post_id, '_log_errors', true );

				if ( is_array( $errors ) && ! empty( $errors ) ) {
					$error_count = count( $errors );
					echo '<span style="color: #d63638; font-weight: bold;">' .
						esc_html( $error_count ) .
						'</span>';
				} else {
					echo '<span style="color: #00a32a;">0</span>';
				}
				break;

			case 'translations':
				$metadata = get_post_meta( $post_id, '_log_metadata', true );

				if ( is_array( $metadata ) &&
					isset( $metadata['translated_items'] ) &&
					isset( $metadata['total_items'] ) ) {
					$translated = intval( $metadata['translated_items'] );
					$total      = intval( $metadata['total_items'] );

					// Calculate percentage
					$percentage = $total > 0 ? round( ( $translated / $total ) * 100 ) : 0;

					// Color based on percentage
					$color = '#00a32a'; // Green for 100%
					if ( $percentage < 100 ) {
						$color = '#dba617'; // Yellow for partial
					}
					if ( $percentage < 50 ) {
						$color = '#d63638'; // Red for less than 50%
					}

					printf(
						'<span style="color: %s;">%d/%d</span><br><small>(%d%%)</small>',
						esc_attr( $color ),
						esc_html( $translated ),
						esc_html( $total ),
						esc_html( $percentage )
					);
				} else {
					echo '—';
				}
				break;

			case 'duration':
				$metadata = get_post_meta( $post_id, '_log_metadata', true );

				if ( is_array( $metadata ) && isset( $metadata['duration'] ) ) {
					$duration = intval( $metadata['duration'] );

					if ( $duration < 60 ) {
						printf(
							/* translators: %d: Number of seconds */
							_n( '%d second', '%d seconds', $duration, 'glotpress-ai-extension' ),
							$duration
						);
					} elseif ( $duration < 3600 ) {
						$minutes = floor( $duration / 60 );
						$seconds = $duration % 60;

						$output = sprintf(
							/* translators: %d: Number of minutes */
							_n( '%d minute', '%d minutes', $minutes, 'glotpress-ai-extension' ),
							$minutes
						);

						if ( $seconds > 0 ) {
							$output .= ' ' . sprintf(
								/* translators: %d: Number of seconds */
								_n( '%d second', '%d seconds', $seconds, 'glotpress-ai-extension' ),
								$seconds
							);
						}

						echo esc_html( $output );
					} else {
						$hours   = floor( $duration / 3600 );
						$minutes = floor( ( $duration % 3600 ) / 60 );

						$output = sprintf(
							/* translators: %d: Number of hours */
							_n( '%d hour', '%d hours', $hours, 'glotpress-ai-extension' ),
							$hours
						);

						if ( $minutes > 0 ) {
							$output .= ' ' . sprintf(
								/* translators: %d: Number of minutes */
								_n( '%d minute', '%d minutes', $minutes, 'glotpress-ai-extension' ),
								$minutes
							);
						}

						echo esc_html( $output );
					}
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * Make columns sortable
	 *
	 * @param array $columns The sortable columns
	 * @return array Modified sortable columns
	 *
	 * @since 1.0.0
	 */
	public function make_columns_sortable( $columns ) {
		$columns['tokens_used'] = 'tokens_used';
		$columns['errors']      = 'errors';
		$columns['duration']    = 'duration';

		return $columns;
	}

	/**
	 * Handle column sorting
	 *
	 * @param WP_Query $query The WP_Query instance
	 *
	 * @since 1.0.0
	 */
	public function handle_column_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== self::POST_TYPE ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		switch ( $orderby ) {
			case 'tokens_used':
				$query->set( 'meta_key', '_api_used_tokens' );
				$query->set( 'orderby', 'meta_value_num' );
				break;

			case 'errors':
				// This is more complex as we need to sort by error count
				// For now, we'll use a meta query approach
				$query->set( 'meta_key', '_log_errors' );
				$query->set( 'orderby', 'meta_value' );
				break;

			case 'duration':
				$query->set( 'meta_key', '_log_metadata' );
				$query->set( 'orderby', 'meta_value' );
				break;
		}
	}

	/**
	 * Initialize meta boxes for log edit screen
	 *
	 * @since 1.0.0
	 */
	public function init_meta_boxes() {
		add_action( 'add_meta_boxes', array( $this, 'add_log_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Remove the default editor for our post type
		add_action(
			'init',
			function () {
				remove_post_type_support( self::POST_TYPE, 'editor' );
			},
			100
		);
	}

	/**
	 * Add meta boxes to the log edit screen
	 *
	 * @since 1.0.0
	 */
	public function add_log_meta_boxes() {
		// Main log overview
		add_meta_box(
			'log_overview',
			__( 'Log Overview', 'glotpress-ai-extension' ),
			array( $this, 'render_log_overview_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		// API Information
		add_meta_box(
			'log_api_info',
			__( 'API Information', 'glotpress-ai-extension' ),
			array( $this, 'render_api_info_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		// Errors (if any)
		add_meta_box(
			'log_errors',
			__( 'Errors', 'glotpress-ai-extension' ),
			array( $this, 'render_errors_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		// Raw Data (collapsible)
		add_meta_box(
			'log_raw_data',
			__( 'Raw Data', 'glotpress-ai-extension' ),
			array( $this, 'render_raw_data_meta_box' ),
			self::POST_TYPE,
			'normal',
			'low'
		);
	}

	/**
	 * Render the log overview meta box
	 *
	 * @param WP_Post $post The post object
	 *
	 * @since 1.0.0
	 */
	public function render_log_overview_meta_box( $post ) {
		$metadata     = get_post_meta( $post->ID, '_log_metadata', true );
		$total_tokens = get_post_meta( $post->ID, '_api_used_tokens', true );

		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}
		?>
		<style>
			.log-overview-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-top: 10px;
			}
			.log-stat-box {
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 15px;
				text-align: center;
			}
			.log-stat-box.success {
				background: #edfaef;
				border-color: #00a32a;
			}
			.log-stat-box.warning {
				background: #fcf9e8;
				border-color: #dba617;
			}
			.log-stat-box.error {
				background: #fcf0f1;
				border-color: #d63638;
			}
			.log-stat-label {
				font-size: 12px;
				color: #646970;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-bottom: 5px;
			}
			.log-stat-value {
				font-size: 24px;
				font-weight: 600;
				color: #1d2327;
				line-height: 1;
			}
			.log-stat-sublabel {
				font-size: 13px;
				color: #646970;
				margin-top: 5px;
			}
			.log-details-table {
				margin-top: 20px;
				width: 100%;
				border-collapse: collapse;
			}
			.log-details-table th,
			.log-details-table td {
				padding: 10px;
				text-align: left;
				border-bottom: 1px solid #c3c4c7;
			}
			.log-details-table th {
				font-weight: 600;
				color: #1d2327;
				background: #f6f7f7;
			}
			.log-details-table tr:last-child td {
				border-bottom: none;
			}
		</style>

		<div class="log-overview-grid">
			<?php
			// Translations stat
			$translated = isset( $metadata['translated_items'] ) ? intval( $metadata['translated_items'] ) : 0;
			$total      = isset( $metadata['total_items'] ) ? intval( $metadata['total_items'] ) : 0;
			$percentage = $total > 0 ? round( ( $translated / $total ) * 100 ) : 0;

			$class = 'success';
			if ( $percentage < 100 ) {
				$class = 'warning';
			}
			if ( $percentage < 50 ) {
				$class = 'error';
			}
			?>
			<div class="log-stat-box <?php echo esc_attr( $class ); ?>">
				<div class="log-stat-label"><?php _e( 'Translations', 'glotpress-ai-extension' ); ?></div>
				<div class="log-stat-value"><?php echo esc_html( $translated . '/' . $total ); ?></div>
				<div class="log-stat-sublabel"><?php echo esc_html( $percentage ); ?>%</div>
			</div>

			<?php
			// Tokens Used
			?>
			<div class="log-stat-box">
				<div class="log-stat-label"><?php _e( 'Tokens Used', 'glotpress-ai-extension' ); ?></div>
				<div class="log-stat-value"><?php echo esc_html( number_format( intval( $total_tokens ) ) ); ?></div>
				<?php
				$api_info = get_post_meta( $post->ID, '_log_api_info', true );
				if ( is_array( $api_info ) && ! empty( $api_info ) ) {
					$runs = count( $api_info );
					?>
					<div class="log-stat-sublabel">
						<?php printf( _n( '%d API run', '%d API runs', $runs, 'glotpress-ai-extension' ), $runs ); ?>
					</div>
					<?php
				}
				?>
			</div>

				<?php
				// Duration
				$duration = isset( $metadata['duration'] ) ? intval( $metadata['duration'] ) : 0;
				?>
			<div class="log-stat-box">
				<div class="log-stat-label"><?php _e( 'Duration', 'glotpress-ai-extension' ); ?></div>
				<div class="log-stat-value"><?php echo esc_html( $this->format_duration_short( $duration ) ); ?></div>
				<div class="log-stat-sublabel"><?php echo esc_html( $duration ); ?> <?php _e( 'seconds', 'glotpress-ai-extension' ); ?></div>
			</div>

			<?php
			// Errors
			$errors      = get_post_meta( $post->ID, '_log_errors', true );
			$error_count = is_array( $errors ) ? count( $errors ) : 0;
			$error_class = $error_count > 0 ? 'error' : 'success';
			?>
			<div class="log-stat-box <?php echo esc_attr( $error_class ); ?>">
				<div class="log-stat-label"><?php _e( 'Errors', 'glotpress-ai-extension' ); ?></div>
				<div class="log-stat-value"><?php echo esc_html( $error_count ); ?></div>
				<div class="log-stat-sublabel">
					<?php echo $error_count > 0 ? __( 'See details below', 'glotpress-ai-extension' ) : __( 'No errors', 'glotpress-ai-extension' ); ?>
				</div>
			</div>
		</div>

		<table class="log-details-table">
			<tbody>
				<?php if ( ! empty( $metadata['project_id'] ) ) : ?>
				<tr>
					<th><?php _e( 'GlotPress Project ID', 'glotpress-ai-extension' ); ?></th>
					<td><?php echo esc_html( $metadata['project_id'] ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $metadata['set_id'] ) ) : ?>
				<tr>
					<th><?php _e( 'Translation Set ID', 'glotpress-ai-extension' ); ?></th>
					<td><?php echo esc_html( $metadata['set_id'] ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $metadata['started_at'] ) ) : ?>
				<tr>
					<th><?php _e( 'Started At', 'glotpress-ai-extension' ); ?></th>
					<td><?php echo esc_html( $this->format_date( $metadata['started_at'] ) ); ?></td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $metadata['finished_at'] ) ) : ?>
				<tr>
					<th><?php _e( 'Finished At', 'glotpress-ai-extension' ); ?></th>
					<td><?php echo esc_html( $this->format_date( $metadata['finished_at'] ) ); ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
			<?php
	}

	/**
	 * Render the API information meta box
	 *
	 * @param WP_Post $post The post object
	 *
	 * @since 1.0.0
	 */
	public function render_api_info_meta_box( $post ) {
		$api_info = get_post_meta( $post->ID, '_log_api_info', true );

		if ( ! is_array( $api_info ) || empty( $api_info ) ) {
			echo '<p>' . __( 'No API information available.', 'glotpress-ai-extension' ) . '</p>';
			return;
		}
		?>
		<style>
			.api-runs-table {
				width: 100%;
				border-collapse: collapse;
			}
			.api-runs-table th,
			.api-runs-table td {
				padding: 8px 12px;
				text-align: left;
				border-bottom: 1px solid #c3c4c7;
			}
			.api-runs-table th {
				background: #f6f7f7;
				font-weight: 600;
			}
			.api-runs-table tr:last-child td {
				border-bottom: none;
			}
			.api-run-index {
				background: #2271b1;
				color: white;
				border-radius: 3px;
				padding: 2px 8px;
				font-size: 12px;
				font-weight: 600;
			}
		</style>

		<table class="api-runs-table">
			<thead>
				<tr>
					<th><?php _e( 'Run #', 'glotpress-ai-extension' ); ?></th>
					<th><?php _e( 'Model', 'glotpress-ai-extension' ); ?></th>
					<th><?php _e( 'Tokens Used', 'glotpress-ai-extension' ); ?></th>
					<th><?php _e( 'Details', 'glotpress-ai-extension' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $api_info as $index => $run ) : ?>
				<tr>
					<td><span class="api-run-index"><?php echo esc_html( $index + 1 ); ?></span></td>
					<td>
						<?php
						echo isset( $run['model'] ) ? '<code>' . esc_html( $run['model'] ) . '</code>' : '—';
						?>
					</td>
					<td>
						<?php
						echo isset( $run['tokens_used'] ) ? '<strong>' . esc_html( number_format( $run['tokens_used'] ) ) . '</strong>' : '—';
						?>
					</td>
					<td>
						<?php
						// Display any additional run information
						$extra_info = array();
						foreach ( $run as $key => $value ) {
							if ( ! in_array( $key, array( 'model', 'tokens_used' ), true ) ) {
								$extra_info[] = '<strong>' . esc_html( $key ) . ':</strong> ' . esc_html( $value );
							}
						}
						echo ! empty( $extra_info ) ? implode( '<br>', $extra_info ) : '—';
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
			<?php
	}

	/**
	 * Render the errors meta box
	 *
	 * @param WP_Post $post The post object
	 *
	 * @since 1.0.0
	 */
	public function render_errors_meta_box( $post ) {
		$errors = get_post_meta( $post->ID, '_log_errors', true );

		if ( ! is_array( $errors ) || empty( $errors ) ) {
			echo '<p style="color: #00a32a;">' . __( '✓ No errors occurred during this operation.', 'glotpress-ai-extension' ) . '</p>';
			return;
		}
		?>
		<style>
			.error-item {
				background: #fcf0f1;
				border: 1px solid #d63638;
				border-left-width: 4px;
				padding: 12px;
				margin-bottom: 10px;
				border-radius: 4px;
			}
			.error-item:last-child {
				margin-bottom: 0;
			}
			.error-code {
				font-family: monospace;
				background: #d63638;
				color: white;
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 12px;
				margin-left: 10px;
			}
			.error-message {
				margin-top: 8px;
				color: #50575e;
			}
		</style>

		<?php foreach ( $errors as $index => $error ) : ?>
		<div class="error-item">
			<strong><?php printf( __( 'Error #%d', 'glotpress-ai-extension' ), $index + 1 ); ?></strong>
			<?php if ( isset( $error['code'] ) ) : ?>
				<span class="error-code"><?php echo esc_html( $error['code'] ); ?></span>
			<?php endif; ?>

			<?php if ( isset( $error['message'] ) ) : ?>
				<div class="error-message"><?php echo esc_html( $error['message'] ); ?></div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Render the raw data meta box
	 *
	 * @param WP_Post $post The post object
	 *
	 * @since 1.0.0
	 */
	public function render_raw_data_meta_box( $post ) {
		$errors   = get_post_meta( $post->ID, '_log_errors', true );
		$api_info = get_post_meta( $post->ID, '_log_api_info', true );
		$metadata = get_post_meta( $post->ID, '_log_metadata', true );
		?>
		<style>
			.raw-data-section {
				margin-bottom: 20px;
			}
			.raw-data-section:last-child {
				margin-bottom: 0;
			}
			.raw-data-title {
				font-weight: 600;
				margin-bottom: 8px;
			}
			.raw-data-content {
				background: #f6f7f7;
				border: 1px solid #c3c4c7;
				padding: 10px;
				border-radius: 4px;
				font-family: monospace;
				font-size: 13px;
				white-space: pre-wrap;
				word-wrap: break-word;
				max-height: 300px;
				overflow-y: auto;
			}
		</style>

		<p class="description">
			<?php _e( 'This section shows the raw data stored for this log entry. Useful for debugging.', 'glotpress-ai-extension' ); ?>
		</p>

		<div class="raw-data-section">
			<div class="raw-data-title"><?php _e( 'Errors Data', 'glotpress-ai-extension' ); ?></div>
			<div class="raw-data-content"><?php echo esc_html( json_encode( $errors, JSON_PRETTY_PRINT ) ); ?></div>
		</div>

		<div class="raw-data-section">
			<div class="raw-data-title"><?php _e( 'API Information Data', 'glotpress-ai-extension' ); ?></div>
			<div class="raw-data-content"><?php echo esc_html( json_encode( $api_info, JSON_PRETTY_PRINT ) ); ?></div>
		</div>

		<div class="raw-data-section">
			<div class="raw-data-title"><?php _e( 'Metadata', 'glotpress-ai-extension' ); ?></div>
			<div class="raw-data-content"><?php echo esc_html( json_encode( $metadata, JSON_PRETTY_PRINT ) ); ?></div>
		</div>
		<?php
	}

	/**
	 * Format duration for short display
	 *
	 * @param int $seconds The duration in seconds
	 * @return string Formatted duration
	 *
	 * @since 1.0.0
	 */
	private function format_duration_short( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		} elseif ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			$secs    = $seconds % 60;
			return $minutes . 'm ' . $secs . 's';
		} else {
			$hours   = floor( $seconds / 3600 );
			$minutes = floor( ( $seconds % 3600 ) / 60 );
			return $hours . 'h ' . $minutes . 'm';
		}
	}

	/**
	 * Format date for display
	 *
	 * @param string $date The date string
	 * @return string Formatted date
	 *
	 * @since 1.0.0
	 */
	private function format_date( $date ) {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return $date;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook The current admin page
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen->post_type !== self::POST_TYPE ) {
			return;
		}

		// Add inline script to make Raw Data meta box closed by default
		wp_add_inline_script(
			'postbox',
			"
			jQuery(document).ready(function($) {
				if ( $('#log_raw_data').hasClass('closed') === false ) {
					$('#log_raw_data').addClass('closed');
				}
			});
		"
		);
	}
}
