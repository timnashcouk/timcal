<?php
/**
 * Migration 001: Initial Setup
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Database\Migrations;

use Timnashcouk\Timcal\Database\Migration;

/**
 * Initial database setup migration.
 *
 * This migration creates the initial database tables and sets up
 * the basic structure for the TimCal plugin.
 *
 * @since 0.1.0
 */
class Migration_001_Initial_Setup implements Migration {

	/**
	 * Get the migration version number.
	 *
	 * @since 0.1.0
	 *
	 * @return string The migration version.
	 */
	public function get_version(): string {
		return '001';
	}

	/**
	 * Get the migration description.
	 *
	 * @since 0.1.0
	 *
	 * @return string A human-readable description.
	 */
	public function get_description(): string {
		return 'Initial database setup - Create bookings and availability tables';
	}

	/**
	 * Execute the migration.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up(): bool {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		try {
			// Create bookings table.
			$bookings_table = $this->create_bookings_table( $charset_collate );
			if ( ! $bookings_table ) {
				return false;
			}

			// Create availability table.
			$availability_table = $this->create_availability_table( $charset_collate );
			if ( ! $availability_table ) {
				return false;
			}

			// Create booking meta table.
			$booking_meta_table = $this->create_booking_meta_table( $charset_collate );
			if ( ! $booking_meta_table ) {
				return false;
			}

			// Create availability meta table.
			$availability_meta_table = $this->create_availability_meta_table( $charset_collate );
			if ( ! $availability_meta_table ) {
				return false;
			}

			// Create notifications table.
			$notifications_table = $this->create_notifications_table( $charset_collate );
			if ( ! $notifications_table ) {
				return false;
			}

			// Create logs table.
			$logs_table = $this->create_logs_table( $charset_collate );
			if ( ! $logs_table ) {
				return false;
			}

			// Insert default data.
			$this->insert_default_data();

			return true;

		} catch ( \Exception $e ) {
			error_log( sprintf( 'Migration 001 failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Rollback the migration.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function down(): bool {
		global $wpdb;

		try {
			// Drop tables in reverse order.
			$tables = array(
				$wpdb->prefix . 'timcal_logs',
				$wpdb->prefix . 'timcal_notifications',
				$wpdb->prefix . 'timcal_availability_meta',
				$wpdb->prefix . 'timcal_booking_meta',
				$wpdb->prefix . 'timcal_availability',
				$wpdb->prefix . 'timcal_bookings',
			);

			foreach ( $tables as $table ) {
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
			}

			return true;

		} catch ( \Exception $e ) {
			error_log( sprintf( 'Migration 001 rollback failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Check if this migration can be safely rolled back.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if rollback is safe.
	 */
	public function is_rollback_safe(): bool {
		return true;
	}

	/**
	 * Get the minimum WordPress version required.
	 *
	 * @since 0.1.0
	 *
	 * @return string The minimum WordPress version.
	 */
	public function get_min_wp_version(): string {
		return '6.0';
	}

	/**
	 * Get the minimum PHP version required.
	 *
	 * @since 0.1.0
	 *
	 * @return string The minimum PHP version.
	 */
	public function get_min_php_version(): string {
		return '8.3';
	}

