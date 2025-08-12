<?php
/**
 * Events Meta Fields Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Admin;

/**
 * Handles validation and saving of custom meta fields for timcal_events.
 *
 * This class is responsible for processing and saving meta field data
 * when events are created or updated, including validation, sanitization,
 * and security checks.
 *
 * @since 0.1.0
 */
class Events_Meta_Fields {

	/**
	 * Initialize the events meta fields functionality.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Guard clause: Only run in admin.
		if ( ! is_admin() ) {
			return;
		}

		// Hook into post save action.
		add_action( 'save_post', array( $this, 'save_event_meta' ), 10, 1 );
	}

	/**
	 * Save event meta fields.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_event_meta( int $post_id ): void {
		// Guard clause: Verify nonce.
		if ( ! isset( $_POST['timcal_event_meta_nonce'] ) ||
			! wp_verify_nonce( $_POST['timcal_event_meta_nonce'], 'timcal_save_event_meta' ) ) {
			return;
		}

		// Guard clause: Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Guard clause: Prevent autosave interference.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Guard clause: Verify post type.
		if ( get_post_type( $post_id ) !== 'timcal_events' ) {
			return;
		}

		// Sanitize and validate meta fields.
		$meta_data = $this->sanitize_meta_fields( $_POST );

		// Save meta fields.
		$this->save_meta_fields( $post_id, $meta_data );
	}

	/**
	 * Sanitize and validate all meta fields.
	 *
	 * @since 0.1.0
	 *
	 * @param array $post_data The POST data array.
	 * @return array Sanitized meta field data.
	 */
	private function sanitize_meta_fields( array $post_data ): array {
		$meta_data = array();

		// Duration field.
		if ( isset( $post_data['timcal_duration'] ) ) {
			$meta_data['_timcal_duration'] = $this->validate_duration( $post_data['timcal_duration'] );
		}

		// Location type field.
		if ( isset( $post_data['timcal_location_type'] ) ) {
			$meta_data['_timcal_location_type'] = $this->validate_location_type( $post_data['timcal_location_type'] );
		}

		// Location address field.
		if ( isset( $post_data['timcal_location_address'] ) ) {
			$meta_data['_timcal_location_address'] = $this->validate_location_address( $post_data['timcal_location_address'] );
		}

		// Host user ID field.
		if ( isset( $post_data['timcal_host_user_id'] ) ) {
			$meta_data['_timcal_host_user_id'] = $this->validate_host_user_id( $post_data['timcal_host_user_id'] );
		}

		// Active status field.
		$meta_data['_timcal_event_active'] = $this->validate_active_status( $post_data['timcal_event_active'] ?? '' );

		return $meta_data;
	}

	/**
	 * Save meta fields to the database.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $meta_data The meta field data to save.
	 * @return void
	 */
	private function save_meta_fields( int $post_id, array $meta_data ): void {
		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}

