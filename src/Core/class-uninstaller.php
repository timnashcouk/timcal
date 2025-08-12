<?php
/**
 * Uninstaller Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Core;

use Timnashcouk\Timcal\Database\Migration_Manager;

/**
 * Handles plugin uninstall cleanup.
 *
 * This class is responsible for completely removing all plugin data
 * when the plugin is uninstalled, including database tables, options,
 * user capabilities, and files.
 *
 * @since 0.1.0
 */
class Uninstaller {

	/**
	 * Option name for storing uninstall timestamp.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const UNINSTALL_TIME_OPTION = 'timcal_uninstall_time';

	/**
	 * Migration manager instance.
	 *
	 * @since 0.1.0
	 * @var Migration_Manager
	 */
	private Migration_Manager $migration_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration_Manager $migration_manager The migration manager instance.
	 */
	public function __construct( Migration_Manager $migration_manager ) {
		$this->migration_manager = $migration_manager;
	}

	/**
	 * Uninstall the plugin.
	 *
	 * This method handles the complete plugin uninstallation process,
	 * removing all data, tables, options, and files.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $preserve_data Whether to preserve user data (default: false).
	 * @return bool True on successful uninstall, false on failure.
	 */
	public function uninstall( bool $preserve_data = false ): bool {
		// Guard clause: Ensure WordPress is loaded.
		if ( ! function_exists( 'delete_option' ) ) {
			return false;
		}

		// Guard clause: Check if user has permission to uninstall.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		try {
			// Set uninstall timestamp.
			$this->set_uninstall_time();

			// Log uninstall start.
			$this->log_uninstall_start( $preserve_data );

			if ( ! $preserve_data ) {
				// Remove all database tables.
				$this->remove_database_tables();

				// Remove all plugin data.
				$this->remove_plugin_data();

				// Remove user capabilities and roles.
				$this->remove_user_capabilities();

				// Remove custom post types and taxonomies.
				$this->remove_custom_post_types();
			}

			// Remove plugin files and directories.
			$this->remove_plugin_files();

			// Clear all scheduled events.
			$this->clear_all_scheduled_events();

			// Clear all transients and cache.
			$this->clear_all_transients_and_cache();

			// Remove plugin options.
			$this->remove_plugin_options( $preserve_data );

			// Flush rewrite rules.
			$this->flush_rewrite_rules();

			// Log uninstall completion.
			$this->log_uninstall_completion();

			return true;

		} catch ( \Exception $e ) {
			error_log( sprintf( 'TimCal uninstall failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Set the uninstall timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function set_uninstall_time(): void {
		update_option( self::UNINSTALL_TIME_OPTION, current_time( 'mysql' ) );
	}

	/**
	 * Remove all database tables.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_database_tables(): void {
		global $wpdb;

		// Get all plugin tables.
		$tables = $this->get_plugin_tables();

		foreach ( $tables as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		// Reset migration version.
		delete_option( 'timcal_migration_version' );
	}

	/**
	 * Get all plugin database tables.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of table names.
	 */
	private function get_plugin_tables(): array {
		global $wpdb;

		// Define plugin table names.
		$plugin_tables = array(
			$wpdb->prefix . 'timcal_bookings',
			$wpdb->prefix . 'timcal_availability',
			$wpdb->prefix . 'timcal_booking_meta',
			$wpdb->prefix . 'timcal_availability_meta',
			$wpdb->prefix . 'timcal_notifications',
			$wpdb->prefix . 'timcal_logs',
		);

		// Filter to only existing tables.
		$existing_tables = array();
		foreach ( $plugin_tables as $table ) {
			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			);
			if ( $table_exists ) {
				$existing_tables[] = $table;
			}
		}

		return $existing_tables;
	}

	/**
	 * Remove all plugin data.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_plugin_data(): void {
		// Remove custom posts.
		$this->remove_custom_posts();

		// Remove user meta.
		$this->remove_user_meta();

		// Remove term meta.
		$this->remove_term_meta();

		// Remove comment meta.
		$this->remove_comment_meta();
	}

	/**
	 * Remove custom posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_custom_posts(): void {
		$post_types = array( 'timcal_booking', 'timcal_availability', 'timcal_events' );

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'any',
				)
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}
	}

	/**
	 * Remove user meta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_user_meta(): void {
		global $wpdb;

		$meta_keys = array(
			'timcal_user_preferences',
			'timcal_booking_history',
			'timcal_notification_settings',
			'timcal_calendar_access',
		);

		foreach ( $meta_keys as $meta_key ) {
			$wpdb->delete(
				$wpdb->usermeta,
				array( 'meta_key' => $meta_key )
			);
		}
	}

	/**
	 * Remove term meta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_term_meta(): void {
		global $wpdb;

		$meta_keys = array(
			'timcal_term_settings',
			'timcal_category_config',
		);

		foreach ( $meta_keys as $meta_key ) {
			$wpdb->delete(
				$wpdb->termmeta,
				array( 'meta_key' => $meta_key )
			);
		}
	}

	/**
	 * Remove comment meta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_comment_meta(): void {
		global $wpdb;

		$meta_keys = array(
			'timcal_booking_review',
			'timcal_rating_data',
		);

		foreach ( $meta_keys as $meta_key ) {
			$wpdb->delete(
				$wpdb->commentmeta,
				array( 'meta_key' => $meta_key )
			);
		}
	}

	/**
	 * Remove user capabilities and roles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_user_capabilities(): void {
		// Remove custom role.
		remove_role( 'timcal_manager' );

		// Remove capabilities from existing roles.
		$capabilities = array(
			'timcal_calendar',
			'manage_timcal_bookings',
			'edit_timcal_bookings',
			'delete_timcal_bookings',
			'view_timcal_bookings',
			'manage_timcal_events',
			'edit_timcal_events',
			'delete_timcal_events',
			'read_timcal_events',
			'manage_timcal_settings',
			'export_timcal_data',
		);

		$roles = array( 'administrator', 'editor' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $capabilities as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	/**
	 * Remove custom post types and taxonomies.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_custom_post_types(): void {
		// Unregister custom post types.
		unregister_post_type( 'timcal_booking' );
		unregister_post_type( 'timcal_availability' );
		unregister_post_type( 'timcal_events' );

		// Unregister custom taxonomies if any were created.
		unregister_taxonomy( 'timcal_booking_category' );
		unregister_taxonomy( 'timcal_booking_status' );
	}

	/**
	 * Remove plugin files and directories.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function remove_plugin_files(): void {
		$upload_dir = wp_upload_dir();
		$timcal_dir = $upload_dir['basedir'] . '/timcal';

		// Remove plugin directory and all contents.
		if ( is_dir( $timcal_dir ) ) {
			$this->remove_directory_recursive( $timcal_dir );
		}
	}

	/**
	 * Recursively remove a directory and all its contents.
	 *
	 * @since 0.1.0
	 *
	 * @param string $directory The directory to remove.
	 * @return bool True on success, false on failure.
	 */
	private function remove_directory_recursive( string $directory ): bool {
		// Guard clause: Directory doesn't exist.
		if ( ! is_dir( $directory ) ) {
			return true;
		}

		$files = array_diff( scandir( $directory ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $directory . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->remove_directory_recursive( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		return rmdir( $directory );
	}

	/**
	 * Clear all scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_all_scheduled_events(): void {
		$scheduled_events = array(
			'timcal_daily_cleanup',
			'timcal_hourly_reminders',
			'timcal_weekly_log_cleanup',
			'timcal_send_reminder_emails',
			'timcal_cleanup_expired_bookings',
			'timcal_backup_data',
			'timcal_sync_calendar',
			'timcal_generate_reports',
		);

		foreach ( $scheduled_events as $event ) {
			wp_clear_scheduled_hook( $event );
		}
	}

	/**
	 * Clear all transients and cache.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_all_transients_and_cache(): void {
		global $wpdb;

		// Remove all plugin transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_timcal_%',
				'_transient_timeout_timcal_%'
			)
		);

		// Remove all plugin site transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_site_transient_timcal_%',
				'_site_transient_timeout_timcal_%'
			)
		);

		// Clear object cache.
		$cache_groups = array(
			'timcal',
			'timcal_bookings',
			'timcal_availability',
			'timcal_settings',
		);

		foreach ( $cache_groups as $group ) {
			wp_cache_flush_group( $group );
		}
	}

	/**
	 * Remove plugin options.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $preserve_data Whether to preserve user data.
	 * @return void
	 */
	private function remove_plugin_options( bool $preserve_data ): void {
		global $wpdb;

		if ( $preserve_data ) {
			// Only remove system options, preserve user settings.
			$system_options = array(
				'timcal_install_time',
				'timcal_install_version',
				'timcal_activation_time',
				'timcal_deactivation_time',
				'timcal_migration_version',
				'timcal_migration_log',
				'timcal_plugin_log',
				'timcal_system_status',
			);

			foreach ( $system_options as $option ) {
				delete_option( $option );
			}
		} else {
			// Remove all plugin options.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'timcal_%'
				)
			);
		}
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function flush_rewrite_rules(): void {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Log uninstall start.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $preserve_data Whether data is being preserved.
	 * @return void
	 */
	private function log_uninstall_start( bool $preserve_data ): void {
		$log_entry = array(
			'timestamp'     => current_time( 'mysql' ),
			'event'         => 'uninstall_started',
			'preserve_data' => $preserve_data,
			'user_id'       => get_current_user_id(),
		);

		error_log( sprintf( 'TimCal uninstall started (preserve_data: %s)', $preserve_data ? 'true' : 'false' ) );
	}

	/**
	 * Log uninstall completion.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function log_uninstall_completion(): void {
		error_log( 'TimCal uninstall completed successfully' );
	}

	/**
	 * Get uninstall statistics.
	 *
	 * @since 0.1.0
	 *
	 * @return array Uninstall statistics.
	 */
	public function get_uninstall_stats(): array {
		return array(
			'tables_removed'       => count( $this->get_plugin_tables() ),
			'options_removed'      => $this->count_plugin_options(),
			'posts_removed'        => $this->count_plugin_posts(),
			'capabilities_removed' => 11,
			'roles_removed'        => 1,
			'files_removed'        => $this->count_plugin_files(),
		);
	}

	/**
	 * Count plugin options.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of plugin options.
	 */
	private function count_plugin_options(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'timcal_%'
			)
		);
	}

	/**
	 * Count plugin posts.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of plugin posts.
	 */
	private function count_plugin_posts(): int {
		$post_types = array( 'timcal_booking', 'timcal_availability', 'timcal_events' );
		$total      = 0;

		foreach ( $post_types as $post_type ) {
			$posts  = get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			);
			$total += count( $posts );
		}

		return $total;
	}

	/**
	 * Count plugin files.
	 *
	 * @since 0.1.0
	 *
	 * @return int Number of plugin files.
	 */
	private function count_plugin_files(): int {
		$upload_dir = wp_upload_dir();
		$timcal_dir = $upload_dir['basedir'] . '/timcal';

		if ( ! is_dir( $timcal_dir ) ) {
			return 0;
		}

		return $this->count_files_recursive( $timcal_dir );
	}

	/**
	 * Recursively count files in a directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $directory The directory to count files in.
	 * @return int Number of files.
	 */
	private function count_files_recursive( string $directory ): int {
		$count = 0;
		$files = glob( $directory . '/*' );

		if ( false === $files ) {
			return 0;
		}

		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				$count += $this->count_files_recursive( $file );
			} else {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Create uninstall backup.
	 *
	 * Creates a backup of plugin data before uninstalling.
	 *
	 * @since 0.1.0
	 *
	 * @return string|false Path to backup file on success, false on failure.
	 */
	public function create_uninstall_backup(): string|false {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/timcal-backups';

		// Create backup directory.
		if ( ! wp_mkdir_p( $backup_dir ) ) {
			return false;
		}

		$backup_file = $backup_dir . '/timcal-uninstall-backup-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';

		$backup_data = array(
			'timestamp'      => current_time( 'mysql' ),
			'plugin_version' => defined( 'TIMCAL_VERSION' ) ? TIMCAL_VERSION : '0.1.0',
			'options'        => $this->get_all_plugin_options(),
			'posts'          => $this->get_all_plugin_posts(),
			'user_meta'      => $this->get_all_plugin_user_meta(),
		);

		$json_data = wp_json_encode( $backup_data, JSON_PRETTY_PRINT );

		if ( false === file_put_contents( $backup_file, $json_data ) ) {
			return false;
		}

		return $backup_file;
	}

	/**
	 * Get all plugin options for backup.
	 *
	 * @since 0.1.0
	 *
	 * @return array Plugin options.
	 */
	private function get_all_plugin_options(): array {
		global $wpdb;

		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'timcal_%'
			),
			ARRAY_A
		);

		$formatted_options = array();
		foreach ( $options as $option ) {
			$formatted_options[ $option['option_name'] ] = maybe_unserialize( $option['option_value'] );
		}

		return $formatted_options;
	}

