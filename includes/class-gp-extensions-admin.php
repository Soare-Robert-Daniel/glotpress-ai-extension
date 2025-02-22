<?php
/**
 * GlotPress AI Extension Admin Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Admin
 *
 * Handles the admin functionality for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Admin {

	public const LOGS_KEY             = 'gb-extensions-logs';
	public const ACTION_TRANSLATE_SET = 'gb_ai_extension_translate_set';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * Sets up the necessary action and filter hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Init the main hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			// TODO: Should show a notice to dashboard to install Action Scheduler.
			return;
		}

		add_action( 'wp_ajax_gp_ai_translate', array( $this, 'handle_translation' ) );
		add_action( self::ACTION_TRANSLATE_SET, array( $this, 'translate_set' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'register_dashboard_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_styles' ) );

		( new GP_Extensions_Project_Extension() )->init();
		GP_Extensions_Progress_Watcher::instance()->init();
	}

	/**
	 * Prepare the batch payload for translations.
	 *
	 * @param Translation_Entry[] $translations_entries Translation entries.
	 * @return array<int, array{id: int|string, text: string, comment: string}>
	 */
	private function prepare_batch( array $translations_entries ): array {
		$batch = array();

		foreach ( $translations_entries as $translation ) {
			$batch[] = array(
				'id'      => $translation->original_id,
				'text'    => $translation->singular,
				'comment' => $translation->translator_comments,
			);
		}

		return $batch;
	}

	/**
	 * Update translations based on the API response.
	 *
	 * @since 1.0.0
	 *
	 * @param Translation_Entry[]                                        $batch The translation entries batch.
	 * @param array<int, array{id: string|int, translated_text: string}> $translations The API response containing translations.
	 * @param GP_Translation_Set                                         $set The translation set.
	 * @return void
	 */
	private function update_translations( array $batch, array $translations, GP_Translation_Set $set ): void {
		foreach ( $batch as $translation_metadata ) {
			$translated_text = null;

			foreach ( $translations as $translation_data ) {
				if ( $translation_data['id'] === $translation_metadata->original_id ) {
					$translated_text = $translation_data['translated_text'];
					break;
				}
			}

			if ( empty( $translated_text ) ) {
				continue;
			}

			if ( ! empty( $translation_metadata->id ) ) {
				/**
				 * The translation from database.
				 *
				 * @var false|\GP_Translation $translation The translation object retrieved from the database or false if not found.
				 */
				$translation = GP::$translation->get( $translation_metadata->id );

				if ( $translation ) {
					$translation->translation_0 = $translated_text;
					$translation->status        = 'current';
					GP::$translation->update( $translation );
				}
			} else {
				$args = array(
					'original_id'        => $translation_metadata->original_id,
					'translation_set_id' => $set->id,
					'user_id'            => get_current_user_id(),
					'status'             => 'current',
					'translation_0'      => $translated_text,
				);
				GP::$translation->create( $args );
			}
		}
	}

	/**
	 * AJAX handler: process untranslated strings in batches of 50.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_translation(): void {
		check_ajax_referer( 'gp-ai-translate', 'nonce' );

		$set_id          = ! empty( $_POST['set_id'] ) ? absint( $_POST['set_id'] ) : 0;
		$target_language = ! empty( $_POST['target_language'] ) ? sanitize_text_field( wp_unslash( $_POST['target_language'] ) ) : '';

		if ( empty( $target_language ) || empty( $set_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid translation set or target language', 'glotpress-ai-extension' ) ) );
		}

		if ( ! GP::$permission->current_user_can( 'approve', 'translation-set', $set_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'glotpress-ai-extension' ) ) );
		}

		/**
		 * The translation set object or false if not found.
		 *
		 * @var false|\GP_Translation_Set $set The translation set instance or false on failure
		 */
		$set = GP::$translation_set->get( $set_id );

		if ( false === $set ) {
			wp_send_json_error( array( 'message' => __( 'Translation Set does not exists!', 'glotpress-ai-extension' ) ) );
		}

		if ( false === as_has_scheduled_action( self::ACTION_TRANSLATE_SET ) ) {
			as_enqueue_async_action( self::ACTION_TRANSLATE_SET, array( $set->id, $target_language ) );
		} else {
			wp_send_json_success( array( 'message' => __( 'Another translation is in action!', 'glotpress-ai-extension' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Translation has started!', 'glotpress-ai-extension' ) ) );
	}

	/**
	 * Translate the Translation Set using AI services.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $set_id The translation set id.
	 * @param string $target_language The language to translate to.
	 * @return void
	 */
	public function translate_set( int $set_id, string $target_language ): void {
		$current_page = 1;
		$max_page     = 3;
		$api          = new GP_Extensions_OpenAI_Service();
		$errors       = array();
		$info         = array();

		/**
		 * The translation set object or false if not found.
		 *
		 * @var false|\GP_Translation_Set $set The translation set instance or false on failure
		 */
		$set = GP::$translation_set->get( $set_id );

		if ( false === $set ) {
			$errors[] = array(
				'message' => __( 'Translation Set could not be find. Maybe it was deleted!', 'glotpress-ai-extension' ),
				'code'    => 'no-translation-set-found',
			);
			return;
		}

		/**
		 * Project object or false if not found
		 *
		 * @var \GP_Project|false $project The project object or false if project does not exist
		 */
		$project = GP::$project->get( $set->project_id );

		if ( false === $project ) {
			$errors[] = array(
				'message' => __( 'Translation Project could not be find. Maybe it was deleted!', 'glotpress-ai-extension' ),
				'code'    => 'no-translation-project-found',
			);
			return;
		}

		$translated_rows = 0;
		$total_rows      = -1;
		$watcher         = GP_Extensions_Progress_Watcher::instance();
		$started_at      = new DateTime();

		try {
			while ( $current_page <= $max_page ) {
				$translations_entries = GP::$translation->for_translation(
					$project,
					$set,
					$current_page,
					array( 'status' => 'untranslated' )
				);

				if ( -1 === $total_rows ) {
					$total_rows = min( GP::$translation->found_rows, GP::$translation->per_page * $max_page );
				}

				$watcher->update_progress( $project->id, $set->id, $translated_rows, $total_rows );

				$batch = $this->prepare_batch( $translations_entries );

				if ( empty( $batch ) ) {
					break;
				}

				$translations = $api->translate_batch( $batch, $target_language );

				if ( is_wp_error( $translations ) ) {
					$errors[] = array(
						'message' => $translations->get_error_message(),
						'code'    => $translations->get_error_code(),
					);
					break;
				}

				if ( empty( $translations ) ) {
					continue;
				}

				$info[] = $api->get_last_response_info();

				$this->update_translations( $translations_entries, $translations, $set );

				$translated_rows += GP::$translation->per_page;
				if ( $translated_rows > $total_rows ) {
					$translated_rows = $total_rows;
				}

				++$current_page;
			}
		} catch ( \Throwable $e ) {
			$errors[] = array( 'message' => $e->getMessage() . ' | ' . $e->getTraceAsString() );
		} finally {
			$finished_at = new DateTime();

			$watcher->update_progress( $project->id, $set->id, $translated_rows, $total_rows, true );
			GP_Extensions_Logger::instance()->add_log(
				'project_' . $project->id . '-set_' . $set->id . '-' . gmdate( 'Y-m-d_H:i:s' ),
				$errors,
				$info,
				array(
					'started_at'  => $started_at->format( 'Y-m-d H:i:s T' ),
					'finished_at' => $finished_at->format( 'Y-m-d H:i:s T' ),
					'duration'    => $finished_at->getTimestamp() - $started_at->getTimestamp(),
				)
			);
		}
	}

	/**
	 * AJAX handler: Get translation progress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function translation_progress(): void {

		check_ajax_referer( 'gp-ai-translate-progress', 'nonce' );

		$project_id = ! empty( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
		$set_id     = ! empty( $_GET['set_id'] ) ? absint( $_GET['set_id'] ) : 0;
		if ( ! $project_id || ! $set_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'glotpress-ai-extension' ) ) );
		}
		$transient_key = 'gp_translation_progress_' . $project_id . '_' . $set_id;
		$progress_data = get_transient( $transient_key );
		if ( false === $progress_data ) {
			$progress_data = array(
				'translated' => 0,
				'total'      => 0,
				'completed'  => false,
			);
		}

		if ( $progress_data['completed'] ) {
			GP_Extensions_Progress_Watcher::instance()->delete_progress( $project_id, $set_id );
		}

		wp_send_json_success( $progress_data );
	}

	/**
	 * Registers a submenu page under the Tools menu in WordPress admin dashboard.
	 * This function adds a "GP Extensions" menu item to the WordPress admin Tools section.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_dashboard_menu() {
		add_submenu_page(
			'tools.php',               // Parent slug (Tools menu).
			__( 'GP Extensions Dashboard', 'glotpress-ai-extension' ), // Page title.
			__( 'GP Extensions', 'glotpress-ai-extension' ),           // Menu title.
			'manage_options',          // Capability required to access this page.
			'gp-extensions-dashboard', // Menu slug.
			array( $this, 'render_dashboard' )   // Callback function that renders the page.
		);
	}

	/**
	 * Enqueues stylesheet for the GlotPress AI extension dashboard page.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_dashboard_styles( $hook_suffix ) {
		if ( 'tools_page_gp-extensions-dashboard' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'gp-extensions-dashboard-css',
			GLOTPRESS_AI_EXTENSION_URL . 'admin/css/dashboard.css',
			array(),
			GLOTPRESS_AI_EXTENSION_VERSION
		);
	}

	/**
	 * Renders the GlotPress AI Extension's dashboard page.
	 *
	 * This method includes the dashboard template which contains the user interface
	 * for the plugin's admin dashboard. The template is located in the plugin's
	 * templates directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_dashboard() {
		include GLOTPRESS_AI_EXTENSION_PATH . 'templates/dashboard.php';
	}
}
