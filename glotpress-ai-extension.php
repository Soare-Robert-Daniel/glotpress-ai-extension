<?php
/**
 * Plugin Name:     GlotPress AI Extension
 * Plugin URI:      glotpress-ai-extension
 * Description:     Enhance GlotPress with AI translation functions.
 * Author:          Soare Robert-Daniel
 * Author URI:      Soare Robert-Daniel
 * Text Domain:     glotpress-ai-extension
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Glotpress_Ai_Extension
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GLOTPRESS_AI_EXTENSION_PATH', plugin_dir_path( __FILE__ ) );
define( 'GLOTPRESS_AI_EXTENSION_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'GP_Extensions_Admin' ) ) {
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-settings.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-stats.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-openai-service.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-openai-service.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-logger.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-progress-watcher.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-project-extension.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/services/class-gp-extensions-endpoints.php';
	require_once GLOTPRESS_AI_EXTENSION_PATH . 'includes/class-gp-extensions-admin.php';
	new GP_Extensions_Admin();
}
