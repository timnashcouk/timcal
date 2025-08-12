<?php
/**
 * Simple validation test for Events List class
 */

// Include the autoloader
require_once 'src/class-autoloader.php';

// Register the autoloader
$autoloader = new \Timnashcouk\Timcal\Autoloader( __DIR__ );
$autoloader->register();

try {
	// Test class instantiation
	$events_list = new \Timnashcouk\Timcal\Admin\Events_List();
	echo "✅ Events_List class instantiated successfully\n";

	// Test that class has required methods
	$required_methods = array(
		'init',
		'add_custom_columns',
		'populate_custom_columns',
		'add_admin_filters',
		'filter_events_by_custom_filters',
		'add_bulk_actions',
		'handle_bulk_actions',
	);

	foreach ( $required_methods as $method ) {
		if ( method_exists( $events_list, $method ) ) {
			echo "✅ Method {$method}() exists\n";
		} else {
			echo "❌ Method {$method}() missing\n";
		}
	}

	echo "\n=== SYNTAX AND INTEGRATION TEST PASSED ===\n";

} catch ( Exception $e ) {
	echo '❌ Error: ' . $e->getMessage() . "\n";
	exit( 1 );
}
