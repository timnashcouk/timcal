<?php
/**
 * Events List Admin Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Admin;

/**
 * Handles the admin events listing functionality.
 *
 * This class customizes the WordPress admin list table for timcal_events
 * post type, adding custom columns, filters, and enhanced functionality.
 *
 * @since 0.1.0
 */
class Events_List {

	/**
	 * Initialize the events list functionality.
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

		// Hook into WordPress admin list table functionality.
		add_filter( 'manage_timcal_events_posts_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_timcal_events_posts_custom_column', array( $this, 'populate_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-timcal_events_sortable_columns', array( $this, 'add_sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_admin_filters' ) );
		add_filter( 'parse_query', array( $this, 'filter_events_by_custom_filters' ) );
		add_action( 'admin_head', array( $this, 'add_admin_styles' ) );
		add_filter( 'post_row_actions', array( $this, 'modify_row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-timcal_events', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-timcal_events', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'show_bulk_action_notices' ) );

		// Cache invalidation hooks.
		add_action( 'save_post_timcal_events', array( $this, 'invalidate_host_cache' ) );
		add_action( 'delete_post', array( $this, 'invalidate_host_cache_on_delete' ) );
	}

	/**
	 * Add custom columns to the events list table.
	 *
	 * @since 0.1.0
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_custom_columns( array $columns ): array {
		// Remove the date column completely.
		unset( $columns['date'] );

		// Add custom columns.
		$columns['duration']      = __( 'Duration', 'timcal' );
		$columns['location']      = __( 'Location', 'timcal' );
		$columns['host']          = __( 'Host', 'timcal' );
		$columns['active_status'] = __( 'Active', 'timcal' );
		$columns['status']        = __( 'Status', 'timcal' );

		return $columns;
	}

	/**
	 * Populate custom column data.
	 *
	 * @since 0.1.0
	 *
	 * @param string $column_name The column name.
	 * @param int    $post_id     The post ID.
	 * @return void
	 */
	public function populate_custom_columns( string $column_name, int $post_id ): void {
		switch ( $column_name ) {
			case 'duration':
				$this->display_duration( $post_id );
				break;

			case 'location':
				$this->display_location( $post_id );
				break;

			case 'host':
				$this->display_host( $post_id );
				break;

			case 'active_status':
				$this->display_active_status( $post_id );
				break;

			case 'status':
				$this->display_post_status( $post_id );
				break;
		}
	}

