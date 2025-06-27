<?php
/**
 * GlotPress AI Extension Stats Class
 *
 * @package GlotPressAI
 * @since 1.0.0
 */

/**
 * Class GP_Extensions_Stats
 *
 * Handles statistics collection for the GlotPress AI Extension.
 *
 * @package GlotPressAI
 * @since 1.0.0
 */
class GP_Extensions_Stats {
	/**
	 * The single instance of the class.
	 *
	 * @var GP_Extensions_Stats|null
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Option name for storing statistics
	 *
	 * @since 1.0.0
	 */
	const STATS_OPTION_NAME = 'gp_ai_extension_stats';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Initialize stats if they don't exist
		$this->initialize_stats();
	}

	/**
	 * Main GP_Extensions_Stats Instance.
	 *
	 * Ensures only one instance of GP_Extensions_Stats is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return GP_Extensions_Stats - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize statistics if they don't exist
	 *
	 * @since 1.0.0
	 */
	private function initialize_stats() {
		$stats = get_option( self::STATS_OPTION_NAME );

		if ( false === $stats ) {
			$default_stats = array(
				'translations_started' => 0,
				'tokens_used'          => 0,
				'last_reset'           => current_time( 'mysql' ),
				'last_updated'         => current_time( 'mysql' ),
			);

			update_option( self::STATS_OPTION_NAME, $default_stats, false );
		}
	}

	/**
	 * Get current statistics
	 *
	 * @since 1.0.0
	 *
	 * @return array Current statistics
	 */
	public function get_stats() {
		$stats = get_option( self::STATS_OPTION_NAME, array() );

		// Ensure all keys exist
		$defaults = array(
			'translations_started' => 0,
			'tokens_used'          => 0,
			'last_reset'           => current_time( 'mysql' ),
			'last_updated'         => current_time( 'mysql' ),
		);

		return wp_parse_args( $stats, $defaults );
	}

	/**
	 * Update translations started count
	 *
	 * @param int $count Number to add to translations started (default: 1)
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the update was successful
	 */
	public function increment_translations_started( $count = 1 ) {
		$stats = $this->get_stats();

		$stats['translations_started'] += max( 0, intval( $count ) );
		$stats['last_updated']          = current_time( 'mysql' );

		return update_option( self::STATS_OPTION_NAME, $stats, false );
	}

	/**
	 * Update tokens used count
	 *
	 * @param int $tokens Number of tokens to add
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the update was successful
	 */
	public function add_tokens_used( $tokens ) {
		$tokens = intval( $tokens );

		if ( $tokens <= 0 ) {
			return false;
		}

		$stats = $this->get_stats();

		$stats['tokens_used'] += $tokens;
		$stats['last_updated'] = current_time( 'mysql' );

		return update_option( self::STATS_OPTION_NAME, $stats, false );
	}

	/**
	 * Update both statistics at once
	 *
	 * @param int $translations Number of translations to add (default: 1)
	 * @param int $tokens       Number of tokens to add
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the update was successful
	 */
	public function update_stats( $translations = 1, $tokens = 0 ) {
		$stats = $this->get_stats();

		$translations = intval( $translations );
		$tokens       = intval( $tokens );

		if ( $translations > 0 ) {
			$stats['translations_started'] += $translations;
		}

		if ( $tokens > 0 ) {
			$stats['tokens_used'] += $tokens;
		}

		$stats['last_updated'] = current_time( 'mysql' );

		return update_option( self::STATS_OPTION_NAME, $stats, false );
	}

	/**
	 * Reset all statistics
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the reset was successful
	 */
	public function reset_stats() {
		$stats = array(
			'translations_started' => 0,
			'tokens_used'          => 0,
			'last_reset'           => current_time( 'mysql' ),
			'last_updated'         => current_time( 'mysql' ),
		);

		return update_option( self::STATS_OPTION_NAME, $stats, false );
	}

	/**
	 * Get formatted statistics for display
	 *
	 * @since 1.0.0
	 *
	 * @return array Formatted statistics
	 */
	public function get_formatted_stats() {
		$stats = $this->get_stats();

		return array(
			'translations_started'     => number_format( $stats['translations_started'] ),
			'tokens_used'              => number_format( $stats['tokens_used'] ),
			'last_reset'               => $this->format_date( $stats['last_reset'] ),
			'last_updated'             => $this->format_date( $stats['last_updated'] ),
			'translations_started_raw' => $stats['translations_started'],
			'tokens_used_raw'          => $stats['tokens_used'],
		);
	}

	/**
	 * Format date for display
	 *
	 * @param string $date MySQL datetime string
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted date
	 */
	private function format_date( $date ) {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return $date;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Get statistics from logs (alternative method)
	 *
	 * This method calculates statistics directly from the log posts
	 * Can be used to verify or rebuild statistics
	 *
	 * @since 1.0.0
	 *
	 * @return array Calculated statistics
	 */
	public function calculate_stats_from_logs() {
		// Check if GP_Extensions_Logger class exists
		if ( ! class_exists( 'GP_Extensions_Logger' ) ) {
			return array(
				'translations_started' => 0,
				'tokens_used'          => 0,
			);
		}

		$args = array(
			'post_type'      => GP_Extensions_Logger::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$post_ids = get_posts( $args );

		$total_translations = 0;
		$total_tokens       = 0;

		foreach ( $post_ids as $post_id ) {
			// Get tokens
			$tokens = get_post_meta( $post_id, '_api_used_tokens', true );
			if ( $tokens ) {
				$total_tokens += intval( $tokens );
			}

			// Get translation count
			$metadata = get_post_meta( $post_id, '_log_metadata', true );
			if ( is_array( $metadata ) && isset( $metadata['translated_items'] ) ) {
				$total_translations += intval( $metadata['translated_items'] );
			}
		}

		return array(
			'translations_started' => $total_translations,
			'tokens_used'          => $total_tokens,
		);
	}

	/**
	 * Sync statistics with logs
	 *
	 * Updates the stored statistics to match calculated values from logs
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the sync was successful
	 */
	public function sync_stats_with_logs() {
		$calculated = $this->calculate_stats_from_logs();
		$current    = $this->get_stats();

		$current['translations_started'] = $calculated['translations_started'];
		$current['tokens_used']          = $calculated['tokens_used'];
		$current['last_updated']         = current_time( 'mysql' );

		return update_option( self::STATS_OPTION_NAME, $current, false );
	}

	/**
	 * Get a specific stat value
	 *
	 * @param string $stat_name The stat to retrieve ('translations_started' or 'tokens_used')
	 *
	 * @since 1.0.0
	 *
	 * @return int|false The stat value or false if invalid stat name
	 */
	public function get_stat( $stat_name ) {
		$stats = $this->get_stats();

		if ( isset( $stats[ $stat_name ] ) ) {
			return intval( $stats[ $stat_name ] );
		}

		return false;
	}

	/**
	 * Set a specific stat value (replaces, doesn't add)
	 *
	 * @param string $stat_name The stat to set
	 * @param int    $value     The new value
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the update was successful
	 */
	public function set_stat( $stat_name, $value ) {
		$valid_stats = array( 'translations_started', 'tokens_used' );

		if ( ! in_array( $stat_name, $valid_stats, true ) ) {
			return false;
		}

		$stats                 = $this->get_stats();
		$stats[ $stat_name ]   = max( 0, intval( $value ) );
		$stats['last_updated'] = current_time( 'mysql' );

		return update_option( self::STATS_OPTION_NAME, $stats, false );
	}
}