	/**
	 * Get all plugin posts for backup.
	 *
	 * @since 0.1.0
	 *
	 * @return array Plugin posts.
	 */
	private function get_all_plugin_posts(): array {
		$post_types = array( 'timcal_booking', 'timcal_availability', 'timcal_events' );
		$all_posts  = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'   => $post_type,
					'numberposts' => -1,
					'post_status' => 'any',
				)
			);

			foreach ( $posts as $post ) {
				$all_posts[] = array(
					'ID'           => $post->ID,
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_status'  => $post->post_status,
					'post_type'    => $post->post_type,
					'post_date'    => $post->post_date,
					'meta'         => get_post_meta( $post->ID ),
				);
			}
		}

		return $all_posts;
	}

	/**
	 * Get all plugin user meta for backup.
	 *
	 * @since 0.1.0
	 *
	 * @return array Plugin user meta.
	 */
	private function get_all_plugin_user_meta(): array {
		global $wpdb;

		$meta_keys = array(
			'timcal_user_preferences',
			'timcal_booking_history',
			'timcal_notification_settings',
			'timcal_calendar_access',
		);

		$user_meta = array();

		foreach ( $meta_keys as $meta_key ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				),
				ARRAY_A
			);

			foreach ( $results as $result ) {
				$user_meta[ $result['user_id'] ][ $meta_key ] = maybe_unserialize( $result['meta_value'] );
			}
		}

		return $user_meta;
	}
}