	/**
	 * Add sortable columns.
	 *
	 * @since 0.1.0
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function add_sortable_columns( array $columns ): array {
		$columns['duration'] = 'duration';
		$columns['status']   = 'post_status';

		return $columns;
	}

	/**
	 * Add admin filters to the events list.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_admin_filters(): void {
		global $typenow;

		// Guard clause: Only show on timcal_events post type.
		if ( 'timcal_events' !== $typenow ) {
			return;
		}

		// Location type filter.
		$this->render_location_type_filter();

		// Host filter.
		$this->render_host_filter();

		// Active status filter.
		$this->render_active_status_filter();
	}

	/**
	 * Filter events based on custom filters.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function filter_events_by_custom_filters( \WP_Query $query ): void {
		// Guard clause: Only modify admin queries for timcal_events.
		if ( ! is_admin() || ! $query->is_main_query() || 'timcal_events' !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();

		// Filter by location type.
		$location_type = $_GET['location_type'] ?? '';
		if ( ! empty( $location_type ) && 'all' !== $location_type ) {
			$meta_query[] = array(
				'key'     => '_timcal_location_type',
				'value'   => sanitize_text_field( $location_type ),
				'compare' => '=',
			);
		}

		// Filter by host user ID.
		$host_user_id = $_GET['host_user_id'] ?? '';
		if ( ! empty( $host_user_id ) && 'all' !== $host_user_id ) {
			$meta_query[] = array(
				'key'     => '_timcal_host_user_id',
				'value'   => intval( $host_user_id ),
				'compare' => '=',
			);
		}

		// Filter by active status.
		$active_status = $_GET['active_status'] ?? '';
		if ( ! empty( $active_status ) && 'all' !== $active_status ) {
			$active_value = ( 'active' === $active_status ) ? 1 : 0;
			$meta_query[] = array(
				'key'     => '_timcal_event_active',
				'value'   => $active_value,
				'compare' => '=',
			);
		}

		// Apply meta query if we have filters.
		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$query->set( 'meta_query', $meta_query );
		}

		// Handle sorting.
		$orderby = $query->get( 'orderby' );
		if ( 'duration' === $orderby ) {
			$query->set( 'meta_key', '_timcal_duration' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'post_status' === $orderby ) {
			$query->set( 'orderby', 'post_status' );
		}
	}

	/**
	 * Add admin styles for the events list.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_admin_styles(): void {
		global $typenow;

		// Guard clause: Only add styles on timcal_events post type.
		if ( 'timcal_events' !== $typenow ) {
			return;
		}

		echo '<style>
			.column-duration { width: 12%; }
			.column-location { width: 15%; }
			.column-host { width: 15%; }
			.column-active_status { width: 10%; }
			.column-status { width: 10%; }
			.timcal-status-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: bold;
				text-transform: uppercase;
			}
			.timcal-status-active { background: #46b450; color: white; }
			.timcal-status-inactive { background: #8c8f94; color: white; }
			.timcal-post-status-published { background: #46b450; color: white; }
			.timcal-post-status-draft { background: #ffb900; color: white; }
			.timcal-post-status-pending { background: #f56e28; color: white; }
			.timcal-post-status-private { background: #826eb4; color: white; }
			.timcal-location-type { font-weight: 600; }
			.timcal-location-address {
				color: #646970;
				font-style: italic;
				display: block;
				margin-top: 2px;
			}
			.timcal-no-events {
				text-align: center;
				padding: 40px 20px;
				color: #666;
			}
			.timcal-no-events h3 {
				margin-bottom: 10px;
				color: #333;
			}
		</style>';
	}

	/**
	 * Modify row actions for events.
	 *
	 * @since 0.1.0
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post object.
	 * @return array Modified row actions.
	 */
	public function modify_row_actions( array $actions, \WP_Post $post ): array {
		// Guard clause: Only modify for timcal_events.
		if ( 'timcal_events' !== $post->post_type ) {
			return $actions;
		}

		// Add custom actions.
		if ( current_user_can( 'edit_timcal_events' ) ) {
			$actions['duplicate'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?action=duplicate_event&post=' . $post->ID ),
					'duplicate_event_' . $post->ID
				),
				/* translators: %s: Event title */
				esc_attr( sprintf( __( 'Duplicate "%s"', 'timcal' ), $post->post_title ) ),
				__( 'Duplicate', 'timcal' )
			);
		}

