<?php
/**
 * Handles the "Load more" AJAX request for paginated [piecal_layouts] output.
 *
 * The button (see PCL_Render::load_more_button) posts the query/render
 * attributes and the next page number. We re-sanitize the attributes through
 * the shortcode's own normalizer — the client's values are never trusted —
 * fetch that page, and return just the item markup for the JS to append.
 *
 * No nonce is used: this only returns published, public event markup (the same
 * data the page already shows), so there is nothing to protect against CSRF,
 * and requiring a nonce would break the button on full-page-cached sites where
 * the served nonce can be stale. Tampering is contained by re-sanitizing every
 * attribute and capping the page size (MAX_PER_PAGE).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Ajax {

	/**
	 * Hard cap on page size for AJAX requests, independent of what the client
	 * sends, so a tampered request can't ask for an unbounded batch.
	 */
	const MAX_PER_PAGE = 50;

	public function __construct() {
		add_action( 'wp_ajax_pcl_load_more', array( $this, 'load_more' ) );
		add_action( 'wp_ajax_nopriv_pcl_load_more', array( $this, 'load_more' ) );
	}

	/**
	 * Return the next page of occurrences as item markup + whether more remain.
	 */
	public function load_more() {
		$raw = array();
		if ( isset( $_POST['atts'] ) ) {
			$decoded = json_decode( sanitize_textarea_field( wp_unslash( $_POST['atts'] ) ), true );
			if ( is_array( $decoded ) ) {
				$raw = $decoded;
			}
		}

		// Re-sanitize every attribute through the shortcode normalizer; the
		// posted values are treated as untrusted input.
		$atts = PCL_Shortcode::normalize_atts( $raw );

		$page     = isset( $_POST['page'] ) ? max( 2, intval( wp_unslash( $_POST['page'] ) ) ) : 2;
		$per_page = min( self::MAX_PER_PAGE, max( 1, intval( $atts['limit'] ) ) );

		$result = PCL_Query::get_events_page(
			array(
				'post_type' => $atts['post_type'],
				'post_id'   => $atts['post_id'],
				'time'      => $atts['time'],
				'order'     => $atts['order'],
				'per_page'  => $per_page,
				'page'      => $page,
			)
		);

		wp_send_json_success(
			array(
				'html'     => PCL_Render::render_items( $result['events'], $atts ),
				'has_more' => (bool) $result['has_more'],
				'page'     => $page,
			)
		);
	}
}
