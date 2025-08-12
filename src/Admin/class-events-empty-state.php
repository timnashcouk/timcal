<?php
/**
 * Events Empty State Admin Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal\Admin;

/**
 * Handles the empty state messaging for the events listing.
 *
 * This class provides enhanced messaging and guidance when no events
 * are found in the admin listing, improving the user experience.
 *
 * @since 0.1.0
 */
class Events_Empty_State {

	/**
	 * Initialize the empty state functionality.
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

		// Hook into WordPress admin functionality.
		add_action( 'admin_footer', array( $this, 'add_empty_state_script' ) );
		add_filter( 'views_edit-timcal_events', array( $this, 'modify_views_for_empty_state' ) );
		add_action( 'admin_notices', array( $this, 'show_getting_started_notice' ) );
	}

	/**
	 * Add JavaScript to handle empty state messaging.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function add_empty_state_script(): void {
		global $typenow;

		// Guard clause: Only add script on timcal_events post type.
		if ( 'timcal_events' !== $typenow ) {
			return;
		}

		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Check if the events table is empty
			var $table = $('.wp-list-table');
			var $tbody = $table.find('tbody#the-list');
			
			if ($tbody.length && $tbody.find('tr').length === 0) {
				// Hide the table and show custom empty state
				$table.hide();
				$('.tablenav.top').hide();
				$('.tablenav.bottom').hide();
				
				// Insert custom empty state HTML
				var emptyStateHtml = <?php echo wp_json_encode( $this->get_empty_state_html() ); ?>;
				$table.after(emptyStateHtml);
			} else if ($tbody.length && $tbody.find('tr.no-items').length > 0) {
				// Replace the default "No items found" message
				var $noItemsRow = $tbody.find('tr.no-items');
				var colCount = $noItemsRow.find('td').attr('colspan') || 1;
				
				var customMessage = <?php echo wp_json_encode( $this->get_no_items_message() ); ?>;
				$noItemsRow.find('td').html(customMessage);
			}
			
			// Handle filtered empty state
			var urlParams = new URLSearchParams(window.location.search);
			var hasFilters = urlParams.get('event_type') || urlParams.get('date_from') || 
							urlParams.get('date_to') || urlParams.get('booking_status') || 
							urlParams.get('s');
			
			if (hasFilters && $tbody.length && ($tbody.find('tr').length === 0 || $tbody.find('tr.no-items').length > 0)) {
				// Show filtered empty state
				var filteredEmptyHtml = <?php echo wp_json_encode( $this->get_filtered_empty_state_html() ); ?>;
				
				if ($tbody.find('tr').length === 0) {
					$table.after(filteredEmptyHtml);
					$table.hide();
				} else {
					$tbody.find('tr.no-items td').html(filteredEmptyHtml);
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * Modify views for empty state.
	 *
	 * @since 0.1.0
	 *
	 * @param array $views Existing views.
	 * @return array Modified views.
	 */
	public function modify_views_for_empty_state( array $views ): array {
		global $wp_query;

		// Check if we have any events at all.
		$total_events = wp_count_posts( 'timcal_events' );
		$total_count  = array_sum( (array) $total_events );

		// If no events exist, modify the "All" view.
		if ( 0 === $total_count && isset( $views['all'] ) ) {
			$views['all'] = sprintf(
				'<a href="%s" class="current" aria-current="page">%s <span class="count">(%d)</span></a>',
				admin_url( 'edit.php?post_type=timcal_events' ),
				__( 'All', 'timcal' ),
				0
			);
		}

		return $views;
	}

