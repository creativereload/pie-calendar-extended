<?php
/**
 * Adds Category / Tag filter controls to Pie Calendar's native front-end
 * (FullCalendar) calendar.
 *
 * This is the former standalone "Pie Calendar Filter" add-on, folded into
 * Pie Calendar Extended. It hooks into Pie Calendar rather than modifying it:
 *
 *   - piecal_event_array_filter        attaches each event's taxonomy terms
 *   - piecal_calendar_object_properties injects an eventsSet callback so the
 *                                       active filter re-applies on navigation
 *                                       and lazy recurring-occurrence loads
 *
 * The dropdowns themselves are built client-side in
 * assets/js/pie-calendar-filter.js and inserted into the `.piecal-controls`
 * bar. Only terms that actually appear on the calendar's events are listed.
 *
 * Note: this filters Pie Calendar's own FullCalendar widget. It does NOT
 * filter the [piecal_layouts] list/compact/column output, which is rendered
 * separately by PCL_Render.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCE_Filter {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'piecal_render_calendar', array( $this, 'enqueue_assets' ) );
		add_filter( 'piecal_event_array_filter', array( $this, 'add_event_filter_data' ) );
		add_filter( 'piecal_calendar_object_properties', array( $this, 'add_calendar_props' ), 10, 1 );
	}

	/**
	 * Register (but don't yet enqueue) the front-end assets. They are only
	 * enqueued when a Pie Calendar is actually rendered, via enqueue_assets().
	 */
	public function register_assets() {
		wp_register_style(
			'pie-calendar-filter',
			PCE_URL . 'assets/css/pie-calendar-filter.css',
			array(),
			PCE_VERSION
		);

		wp_register_script(
			'pie-calendar-filter',
			PCE_URL . 'assets/js/pie-calendar-filter.js',
			array(),
			PCE_VERSION,
			true
		);

		// Translatable UI strings passed to the front-end script.
		wp_localize_script(
			'pie-calendar-filter',
			'PieCalendarFilterData',
			array(
				'strings' => array(
					'filterByCategory' => __( 'Filter by Category', 'pie-calendar-extended' ),
					'allCategories'    => __( 'All Categories', 'pie-calendar-extended' ),
					'filterByTag'      => __( 'Filter by Tag', 'pie-calendar-extended' ),
					'allTags'          => __( 'All Tags', 'pie-calendar-extended' ),
					'resetFilters'     => __( 'Reset Filters', 'pie-calendar-extended' ),
				),
			)
		);
	}

	/**
	 * Enqueue the assets only when a Pie Calendar shortcode is being rendered.
	 * piecal_render_calendar fires at the top of Pie Calendar's shortcode render.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'pie-calendar-filter' );
		wp_enqueue_script( 'pie-calendar-filter' );
	}

	/**
	 * Attach taxonomy terms to every event so the front-end script can build
	 * the filter dropdowns and match events against selections.
	 *
	 * These extra keys land in FullCalendar's event.extendedProps. Recurring
	 * occurrences clone their parent event, so they inherit this data too.
	 *
	 * @param array $event The event array being built by Pie Calendar.
	 * @return array
	 */
	public function add_event_filter_data( $event ) {
		$post_id = isset( $event['postId'] ) ? $event['postId'] : null;

		if ( ! $post_id ) {
			return $event;
		}

		$event['pcfCategories'] = $this->terms_for( $post_id, apply_filters( 'pcf_category_taxonomy', 'category' ) );
		$event['pcfTags']       = $this->terms_for( $post_id, apply_filters( 'pcf_tag_taxonomy', 'post_tag' ) );

		return $event;
	}

	/**
	 * Build a list of {slug, name} pairs for a post's terms in a taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return array
	 */
	private function terms_for( $post_id, $taxonomy ) {
		$out   = array();
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[] = array(
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}

		return $out;
	}

	/**
	 * Inject an eventsSet callback into the FullCalendar config object.
	 *
	 * This re-applies the active filter whenever the event data changes (initial
	 * load, month navigation, or lazy expansion of recurring occurrences). The
	 * strings returned here are echoed verbatim as calendar object properties by
	 * Pie Calendar, so this must be a valid `key: value,` JS property.
	 *
	 * @param array $props Existing custom calendar object property strings.
	 * @return array
	 */
	public function add_calendar_props( $props ) {
		$props[] = "eventsSet: function() { if ( typeof window.piecalFilterApply === 'function' ) { window.piecalFilterApply(); } },";

		return $props;
	}
}
