<?php
/**
 * Activator Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Core;

use Timnashcouk\Timcal\Database\Migration_Manager;

/**
 * Handles plugin activation logic.
 *
 * This class is responsible for all tasks that need to be performed
 * when the plugin is activated, including running the installer,
 * checking system requirements, and setting up initial state.
 *
 * @since 0.1.0
 */
class Activator {

	/**
	 * Option name for storing activation timestamp.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const ACTIVATION_TIME_OPTION = 'timcal_activation_time';

	/**
	 * Option name for storing activation errors.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const ACTIVATION_ERRORS_OPTION = 'timcal_activation_errors';

	/**
	 * Installer instance.
	 *
	 * @since 0.1.0
	 * @var Installer
	 */
	private Installer $installer;

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
	 * @param Installer         $installer The installer instance.
	 * @param Migration_Manager $migration_manager The migration manager instance.
	 */
	public function __construct( Installer $installer, Migration_Manager $migration_manager ) {
		$this->installer         = $installer;
		$this->migration_manager = $migration_manager;
	}

	/**
	 * Activate the plugin.
	 *
	 * This method handles the complete plugin activation process,
	 * including system requirement checks, installation, and setup.
	 *
	 * @since 0.1.0
	 *
	 * @param string $plugin_version The plugin version being activated.
	 * @return bool True on successful activation, false on failure.
	 */
	public function activate( string $plugin_version ): bool {
		// Guard clause: Ensure WordPress is loaded.
		if ( ! function_exists( 'add_option' ) ) {
			$this->add_activation_error( 'WordPress is not properly loaded' );
			return false;
		}

		// Clear any previous activation errors.
		$this->clear_activation_errors();

		try {
			// Check system requirements.
			if ( ! $this->check_system_requirements() ) {
				return false;
			}

			// Check for conflicting plugins.
			if ( ! $this->check_plugin_conflicts() ) {
				return false;
			}

			// Run the installer.
			if ( ! $this->installer->install( $plugin_version ) ) {
				$this->add_activation_error( 'Installation failed' );
				return false;
			}

			// Set activation timestamp.
			$this->set_activation_time();

			// Perform post-activation tasks.
			$this->perform_post_activation_tasks();

			// Schedule activation notice.
			$this->schedule_activation_notice();

			return true;

		} catch ( \Exception $e ) {
			$this->add_activation_error( sprintf( 'Activation failed: %s', $e->getMessage() ) );
			error_log( sprintf( 'TimCal activation failed: %s', $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Check system requirements.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if requirements are met, false otherwise.
	 */
	private function check_system_requirements(): bool {
		$requirements = $this->installer->check_system_requirements();
		$errors       = array();

		// Check PHP version.
		if ( ! $requirements['php_version']['met'] ) {
			$errors[] = sprintf(
				'PHP version %s or higher is required. You are running PHP %s.',
				$requirements['php_version']['required'],
				$requirements['php_version']['current']
			);
		}

		// Check WordPress version.
		if ( ! $requirements['wp_version']['met'] ) {
			$errors[] = sprintf(
				'WordPress version %s or higher is required. You are running WordPress %s.',
				$requirements['wp_version']['required'],
				$requirements['wp_version']['current']
			);
		}

		// Check MySQL version.
		if ( ! $requirements['mysql_version']['met'] ) {
			$errors[] = sprintf(
				'MySQL version %s or higher is required. You are running MySQL %s.',
				$requirements['mysql_version']['required'],
				$requirements['mysql_version']['current']
			);
		}

		// Check PHP extensions.
		if ( ! $requirements['php_extensions']['met'] ) {
			$errors[] = sprintf(
				'The following PHP extensions are required: %s',
				implode( ', ', $requirements['php_extensions']['missing'] )
			);
		}

		// Guard clause: Requirements not met.
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				$this->add_activation_error( $error );
			}
			return false;
		}

		return true;
	}

	/**
	 * Check for conflicting plugins.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if no conflicts, false if conflicts found.
	 */
	private function check_plugin_conflicts(): bool {
		$conflicting_plugins = array(
			'booking-calendar/booking-calendar.php'   => 'Booking Calendar',
			'simply-schedule-appointments/simply-schedule-appointments.php' => 'Simply Schedule Appointments',
			'easy-appointments/easy-appointments.php' => 'Easy Appointments',
		);

		$active_conflicts = array();

		foreach ( $conflicting_plugins as $plugin_file => $plugin_name ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active_conflicts[] = $plugin_name;
			}
		}

		// Guard clause: Conflicts found.
		if ( ! empty( $active_conflicts ) ) {
			$this->add_activation_error(
				sprintf(
					'The following conflicting plugins are active and may cause issues: %s. Please deactivate them before activating TimCal.',
					implode( ', ', $active_conflicts )
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Set the activation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function set_activation_time(): void {
		update_option( self::ACTIVATION_TIME_OPTION, current_time( 'mysql' ) );
	}

	/**
	 * Get the activation timestamp.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null The activation timestamp, null if not set.
	 */
	public function get_activation_time(): ?string {
		$activation_time = get_option( self::ACTIVATION_TIME_OPTION );
		return false !== $activation_time ? $activation_time : null;
	}

	/**
	 * Perform post-activation tasks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function perform_post_activation_tasks(): void {
		// Set up user capabilities.
		$this->setup_user_capabilities();

		// Register custom post types and taxonomies.
		$this->register_custom_post_types();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set up default user roles.
		$this->setup_default_user_roles();

		// Initialize plugin settings.
		$this->initialize_plugin_settings();
	}

	/**
	 * Set up user capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function setup_user_capabilities(): void {
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

		// Add capabilities to administrator role.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( $capabilities as $capability ) {
				$admin_role->add_cap( $capability );
			}
		}

		// Add limited capabilities to editor role.
		$editor_role = get_role( 'editor' );
		if ( $editor_role ) {
			$editor_capabilities = array(
				'edit_timcal_bookings',
				'view_timcal_bookings',
			);
			foreach ( $editor_capabilities as $capability ) {
				$editor_role->add_cap( $capability );
			}
		}
	}

	/**
	 * Register custom post types and taxonomies.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_custom_post_types(): void {
		// Register booking post type.
		register_post_type(
			'timcal_booking',
			array(
				'labels'       => array(
					'name'          => __( 'Bookings', 'timcal' ),
					'singular_name' => __( 'Booking', 'timcal' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'custom-fields' ),
				'capabilities' => array(
					'edit_post'          => 'edit_timcal_bookings',
					'read_post'          => 'view_timcal_bookings',
					'delete_post'        => 'delete_timcal_bookings',
					'edit_posts'         => 'edit_timcal_bookings',
					'edit_others_posts'  => 'manage_timcal_bookings',
					'delete_posts'       => 'delete_timcal_bookings',
					'publish_posts'      => 'edit_timcal_bookings',
					'read_private_posts' => 'view_timcal_bookings',
				),
			)
		);

		// Register availability post type.
		register_post_type(
			'timcal_availability',
			array(
				'labels'       => array(
					'name'          => __( 'Availability', 'timcal' ),
					'singular_name' => __( 'Availability', 'timcal' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'custom-fields' ),
				'capabilities' => array(
					'edit_post'          => 'manage_timcal_settings',
					'read_post'          => 'manage_timcal_settings',
					'delete_post'        => 'manage_timcal_settings',
					'edit_posts'         => 'manage_timcal_settings',
					'edit_others_posts'  => 'manage_timcal_settings',
					'delete_posts'       => 'manage_timcal_settings',
					'publish_posts'      => 'manage_timcal_settings',
					'read_private_posts' => 'manage_timcal_settings',
				),
			)
		);

		// Register events post type.
		register_post_type(
			'timcal_events',
			array(
				'labels'       => array(
					'name'          => __( 'Events', 'timcal' ),
					'singular_name' => __( 'Event', 'timcal' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'hierarchical' => true,
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
				'capabilities' => array(
					'edit_post'          => 'edit_timcal_events',
					'read_post'          => 'read_timcal_events',
					'delete_post'        => 'delete_timcal_events',
					'edit_posts'         => 'edit_timcal_events',
					'edit_others_posts'  => 'manage_timcal_events',
					'delete_posts'       => 'delete_timcal_events',
					'publish_posts'      => 'edit_timcal_events',
					'read_private_posts' => 'read_timcal_events',
				),
			)
		);
	}

	/**
	 * Set up default user roles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function setup_default_user_roles(): void {
		// Add TimCal Manager role.
		add_role(
			'timcal_manager',
			__( 'TimCal Manager', 'timcal' ),
			array(
				'read'                   => true,
				'timcal_calendar'        => true,
				'manage_timcal_bookings' => true,
				'edit_timcal_bookings'   => true,
				'delete_timcal_bookings' => true,
				'view_timcal_bookings'   => true,
				'manage_timcal_events'   => true,
				'edit_timcal_events'     => true,
				'delete_timcal_events'   => true,
				'read_timcal_events'     => true,
				'manage_timcal_settings' => true,
				'export_timcal_data'     => true,
			)
		);
	}

	/**
	 * Initialize plugin settings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function initialize_plugin_settings(): void {
		// Set up default permalink structure for calendar endpoints.
		$permalink_structure = get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			// Suggest pretty permalinks for better calendar URLs.
			add_option( 'timcal_suggest_permalinks', true );
		}

		// Initialize calendar cache.
		wp_cache_delete( 'timcal_calendar_data', 'timcal' );

		// Set up default email templates.
		$this->setup_default_email_templates();
	}

	/**
	 * Set up default email templates.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function setup_default_email_templates(): void {
		$email_templates = array(
			'booking_confirmation' => array(
				'subject' => __( 'Booking Confirmation - {booking_title}', 'timcal' ),
				'body'    => __( "Hello {customer_name},\n\nYour booking has been confirmed:\n\nTitle: {booking_title}\nDate: {booking_date}\nTime: {booking_time}\nDuration: {booking_duration}\n\nThank you!", 'timcal' ),
			),
			'booking_reminder'     => array(
				'subject' => __( 'Reminder: {booking_title}', 'timcal' ),
				'body'    => __( "Hello {customer_name},\n\nThis is a reminder about your upcoming booking:\n\nTitle: {booking_title}\nDate: {booking_date}\nTime: {booking_time}\n\nSee you soon!", 'timcal' ),
			),
			'booking_cancellation' => array(
				'subject' => __( 'Booking Cancelled - {booking_title}', 'timcal' ),
				'body'    => __( "Hello {customer_name},\n\nYour booking has been cancelled:\n\nTitle: {booking_title}\nDate: {booking_date}\nTime: {booking_time}\n\nIf you have any questions, please contact us.", 'timcal' ),
			),
		);

		add_option( 'timcal_email_templates', $email_templates );
	}

	/**
	 * Schedule activation notice.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function schedule_activation_notice(): void {
		// Schedule a notice to show after activation.
		set_transient( 'timcal_activation_notice', true, 60 );
	}

	/**
	 * Add an activation error.
	 *
	 * @since 0.1.0
	 *
	 * @param string $error The error message.
	 * @return void
	 */
	private function add_activation_error( string $error ): void {
		$errors = get_option( self::ACTIVATION_ERRORS_OPTION, array() );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		$errors[] = $error;
		update_option( self::ACTIVATION_ERRORS_OPTION, $errors );
	}

	/**
	 * Get activation errors.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of activation error messages.
	 */
	public function get_activation_errors(): array {
		$errors = get_option( self::ACTIVATION_ERRORS_OPTION, array() );
		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Clear activation errors.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function clear_activation_errors(): void {
		delete_option( self::ACTIVATION_ERRORS_OPTION );
	}

	/**
	 * Check if activation was successful.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if activation was successful, false otherwise.
	 */
	public function is_activation_successful(): bool {
		return empty( $this->get_activation_errors() ) && null !== $this->get_activation_time();
	}
}