	/**
	 * Show getting started notice for new installations.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function show_getting_started_notice(): void {
		global $typenow;

		// Guard clause: Only show on timcal_events post type.
		if ( 'timcal_events' !== $typenow ) {
			return;
		}

		// Check if this is a new installation with no events.
		$total_events = wp_count_posts( 'timcal_events' );
		$total_count  = array_sum( (array) $total_events );

		// Guard clause: Events already exist.
		if ( $total_count > 0 ) {
			return;
		}

		// Check if notice was dismissed.
		$dismissed = get_user_meta( get_current_user_id(), 'timcal_getting_started_dismissed', true );
		if ( $dismissed ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible timcal-getting-started-notice">
			<h3><?php esc_html_e( 'Welcome to TimCal Events!', 'timcal' ); ?></h3>
			<p><?php esc_html_e( 'Get started by creating your first event. Events help you organize and manage your calendar bookings effectively.', 'timcal' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=timcal_events' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Your First Event', 'timcal' ); ?>
				</a>
				<a href="#" class="button button-secondary timcal-dismiss-notice" data-notice="getting_started">
					<?php esc_html_e( 'Dismiss', 'timcal' ); ?>
				</a>
			</p>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.timcal-dismiss-notice').on('click', function(e) {
				e.preventDefault();
				var notice = $(this).data('notice');
				
				$.post(ajaxurl, {
					action: 'timcal_dismiss_notice',
					notice: notice,
					nonce: '<?php echo esc_js( wp_create_nonce( 'timcal_dismiss_notice' ) ); ?>'
				});
				
				$(this).closest('.notice').fadeOut();
			});
		});
		</script>
		<?php
	}

	/**
	 * Get empty state HTML.
	 *
	 * @since 0.1.0
	 *
	 * @return string Empty state HTML.
	 */
	private function get_empty_state_html(): string {
		$create_url = admin_url( 'post-new.php?post_type=timcal_events' );

		return sprintf(
			'<div class="timcal-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #c3c4c7; margin-top: 20px;">
				<div style="max-width: 400px; margin: 0 auto;">
					<div style="font-size: 48px; color: #dcdcde; margin-bottom: 20px;">📅</div>
					<h2 style="color: #1d2327; margin-bottom: 16px;">%s</h2>
					<p style="color: #646970; margin-bottom: 24px; line-height: 1.5;">%s</p>
					<a href="%s" class="button button-primary button-large">%s</a>
					<div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #dcdcde;">
						<h4 style="color: #1d2327; margin-bottom: 12px;">%s</h4>
						<ul style="text-align: left; color: #646970; line-height: 1.6;">
							<li>%s</li>
							<li>%s</li>
							<li>%s</li>
							<li>%s</li>
						</ul>
					</div>
				</div>
			</div>',
			esc_html__( 'No events yet', 'timcal' ),
			esc_html__( 'Events help you organize and manage your calendar bookings. Create your first event to get started with TimCal.', 'timcal' ),
			esc_url( $create_url ),
			esc_html__( 'Create Your First Event', 'timcal' ),
			esc_html__( 'What you can do with events:', 'timcal' ),
			esc_html__( 'Set up meeting types and durations', 'timcal' ),
			esc_html__( 'Define availability and booking rules', 'timcal' ),
			esc_html__( 'Manage attendee limits and requirements', 'timcal' ),
			esc_html__( 'Track booking status and history', 'timcal' )
		);
	}

	/**
	 * Get no items message for table.
	 *
	 * @since 0.1.0
	 *
	 * @return string No items message HTML.
	 */
	private function get_no_items_message(): string {
		$create_url = admin_url( 'post-new.php?post_type=timcal_events' );

		return sprintf(
			'<div style="padding: 40px 20px; text-align: center;">
				<div style="font-size: 24px; margin-bottom: 12px;">📅</div>
				<h3 style="margin-bottom: 8px; color: #1d2327;">%s</h3>
				<p style="color: #646970; margin-bottom: 20px;">%s</p>
				<a href="%s" class="button button-primary">%s</a>
			</div>',
			esc_html__( 'No events found', 'timcal' ),
			esc_html__( 'Create your first event to start managing your calendar bookings.', 'timcal' ),
			esc_url( $create_url ),
			esc_html__( 'Create Event', 'timcal' )
		);
	}

	/**
	 * Get filtered empty state HTML.
	 *
	 * @since 0.1.0
	 *
	 * @return string Filtered empty state HTML.
	 */
	private function get_filtered_empty_state_html(): string {
		$clear_filters_url = admin_url( 'edit.php?post_type=timcal_events' );

		return sprintf(
			'<div style="padding: 40px 20px; text-align: center;">
				<div style="font-size: 24px; margin-bottom: 12px;">🔍</div>
				<h3 style="margin-bottom: 8px; color: #1d2327;">%s</h3>
				<p style="color: #646970; margin-bottom: 20px;">%s</p>
				<a href="%s" class="button button-secondary">%s</a>
			</div>',
			esc_html__( 'No events match your filters', 'timcal' ),
			esc_html__( 'Try adjusting your search criteria or clear the filters to see all events.', 'timcal' ),
			esc_url( $clear_filters_url ),
			esc_html__( 'Clear Filters', 'timcal' )
		);
	}

	/**
	 * Handle AJAX notice dismissal.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function handle_notice_dismissal(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'timcal_dismiss_notice' ) ) {
			wp_die( 'Security check failed' );
		}

		$notice = sanitize_text_field( $_POST['notice'] ?? '' );

		if ( 'getting_started' === $notice ) {
			update_user_meta( get_current_user_id(), 'timcal_getting_started_dismissed', true );
		}

		wp_die(); // This is required to terminate immediately and return a proper response.
	}
}