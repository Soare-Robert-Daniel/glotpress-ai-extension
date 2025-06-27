<?php
/**
 * GlotPress AI Extension Endpoints Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Endpoints
 *
 * Handles endpoints functionality for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Endpoints {

	/**
	 * The namespace for our REST API endpoints.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API_NAMESPACE = 'glotpress-ai-extension/v1';

	/**
	 * Settings option name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SETTINGS_OPTION = 'gp_ai_ext_settings';

	/**
	 * Default OpenAI model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_OPENAI_MODEL = 'gpt-4.1-nano';

	/**
	 * Instance of the class.
	 *
	 * @since 1.0.0
	 * @var GP_Extensions_Endpoints
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return GP_Extensions_Endpoints
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Get settings endpoint.
		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_settings' ),
				'permission_callback' => array( $this, 'check_settings_permissions' ),
			)
		);

		// Update settings endpoint.
		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update_settings' ),
				'permission_callback' => array( $this, 'check_settings_permissions' ),
				'args'                => $this->get_settings_args(),
			)
		);
	}

	/**
	 * Get settings schema definition.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_settings_schema() {
		return array(
			'open_ai_key'   => array(
				'description'       => __( 'OpenAI API key', 'glotpress-ai-extension' ),
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'validate_callback' => array( $this, 'validate_api_key' ),
				'required'          => false,
				'default'           => '',
			),
			'open_ai_model' => array(
				'description'       => __( 'OpenAI model to use', 'glotpress-ai-extension' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_model_name' ),
				'required'          => false,
				'default'           => self::DEFAULT_OPENAI_MODEL,
				'enum'              => array(
					'gpt-4.1-mini',
					'gpt-4.1-nano',
				),
			),
		);
	}

	/**
	 * Get settings arguments for REST API.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_settings_args() {
		return $this->get_settings_schema();
	}

	/**
	 * Handle get settings request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_get_settings( $request ) {
		$settings = $this->get_settings();

		// Mask the API key for security.
		if ( ! empty( $settings['open_ai_key'] ) ) {
			$settings['open_ai_key_masked'] = $this->mask_api_key( $settings['open_ai_key'] );
			unset( $settings['open_ai_key'] );
		}

		return rest_ensure_response( $settings );
	}

	/**
	 * Handle update settings request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_settings( $request ) {
		$current_settings = $this->get_settings();
		$new_settings     = array();

		// Process each setting from the schema.
		foreach ( $this->get_settings_schema() as $key => $schema ) {
			if ( $request->has_param( $key ) ) {
				$value = $request->get_param( $key );

				// Skip if trying to update with masked API key.
				if ( 'open_ai_key' === $key && $this->is_masked_api_key( $value ) ) {
					$new_settings[ $key ] = $current_settings[ $key ];
				} else {
					$new_settings[ $key ] = $value;
				}
			} else {
				// Keep existing value if not provided.
				$new_settings[ $key ] = isset( $current_settings[ $key ] ) ? $current_settings[ $key ] : null;
			}
		}

		$saved = update_option( self::SETTINGS_OPTION, $new_settings );

		if ( false === $saved && $new_settings !== $current_settings ) {
			return new WP_Error(
				'settings_update_failed',
				__( 'Failed to update settings.', 'glotpress-ai-extension' ),
				array( 'status' => 500 )
			);
		}

		// Return settings with masked API key.
		if ( ! empty( $new_settings['open_ai_key'] ) ) {
			$new_settings['open_ai_key'] = $this->mask_api_key( $new_settings['open_ai_key'] );
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $new_settings,
			)
		);
	}

	/**
	 * Get settings with defaults.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'open_ai_key'   => '',
			'open_ai_model' => self::DEFAULT_OPENAI_MODEL,
		);

		$settings = get_option( self::SETTINGS_OPTION, array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Sanitize API key.
	 *
	 * @since 1.0.0
	 * @param string $value API key value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		// Remove any whitespace.
		$value = trim( $value );

		// Remove any non-alphanumeric characters except hyphens.
		$value = preg_replace( '/[^a-zA-Z0-9\-]/', '', $value );

		return $value;
	}

	/**
	 * Validate API key format.
	 *
	 * @since 1.0.0
	 * @param string $value API key value.
	 * @return bool|WP_Error
	 */
	public function validate_api_key( $value ) {
		if ( empty( $value ) ) {
			return true; // Optional field.
		}

		// Check if it's a masked key (for updates).
		if ( $this->is_masked_api_key( $value ) ) {
			return true;
		}

		// OpenAI API keys typically start with 'sk-' and are 51 characters long.
		if ( ! preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $value ) ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid OpenAI API key format.', 'glotpress-ai-extension' )
			);
		}

		return true;
	}

	/**
	 * Validate model name.
	 *
	 * @since 1.0.0
	 * @param string $value Model name.
	 * @return bool|WP_Error
	 */
	public function validate_model_name( $value ) {
		if ( empty( $value ) ) {
			return true;
		}

		$allowed_models = array(
			'gpt-4.1-nano',
			'gpt-4.1-mini',
		);

		if ( ! in_array( $value, $allowed_models, true ) ) {
			return new WP_Error(
				'invalid_model',
				sprintf(
					__( 'Invalid model. Allowed models are: %s', 'glotpress-ai-extension' ),
					implode( ', ', $allowed_models )
				)
			);
		}

		return true;
	}

	/**
	 * Mask API key for security.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to mask.
	 * @return string
	 */
	private function mask_api_key( $api_key ) {
		if ( strlen( $api_key ) <= 8 ) {
			return str_repeat( '*', strlen( $api_key ) );
		}

		return substr( $api_key, 0, 8 ) . str_repeat( '*', strlen( $api_key ) - 8 ) . substr( $api_key, -4 );
	}

	/**
	 * Check if value is a masked API key.
	 *
	 * @since 1.0.0
	 * @param string $value Value to check.
	 * @return bool
	 */
	private function is_masked_api_key( $value ) {
		return strpos( $value, '*' ) !== false;
	}

	/**
	 * Check settings permissions.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_settings_permissions( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage settings.', 'glotpress-ai-extension' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
