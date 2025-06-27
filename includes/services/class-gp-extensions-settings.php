<?php
/**
 * GlotPress AI Settings Manager Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_AI_Settings_Manager
 *
 * Handles settings storage and retrieval for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_AI_Settings_Manager {

	/**
	 * Settings option name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'gp_ai_ext_settings';

	/**
	 * Default OpenAI model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_OPENAI_MODEL = 'gpt-4.1-mini';

	/**
	 * Allowed OpenAI models.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const ALLOWED_MODELS = array(
		'gpt-4.1-mini',
		'gpt-4.1-nano',
	);

	/**
	 * Instance of the class.
	 *
	 * @since 1.0.0
	 * @var GP_AI_Settings_Manager
	 */
	private static $instance = null;

	/**
	 * Cached settings.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $settings_cache = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return GP_AI_Settings_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @since 1.0.0
	 * @param bool $force_refresh Force refresh from database.
	 * @return array
	 */
	public function get_all( $force_refresh = false ) {
		if ( null === $this->settings_cache || $force_refresh ) {
			$defaults = $this->get_defaults();
			$settings = get_option( self::OPTION_NAME, array() );

			$this->settings_cache = wp_parse_args( $settings, $defaults );
		}

		return $this->settings_cache;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @since 1.0.0
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		if ( null === $default ) {
			$defaults = $this->get_defaults();
			return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
		}

		return $default;
	}

	/**
	 * Update settings.
	 *
	 * @since 1.0.0
	 * @param array $new_settings Settings to update.
	 * @return bool True if updated successfully, false otherwise.
	 */
	public function update( array $new_settings ) {
		$current_settings = $this->get_all();

		// Sanitize and validate new settings.
		$sanitized_settings = $this->sanitize_settings( $new_settings );

		// Merge with current settings.
		$updated_settings = array_merge( $current_settings, $sanitized_settings );

		// Save to database.
		$result = update_option( self::OPTION_NAME, $updated_settings );

		// Clear cache if successful.
		if ( $result || $updated_settings === $current_settings ) {
			$this->settings_cache = $updated_settings;
			return true;
		}

		return false;
	}

	/**
	 * Update a single setting.
	 *
	 * @since 1.0.0
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True if updated successfully, false otherwise.
	 */
	public function update_single( $key, $value ) {
		return $this->update( array( $key => $value ) );
	}

	/**
	 * Delete all settings.
	 *
	 * @since 1.0.0
	 * @return bool True if deleted successfully, false otherwise.
	 */
	public function delete_all() {
		$result = delete_option( self::OPTION_NAME );

		if ( $result ) {
			$this->settings_cache = null;
		}

		return $result;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_defaults() {
		return array(
			'open_ai_key'   => '',
			'open_ai_model' => self::DEFAULT_OPENAI_MODEL,
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( array $settings ) {
		$sanitized = array();

		// Only process known settings
		$known_settings = array_keys( $this->get_defaults() );

		foreach ( $settings as $key => $value ) {
			if ( ! in_array( $key, $known_settings, true ) ) {
				continue;
			}

			switch ( $key ) {
				case 'open_ai_key':
					$sanitized[ $key ] = $this->sanitize_api_key( $value );
					break;

				case 'open_ai_model':
					$sanitized[ $key ] = $this->sanitize_model( $value );
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize API key.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to sanitize.
	 * @return string Sanitized API key.
	 */
	private function sanitize_api_key( $api_key ) {
		// Skip sanitization if it's a masked key
		if ( $this->is_masked_api_key( $api_key ) ) {
			return $this->get( 'open_ai_key', '' );
		}

		// Remove whitespace
		$api_key = trim( $api_key );

		// Remove any non-alphanumeric characters except hyphens
		$api_key = preg_replace( '/[^a-zA-Z0-9\-]/', '', $api_key );

		return $api_key;
	}

	/**
	 * Sanitize model name.
	 *
	 * @since 1.0.0
	 * @param string $model Model name to sanitize.
	 * @return string Sanitized model name.
	 */
	private function sanitize_model( $model ) {
		$model = sanitize_text_field( $model );

		// If not in allowed models, return default
		if ( ! in_array( $model, self::ALLOWED_MODELS, true ) ) {
			return self::DEFAULT_OPENAI_MODEL;
		}

		return $model;
	}

	/**
	 * Validate API key format.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return true; // Optional field
		}

		// Check if it's a masked key
		if ( $this->is_masked_api_key( $api_key ) ) {
			return true;
		}

		// OpenAI API keys typically start with 'sk-' and are 51 characters long
		return preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $api_key );
	}

	/**
	 * Validate model name.
	 *
	 * @since 1.0.0
	 * @param string $model Model name to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_model( $model ) {
		if ( empty( $model ) ) {
			return true; // Will use default
		}

		return in_array( $model, self::ALLOWED_MODELS, true );
	}

	/**
	 * Mask API key for security.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to mask.
	 * @return string Masked API key.
	 */
	public function mask_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		if ( strlen( $api_key ) <= 8 ) {
			return str_repeat( '*', strlen( $api_key ) );
		}

		return substr( $api_key, 0, 4 ) . str_repeat( '*', strlen( $api_key ) - 8 ) . substr( $api_key, -4 );
	}

	/**
	 * Check if value is a masked API key.
	 *
	 * @since 1.0.0
	 * @param string $value Value to check.
	 * @return bool True if masked, false otherwise.
	 */
	public function is_masked_api_key( $value ) {
		return strpos( $value, '*' ) !== false;
	}

	/**
	 * Get settings for display (with masked sensitive data).
	 *
	 * @since 1.0.0
	 * @return array Settings with masked sensitive data.
	 */
	public function get_for_display() {
		$settings = $this->get_all();

		if ( ! empty( $settings['open_ai_key'] ) ) {
			$settings['open_ai_key'] = $this->mask_api_key( $settings['open_ai_key'] );
		}

		return $settings;
	}

	/**
	 * Check if API key is configured.
	 *
	 * @since 1.0.0
	 * @return bool True if API key is set, false otherwise.
	 */
	public function has_api_key() {
		$api_key = $this->get( 'open_ai_key' );
		return ! empty( $api_key );
	}

	/**
	 * Get the current model.
	 *
	 * @since 1.0.0
	 * @return string Current model name.
	 */
	public function get_model() {
		return $this->get( 'open_ai_model', self::DEFAULT_OPENAI_MODEL );
	}

	/**
	 * Get the API Key.
	 *
	 * @since 1.0.0
	 * @return string Current API Key.
	 */
	public function get_api_key() {
		return $this->get( 'open_ai_key', self::DEFAULT_OPENAI_MODEL );
	}
}