		// Log successful save for debugging.
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( sprintf( 'TimCal: Meta fields saved for event %d', $post_id ) );
		}
	}

	/**
	 * Validate duration field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $duration The duration value to validate.
	 * @return int Validated duration value.
	 */
	private function validate_duration( string $duration ): int {
		$duration          = intval( $duration );
		$allowed_durations = array( 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60 );

		// Guard clause: Invalid duration.
		if ( ! in_array( $duration, $allowed_durations, true ) ) {
			return 30; // Default to 30 minutes.
		}

		return $duration;
	}

	/**
	 * Validate location type field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $location_type The location type value to validate.
	 * @return string Validated location type value.
	 */
	private function validate_location_type( string $location_type ): string {
		$location_type = sanitize_text_field( $location_type );
		$allowed_types = array( 'online', 'in_person' );

		// Guard clause: Invalid location type.
		if ( ! in_array( $location_type, $allowed_types, true ) ) {
			return 'online'; // Default to online.
		}

		return $location_type;
	}

	/**
	 * Validate location address field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $address The address value to validate.
	 * @return string Validated address value.
	 */
	private function validate_location_address( string $address ): string {
		$address = sanitize_textarea_field( $address );

		// Guard clause: Address too long.
		if ( strlen( $address ) > 500 ) {
			$address = substr( $address, 0, 500 );
		}

		return $address;
	}

	/**
	 * Validate host user ID field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $user_id The user ID value to validate.
	 * @return int Validated user ID value.
	 */
	private function validate_host_user_id( string $user_id ): int {
		$user_id = intval( $user_id );

		// Guard clause: Invalid user ID.
		if ( $user_id <= 0 ) {
			return get_current_user_id();
		}

		// Verify user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return get_current_user_id();
		}

		// Verify user has appropriate capabilities.
		if ( ! user_can( $user_id, 'read' ) ) {
			return get_current_user_id();
		}

		return $user_id;
	}

	/**
	 * Validate active status field.
	 *
	 * @since 0.1.0
	 *
	 * @param string $active The active status value to validate.
	 * @return int Validated active status value (1 or 0).
	 */
	private function validate_active_status( string $active ): int {
		// Handle checkbox values.
		return in_array( $active, array( '1', 'on', 'true' ), true ) ? 1 : 0;
	}

	/**
	 * Get default meta values for new events.
	 *
	 * @since 0.1.0
	 *
	 * @return array Default meta field values.
	 */
	public function get_default_meta_values(): array {
		return array(
			'_timcal_duration'         => 30,
			'_timcal_location_type'    => 'online',
			'_timcal_location_address' => '',
			'_timcal_host_user_id'     => get_current_user_id(),
			'_timcal_event_active'     => 1,
		);
	}

	/**
	 * Set default meta values for new events.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	public function set_default_meta_values( int $post_id ): void {
		// Guard clause: Only set defaults for new posts.
		if ( get_post_status( $post_id ) !== 'auto-draft' ) {
			return;
		}

		// Guard clause: Verify post type.
		if ( get_post_type( $post_id ) !== 'timcal_events' ) {
			return;
		}

		$defaults = $this->get_default_meta_values();

		foreach ( $defaults as $meta_key => $meta_value ) {
			// Only set if meta doesn't already exist.
			if ( ! metadata_exists( 'post', $post_id, $meta_key ) ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
	}

	/**
	 * Validate meta field data before saving.
	 *
	 * @since 0.1.0
	 *
	 * @param array $meta_data The meta field data to validate.
	 * @return array Validation results with errors if any.
	 */
	public function validate_meta_data( array $meta_data ): array {
		$errors = array();

		// Validate duration.
		if ( isset( $meta_data['_timcal_duration'] ) ) {
			$duration          = intval( $meta_data['_timcal_duration'] );
			$allowed_durations = array( 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60 );

			if ( ! in_array( $duration, $allowed_durations, true ) ) {
				$errors['duration'] = __( 'Invalid duration. Please select a value between 5 and 60 minutes in 5-minute increments.', 'timcal' );
			}
		}

		// Validate location type.
		if ( isset( $meta_data['_timcal_location_type'] ) ) {
			$location_type = $meta_data['_timcal_location_type'];
			$allowed_types = array( 'online', 'in_person' );

			if ( ! in_array( $location_type, $allowed_types, true ) ) {
				$errors['location_type'] = __( 'Invalid location type. Please select either Online or In Person.', 'timcal' );
			}
		}

		// Validate address for in-person events.
		if ( isset( $meta_data['_timcal_location_type'] ) &&
			$meta_data['_timcal_location_type'] === 'in_person' &&
			isset( $meta_data['_timcal_location_address'] ) &&
			empty( trim( $meta_data['_timcal_location_address'] ) ) ) {
			$errors['location_address'] = __( 'Address is required for in-person events.', 'timcal' );
		}

		// Validate host user ID.
		if ( isset( $meta_data['_timcal_host_user_id'] ) ) {
			$user_id = intval( $meta_data['_timcal_host_user_id'] );
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				$errors['host_user_id'] = __( 'Invalid host user selected.', 'timcal' );
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}
}
