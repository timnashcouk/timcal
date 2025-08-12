<?php
/**
 * Migration Interface
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Database;

/**
 * Interface for database migrations.
 *
 * All migration classes must implement this interface to ensure
 * consistent migration execution and rollback capabilities.
 *
 * @since 0.1.0
 */
interface Migration {

	/**
	 * Get the migration version number.
	 *
	 * @since 0.1.0
	 *
	 * @return string The migration version (e.g., '001', '002').
	 */
	public function get_version(): string;

	/**
	 * Get the migration description.
	 *
	 * @since 0.1.0
	 *
	 * @return string A human-readable description of what this migration does.
	 */
	public function get_description(): string;

	/**
	 * Execute the migration.
	 *
	 * This method should contain all the database changes needed
	 * to upgrade the database to this migration version.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up(): bool;

	/**
	 * Rollback the migration.
	 *
	 * This method should undo all changes made by the up() method,
	 * returning the database to its previous state.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function down(): bool;

	/**
	 * Check if this migration can be safely rolled back.
	 *
	 * Some migrations may not be reversible (e.g., data deletion).
	 * This method allows migrations to indicate if rollback is safe.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if rollback is safe, false otherwise.
	 */
	public function is_rollback_safe(): bool;

	/**
	 * Get the minimum WordPress version required for this migration.
	 *
	 * @since 0.1.0
	 *
	 * @return string The minimum WordPress version (e.g., '6.0').
	 */
	public function get_min_wp_version(): string;

	/**
	 * Get the minimum PHP version required for this migration.
	 *
	 * @since 0.1.0
	 *
	 * @return string The minimum PHP version (e.g., '8.3').
	 */
	public function get_min_php_version(): string;
}
