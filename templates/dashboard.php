<?php
/**
 * Dashboard template for GlotPress AI Extension,
 * tailored to your new data structure with metadata.
 *
 * @package GlotPress_AI_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Retrieve and unserialize the logs stored in the gb-extensions-logs option.
$logs_serialized = get_option( 'gb-extensions-logs', array() );
$logs            = maybe_unserialize( $logs_serialized );

// Prepare token stats.
$total_tokens_all = 0;
if ( ! empty( $logs ) && is_array( $logs ) ) {
	foreach ( $logs as $index => $log ) {
		$tokens_this_run = 0;

		// Sum tokens for each run (info array).
		if ( ! empty( $log['info'] ) && is_array( $log['info'] ) ) {
			foreach ( $log['info'] as $info ) {
				$tokens_this_run += intval( $info['tokens_used'] );
			}
		}

		// Store total tokens for this run so we can display it in the table.
		$logs[ $index ]['tokens_this_run'] = $tokens_this_run;

		// Add to grand total.
		$total_tokens_all += $tokens_this_run;
	}
}
?>

<div class="gp-ext-dashboard-wrap">
	<h1><?php esc_html_e( 'GB Extensions Dashboard', 'glotpress-ai-extension' ); ?></h1>

	<?php if ( ! empty( $logs ) && is_array( $logs ) ) : ?>

		<?php
		$total_logs = count( $logs );
		$avg_tokens = $total_logs > 0 ? $total_tokens_all / $total_logs : 0;
		?>

		<!-- Stats / Summary Bar -->
		<div class="gp-ext-dashboard-stats">
			<div class="gp-ext-stats-item">
				<h2 class="gp-ext-stats-value">
					<?php echo intval( $total_logs ); ?>
				</h2>
				<p class="gp-ext-stats-label">
					<?php esc_html_e( 'Total Logs', 'glotpress-ai-extension' ); ?>
				</p>
			</div>
			<div class="gp-ext-stats-item">
				<h2 class="gp-ext-stats-value">
					<?php echo number_format( $total_tokens_all ); ?>
				</h2>
				<p class="gp-ext-stats-label">
					<?php esc_html_e( 'Total Tokens Used', 'glotpress-ai-extension' ); ?>
				</p>
			</div>
			<div class="gp-ext-stats-item">
				<h2 class="gp-ext-stats-value">
					<?php echo number_format( $avg_tokens, 2 ); ?>
				</h2>
				<p class="gp-ext-stats-label">
					<?php esc_html_e( 'Average Tokens per Log', 'glotpress-ai-extension' ); ?>
				</p>
			</div>
		</div>

		<!-- Logs Table -->
		<table class="gp-ext-dashboard-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Errors', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'API Requests', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Tokens (Run)', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Created At', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Started At', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Finished At', 'glotpress-ai-extension' ); ?></th>
					<th><?php esc_html_e( 'Duration (s)', 'glotpress-ai-extension' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<!-- ID -->
						<td><?php echo esc_html( $log['id'] ); ?></td>

						<!-- Errors -->
						<td>
							<?php
							if ( ! empty( $log['errors'] ) && is_array( $log['errors'] ) ) {
								foreach ( $log['errors'] as $er ) {
									if ( ! empty( $er['message'] ) ) {
										echo esc_html( $er['message'] ) . '<br />';
									}
								}
							} else {
								esc_html_e( 'None', 'glotpress-ai-extension' );
							}
							?>
						</td>

						<!-- API Requests (Info) -->
						<td>
							<?php
							if ( ! empty( $log['info'] ) && is_array( $log['info'] ) ) {
								echo '<ol class="gp-ext-info-list">';
								foreach ( $log['info'] as $info ) {
									echo '<li>';
									printf(
										/* translators: %1$d is tokens used, %2$s is model name */
										esc_html__( 'Tokens used: %1$d, Model: %2$s', 'glotpress-ai-extension' ),
										intval( $info['tokens_used'] ),
										esc_html( $info['model'] )
									);
									echo '</li>';
								}
								echo '</ol>';
							} else {
								esc_html_e( 'No Info', 'glotpress-ai-extension' );
							}
							?>
						</td>

						<!-- Tokens (Run) -->
						<td><?php echo intval( $log['tokens_this_run'] ); ?></td>

						<!-- Created At -->
						<td><?php echo esc_html( $log['created_at'] ); ?></td>

						<!-- Metadata: Started At, Finished At, Duration -->
						<td>
							<?php
							if ( ! empty( $log['metadata']['started_at'] ) ) {
								echo esc_html( $log['metadata']['started_at'] );
							} else {
								esc_html_e( 'N/A', 'glotpress-ai-extension' );
							}
							?>
						</td>
						<td>
							<?php
							if ( ! empty( $log['metadata']['finished_at'] ) ) {
								echo esc_html( $log['metadata']['finished_at'] );
							} else {
								esc_html_e( 'N/A', 'glotpress-ai-extension' );
							}
							?>
						</td>
						<td>
							<?php
							if ( isset( $log['metadata']['duration'] ) ) {
								$duration = intval( $log['metadata']['duration'] );
								$hours    = floor( $duration / 3600 );
								$minutes  = floor( ( $duration % 3600 ) / 60 );
								$seconds  = $duration % 60;
								printf( '%02d:%02d:%02d', (int) $hours, (int) $minutes, (int) $seconds );
							} else {
								esc_html_e( 'N/A', 'glotpress-ai-extension' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php else : ?>
		<p><?php esc_html_e( 'No logs available.', 'glotpress-ai-extension' ); ?></p>
	<?php endif; ?>
</div>