		return $actions;
	}

	/**
	 * Add bulk actions.
	 *
	 * @since 0.1.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['mark_active']   = __( 'Mark as Active', 'timcal' );
		$actions['mark_inactive'] = __( 'Mark as Inactive', 'timcal' );
		$actions['export_events'] = __( 'Export Events', 'timcal' );

		return $actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since 0.1.0
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Action being performed.
	 * @param array  $post_ids    Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( string $redirect_to, string $doaction, array $post_ids ): string {
		// Guard clause: No posts selected.
		if ( empty( $post_ids ) ) {
			return $redirect_to;
		}

		$count = 0;

		switch ( $doaction ) {
			case 'mark_active':
			case 'mark_inactive':
				$active_status = ( 'mark_active' === $doaction ) ? 1 : 0;
				foreach ( $post_ids as $post_id ) {
					if ( current_user_can( 'edit_post', $post_id ) ) {
						update_post_meta( $post_id, '_timcal_event_active', $active_status );
						++$count;
					}
				}
				$redirect_to = add_query_arg( 'bulk_active_updated', $count, $redirect_to );
				break;

			case 'export_events':
				$this->export_events( $post_ids );
				$redirect_to = add_query_arg( 'bulk_exported', count( $post_ids ), $redirect_to );
				break;
		}

		return $redirect_to;
	}

	/**
	 * Show bulk action notices.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function show_bulk_action_notices(): void {
		if ( ! empty( $_REQUEST['bulk_active_updated'] ) ) {
			$count = intval( $_REQUEST['bulk_active_updated'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: Number of events */
				esc_html( sprintf( _n( '%d event active status updated.', '%d event active statuses updated.', $count, 'timcal' ), $count ) )
			);
		}

		if ( ! empty( $_REQUEST['bulk_exported'] ) ) {
			$count = intval( $_REQUEST['bulk_exported'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: Number of events */
				esc_html( sprintf( _n( '%d event exported.', '%d events exported.', $count, 'timcal' ), $count ) )
			);
		}
	}

	/**
	 * Display event type column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_event_type( int $post_id ): void {
		$event_type = get_post_meta( $post_id, '_timcal_event_type', true );
		if ( empty( $event_type ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$types = $this->get_event_types();
		echo esc_html( $types[ $event_type ] ?? ucfirst( $event_type ) );
	}

	/**
	 * Display event date column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_event_date( int $post_id ): void {
		$event_date = get_post_meta( $post_id, '_timcal_event_date', true );
		if ( empty( $event_date ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$formatted_date = date_i18n( get_option( 'date_format' ), strtotime( $event_date ) );
		echo esc_html( $formatted_date );
	}

	/**
	 * Display event time column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_event_time( int $post_id ): void {
		$start_time = get_post_meta( $post_id, '_timcal_start_time', true );
		$end_time   = get_post_meta( $post_id, '_timcal_end_time', true );

		if ( empty( $start_time ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$time_format     = get_option( 'time_format' );
		$formatted_start = date_i18n( $time_format, strtotime( $start_time ) );

		if ( ! empty( $end_time ) ) {
			$formatted_end = date_i18n( $time_format, strtotime( $end_time ) );
			echo esc_html( $formatted_start . ' - ' . $formatted_end );
		} else {
			echo esc_html( $formatted_start );
		}
	}

	/**
	 * Display duration column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_duration( int $post_id ): void {
		$duration = get_post_meta( $post_id, '_timcal_duration', true );
		if ( empty( $duration ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$duration_minutes = intval( $duration );
		if ( $duration_minutes >= 60 ) {
			$hours   = floor( $duration_minutes / 60 );
			$minutes = $duration_minutes % 60;
			if ( $minutes > 0 ) {
				/* translators: %1$d: Hours, %2$d: Minutes */
				echo esc_html( sprintf( __( '%1$dh %2$dm', 'timcal' ), $hours, $minutes ) );
			} else {
				/* translators: %d: Hours */
				echo esc_html( sprintf( __( '%dh', 'timcal' ), $hours ) );
			}
		} else {
			/* translators: %d: Minutes */
			echo esc_html( sprintf( __( '%dm', 'timcal' ), $duration_minutes ) );
		}
	}

	/**
	 * Display attendees column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_attendees( int $post_id ): void {
		$max_attendees     = get_post_meta( $post_id, '_timcal_max_attendees', true );
		$current_attendees = get_post_meta( $post_id, '_timcal_current_attendees', true );

		$max_attendees     = intval( $max_attendees );
		$current_attendees = intval( $current_attendees );

		if ( $max_attendees > 0 ) {
			$percentage = $max_attendees > 0 ? ( $current_attendees / $max_attendees ) * 100 : 0;
			$color      = $percentage >= 80 ? '#dc3232' : ( $percentage >= 60 ? '#ffb900' : '#46b450' );

			printf(
				'<span style="color: %s; font-weight: bold;">%d/%d</span>',
				esc_attr( $color ),
				esc_html( (string) $current_attendees ),
				esc_html( (string) $max_attendees )
			);
		} else {
			echo '<span style="color: #999;">' . esc_html__( 'Unlimited', 'timcal' ) . '</span>';
		}
	}

	/**
	 * Display location column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_location( int $post_id ): void {
		$location_type = get_post_meta( $post_id, '_timcal_location_type', true );
		if ( empty( $location_type ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$location_address = get_post_meta( $post_id, '_timcal_location_address', true );

		if ( 'in_person' === $location_type ) {
			echo '<span class="timcal-location-type">' . esc_html__( 'In Person', 'timcal' ) . '</span>';
			if ( ! empty( $location_address ) ) {
				echo '<br><small class="timcal-location-address" title="' . esc_attr( $location_address ) . '">';
				echo esc_html( strlen( $location_address ) > 30 ? substr( $location_address, 0, 30 ) . '...' : $location_address );
				echo '</small>';
			}
		} else {
			echo '<span class="timcal-location-type">' . esc_html__( 'Online', 'timcal' ) . '</span>';
		}
	}

	/**
	 * Display host column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_host( int $post_id ): void {
		$host_user_id = get_post_meta( $post_id, '_timcal_host_user_id', true );
		if ( empty( $host_user_id ) ) {
			echo '<span style="color: #999;">' . esc_html__( 'Not set', 'timcal' ) . '</span>';
			return;
		}

		$user = get_user_by( 'id', intval( $host_user_id ) );
		if ( ! $user ) {
			echo '<span style="color: #dc3232;">' . esc_html__( 'Invalid user', 'timcal' ) . '</span>';
			return;
		}

		printf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_attr( $user->user_email ),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Display active status column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_active_status( int $post_id ): void {
		$event_active = get_post_meta( $post_id, '_timcal_event_active', true );

		// Default to active if not set
		if ( '' === $event_active ) {
			$event_active = 1;
		}

		if ( $event_active ) {
			echo '<span class="timcal-status-badge timcal-status-active" style="background: #46b450; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">';
			echo esc_html__( 'Active', 'timcal' );
			echo '</span>';
		} else {
			echo '<span class="timcal-status-badge timcal-status-inactive" style="background: #8c8f94; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase;">';
			echo esc_html__( 'Inactive', 'timcal' );
			echo '</span>';
		}
	}


	/**
	 * Render location type filter.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function render_location_type_filter(): void {
		$selected = $_GET['location_type'] ?? '';
		$types    = array(
			'online'    => __( 'Online', 'timcal' ),
			'in_person' => __( 'In Person', 'timcal' ),
		);

		echo '<select name="location_type">';
		echo '<option value="all">' . esc_html__( 'All Locations', 'timcal' ) . '</option>';

		foreach ( $types as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Render host filter.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function render_host_filter(): void {
		$selected = $_GET['host_user_id'] ?? '';
		$hosts    = $this->get_cached_event_hosts();

		echo '<select name="host_user_id">';
		echo '<option value="all">' . esc_html__( 'All Hosts', 'timcal' ) . '</option>';

		foreach ( $hosts as $host ) {
			printf(
				'<option value="%d"%s>%s</option>',
				esc_attr( $host['ID'] ),
				selected( $selected, $host['ID'], false ),
				esc_html( $host['display_name'] )
			);
		}

		echo '</select>';
	}

	/**
	 * Render active status filter.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function render_active_status_filter(): void {
		$selected = $_GET['active_status'] ?? '';
		$statuses = array(
			'active'   => __( 'Active', 'timcal' ),
			'inactive' => __( 'Inactive', 'timcal' ),
		);

		echo '<select name="active_status">';
		echo '<option value="all">' . esc_html__( 'All Status', 'timcal' ) . '</option>';

		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}




	/**
	 * Export events to CSV.
	 *
	 * @since 0.1.0
	 *
	 * @param array $post_ids Array of post IDs to export.
	 * @return void
	 */
	private function export_events( array $post_ids ): void {
		// Guard clause: No posts to export.
		if ( empty( $post_ids ) ) {
			return;
		}

		$filename = 'timcal-events-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );

		// CSV headers.
		fputcsv(
			$output,
			array(
				__( 'Title', 'timcal' ),
				__( 'Duration', 'timcal' ),
				__( 'Location Type', 'timcal' ),
				__( 'Location Address', 'timcal' ),
				__( 'Host', 'timcal' ),
				__( 'Active Status', 'timcal' ),
				__( 'Event Date', 'timcal' ),
				__( 'Start Time', 'timcal' ),
				__( 'End Time', 'timcal' ),
				__( 'Description', 'timcal' ),
			)
		);

		// Export data.
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'timcal_events' !== $post->post_type ) {
				continue;
			}

			// Get host information
			$host_user_id = get_post_meta( $post_id, '_timcal_host_user_id', true );
			$host_name    = '';
			if ( $host_user_id ) {
				$user      = get_user_by( 'id', intval( $host_user_id ) );
				$host_name = $user ? $user->display_name : '';
			}

			// Get active status
			$event_active  = get_post_meta( $post_id, '_timcal_event_active', true );
			$active_status = ( '' === $event_active || $event_active ) ? __( 'Active', 'timcal' ) : __( 'Inactive', 'timcal' );

			$row = array(
				$post->post_title,
				get_post_meta( $post_id, '_timcal_duration', true ),
				get_post_meta( $post_id, '_timcal_location_type', true ),
				get_post_meta( $post_id, '_timcal_location_address', true ),
				$host_name,
				$active_status,
				get_post_meta( $post_id, '_timcal_event_date', true ),
				get_post_meta( $post_id, '_timcal_start_time', true ),
				get_post_meta( $post_id, '_timcal_end_time', true ),
				wp_strip_all_tags( $post->post_content ),
			);

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Display post status column.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function display_post_status( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			echo '<span style="color: #999;">' . esc_html__( 'Unknown', 'timcal' ) . '</span>';
			return;
		}

		$status_labels = array(
			'publish' => __( 'Published', 'timcal' ),
			'draft'   => __( 'Draft', 'timcal' ),
			'pending' => __( 'Pending', 'timcal' ),
			'private' => __( 'Private', 'timcal' ),
			'future'  => __( 'Scheduled', 'timcal' ),
			'trash'   => __( 'Trash', 'timcal' ),
		);

		$status_label = $status_labels[ $post->post_status ] ?? ucfirst( $post->post_status );
		$status_class = 'timcal-post-status-' . $post->post_status;

		printf(
			'<span class="timcal-status-badge %s">%s</span>',
			esc_attr( $status_class ),
			esc_html( $status_label )
		);
	}

	/**
	 * Get cached event hosts.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of host users who have events.
	 */
	private function get_cached_event_hosts(): array {
		$cache_key = 'timcal_event_hosts_cache';
		$hosts     = get_transient( $cache_key );

		if ( false === $hosts ) {
			$hosts = $this->generate_event_hosts_list();
			// Cache for 1 hour.
			set_transient( $cache_key, $hosts, HOUR_IN_SECONDS );
		}

		return $hosts;
	}

	/**
	 * Generate list of users who are hosts in events.
	 *
	 * @since 0.1.0
	 *
	 * @return array Array of host users.
	 */
	private function generate_event_hosts_list(): array {
		global $wpdb;

		// Get unique host user IDs from events.
		$host_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status != %s
				AND pm.meta_value != ''
				AND pm.meta_value IS NOT NULL",
				'_timcal_host_user_id',
				'timcal_events',
				'trash'
			)
		);

		if ( empty( $host_ids ) ) {
			return array();
		}

		// Get user details for these IDs.
		$users = get_users(
			array(
				'include' => array_map( 'intval', $host_ids ),
				'fields'  => array( 'ID', 'display_name' ),
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		// Convert to array format.
		$hosts = array();
		foreach ( $users as $user ) {
			$hosts[] = array(
				'ID'           => $user->ID,
				'display_name' => $user->display_name,
			);
		}

		return $hosts;
	}

	/**
	 * Invalidate host cache when events are saved.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_host_cache( int $post_id ): void {
		// Guard clause: Only for timcal_events.
		if ( 'timcal_events' !== get_post_type( $post_id ) ) {
			return;
		}

		delete_transient( 'timcal_event_hosts_cache' );
	}

	/**
	 * Invalidate host cache when posts are deleted.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_host_cache_on_delete( int $post_id ): void {
		// Guard clause: Only for timcal_events.
		if ( 'timcal_events' !== get_post_type( $post_id ) ) {
			return;
		}

		delete_transient( 'timcal_event_hosts_cache' );
	}

	/**
	 * Get available event types.
	 *
	 * @since 0.1.0
	 *
	 * @return array Event types.
	 */
	private function get_event_types(): array {
		return array(
			'meeting'      => __( 'Meeting', 'timcal' ),
			'consultation' => __( 'Consultation', 'timcal' ),
			'workshop'     => __( 'Workshop', 'timcal' ),
			'webinar'      => __( 'Webinar', 'timcal' ),
			'conference'   => __( 'Conference', 'timcal' ),
			'other'        => __( 'Other', 'timcal' ),
		);
	}
}
