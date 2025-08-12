<?php
/**
 * Meta Field Manager Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Core;

/**
 * Handles registration and management of custom meta fields for timcal_events.
 *
 * This class is responsible for registering all custom meta fields used by
 * the timcal_events post type, including validation callbacks and schema
 * definitions.
 *
 * @since 0.1.0
 */
class Meta_Field_Manager {

	/**
	 * Initialize the meta field manager.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Guard clause: Only run when WordPress is loaded.
		if ( ! function_exists( 'register_meta' ) ) {
			return;
		}

		// Register meta fields on init hook.
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Register all custom meta fields for timcal_events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_meta_fields(): void {
		$this->register_duration_meta();
		$this->register_location_meta();
		$this->register_host_meta();
		$this->register_active_meta();
	}

	/**
	 * Get the complete meta field schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array Meta field schema definitions.
	 */
	public function get_meta_field_schema(): array {
		return array(
			'_timcal_duration'         => array(
				'type'        => 'integer',
				'description' => __( 'Event duration in minutes', 'timcal' ),
				'default'     => 30,
				'minimum'     => 5,
				'maximum'     => 60,
			),
			'_timcal_location_type'    => array(
				'type'        => 'string',
				'description' => __( 'Event location type', 'timcal' ),
				'default'     => 'online',
				'enum'        => array( 'online', 'in_person' ),
			),
			'_timcal_location_address' => array(
				'type'        => 'string',
				'description' => __( 'Event location address', 'timcal' ),
				'default'     => '',
				'maxLength'   => 500,
			),
			'_timcal_host_user_id'     => array(
				'type'        => 'integer',
				'description' => __( 'Event host user ID', 'timcal' ),
				'default'     => 0,
				'minimum'     => 1,
			),
			'_timcal_event_active'     => array(
				'type'        => 'boolean',
				'description' => __( 'Whether event is active for booking', 'timcal' ),
				'default'     => true,
			),
		);
	}

	/**
	 * Register duration meta field.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_duration_meta(): void {
		register_meta(
			'post',
			'_timcal_duration',
			array(
				'object_subtype'    => 'timcal_events',
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_duration' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
				'show_in_rest'      => false,
				'default'           => 30,
			)
		);
	}

	/**
	 * Register location meta fields.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_location_meta(): void {
		// Location type field.
		register_meta(
			'post',
			'_timcal_location_type',
			array(
				'object_subtype'    => 'timcal_events',
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_location_type' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
				'show_in_rest'      => false,
				'default'           => 'online',
			)
		);

		// Location address field.
		register_meta(
			'post',
			'_timcal_location_address',
			array(
				'object_subtype'    => 'timcal_events',
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_location_address' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
				'show_in_rest'      => false,
				'default'           => '',
			)
		);
	}

	/**
	 * Register host meta field.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_host_meta(): void {
		register_meta(
			'post',
			'_timcal_host_user_id',
			array(
				'object_subtype'    => 'timcal_events',
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_host_user_id' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
				'show_in_rest'      => false,
				'default'           => 0,
			)
		);
	}

	/**
	 * Register active status meta field.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function register_active_meta(): void {
		register_meta(
			'post',
			'_timcal_event_active',
			array(
				'object_subtype'    => 'timcal_events',
				'type'              => 'boolean',
				'single'            => true,
				'sanitize_callback' => array( $this, 'sanitize_active_status' ),
				'auth_callback'     => array( $this, 'auth_meta_callback' ),
				'show_in_rest'      => false,
				'default'           => true,
			)
		);
	}

	/**
	 * Sanitize duration value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $duration The duration value to sanitize.
	 * @return int Sanitized duration value.
	 */
	public function sanitize_duration( $duration ): int {
		$duration          = intval( $duration );
		$allowed_durations = array( 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60 );

		// Guard clause: Invalid duration.
		if ( ! in_array( $duration, $allowed_durations, true ) ) {
			return 30; // Default to 30 minutes.
		}

		return $duration;
	}

	/**
	 * Sanitize location type value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $location_type The location type value to sanitize.
	 * @return string Sanitized location type value.
	 */
	public function sanitize_location_type( $location_type ): string {
		$location_type = sanitize_text_field( (string) $location_type );
		$allowed_types = array( 'online', 'in_person' );

		// Guard clause: Invalid location type.
		if ( ! in_array( $location_type, $allowed_types, true ) ) {
			return 'online'; // Default to online.
		}

		return $location_type;
	}

	/**
	 * Sanitize location address value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $address The address value to sanitize.
	 * @return string Sanitized address value.
	 */
	public function sanitize_location_address( $address ): string {
		$address = sanitize_textarea_field( (string) $address );

		// Guard clause: Address too long.
		if ( strlen( $address ) > 500 ) {
			$address = substr( $address, 0, 500 );
		}

		return $address;
	}

	/**
	 * Sanitize host user ID value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $user_id The user ID value to sanitize.
	 * @return int Sanitized user ID value.
	 */
	public function sanitize_host_user_id( $user_id ): int {
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

		return $user_id;
	}

	/**
	 * Sanitize active status value.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $active The active status value to sanitize.
	 * @return bool Sanitized active status value.
	 */
	public function sanitize_active_status( $active ): bool {
		// Handle various truthy values.
		if ( is_string( $active ) ) {
			return in_array( strtolower( $active ), array( '1', 'on', 'true', 'yes' ), true );
		}

		return (bool) $active;
	}

	/**
	 * Authorization callback for meta fields.
	 *
	 * @since 0.1.0
	 *
	 * @param bool   $allowed   Whether the user can edit the meta field.
	 * @param string $meta_key  The meta key being checked.
	 * @param int    $object_id The object ID.
	 * @param int    $user_id   The user ID.
	 * @param string $cap       The capability being checked.
	 * @param array  $caps      The capabilities required.
	 * @return bool Whether the user can edit the meta field.
	 */
	public function auth_meta_callback( bool $allowed, string $meta_key, int $object_id, int $user_id, string $cap, array $caps ): bool {
		// Guard clause: Check if user can edit timcal events.
		if ( ! user_can( $user_id, 'edit_timcal_events' ) ) {
			return false;
		}

		// Guard clause: Check if user can edit this specific post.
		if ( ! user_can( $user_id, 'edit_post', $object_id ) ) {
			return false;
		}

		return true;
	}
}
