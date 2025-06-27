<?php
/**
 * GlotPress AI Extension - OpenAI Translation Service
 *
 * @package    GlotPress\AI_Extension
 * @subpackage Services
 * @license    GPL-2.0-or-later
 *
 * @since      1.0.0
 */

/**
 * Class GP_Extensions_OpenAI_Service
 *
 * Handles translation requests to the OpenAI API service.
 *
 * @package GlotPress\AI_Extension
 */
class GP_Extensions_OpenAI_Service {

	/**
	 * OpenAI API endpoint URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * OpenAI model identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * WordPress option name for the API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API_KEY_OPTION = 'gp_ai_extensions_openai_api_key';

	/**
	 * WordPress option name for the API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MODEL_OPTION = 'gp_ai_extensions_openai_model';

	/**
	 * The last response data from endpoint call.
	 *
	 * @var array<string, mixed>
	 */
	private array $last_response_data = array();

	/**
	 * Retrieves the OpenAI API key from WordPress options.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The API key if exists, false otherwise.
	 */
	private function get_api_key() {
		return GP_AI_Settings_Manager::get_instance()->get_api_key();
	}

	/**
	 * Retrieves the OpenAI API model name from WordPress options.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The model name if exists, false otherwise.
	 */
	private function get_model_name() {
		return GP_AI_Settings_Manager::get_instance()->get_model();
	}

	/**
	 * Sends a batch translation request to the OpenAI API.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{id: int|string, text: string, comment: string}> $batch Array of translation items to process.
	 * @param string                                                           $target_language Target language for translation.
	 * @return array<int, array{id: string|int, translated_text: string}>|\WP_Error The API response or WP_Error on failure.
	 */
	public function translate_batch( array $batch, string $target_language ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', 'OpenAI API key is not configured' );
		}

		$messages = $batch;

		$api_request = array(
			'model'     => $this->get_model_name(),
			'messages'  => array(
				array(
					'role'    => 'user',
					'content' => wp_json_encode(
						array(
							'target_language' => $target_language,
							'translations'    => $messages,
						)
					),
				),
			),
			'functions' => array(
				array(
					'name'        => 'translate_text_batch',
					'description' => 'Translate multiple texts while maintaining IDs.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'translations' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'id'              => array(
											'type'        => 'string',
											'description' => 'The original translation ID from database.',
										),
										'translated_text' => array(
											'type'        => 'string',
											'description' => 'The translated text.',
										),
									),
									'required'   => array( 'id', 'translated_text' ),
								),
							),
						),
						'required'   => array( 'translations' ),
					),
				),
			),
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode( $api_request ),
				'timeout' => 20,
			)
		);

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$response_body = wp_remote_retrieve_body( $response );
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP response code from the API, 2: Response body */
					__( 'API request failed with status code: %1$d. Response: %2$s', 'glotpress-ai-extension' ),
					$response_code,
					$response_body
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['choices'][0]['message']['function_call']['arguments'] ) ) {
			return array();
		}

		$translations = json_decode( $data['choices'][0]['message']['function_call']['arguments'], true );

		if ( ! isset( $translations['translations'] ) || ! is_array( $translations['translations'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Received no translations from the endpoint!', 'glotpress-ai-extension' )
			);
		}

		$this->last_response_data = $data;
		$translations             = $translations['translations'];

		return $translations;
	}

	/**
	 * Retrieves information about the last API response.
	 *
	 * @since 1.0.0
	 *
	 * @return array{tokens_used?: int, model?: string}
	 */
	public function get_last_response_info(): array {
		if ( empty( $this->last_response_data ) ) {
			return array();
		}

		return array(
			'tokens_used' => $this->last_response_data['usage']['total_tokens'],
			'model'       => $this->last_response_data['model'],
		);
	}
}
