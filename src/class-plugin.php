<?php
/**
 * Main Plugin Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal;

use Timnashcouk\Timcal\Core\Installer;
use Timnashcouk\Timcal\Core\Activator;
use Timnashcouk\Timcal\Core\Deactivator;
use Timnashcouk\Timcal\Core\Uninstaller;
use Timnashcouk\Timcal\Database\Migration_Manager;
use Timnashcouk\Timcal\Database\Migrations\Migration_001_Initial_Setup;
use Timnashcouk\Timcal\Admin\Events_List;
use Timnashcouk\Timcal\Admin\Events_Empty_State;
use Timnashcouk\Timcal\Admin\Events_Meta_Fields;
use Timnashcouk\Timcal\Admin\Events_Meta_Box;
use Timnashcouk\Timcal\Core\Meta_Field_Manager;

/**
 * Main Plugin class that orchestrates the entire plugin functionality.
 *
 * This class implements the singleton pattern to ensure only one instance
 * of the plugin is running at any time.
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * Plugin version.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const VERSION = '0.1.0';

	/**
	 * Plugin instance.
	 *
	 * @since 0.1.0
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Plugin file path.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin directory path.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Migration manager instance.
	 *
	 * @since 0.1.0
	 * @var Migration_Manager|null
	 */
	private ?Migration_Manager $migration_manager = null;

	/**
	 * Installer instance.
	 *
	 * @since 0.1.0
	 * @var Installer|null
	 */
	private ?Installer $installer = null;

	/**
	 * Activator instance.
	 *
	 * @since 0.1.0
	 * @var Activator|null
	 */
	private ?Activator $activator = null;

	/**
	 * Deactivator instance.
	 *
	 * @since 0.1.0
	 * @var Deactivator|null
	 */
	private ?Deactivator $deactivator = null;

	/**
	 * Uninstaller instance.
	 *
	 * @since 0.1.0
	 * @var Uninstaller|null
	 */
	private ?Uninstaller $uninstaller = null;

	/**
	 * Events list admin instance.
	 *
	 * @since 0.1.0
	 * @var Events_List|null
	 */
	private ?Events_List $events_list = null;

	/**
	 * Events empty state admin instance.
	 *
	 * @since 0.1.0
	 * @var Events_Empty_State|null
	 */
	private ?Events_Empty_State $events_empty_state = null;

	/**
	 * Events meta fields admin instance.
	 *
	 * @since 0.1.0
	 * @var Events_Meta_Fields|null
	 */
	private ?Events_Meta_Fields $events_meta_fields = null;

	/**
	 * Events meta box admin instance.
	 *
	 * @since 0.1.0
	 * @var Events_Meta_Box|null
	 */
	private ?Events_Meta_Box $events_meta_box = null;

	/**
	 * Meta field manager instance.
	 *
	 * @since 0.1.0
	 * @var Meta_Field_Manager|null
	 */
	private ?Meta_Field_Manager $meta_field_manager = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $plugin_file The main plugin file path.
	 */
	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_dir  = plugin_dir_path( $plugin_file );
		$this->plugin_url  = plugin_dir_url( $plugin_file );

		// Initialize version management components.
		$this->init_version_management();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $plugin_file The main plugin file path.
	 * @return Plugin The plugin instance.
	 */
	public static function get_instance( ?string $plugin_file = null ): Plugin {
		if ( null === self::$instance ) {
			if ( null === $plugin_file ) {
				throw new \InvalidArgumentException( 'Plugin file path is required for first instantiation.' );
			}
			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Guard clause: Ensure WordPress is loaded.
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// Check and run migrations if needed.
		$this->check_and_run_migrations();

		// Initialize admin functionality.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize frontend functionality.
		if ( ! is_admin() ) {
			$this->init_frontend();
		}

		// Initialize common functionality.
		$this->init_common();
	}

	/**
	 * Initialize admin-specific functionality.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init_admin(): void {
		// Initialize admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Initialize admin events functionality.
		$this->init_admin_events();

		// Add AJAX handlers.
		add_action( 'wp_ajax_timcal_dismiss_notice', array( $this, 'handle_ajax_dismiss_notice' ) );
	}

	/**
	 * Initialize frontend-specific functionality.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init_frontend(): void {
		// TODO: Initialize frontend components.
		// Example: Shortcodes, frontend scripts, etc.
	}

	/**
	 * Initialize common functionality.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init_common(): void {
		// Register custom post types.
		add_action( 'init', array( $this, 'register_post_types' ) );

		// Initialize meta field manager.
		$this->meta_field_manager = new Meta_Field_Manager();
		$this->meta_field_manager->init();

		// TODO: Initialize common components.
		// Example: REST API endpoints, database tables, etc.
	}

	/**
	 * Register custom post types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		// Register events post type.
		register_post_type(
			'timcal_events',
			array(
				'labels'       => array(
					'name'                  => __( 'Events', 'timcal' ),
					'singular_name'         => __( 'Event', 'timcal' ),
					'add_new'               => __( 'Add New', 'timcal' ),
					'add_new_item'          => __( 'Add New Event', 'timcal' ),
					'edit_item'             => __( 'Edit Event', 'timcal' ),
					'new_item'              => __( 'New Event', 'timcal' ),
					'view_item'             => __( 'View Event', 'timcal' ),
					'view_items'            => __( 'View Events', 'timcal' ),
					'search_items'          => __( 'Search Events', 'timcal' ),
					'not_found'             => __( 'No events found', 'timcal' ),
					'not_found_in_trash'    => __( 'No events found in trash', 'timcal' ),
					'all_items'             => __( 'All Events', 'timcal' ),
					'archives'              => __( 'Event Archives', 'timcal' ),
					'attributes'            => __( 'Event Attributes', 'timcal' ),
					'insert_into_item'      => __( 'Insert into event', 'timcal' ),
					'uploaded_to_this_item' => __( 'Uploaded to this event', 'timcal' ),
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
				'menu_icon'    => 'dashicons-calendar-alt',
				'rewrite'      => false,
			)
		);
	}

	/**
	 * Initialize admin events functionality.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init_admin_events(): void {
		// Initialize events list functionality.
		$this->events_list = new Events_List();
		$this->events_list->init();

		// Initialize events empty state functionality.
		$this->events_empty_state = new Events_Empty_State();
		$this->events_empty_state->init();

		// Initialize events meta fields functionality.
		$this->events_meta_fields = new Events_Meta_Fields();
		$this->events_meta_fields->init();

		// Initialize events meta box functionality.
		$this->events_meta_box = new Events_Meta_Box();
		$this->events_meta_box->init();
	}

	/**
	 * Handle AJAX notice dismissal.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle_ajax_dismiss_notice(): void {
		// Guard clause: Ensure user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_die( 'Unauthorized' );
		}

		// Delegate to the events empty state handler.
		if ( null !== $this->events_empty_state ) {
			$this->events_empty_state->handle_notice_dismissal();
		}

		wp_die();
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Guard clause: Ensure user has calendar capability.
		if ( ! current_user_can( 'timcal_calendar' ) ) {
			return;
		}

		// Add main Calendar menu page.
		add_menu_page(
			__( 'Calendar', 'timcal' ),           // Page title
			__( 'Calendar', 'timcal' ),           // Menu title
			'timcal_calendar',                    // Capability
			'timcal-calendar',                    // Menu slug
			array( $this, 'calendar_dashboard_page' ), // Callback function
			'dashicons-calendar-alt',             // Icon
			30                                    // Position
		);

		// Add Events submenu page.
		add_submenu_page(
			'timcal-calendar',                    // Parent slug
			__( 'Events', 'timcal' ),             // Page title
			__( 'Events', 'timcal' ),             // Menu title
			'edit_timcal_events',                 // Capability
			'edit.php?post_type=timcal_events',   // Menu slug (redirect to post type)
			null                                  // No callback needed for redirect
		);
	}

	/**
	 * Calendar dashboard page callback.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function calendar_dashboard_page(): void {
		// Redirect to Events page as the main calendar view.
		wp_safe_redirect( admin_url( 'edit.php?post_type=timcal_events' ) );
		exit;
	}

	/**
	 * Plugin activation hook.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		$instance  = self::get_instance();
		$activator = $instance->get_activator();
		$activator->activate( self::VERSION );
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		$instance    = self::get_instance();
		$deactivator = $instance->get_deactivator();
		$deactivator->deactivate();
	}

	/**
	 * Plugin uninstall hook.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $preserve_data Whether to preserve user data.
	 * @return void
	 */
	public static function uninstall( bool $preserve_data = false ): void {
		$instance    = self::get_instance();
		$uninstaller = $instance->get_uninstaller();
		$uninstaller->uninstall( $preserve_data );
	}

	/**
	 * Get plugin version.
	 *
	 * @since 0.1.0
	 *
	 * @return string The plugin version.
	 */
	public function get_version(): string {
		return self::VERSION;
	}

	/**
	 * Get plugin file path.
	 *
	 * @since 0.1.0
	 *
	 * @return string The plugin file path.
	 */
	public function get_plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Get plugin directory path.
	 *
	 * @since 0.1.0
	 *
	 * @return string The plugin directory path.
	 */
	public function get_plugin_dir(): string {
		return $this->plugin_dir;
	}

	/**
	 * Get plugin URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string The plugin URL.
	 */
	public function get_plugin_url(): string {
		return $this->plugin_url;
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function __clone() {
		// Prevent cloning.
	}

	/**
	 * Initialize version management components.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function init_version_management(): void {
		// Initialize migration manager.
		$this->migration_manager = new Migration_Manager();

		// Register migrations.
		$this->migration_manager->add_migration( new Migration_001_Initial_Setup() );

		// Initialize installer.
		$this->installer = new Installer( $this->migration_manager );

		// Initialize activator.
		$this->activator = new Activator( $this->installer, $this->migration_manager );

		// Initialize deactivator.
		$this->deactivator = new Deactivator();

		// Initialize uninstaller.
		$this->uninstaller = new Uninstaller( $this->migration_manager );
	}

	/**
	 * Check and run migrations if needed.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function check_and_run_migrations(): void {
		// Guard clause: Migration manager not initialized.
		if ( null === $this->migration_manager ) {
			return;
		}

		// Run migrations if needed.
		if ( $this->migration_manager->needs_migration() ) {
			$this->migration_manager->run_migrations();
		}
	}

	/**
	 * Get the migration manager instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Migration_Manager The migration manager instance.
	 */
	public function get_migration_manager(): Migration_Manager {
		if ( null === $this->migration_manager ) {
			$this->init_version_management();
		}
		return $this->migration_manager;
	}

	/**
	 * Get the installer instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Installer The installer instance.
	 */
	public function get_installer(): Installer {
		if ( null === $this->installer ) {
			$this->init_version_management();
		}
		return $this->installer;
	}

	/**
	 * Get the activator instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Activator The activator instance.
	 */
	public function get_activator(): Activator {
		if ( null === $this->activator ) {
			$this->init_version_management();
		}
		return $this->activator;
	}

	/**
	 * Get the deactivator instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Deactivator The deactivator instance.
	 */
	public function get_deactivator(): Deactivator {
		if ( null === $this->deactivator ) {
			$this->init_version_management();
		}
		return $this->deactivator;
	}

	/**
	 * Get the uninstaller instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Uninstaller The uninstaller instance.
	 */
	public function get_uninstaller(): Uninstaller {
		if ( null === $this->uninstaller ) {
			$this->init_version_management();
		}
		return $this->uninstaller;
	}

	/**
	 * Get version management status.
	 *
	 * @since 0.1.0
	 *
	 * @return array Version management status information.
	 */
	public function get_version_status(): array {
		$migration_manager = $this->get_migration_manager();
		$installer         = $this->get_installer();

		return array(
			'plugin_version'   => self::VERSION,
			'is_installed'     => $installer->is_installed(),
			'install_time'     => $installer->get_install_time(),
			'install_version'  => $installer->get_install_version(),
			'migration_status' => $migration_manager->get_migration_status(),
		);
	}

	/**
	 * Force migration check and execution.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if migrations completed successfully, false otherwise.
	 */
	public function force_migration_check(): bool {
		$migration_manager = $this->get_migration_manager();
		return $migration_manager->run_migrations();
	}

	/**
	 * Prevent unserialization.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __wakeup(): void {
		// Prevent unserialization.
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