	/**
	 * Create the bookings table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_bookings_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_bookings';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_uuid varchar(36) NOT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_phone varchar(50) DEFAULT NULL,
			booking_title varchar(255) NOT NULL,
			booking_description text DEFAULT NULL,
			start_datetime datetime NOT NULL,
			end_datetime datetime NOT NULL,
			duration_minutes int(11) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			booking_type varchar(50) NOT NULL DEFAULT 'standard',
			location varchar(255) DEFAULT NULL,
			meeting_url varchar(500) DEFAULT NULL,
			timezone varchar(50) NOT NULL DEFAULT 'UTC',
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY booking_uuid (booking_uuid),
			KEY customer_email (customer_email),
			KEY start_datetime (start_datetime),
			KEY status (status),
			KEY created_by (created_by),
			KEY booking_type (booking_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create the availability table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_availability_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_availability';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			availability_uuid varchar(36) NOT NULL,
			title varchar(255) NOT NULL,
			description text DEFAULT NULL,
			availability_type varchar(50) NOT NULL DEFAULT 'recurring',
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			duration_minutes int(11) NOT NULL DEFAULT 30,
			buffer_minutes int(11) NOT NULL DEFAULT 15,
			max_bookings int(11) NOT NULL DEFAULT 1,
			days_of_week varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
			timezone varchar(50) NOT NULL DEFAULT 'UTC',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			priority int(11) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY availability_uuid (availability_uuid),
			KEY availability_type (availability_type),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY is_active (is_active),
			KEY created_by (created_by),
			KEY priority (priority)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create the booking meta table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_booking_meta_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_booking_meta';

		$sql = "CREATE TABLE {$table_name} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY (meta_id),
			KEY booking_id (booking_id),
			KEY meta_key (meta_key(191)),
			CONSTRAINT fk_booking_meta_booking_id FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}timcal_bookings (id) ON DELETE CASCADE
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create the availability meta table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_availability_meta_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_availability_meta';

		$sql = "CREATE TABLE {$table_name} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			availability_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext DEFAULT NULL,
			PRIMARY KEY (meta_id),
			KEY availability_id (availability_id),
			KEY meta_key (meta_key(191)),
			CONSTRAINT fk_availability_meta_availability_id FOREIGN KEY (availability_id) REFERENCES {$wpdb->prefix}timcal_availability (id) ON DELETE CASCADE
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create the notifications table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_notifications_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_notifications';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			notification_uuid varchar(36) NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			notification_type varchar(50) NOT NULL,
			recipient_email varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			message text NOT NULL,
			scheduled_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int(11) NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY notification_uuid (notification_uuid),
			KEY booking_id (booking_id),
			KEY notification_type (notification_type),
			KEY recipient_email (recipient_email),
			KEY scheduled_at (scheduled_at),
			KEY status (status),
			CONSTRAINT fk_notification_booking_id FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}timcal_bookings (id) ON DELETE CASCADE
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Create the logs table.
	 *
	 * @since 0.1.0
	 *
	 * @param string $charset_collate The charset collation.
	 * @return bool True on success, false on failure.
	 */
	private function create_logs_table( string $charset_collate ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'timcal_logs';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			log_uuid varchar(36) NOT NULL,
			level varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext DEFAULT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY log_uuid (log_uuid),
			KEY level (level),
			KEY booking_id (booking_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created successfully.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Insert default data.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function insert_default_data(): void {
		global $wpdb;

		// Insert default availability for the site admin.
		$admin_user = get_user_by( 'email', get_option( 'admin_email' ) );
		if ( $admin_user ) {
			$availability_data = array(
				'availability_uuid' => wp_generate_uuid4(),
				'title'             => 'Default Availability',
				'description'       => 'Default availability schedule for meetings',
				'availability_type' => 'recurring',
				'start_date'        => current_time( 'Y-m-d' ),
				'start_time'        => '09:00:00',
				'end_time'          => '17:00:00',
				'duration_minutes'  => 30,
				'buffer_minutes'    => 15,
				'max_bookings'      => 1,
				'days_of_week'      => '1,2,3,4,5', // Monday to Friday
				'timezone'          => get_option( 'timezone_string', 'UTC' ),
				'is_active'         => 1,
				'priority'          => 0,
				'created_by'        => $admin_user->ID,
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			);

			$wpdb->insert(
				$wpdb->prefix . 'timcal_availability',
				$availability_data,
				array(
					'%s', // availability_uuid
					'%s', // title
					'%s', // description
					'%s', // availability_type
					'%s', // start_date
					'%s', // start_time
					'%s', // end_time
					'%d', // duration_minutes
					'%d', // buffer_minutes
					'%d', // max_bookings
					'%s', // days_of_week
					'%s', // timezone
					'%d', // is_active
					'%d', // priority
					'%d', // created_by
					'%s', // created_at
					'%s', // updated_at
				)
			);
		}

		// Log the migration completion.
		$log_data = array(
			'log_uuid'   => wp_generate_uuid4(),
			'level'      => 'info',
			'message'    => 'Initial database setup completed successfully',
			'context'    => wp_json_encode( array( 'migration' => '001' ) ),
			'user_id'    => get_current_user_id(),
			'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
			'created_at' => current_time( 'mysql' ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'timcal_logs',
			$log_data,
			array(
				'%s', // log_uuid
				'%s', // level
				'%s', // message
				'%s', // context
				'%d', // user_id
				'%s', // ip_address
				'%s', // user_agent
				'%s', // created_at
			)
		);
	}
}
