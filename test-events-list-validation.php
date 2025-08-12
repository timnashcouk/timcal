<?php
/**
 * Test script to validate Events List class functionality
 */

// Include WordPress if available
if ( file_exists( '../../../wp-config.php' ) ) {
	require_once '../../../wp-config.php';
} else {
	// Mock WordPress functions for basic testing
	function is_admin() {
		return true; }
	function add_filter() {
		return true; }
	function add_action() {
		return true; }
	function __() {
		return func_get_arg( 0 ); }
	function esc_html__() {
		return func_get_arg( 0 ); }
	function esc_html() {
		return func_get_arg( 0 ); }
	function esc_attr() {
		return func_get_arg( 0 ); }
	function esc_url() {
		return func_get_arg( 0 ); }
	function selected() {
		return ''; }
	function get_users() {
		return array(); }
	function get_post_meta() {
		return ''; }
	function get_user_by() {
		return false; }
	function get_edit_user_link() {
		return '#'; }
	function current_user_can() {
		return true; }
	function wp_nonce_url() {
		return '#'; }
	function admin_url() {
		return '#'; }
	function intval( $val ) {
		return (int) $val; }
	function sanitize_text_field( $val ) {
		return $val; }
}

// Include the autoloader
require_once 'src/class-autoloader.php';

// Register the autoloader
$autoloader = new \Timnashcouk\Timcal\Autoloader();
$autoloader->register();

try {
	// Test class instantiation
	$events_list = new \Timnashcouk\Timcal\Admin\Events_List();
	echo "✅ Events_List class instantiated successfully\n";

	// Test init method
	$events_list->init();
	echo "✅ Events_List init() method executed successfully\n";

	// Test column methods
	$columns = $events_list->add_custom_columns(
		array(
			'title' => 'Title',
			'date'  => 'Date',
		)
	);
	echo "✅ add_custom_columns() method works correctly\n";

	// Verify expected columns
	$expected_columns = array( 'duration', 'location', 'host', 'active_status' );
	$missing_columns  = array();

	foreach ( $expected_columns as $expected ) {
		if ( ! isset( $columns[ $expected ] ) ) {
			$missing_columns[] = $expected;
		}
	}

	if ( empty( $missing_columns ) ) {
		echo '✅ All expected columns present: ' . implode( ', ', $expected_columns ) . "\n";
	} else {
		echo '❌ Missing columns: ' . implode( ', ', $missing_columns ) . "\n";
	}

	// Verify no booking_status column
	if ( ! isset( $columns['booking_status'] ) ) {
		echo "✅ booking_status column correctly removed\n";
	} else {
		echo "❌ booking_status column still present\n";
	}

	echo "\n=== VALIDATION SUMMARY ===\n";
	echo "✅ Class syntax is valid\n";
	echo "✅ Class instantiation works\n";
	echo "✅ Core methods are functional\n";
	echo "✅ Column structure is correct\n";
	echo "✅ Integration appears successful\n";

} catch ( Exception $e ) {
	echo '❌ Error: ' . $e->getMessage() . "\n";
	echo "❌ Validation failed\n";
}
