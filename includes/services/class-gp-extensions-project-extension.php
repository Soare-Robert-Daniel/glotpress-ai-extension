<?php
/**
 * GlotPress AI Extension Project Extension Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Project_Extension
 *
 * Handles the project-related functionality for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Project_Extension {

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Init the project extension hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'gp_translations_footer_links', array( $this, 'add_translation_button' ), 10, 4 );
		add_action( 'gp_head', array( $this, 'register_scripts' ) );
		add_action( 'gp_head', array( $this, 'print_scripts' ) );
	}

	/**
	 * Add "Translate with AI" button to the footer links.
	 *
	 * @param array<int|string, string> $footer_links Default links.
	 * @param GP_Project                $project The current project.
	 * @param GP_Locale                 $locale The current locale.
	 * @param GP_Translation_Set        $translation_set The current translation set.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int|string, string> Modified footer links with our AI translation button.
	 */
	public function add_translation_button( array $footer_links, GP_Project $project, GP_Locale $locale, GP_Translation_Set $translation_set ): array {
		$nonce          = wp_create_nonce( 'gp-ai-translate' );
		$is_translating = as_has_scheduled_action( GP_Extensions_Admin::ACTION_TRANSLATE_SET );

		$button  = '<button id="ai-translate-btn" class="button" ';
		$button .= 'data-set-id="' . esc_attr( (string) $translation_set->id ) . '" ';
		$button .= 'data-target-lang="' . esc_attr( $translation_set->locale ) . '" ';
		$button .= 'data-nonce="' . esc_attr( $nonce ) . '" ';
		$button .= $is_translating ? 'disabled' : '';
		$button .= '>';
		$button .= $is_translating
			? esc_html__( 'Translation in progress', 'glotpress-ai-extension' )
			: esc_html__( 'Translate with AI', 'glotpress-ai-extension' );
		$button .= '</button>';

		$nonce = wp_create_nonce( 'gp-ai-translate-progress' );

		$progress  = '<div id="ai-translate-progress"';
		$progress .= 'data-project-id="' . esc_attr( (string) $project->id ) . '" ';
		$progress .= 'data-set-id="' . esc_attr( (string) $translation_set->id ) . '" ';
		$progress .= 'data-nonce="' . esc_attr( $nonce ) . '" ';
		$progress .= '>';
		$progress .= '</div>';

		$footer_links[] = $button . $progress;
		return $footer_links;
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_scripts(): void {
		// @phpstan-ignore include.fileNotFound
		$asset_file = include GLOTPRESS_AI_EXTENSION_PATH . '/build/addons/addons.asset.php';
		wp_register_script(
			'gp-ai-translation-addons',
			GLOTPRESS_AI_EXTENSION_URL . 'build/addons/addons.js',
			$asset_file['dependencies'],
			$asset_file['version']
		);
		wp_register_style(
			'gp-ai-translation-addons-style',
			GLOTPRESS_AI_EXTENSION_URL . 'build/addons/style-index.css',
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * Print scripts in the admin header.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_scripts(): void {
		wp_print_scripts( array( 'gp-ai-translation-addons' ) );
		wp_print_styles( array( 'gp-ai-translation-addons-style' ) );
		echo '<script>';
		echo 'var gpAiTranslation = ' . wp_json_encode(
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'labels'  => array(
					'missingButton'       => __( 'Translate Button is not preset.', 'glotpress-ai-extension' ),
					'missingProgress'     => __( 'Translation Progress is not preset.', 'glotpress-ai-extension' ),
					'translationFailed'   => __( 'Translation failed.', 'glotpress-ai-extension' ),
					'errorOccurred'       => __( 'An error occurred while processing the translation.', 'glotpress-ai-extension' ),
					'translating'         => __( 'Translating...', 'glotpress-ai-extension' ),
					'translationProgress' => __( 'Translation Progress', 'glotpress-ai-extension' ),
					'translationComplete' => __( 'Translation completed successfully. Refresh the page!', 'glotpress-ai-extension' ),
					'error'               => __( 'Error', 'glotpress-ai-extension' ),
					'unknownError'        => __( 'Unknown error', 'glotpress-ai-extension' ),
					'logError'            => __( 'An error occured while translating, check the log.', 'glotpress-ai-extension' ),
					'missingLogError'     => __( 'An error occured while translating, the log is unavailble.', 'glotpress-ai-extension' ),
					'connectionError'     => __( 'Connection error. Please check your network.', 'glotpress-ai-extension' ),
				),
			)
		) . ';';
		echo '</script>';
	}
}
