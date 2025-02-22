<?php
/**
 * Constants.
 */

 define( 'GLOTPRESS_AI_EXTENSION_PATH', plugin_dir_path( __FILE__ ) );
 define( 'GLOTPRESS_AI_EXTENSION_URL', plugin_dir_url( __FILE__ ) );
 define( 'GLOTPRESS_AI_EXTENSION_VERSION', strval( time() ) );

/**
 * Action Scheduler stubs for static analysis
 */

/**
 * Check if an action is scheduled
 * 
 * @param string $hook
 * @param array<mixed> $args
 * @return bool
 */
function as_has_scheduled_action($hook, $args = array()) {
    return false;
}

/**
 * Schedule an async action
 * 
 * @param string $hook
 * @param array<mixed> $args
 * @param string $group
 * @return int
 */
function as_enqueue_async_action($hook, $args = array(), $group = '') {
    return 0;
}