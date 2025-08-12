<?php
/**
 * Deactivator Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Core;

/**
 * Handles plugin deactivation logic.
 *
 * This class is responsible for all tasks that need to be performed
 * when the plugin is deactivated, including cleanup of temporary data,
 * clearing scheduled events, and preserving user data.
 *
 * @since 0.1.0
 */
class Deactivator {

	/**
	 * Option name for storing deactivation timestamp.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const DEACTIVATION_TIME_OPTION = 'timcal_deactivation_time';

	/**
	 * Option name for storing deactivation reason.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const DEACTIVATION_REASON_OPTION = 'timcal_deactivation_reason';

	/**
	 * Deactivate the plugin.
	 *
	 * This method handles the complete plugin deactivation process,
	 * including cleanup of temporary data and scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $reason Optional reason for deactivation.
	 * @return bool True on successful deactivation, false on failure.
	 */
	public function deactivate( ?string $reason = null ): bool {
		// Guard clause: Ensure WordPress is loaded.
		if ( ! function_exists( 'delete_option' ) ) {
			return false;
		}

		try {
			// Set deactivation timestamp.
			$this->set_deactivation_time();

			// Set deactivation reason if provided.
			if ( null !== $reason ) {
				$this->set_deactivation_reason( $reason );
			}

			// Clear scheduled events.
			$this->clear_scheduled_events();

			// Clear transients and cache.
			$this->clear_transients_and_cache();

			// Flush rewrite rules.
			$this->flush_rewrite_rules();

			// Clean up temporary files.
			$this->cleanup_temporary_files();

			// Cancel pending notifications.
			$this->cancel_pending_notifications();

			// Log deactivation.
			$this->log_deactivation();

			return true;

		} catch ( \Exception $e ) {
			error_log( sprintf( 'TimCal deactivation failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Set the deactivation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function set_deactivation_time(): void {
		update_option( self::DEACTIVATION_TIME_OPTION, current_time( 'mysql' ) );
	}

	/**
	 * Get the deactivation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The deactivation timestamp, null if not set.
	 */
	public function get_deactivation_time(): ?string {
		$deactivation_time = get_option( self::DEACTIVATION_TIME_OPTION );
		return false !== $deactivation_time ? $deactivation_time : null;
	}

	/**
	 * Set the deactivation reason.
	 *
	 * @since 0.1.0
	 *
	 * @param string $reason The reason for deactivation.
	 * @return void
	 */
	private function set_deactivation_reason( string $reason ): void {
		update_option( self::DEACTIVATION_REASON_OPTION, $reason );
	}

	/**
	 * Get the deactivation reason.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The deactivation reason, null if not set.
	 */
	public function get_deactivation_reason(): ?string {
		$deactivation_reason = get_option( self::DEACTIVATION_REASON_OPTION );
		return false !== $deactivation_reason ? $deactivation_reason : null;
	}

	/**
	 * Clear all scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_scheduled_events(): void {
		$scheduled_events = array(
			'timcal_daily_cleanup',
			'timcal_hourly_reminders',
			'timcal_weekly_log_cleanup',
			'timcal_send_reminder_emails',
			'timcal_cleanup_expired_bookings',
			'timcal_backup_data',
		);

		foreach ( $scheduled_events as $event ) {
			wp_clear_scheduled_hook( $event );
		}
	}

	/**
	 * Clear transients and cache.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_transients_and_cache(): void {
		// Clear plugin-specific transients.
		$transients = array(
			'timcal_calendar_data',
			'timcal_availability_cache',
			'timcal_booking_stats',
			'timcal_system_status',
			'timcal_activation_notice',
			'timcal_update_notice',
		);

		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}

		// Clear object cache for plugin data.
		$cache_groups = array(
			'timcal',
			'timcal_bookings',
			'timcal_availability',
			'timcal_settings',
		);

		foreach ( $cache_groups as $group ) {
			wp_cache_flush_group( $group );
		}

		// Clear any site transients.
		delete_site_transient( 'timcal_multisite_data' );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function flush_rewrite_rules(): void {
		// Flush rewrite rules to clean up custom endpoints.
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Clean up temporary files.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function cleanup_temporary_files(): void {
		$upload_dir = wp_upload_dir();
		$timcal_dir = $upload_dir['basedir'] . '/timcal';

		// Clean up temporary export files.
		$exports_dir = $timcal_dir . '/exports';
		if ( is_dir( $exports_dir ) ) {
			$this->cleanup_old_files( $exports_dir, 0 ); // Remove all export files
		}

		// Clean up old log files (keep last 30 days).
		$logs_dir = $timcal_dir . '/logs';
		if ( is_dir( $logs_dir ) ) {
			$this->cleanup_old_files( $logs_dir, 30 * DAY_IN_SECONDS );
		}

		// Clean up temporary cache files.
		$cache_dir = $timcal_dir . '/cache';
		if ( is_dir( $cache_dir ) ) {
			$this->cleanup_old_files( $cache_dir, 0 ); // Remove all cache files
		}
	}

	/**
	 * Clean up old files in a directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $directory The directory to clean up.
	 * @param int    $max_age Maximum age in seconds (0 = delete all).
	 * @return void
	 */
	private function cleanup_old_files( string $directory, int $max_age ): void {
		// Guard clause: Directory doesn't exist.
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$files = glob( $directory . '/*' );
		if ( false === $files ) {
			return;
		}

		$current_time = time();

		foreach ( $files as $file ) {
			// Skip directories and special files.
			if ( is_dir( $file ) || basename( $file ) === '.htaccess' || basename( $file ) === 'index.php' ) {
				continue;
			}

			// Delete file if it's older than max_age or if max_age is 0.
			if ( 0 === $max_age || ( $current_time - filemtime( $file ) ) > $max_age ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Cancel pending notifications.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function cancel_pending_notifications(): void {
		// Cancel any pending email notifications.
		$this->cancel_scheduled_emails();

		// Clear notification queue.
		delete_option( 'timcal_notification_queue' );

		// Clear reminder queue.
		delete_option( 'timcal_reminder_queue' );
	}

	/**
	 * Cancel scheduled emails.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function cancel_scheduled_emails(): void {
		global $wpdb;

		// Get all scheduled email events.
		$scheduled_emails = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timcal_scheduled_email_%'
			)
		);

		// Cancel each scheduled email.
		foreach ( $scheduled_emails as $email ) {
			$transient_name = str_replace( '_transient_', '', $email->option_name );
			delete_transient( $transient_name );
		}
	}

	/**
	 * Log deactivation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function log_deactivation(): void {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event'     => 'plugin_deactivated',
			'reason'    => $this->get_deactivation_reason(),
			'user_id'   => get_current_user_id(),
		);

		// Add to plugin log.
		$plugin_log = get_option( 'timcal_plugin_log', array() );
		if ( ! is_array( $plugin_log ) ) {
			$plugin_log = array();
		}

		array_unshift( $plugin_log, $log_entry );

		// Limit log size.
		if ( count( $plugin_log ) > 50 ) {
			$plugin_log = array_slice( $plugin_log, 0, 50 );
		}

		update_option( 'timcal_plugin_log', $plugin_log );
	}

	/**
	 * Perform emergency deactivation.
	 *
	 * This method is used when the plugin needs to be deactivated
	 * due to critical errors or system issues.
	 *
	 * @since 0.1.0
	 *
	 * @param string $reason The reason for emergency deactivation.
	 * @return bool True on success, false on failure.
	 */
	public function emergency_deactivate( string $reason ): bool {
		// Set emergency deactivation flag.
		update_option( 'timcal_emergency_deactivation', true );

		// Log emergency deactivation.
		error_log( sprintf( 'TimCal emergency deactivation: %s', $reason ) );

		// Perform minimal cleanup.
		$this->clear_scheduled_events();
		$this->clear_transients_and_cache();

		// Set deactivation details.
		$this->set_deactivation_time();
		$this->set_deactivation_reason( 'Emergency: ' . $reason );

		return true;
	}

	/**
	 * Check if the plugin was emergency deactivated.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if emergency deactivated, false otherwise.
	 */
	public function is_emergency_deactivated(): bool {
		return (bool) get_option( 'timcal_emergency_deactivation', false );
	}

	/**
	 * Clear emergency deactivation flag.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function clear_emergency_deactivation(): void {
		delete_option( 'timcal_emergency_deactivation' );
	}

	/**
	 * Get deactivation statistics.
	 *
	 * @since 0.1.0
	 *
	 * @return array Deactivation statistics.
	 */
	public function get_deactivation_stats(): array {
		return array(
			'deactivation_time'        => $this->get_deactivation_time(),
			'deactivation_reason'      => $this->get_deactivation_reason(),
			'emergency_deactivated'    => $this->is_emergency_deactivated(),
			'scheduled_events_cleared' => $this->count_cleared_events(),
			'transients_cleared'       => $this->count_cleared_transients(),
		);
	}

	/**
	 * Count cleared scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of cleared events.
	 */
	private function count_cleared_events(): int {
		// This is a placeholder - in a real implementation,
		// you might track this during the clearing process.
		return 6; // Based on the events we clear
	}

	/**
	 * Count cleared transients.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of cleared transients.
	 */
	private function count_cleared_transients(): int {
		// This is a placeholder - in a real implementation,
		// you might track this during the clearing process.
		return 6; // Based on the transients we clear
	}

	/**
	 * Prepare for reactivation.
	 *
	 * This method cleans up deactivation-specific data
	 * to prepare for potential reactivation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function prepare_for_reactivation(): void {
		// Clear deactivation timestamp and reason.
		delete_option( self::DEACTIVATION_TIME_OPTION );
		delete_option( self::DEACTIVATION_REASON_OPTION );

		// Clear emergency deactivation flag.
		$this->clear_emergency_deactivation();

		// Clear any deactivation notices.
		delete_transient( 'timcal_deactivation_notice' );
	}
}
