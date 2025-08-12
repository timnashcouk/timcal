<?php
/**
 * Plugin Name: TimCal
 * Plugin URI: https://github.com/timnashcouk/timcal
 * Description: A simple Calendar to book meetings with Tim
 * Version: 1.0.0
 * Author: Tim Nash
 * Author URI: https://timnash.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: timcal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.3
 * Network: false
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TIMCAL_VERSION', '1.0.0' );
define( 'TIMCAL_PLUGIN_FILE', __FILE__ );
define( 'TIMCAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIMCAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TIMCAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include and register the custom autoloader.
require_once TIMCAL_PLUGIN_DIR . 'src/class-autoloader.php';

// Initialize and register the autoloader.
$autoloader = new \Timnashcouk\Timcal\Autoloader( TIMCAL_PLUGIN_DIR . 'src/' );
$autoloader->register();

// Use the main plugin class.
use Timnashcouk\Timcal\Plugin;

/**
 * Initialize the plugin.
 *
 * @return void
 */
function timcal_init(): void {

	// Initialize the plugin instance.
	$plugin = Plugin::get_instance( TIMCAL_PLUGIN_FILE );
	$plugin->init();
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function timcal_activate(): void {

	// Initialize the plugin instance for activation.
	Plugin::get_instance( TIMCAL_PLUGIN_FILE );
	Plugin::activate();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function timcal_deactivate(): void {

	// Call deactivation logic.
	Plugin::deactivate();
}

/**
 * Plugin uninstall hook.
 *
 * @return void
 */
function timcal_uninstall(): void {

	// Call uninstall logic.
	Plugin::uninstall();
}

// Register activation, deactivation, and uninstall hooks.
register_activation_hook( __FILE__, 'timcal_activate' );
register_deactivation_hook( __FILE__, 'timcal_deactivate' );
register_uninstall_hook( __FILE__, 'timcal_uninstall' );

// Initialize the plugin.
add_action( 'plugins_loaded', 'timcal_init' );
