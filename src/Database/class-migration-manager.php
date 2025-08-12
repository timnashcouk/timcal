<?php
/**
 * Migration Manager Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Database;

/**
 * Manages database migrations for the plugin.
 *
 * This class handles the execution, tracking, and rollback of database migrations.
 * It maintains a record of applied migrations and ensures they are executed in order.
 *
 * @since 0.1.0
 */
class Migration_Manager {

	/**
	 * Option name for storing migration version.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const MIGRATION_VERSION_OPTION = 'timcal_migration_version';

	/**
	 * Option name for storing migration log.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const MIGRATION_LOG_OPTION = 'timcal_migration_log';

	/**
	 * Maximum number of log entries to keep.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	private const MAX_LOG_ENTRIES = 100;

	/**
	 * Array of registered migrations.
	 *
	 * @since 0.1.0
	 * @var Migration[]
	 */
	private array $migrations = array();

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->register_migrations();
	}

	/**
	 * Register all available migrations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_migrations(): void {
		// TODO: Auto-discover migration files when autoloader is implemented.
		// For now, migrations will be manually registered.
	}

	/**
	 * Add a migration to the manager.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration to add.
	 * @return void
	 */
	public function add_migration( Migration $migration ): void {
		$this->migrations[ $migration->get_version() ] = $migration;
		ksort( $this->migrations );
	}

	/**
	 * Get the current database migration version.
	 *
	 * @since 0.1.0
	 *
	 * @return string The current migration version, '000' if none applied.
	 */
	public function get_current_version(): string {
		return get_option( self::MIGRATION_VERSION_OPTION, '000' );
	}

	/**
	 * Get the latest available migration version.
	 *
	 * @since 0.1.0
	 *
	 * @return string The latest migration version, '000' if no migrations.
	 */
	public function get_latest_version(): string {
		if ( empty( $this->migrations ) ) {
			return '000';
		}

		$versions = array_keys( $this->migrations );
		return end( $versions );
	}

	/**
	 * Check if migrations are needed.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if migrations are pending, false otherwise.
	 */
	public function needs_migration(): bool {
		return version_compare( $this->get_current_version(), $this->get_latest_version(), '<' );
	}

	/**
	 * Get pending migrations.
	 *
	 * @since 0.1.0
	 *
	 * @return Migration[] Array of pending migrations.
	 */
	public function get_pending_migrations(): array {
		$current_version = $this->get_current_version();
		$pending         = array();

		foreach ( $this->migrations as $version => $migration ) {
			if ( version_compare( $version, $current_version, '>' ) ) {
				$pending[] = $migration;
			}
		}

		return $pending;
	}

	/**
	 * Run all pending migrations.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if all migrations succeeded, false otherwise.
	 */
	public function run_migrations(): bool {
		// Guard clause: Check if WordPress is loaded.
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$pending_migrations = $this->get_pending_migrations();

		// Guard clause: No pending migrations.
		if ( empty( $pending_migrations ) ) {
			return true;
		}

		foreach ( $pending_migrations as $migration ) {
			if ( ! $this->run_migration( $migration ) ) {
				$this->log_migration_error( $migration, 'Migration failed during execution' );
				return false;
			}
		}

		return true;
	}

	/**
	 * Run a single migration.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration to run.
	 * @return bool True on success, false on failure.
	 */
	private function run_migration( Migration $migration ): bool {
		// Guard clause: Check WordPress and PHP version requirements.
		if ( ! $this->check_migration_requirements( $migration ) ) {
			return false;
		}

		$this->log_migration_start( $migration );

		try {
			// Execute the migration.
			$result = $migration->up();

			if ( $result ) {
				// Update the migration version.
				update_option( self::MIGRATION_VERSION_OPTION, $migration->get_version() );
				$this->log_migration_success( $migration );
				return true;
			} else {
				$this->log_migration_error( $migration, 'Migration up() method returned false' );
				return false;
			}
		} catch ( \Exception $e ) {
			$this->log_migration_error( $migration, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Rollback to a specific migration version.
	 *
	 * @since 0.1.0
	 *
	 * @param string $target_version The version to rollback to.
	 * @return bool True on success, false on failure.
	 */
	public function rollback_to_version( string $target_version ): bool {
		// Guard clause: Check if WordPress is loaded.
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$current_version = $this->get_current_version();

		// Guard clause: Target version is not lower than current.
		if ( version_compare( $target_version, $current_version, '>=' ) ) {
			return true;
		}

		$migrations_to_rollback = array();

		// Get migrations to rollback in reverse order.
		foreach ( array_reverse( $this->migrations, true ) as $version => $migration ) {
			if ( version_compare( $version, $target_version, '>' ) && version_compare( $version, $current_version, '<=' ) ) {
				$migrations_to_rollback[] = $migration;
			}
		}

		foreach ( $migrations_to_rollback as $migration ) {
			if ( ! $this->rollback_migration( $migration ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Rollback a single migration.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration to rollback.
	 * @return bool True on success, false on failure.
	 */
	private function rollback_migration( Migration $migration ): bool {
		// Guard clause: Check if rollback is safe.
		if ( ! $migration->is_rollback_safe() ) {
			$this->log_migration_error( $migration, 'Migration rollback is not safe' );
			return false;
		}

		$this->log_migration_rollback_start( $migration );

		try {
			$result = $migration->down();

			if ( $result ) {
				// Find the previous migration version.
				$previous_version = $this->get_previous_migration_version( $migration->get_version() );
				update_option( self::MIGRATION_VERSION_OPTION, $previous_version );
				$this->log_migration_rollback_success( $migration );
				return true;
			} else {
				$this->log_migration_error( $migration, 'Migration down() method returned false' );
				return false;
			}
		} catch ( \Exception $e ) {
			$this->log_migration_error( $migration, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the previous migration version.
	 *
	 * @since 0.1.0
	 *
	 * @param string $current_version The current migration version.
	 * @return string The previous migration version, '000' if none.
	 */
	private function get_previous_migration_version( string $current_version ): string {
		$versions      = array_keys( $this->migrations );
		$current_index = array_search( $current_version, $versions, true );

		if ( false === $current_index || 0 === $current_index ) {
			return '000';
		}

		return $versions[ $current_index - 1 ];
	}

	/**
	 * Check if migration requirements are met.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration to check.
	 * @return bool True if requirements are met, false otherwise.
	 */
	private function check_migration_requirements( Migration $migration ): bool {
		global $wp_version;

		// Check WordPress version.
		if ( version_compare( $wp_version, $migration->get_min_wp_version(), '<' ) ) {
			$this->log_migration_error(
				$migration,
				sprintf(
					'WordPress version %s required, %s installed',
					$migration->get_min_wp_version(),
					$wp_version
				)
			);
			return false;
		}

		// Check PHP version.
		if ( version_compare( PHP_VERSION, $migration->get_min_php_version(), '<' ) ) {
			$this->log_migration_error(
				$migration,
				sprintf(
					'PHP version %s required, %s installed',
					$migration->get_min_php_version(),
					PHP_VERSION
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Log migration start.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration being started.
	 * @return void
	 */
	private function log_migration_start( Migration $migration ): void {
		$this->add_log_entry(
			'info',
			sprintf(
				'Starting migration %s: %s',
				$migration->get_version(),
				$migration->get_description()
			)
		);
	}

	/**
	 * Log migration success.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration that succeeded.
	 * @return void
	 */
	private function log_migration_success( Migration $migration ): void {
		$this->add_log_entry(
			'success',
			sprintf(
				'Migration %s completed successfully',
				$migration->get_version()
			)
		);
	}

	/**
	 * Log migration error.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration that failed.
	 * @param string    $error_message The error message.
	 * @return void
	 */
	private function log_migration_error( Migration $migration, string $error_message ): void {
		$this->add_log_entry(
			'error',
			sprintf(
				'Migration %s failed: %s',
				$migration->get_version(),
				$error_message
			)
		);
	}

	/**
	 * Log migration rollback start.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration being rolled back.
	 * @return void
	 */
	private function log_migration_rollback_start( Migration $migration ): void {
		$this->add_log_entry(
			'info',
			sprintf(
				'Starting rollback of migration %s',
				$migration->get_version()
			)
		);
	}

	/**
	 * Log migration rollback success.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration $migration The migration that was rolled back.
	 * @return void
	 */
	private function log_migration_rollback_success( Migration $migration ): void {
		$this->add_log_entry(
			'success',
			sprintf(
				'Migration %s rolled back successfully',
				$migration->get_version()
			)
		);
	}

	/**
	 * Add an entry to the migration log.
	 *
	 * @since 0.1.0
	 *
	 * @param string $level The log level (info, success, error).
	 * @param string $message The log message.
	 * @return void
	 */
	private function add_log_entry( string $level, string $message ): void {
		$log = get_option( self::MIGRATION_LOG_OPTION, array() );

		// Ensure log is an array.
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		array_unshift( $log, $entry );

		// Limit log size.
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::MIGRATION_LOG_OPTION, $log );
	}

	/**
	 * Get the migration log.
	 *
	 * @since 0.1.0
	 *
	 * @return array The migration log entries.
	 */
	public function get_migration_log(): array {
		$log = get_option( self::MIGRATION_LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the migration log.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function clear_migration_log(): void {
		delete_option( self::MIGRATION_LOG_OPTION );
	}

	/**
	 * Get migration status information.
	 *
	 * @since 0.1.0
	 *
	 * @return array Migration status information.
	 */
	public function get_migration_status(): array {
		return array(
			'current_version'  => $this->get_current_version(),
			'latest_version'   => $this->get_latest_version(),
			'needs_migration'  => $this->needs_migration(),
			'pending_count'    => count( $this->get_pending_migrations() ),
			'total_migrations' => count( $this->migrations ),
			'log_entries'      => count( $this->get_migration_log() ),
		);
	}
}
