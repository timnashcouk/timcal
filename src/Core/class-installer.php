<?php
/**
 * Installer Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Core;

use Timnashcouk\Timcal\Database\Migration_Manager;

/**
 * Handles plugin installation and database setup.
 *
 * This class is responsible for setting up the plugin during installation,
 * including creating database tables, setting default options, and running
 * initial migrations.
 *
 * @since 0.1.0
 */
class Installer {

	/**
	 * Option name for storing installation timestamp.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const INSTALL_TIME_OPTION = 'timcal_install_time';

	/**
	 * Option name for storing plugin version at installation.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const INSTALL_VERSION_OPTION = 'timcal_install_version';

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
	 * Install the plugin.
	 *
	 * This method handles the complete plugin installation process,
	 * including database setup and initial configuration.
	 *
	 * @since 0.1.0
	 *
	 * @param string $plugin_version The plugin version being installed.
	 * @return bool True on successful installation, false on failure.
	 */
	public function install( string $plugin_version ): bool {
		// Guard clause: Ensure WordPress is loaded.
		if ( ! function_exists( 'add_option' ) ) {
			return false;
		}

		// Guard clause: Check if already installed.
		if ( $this->is_installed() ) {
			return $this->handle_existing_installation( $plugin_version );
		}

		try {
			// Set installation timestamp.
			$this->set_install_time();

			// Set installation version.
			$this->set_install_version( $plugin_version );

			// Set default options.
			$this->set_default_options();

			// Run database migrations.
			if ( ! $this->migration_manager->run_migrations() ) {
				$this->cleanup_failed_installation();
				return false;
			}

			// Create necessary directories.
			$this->create_directories();

			// Set up scheduled events.
			$this->setup_scheduled_events();

			// Flush rewrite rules.
			$this->flush_rewrite_rules();

			return true;

		} catch ( \Exception $e ) {
			$this->cleanup_failed_installation();
			error_log( sprintf( 'TimCal installation failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Check if the plugin is already installed.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if installed, false otherwise.
	 */
	public function is_installed(): bool {
		return false !== get_option( self::INSTALL_TIME_OPTION );
	}

	/**
	 * Get the installation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The installation timestamp, null if not installed.
	 */
	public function get_install_time(): ?string {
		$install_time = get_option( self::INSTALL_TIME_OPTION );
		return false !== $install_time ? $install_time : null;
	}

	/**
	 * Get the version at which the plugin was installed.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The installation version, null if not installed.
	 */
	public function get_install_version(): ?string {
		$install_version = get_option( self::INSTALL_VERSION_OPTION );
		return false !== $install_version ? $install_version : null;
	}

	/**
	 * Handle existing installation during activation.
	 *
	 * @since 0.1.0
	 *
	 * @param string $plugin_version The current plugin version.
	 * @return bool True on success, false on failure.
	 */
	private function handle_existing_installation( string $plugin_version ): bool {
		// Check if migrations are needed.
		if ( $this->migration_manager->needs_migration() ) {
			return $this->migration_manager->run_migrations();
		}

		// Flush rewrite rules in case of URL structure changes.
		$this->flush_rewrite_rules();

		return true;
	}

	/**
	 * Set the installation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function set_install_time(): void {
		add_option( self::INSTALL_TIME_OPTION, current_time( 'mysql' ) );
	}

	/**
	 * Set the installation version.
	 *
	 * @since 0.1.0
	 *
	 * @param string $version The plugin version.
	 * @return void
	 */
	private function set_install_version( string $version ): void {
		add_option( self::INSTALL_VERSION_OPTION, $version );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function set_default_options(): void {
		$default_options = array(
			'timcal_calendar_settings'     => array(
				'time_zone'           => get_option( 'timezone_string', 'UTC' ),
				'default_duration'    => 30,
				'booking_buffer'      => 15,
				'advance_booking'     => 30,
				'working_hours_start' => '09:00',
				'working_hours_end'   => '17:00',
				'working_days'        => array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ),
			),
			'timcal_notification_settings' => array(
				'admin_email'        => get_option( 'admin_email' ),
				'send_confirmations' => true,
				'send_reminders'     => true,
				'reminder_time'      => 24,
			),
			'timcal_appearance_settings'   => array(
				'theme'             => 'default',
				'primary_color'     => '#0073aa',
				'secondary_color'   => '#005177',
				'show_availability' => true,
			),
		);

		foreach ( $default_options as $option_name => $option_value ) {
			add_option( $option_name, $option_value );
		}
	}

	/**
	 * Create necessary directories.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function create_directories(): void {
		$upload_dir = wp_upload_dir();
		$timcal_dir = $upload_dir['basedir'] . '/timcal';

		// Create main plugin directory in uploads.
		if ( ! file_exists( $timcal_dir ) ) {
			wp_mkdir_p( $timcal_dir );
		}

		// Create logs directory.
		$logs_dir = $timcal_dir . '/logs';
		if ( ! file_exists( $logs_dir ) ) {
			wp_mkdir_p( $logs_dir );
		}

		// Create exports directory.
		$exports_dir = $timcal_dir . '/exports';
		if ( ! file_exists( $exports_dir ) ) {
			wp_mkdir_p( $exports_dir );
		}

		// Add .htaccess file to protect directories.
		$htaccess_content = "Order deny,allow\nDeny from all\n";
		file_put_contents( $timcal_dir . '/.htaccess', $htaccess_content );
		file_put_contents( $logs_dir . '/.htaccess', $htaccess_content );
		file_put_contents( $exports_dir . '/.htaccess', $htaccess_content );

		// Add index.php files to prevent directory listing.
		$index_content = "<?php\n// Silence is golden.\n";
		file_put_contents( $timcal_dir . '/index.php', $index_content );
		file_put_contents( $logs_dir . '/index.php', $index_content );
		file_put_contents( $exports_dir . '/index.php', $index_content );
	}

	/**
	 * Set up scheduled events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function setup_scheduled_events(): void {
		// Schedule daily cleanup of expired bookings.
		if ( ! wp_next_scheduled( 'timcal_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'timcal_daily_cleanup' );
		}

		// Schedule hourly reminder checks.
		if ( ! wp_next_scheduled( 'timcal_hourly_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'timcal_hourly_reminders' );
		}

		// Schedule weekly log cleanup.
		if ( ! wp_next_scheduled( 'timcal_weekly_log_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'timcal_weekly_log_cleanup' );
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
		// Flush rewrite rules to ensure custom endpoints work.
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Clean up after a failed installation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function cleanup_failed_installation(): void {
		// Remove installation options.
		delete_option( self::INSTALL_TIME_OPTION );
		delete_option( self::INSTALL_VERSION_OPTION );

		// Remove default options.
		delete_option( 'timcal_calendar_settings' );
		delete_option( 'timcal_notification_settings' );
		delete_option( 'timcal_appearance_settings' );

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'timcal_daily_cleanup' );
		wp_clear_scheduled_hook( 'timcal_hourly_reminders' );
		wp_clear_scheduled_hook( 'timcal_weekly_log_cleanup' );
	}

	/**
	 * Check system requirements.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of requirement check results.
	 */
	public function check_system_requirements(): array {
		$requirements = array(
			'php_version'    => array(
				'required' => '8.3',
				'current'  => PHP_VERSION,
				'met'      => version_compare( PHP_VERSION, '8.3', '>=' ),
			),
			'wp_version'     => array(
				'required' => '6.0',
				'current'  => get_bloginfo( 'version' ),
				'met'      => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
			),
			'mysql_version'  => array(
				'required' => '5.7',
				'current'  => $this->get_mysql_version(),
				'met'      => version_compare( $this->get_mysql_version(), '5.7', '>=' ),
			),
			'php_extensions' => array(
				'required' => array( 'json', 'mbstring', 'openssl' ),
				'missing'  => $this->get_missing_php_extensions(),
				'met'      => empty( $this->get_missing_php_extensions() ),
			),
		);

		return $requirements;
	}

	/**
	 * Get MySQL version.
	 *
	 * @since 0.1.0
	 *
	 * @return string The MySQL version.
	 */
	private function get_mysql_version(): string {
		global $wpdb;
		return $wpdb->get_var( 'SELECT VERSION()' ) ? $wpdb->get_var( 'SELECT VERSION()' ) : '0.0.0';
	}

	/**
	 * Get missing PHP extensions.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of missing PHP extensions.
	 */
	private function get_missing_php_extensions(): array {
		$required_extensions = array( 'json', 'mbstring', 'openssl' );
		$missing_extensions  = array();

		foreach ( $required_extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$missing_extensions[] = $extension;
			}
		}

		return $missing_extensions;
	}
}
