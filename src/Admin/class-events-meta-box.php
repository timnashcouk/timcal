<?php
/**
 * Events Meta Box Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Admin;

/**
 * Handles the admin meta box interface for timcal_events.
 *
 * This class is responsible for rendering and managing the custom meta box
 * that appears on the event edit screen, including all form fields and
 * conditional display logic.
 *
 * @since 0.1.0
 */
class Events_Meta_Box {

	/**
	 * Initialize the events meta box functionality.
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

		// Hook into meta box registration.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Hook into admin script enqueuing.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add meta boxes for timcal_events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		// Guard clause: Only add meta boxes for timcal_events.
		if ( get_current_screen()->post_type !== 'timcal_events' ) {
			return;
		}

		// Guard clause: Check user permissions.
		if ( ! current_user_can( 'edit_timcal_events' ) ) {
			return;
		}

		add_meta_box(
			'timcal_event_details',
			__( 'Event Details', 'timcal' ),
			array( $this, 'render_event_details_meta_box' ),
			'timcal_events',
			'normal',
			'high'
		);
	}

	/**
	 * Render the event details meta box.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_event_details_meta_box( \WP_Post $post ): void {
		// Add nonce field for security.
		wp_nonce_field( 'timcal_save_event_meta', 'timcal_event_meta_nonce' );

		echo '<div class="timcal-meta-box-wrapper">';

		// Duration field.
		$this->render_duration_field( $post->ID );

		// Location fields.
		$this->render_location_fields( $post->ID );

		// Host field.
		$this->render_host_field( $post->ID );

		// Active status field.
		$this->render_active_field( $post->ID );

		echo '</div>';
	}

	/**
	 * Render the duration field.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function render_duration_field( int $post_id ): void {
		$duration = get_post_meta( $post_id, '_timcal_duration', true );
		if ( empty( $duration ) ) {
			$duration = 30; // Default to 30 minutes.
		}

		$duration_options = $this->get_duration_options();

		echo '<div class="timcal-field-group">';
		echo '<label for="timcal_duration">' . esc_html__( 'Duration', 'timcal' ) . '</label>';
		echo '<select id="timcal_duration" name="timcal_duration" class="timcal-duration-select">';

		foreach ( $duration_options as $value => $label ) {
			printf(
				'<option value="%d"%s>%s</option>',
				esc_attr( $value ),
				selected( $duration, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the duration for this event in 5-minute increments.', 'timcal' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the location fields.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function render_location_fields( int $post_id ): void {
		$location_type = get_post_meta( $post_id, '_timcal_location_type', true );
		if ( empty( $location_type ) ) {
			$location_type = 'online'; // Default to online.
		}

		$location_address = get_post_meta( $post_id, '_timcal_location_address', true );

		echo '<div class="timcal-field-group">';
		echo '<label>' . esc_html__( 'Location Type', 'timcal' ) . '</label>';
		echo '<div class="timcal-radio-group">';

		// Online option.
		printf(
			'<label class="timcal-radio-label"><input type="radio" id="timcal_location_online" name="timcal_location_type" value="online"%s> %s</label>',
			checked( $location_type, 'online', false ),
			esc_html__( 'Online', 'timcal' )
		);

		// In Person option.
		printf(
			'<label class="timcal-radio-label"><input type="radio" id="timcal_location_in_person" name="timcal_location_type" value="in_person"%s> %s</label>',
			checked( $location_type, 'in_person', false ),
			esc_html__( 'In Person', 'timcal' )
		);

		echo '</div>';

		// Address field (conditional).
		echo '<div class="timcal-address-field" id="timcal_address_field">';
		echo '<label for="timcal_location_address">' . esc_html__( 'Address', 'timcal' ) . '</label>';
		printf(
			'<textarea id="timcal_location_address" name="timcal_location_address" rows="3" class="large-text" placeholder="%s">%s</textarea>',
			esc_attr__( 'Enter the event address...', 'timcal' ),
			esc_textarea( $location_address )
		);
		echo '<p class="description">' . esc_html__( 'Enter the full address where the event will take place.', 'timcal' ) . '</p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render the host field.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function render_host_field( int $post_id ): void {
		$host_user_id = get_post_meta( $post_id, '_timcal_host_user_id', true );
		if ( empty( $host_user_id ) ) {
			$host_user_id = get_current_user_id(); // Default to current user.
		}

		$users = $this->get_users_for_host_selection();

		echo '<div class="timcal-field-group">';
		echo '<label for="timcal_host_user_id">' . esc_html__( 'Host', 'timcal' ) . '</label>';
		echo '<select id="timcal_host_user_id" name="timcal_host_user_id" class="timcal-host-select">';

		foreach ( $users as $user ) {
			printf(
				'<option value="%d"%s>%s (%s)</option>',
				esc_attr( $user->ID ),
				selected( $host_user_id, $user->ID, false ),
				esc_html( $user->display_name ),
				esc_html( $user->user_email )
			);
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the user who will host this event.', 'timcal' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the active status field.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 */
	private function render_active_field( int $post_id ): void {
		$event_active = get_post_meta( $post_id, '_timcal_event_active', true );
		if ( '' === $event_active ) {
			$event_active = 1; // Default to active.
		}

		echo '<div class="timcal-field-group">';
		echo '<label class="timcal-checkbox-label">';
		printf(
			'<input type="checkbox" id="timcal_event_active" name="timcal_event_active" value="1"%s> %s',
			checked( $event_active, 1, false ),
			esc_html__( 'Active (Available for booking)', 'timcal' )
		);
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Uncheck to make this event unavailable for new bookings.', 'timcal' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Guard clause: Only load on post edit screens.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Guard clause: Only load for timcal_events post type.
		global $post_type;
		if ( 'timcal_events' !== $post_type ) {
			return;
		}

		// Enqueue JavaScript.
		wp_enqueue_script(
			'timcal-events-admin',
			plugin_dir_url( __FILE__ ) . '../assets/js/events-admin.js',
			array( 'jquery' ),
			'0.1.0',
			true
		);

		// Enqueue CSS.
		wp_enqueue_style(
			'timcal-events-admin',
			plugin_dir_url( __FILE__ ) . '../assets/css/events-admin.css',
			array(),
			'0.1.0'
		);

		// Localize script with data.
		wp_localize_script(
			'timcal-events-admin',
			'timcalAdmin',
			array(
				'nonce'   => wp_create_nonce( 'timcal_admin_ajax' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'addressRequired' => __( 'Address is required for in-person events.', 'timcal' ),
				),
			)
		);
	}

	/**
	 * Get duration options for the dropdown.
	 *
	 * @since 0.1.0
	 *
	 * @return array Duration options array.
	 */
	private function get_duration_options(): array {
		$options = array();

		for ( $minutes = 5; $minutes <= 60; $minutes += 5 ) {
			if ( $minutes === 60 ) {
				$hours             = floor( $minutes / 60 );
				$remaining_minutes = $minutes % 60;
				if ( $remaining_minutes > 0 ) {
					/* translators: %1$d: Hours, %2$d: Minutes */
					$label = sprintf( __( '%1$dh %2$dm', 'timcal' ), $hours, $remaining_minutes );
				} else {
					/* translators: %d: Hours */
					$label = sprintf( __( '%dh', 'timcal' ), $hours );
				}
			} else {
				/* translators: %d: Minutes */
				$label = sprintf( __( '%dm', 'timcal' ), $minutes );
			}

			$options[ $minutes ] = $label;
		}

		return $options;
	}

	/**
	 * Get users available for host selection.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of user objects.
	 */
	private function get_users_for_host_selection(): array {
		return get_users(
			array(
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'number'  => 100, // Limit to prevent performance issues.
			)
		);
	}
}
