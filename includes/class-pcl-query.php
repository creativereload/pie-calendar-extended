<?php
/**
 * Handles fetching Pie Calendar events from the database.
 *
 * Pie Calendar stores events as regular posts (any post type) with these
 * meta fields:
 *   _piecal_is_event   - "1" when the "Show on Calendar" toggle is enabled
 *   _piecal_start_date - e.g. 2026-08-14T18:00:00
 *   _piecal_end_date   - optional, same format
 *   _piecal_is_allday  - "1" or "0"
 *   _piecal_rsets      - JSON array of recurrence entries, each one of:
 *                          RDATE  - a single extra occurrence date, e.g.
 *                                   {"type":"RDATE","RDATE":{"date":"...",
 *                                   "dateEnd":null,"setExplicitTime":false}}.
 *                                   If setExplicitTime is falsy, the
 *                                   occurrence uses the base event's own
 *                                   start/end time instead of the date's
 *                                   embedded time. dateEnd, if set, is used
 *                                   as the occurrence's end instead of the
 *                                   base event's duration.
 *                          RRULE  - a repeating rule, e.g.
 *                                   {"type":"RRULE","RRULE":{"freq":"WEEKLY",
 *                                   "interval":"2","exact":0,"until":"..."}}.
 *                                   freq is DAILY/WEEKLY/MONTHLY/YEARLY.
 *                                   "exact" (MONTHLY only) means "same
 *                                   weekday & position" (e.g. "3rd Tuesday")
 *                                   instead of "same day of month".
 *                          EXDATE - a blackout date/range to exclude from
 *                                   the expanded occurrences, e.g.
 *                                   {"type":"EXDATE","EXDATE":{"date":"...",
 *                                   "endDate":""}}. Note the field is
 *                                   "endDate" here, not "dateEnd" like
 *                                   RDATE uses — an inconsistency in Pie
 *                                   Calendar's own data, not a typo here.
 *                        Source: Pie Calendar Pro's own
 *                        includes/metabox-pro.php (classic editor metabox),
 *                        confirmed against this exact JSON shape.
 *
 * There's also a legacy recurrence path predating _piecal_rsets:
 * _piecal_is_recurring (bool) plus _piecal_recurring_frequency/_interval/
 * _exact_position/_end. Saving an event through Pie Calendar Pro's metabox
 * migrates this into an RRULE-type rset entry and resets _piecal_is_recurring
 * to false, but an event that hasn't been re-saved since could still be in
 * this old shape, so we check both. Confirmed against Pie Calendar Pro's own
 * includes/piecal-pro.php, which builds an equivalent RRULE array
 * (freq/interval/dtstart/until, plus a computed BYDAY for the "exact
 * position" monthly case) from these same fields.
 *
 * Blackout dates are a separate, *global* feature (Pie Calendar Pro's
 * "Blackout Dates" admin setting), stored as the `piecal_blackout_dates`
 * option — not per-event. Confirmed against
 * Piecal\Pro\BlackoutDates::injectBlackoutEntries(). Entries are shaped like
 * rset entries but only ever type EXRULE (a recurring blackout — freq/
 * interval/dtstart/until/exact) or EXDATE (date/endDate). These apply to
 * every event, not just ones with their own rset.
 *
 * Sites can remap the start/end meta keys via the `piecal_start_date_meta_key`
 * and `piecal_end_date_meta_key` filters (see Pie Calendar's own docs on using
 * custom fields for date/time). We respect those filters so this add-on keeps
 * working on customized installs. We also run queries through the
 * `piecal_event_query_args` filter for the same reason.
 *
 * RRULE/EXRULE date generation uses Pie Calendar Pro's own bundled
 * php-rrule library (vendor/php-rrule/RRule.php, the RRule\RRule class)
 * when it's available, via expand_rrule_dates_via_library() — ported
 * directly from Piecal\Utils\RRuleUtil::expandOccurrencesWithinRange()'s
 * RRULE construction (DTSTART/FREQ/INTERVAL/UNTIL/BYDAY), so this matches
 * Pie Calendar's own expansion exactly rather than approximating it. Falls
 * back to our own simpler implementation (expand_rrule_dates_fallback())
 * only if that library can't be found — see its docblock for that caveat.
 *
 * Caveat: the RDATE expansion (date/dateEnd/setExplicitTime) has been
 * verified against a real recurring event's live output.
 *
 * Caveat: blackout handling here only excludes an occurrence outright if
 * its start falls in the blackout range. Pie Calendar Pro's real blackout
 * handling (`Piecal\Pro\BlackoutDates::splitMultiDayEventsAroundBlackouts`)
 * can also *split* a multi-day event around a blackout instead of dropping
 * it — that splitting isn't implemented here, since none of the events we
 * could inspect are multi-day.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Query {

	/**
	 * Safety cap on how many extra occurrences a single RRULE entry can
	 * generate, in case "until" is missing/malformed and would otherwise
	 * recur indefinitely.
	 */
	const MAX_RRULE_OCCURRENCES = 366;

	/**
	 * Fetch events matching the given (already-sanitized) options, with
	 * recurring occurrences expanded into their own entries.
	 *
	 * @param array $options {
	 *     @type string $post_type   Post type slug, or '' for any.
	 *     @type int    $post_id     Restrict to a single event's own occurrences
	 *                               (its base date plus any recurrences), or 0
	 *                               for all matching events. Overrides post_type.
	 *     @type int    $limit       Number of occurrences to return. -1 for all.
	 *     @type string $time        'upcoming' | 'past' | 'all'.
	 *     @type string $order       'ASC' | 'DESC'.
	 * }
	 * @return WP_Post[] Array of post objects (one per occurrence — a
	 *                    recurring event's post may appear more than once),
	 *                    each with a `pcl_start`, `pcl_end`, and `pcl_all_day`
	 *                    property attached for convenience.
	 */
	public static function get_events( $options = array() ) {
		$defaults = array(
			'post_type' => '',
			'post_id'   => 0,
			'limit'     => 10,
			'time'      => 'upcoming',
			'order'     => 'ASC',
		);
		$options = wp_parse_args( $options, $defaults );

		$start_meta_key = apply_filters( 'piecal_start_date_meta_key', '_piecal_start_date' );
		$end_meta_key   = apply_filters( 'piecal_end_date_meta_key', '_piecal_end_date' );
		$rsets_meta_key = apply_filters( 'piecal_rsets_meta_key', '_piecal_rsets' );

		// We can't filter "upcoming"/"past" at the query level any more:
		// a recurring event's base date might be in the past while it still
		// has future extra dates (or vice versa), so every occurrence has to
		// be expanded first and then filtered individually.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_piecal_is_event',
				'value'   => '1',
				'compare' => '=',
			),
			array(
				'key'     => $start_meta_key,
				'value'   => '',
				'compare' => '!=',
			),
		);

		$query_args = array(
			'post_type'      => ! empty( $options['post_type'] ) ? $options['post_type'] : 'any',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'no_found_rows'  => true,
		);

		if ( ! empty( $options['post_id'] ) ) {
			// A specific post ID is unambiguous on its own — ignore any
			// post_type filter so this works regardless of the event's type.
			$query_args['post_type'] = 'any';
			$query_args['p']         = intval( $options['post_id'] );
		}

		// Let Pie Calendar / site customizations adjust the query the same
		// way they would for the native [piecal] shortcode.
		$query_args = apply_filters( 'piecal_event_query_args', $query_args, $options );

		$query = new WP_Query( $query_args );

		// Computed once and reused for every event — blackout dates are
		// global, not per-event.
		$global_exclusions = self::get_global_blackout_exclusions();

		$occurrences = array();
		foreach ( $query->posts as $post ) {
			$start = get_post_meta( $post->ID, $start_meta_key, true );
			$end   = get_post_meta( $post->ID, $end_meta_key, true );

			if ( empty( $start ) ) {
				continue;
			}

			$all_day = (bool) get_post_meta( $post->ID, '_piecal_is_allday', true );

			$event_occurrences   = array();
			$event_occurrences[] = self::build_occurrence( $post, $start, $end, $all_day );

			$rsets_raw = get_post_meta( $post->ID, $rsets_meta_key, true );
			$rsets     = json_decode( (string) $rsets_raw, true );

			$exclusions = $global_exclusions;

			if ( is_array( $rsets ) ) {
				// EXDATE entries only mark blackout ranges — collect them
				// first so they can be applied to RDATE/RRULE results below.
				foreach ( $rsets as $rset ) {
					if ( ( $rset['type'] ?? '' ) === 'EXDATE' ) {
						$exclusion = self::parse_exdate_range( $rset['EXDATE'] ?? array() );
						if ( $exclusion ) {
							$exclusions[] = $exclusion;
						}
					}
				}

				foreach ( $rsets as $rset ) {
					$type = $rset['type'] ?? '';

					if ( 'RDATE' === $type ) {
						$occurrence = self::build_rdate_occurrence( $post, $start, $end, $rset['RDATE'] ?? array(), $all_day );
						if ( $occurrence ) {
							$event_occurrences[] = $occurrence;
						}
					} elseif ( 'RRULE' === $type ) {
						$event_occurrences = array_merge(
							$event_occurrences,
							self::build_rrule_occurrences( $post, $start, $end, $rset['RRULE'] ?? array(), $all_day )
						);
					}
				}
			}

			// Legacy recurrence: events created with the old "Repeats"
			// toggle store their rule in these separate fields instead of
			// _piecal_rsets. Saving the event through the metabox migrates
			// this into an RRULE-type rset entry and clears this flag, but
			// an event that hasn't been re-saved since could still be in
			// this old shape.
			if ( (bool) get_post_meta( $post->ID, '_piecal_is_recurring', true ) ) {
				$legacy_rrule = array(
					'freq'     => get_post_meta( $post->ID, '_piecal_recurring_frequency', true ) ?: 'DAILY',
					'interval' => get_post_meta( $post->ID, '_piecal_recurring_interval', true ) ?: 1,
					'exact'    => get_post_meta( $post->ID, '_piecal_recurring_exact_position', true ),
					'until'    => get_post_meta( $post->ID, '_piecal_recurring_end', true ),
				);
				$event_occurrences = array_merge(
					$event_occurrences,
					self::build_rrule_occurrences( $post, $start, $end, $legacy_rrule, $all_day )
				);
			}

			if ( ! empty( $exclusions ) ) {
				$event_occurrences = self::exclude_dates( $event_occurrences, $exclusions );
			}

			$event_occurrences = self::dedupe_event_occurrences( $event_occurrences );

			$occurrences = array_merge( $occurrences, $event_occurrences );
		}

		$occurrences = self::filter_by_time( $occurrences, $options['time'] );
		$occurrences = self::sort_by_start( $occurrences, $options['order'] );

		$limit = intval( $options['limit'] );
		if ( $limit > 0 && count( $occurrences ) > $limit ) {
			$occurrences = array_slice( $occurrences, 0, $limit );
		}

		return $occurrences;
	}

	/**
	 * Clone a post and attach the convenience `pcl_*` properties for one
	 * occurrence. Cloning matters: a recurring event contributes several
	 * occurrences that all share the same underlying post, so each needs
	 * its own copy rather than a shared reference.
	 */
	protected static function build_occurrence( $post, $start, $end, $all_day ) {
		$occurrence              = clone $post;
		$occurrence->pcl_start   = $start;
		$occurrence->pcl_end     = $end;
		$occurrence->pcl_all_day = $all_day;
		return $occurrence;
	}

	/**
	 * Build one occurrence from an RDATE entry. Honors setExplicitTime
	 * (use the RDATE's own time instead of the base event's) and dateEnd
	 * (an explicit end instead of preserving the base event's duration).
	 */
	protected static function build_rdate_occurrence( $post, $start, $end, $rdate, $all_day ) {
		$date = $rdate['date'] ?? '';
		if ( empty( $date ) ) {
			return null;
		}

		$start_ts = strtotime( $start );
		$date_ts  = strtotime( $date );

		if ( ! $start_ts || ! $date_ts ) {
			return null;
		}

		$use_explicit_time = ! empty( $rdate['setExplicitTime'] );

		$occurrence_start_ts = $use_explicit_time
			? $date_ts
			: strtotime( date( 'Y-m-d', $date_ts ) . ' ' . date( 'H:i:s', $start_ts ) );

		$occurrence_end = '';

		if ( ! empty( $rdate['dateEnd'] ) ) {
			$date_end_ts = strtotime( $rdate['dateEnd'] );
			if ( $date_end_ts ) {
				$occurrence_end = date( 'Y-m-d\TH:i:s', $date_end_ts );
			}
		} elseif ( ! empty( $end ) ) {
			$end_ts = strtotime( $end );
			if ( $end_ts ) {
				$duration       = $end_ts - $start_ts;
				$occurrence_end = date( 'Y-m-d\TH:i:s', $occurrence_start_ts + $duration );
			}
		}

		return self::build_occurrence( $post, date( 'Y-m-d\TH:i:s', $occurrence_start_ts ), $occurrence_end, $all_day );
	}

	/**
	 * Expand an RRULE entry into a series of extra occurrences, starting
	 * one interval after the base event's date and continuing until
	 * "until" (or a safety cap if "until" is empty/malformed).
	 */
	protected static function build_rrule_occurrences( $post, $start, $end, $rrule, $all_day ) {
		$start_ts = strtotime( $start );
		if ( ! $start_ts ) {
			return array();
		}

		$freq     = strtoupper( $rrule['freq'] ?? '' );
		$interval = max( 1, intval( $rrule['interval'] ?? 1 ) );
		$exact    = ! empty( $rrule['exact'] );
		$until_ts = ! empty( $rrule['until'] ) ? strtotime( $rrule['until'] ) : false;

		$duration = 0;
		if ( ! empty( $end ) ) {
			$end_ts = strtotime( $end );
			if ( $end_ts ) {
				$duration = $end_ts - $start_ts;
			}
		}

		$occurrences = array();

		foreach ( self::expand_rrule_dates( $start_ts, $freq, $interval, $exact, $until_ts ) as $next_ts ) {
			$occurrence_start = date( 'Y-m-d\TH:i:s', $next_ts );
			$occurrence_end   = $duration ? date( 'Y-m-d\TH:i:s', $next_ts + $duration ) : '';

			$occurrences[] = self::build_occurrence( $post, $occurrence_start, $occurrence_end, $all_day );
		}

		return $occurrences;
	}

	/**
	 * Generate the series of timestamps an RRULE produces, starting one
	 * interval after $anchor_ts and continuing until $until_ts (defaulting
	 * to +2 years, matching Pie Calendar Pro's own
	 * RRuleUtil::recurringEventCutoffDate() default). Shared by event RRULE
	 * expansion and EXRULE (recurring blackout) expansion.
	 *
	 * Uses Pie Calendar Pro's own bundled php-rrule library
	 * (vendor/php-rrule/RRule.php) when available, for byte-for-byte
	 * matching RFC5545 expansion instead of our own approximation. Falls
	 * back to a hand-rolled implementation if that library can't be found
	 * (e.g. Pie Calendar Pro isn't installed/active, or its file layout
	 * changes in a future version).
	 */
	protected static function expand_rrule_dates( $anchor_ts, $freq, $interval, $exact, $until_ts ) {
		if ( ! $until_ts ) {
			$until_ts = strtotime( self::recurring_event_cutoff() );
		}

		if ( self::rrule_library_loaded() ) {
			return self::expand_rrule_dates_via_library( $anchor_ts, $freq, $interval, $exact, $until_ts );
		}

		return self::expand_rrule_dates_fallback( $anchor_ts, $freq, $interval, $exact, $until_ts );
	}

	/**
	 * Default recurrence horizon when no "until" is set — matches Pie
	 * Calendar Pro's RRuleUtil::recurringEventCutoffDate() (today + 2 years).
	 */
	protected static function recurring_event_cutoff() {
		return date( 'Y-m-d', strtotime( '+2 years' ) );
	}

	/**
	 * Attempts to load Pie Calendar Pro's bundled php-rrule library.
	 * Cached after the first call since the file-existence checks and
	 * requires only need to happen once per request.
	 */
	protected static function rrule_library_loaded() {
		static $loaded = null;

		if ( null !== $loaded ) {
			return $loaded;
		}

		if ( class_exists( 'RRule\RRule' ) ) {
			return $loaded = true;
		}

		if ( ! defined( 'PIECAL_DIR' ) ) {
			return $loaded = false;
		}

		$files = array(
			PIECAL_DIR . 'vendor/php-rrule/RRuleTrait.php',
			PIECAL_DIR . 'vendor/php-rrule/RRuleInterface.php',
			PIECAL_DIR . 'vendor/php-rrule/RSet.php',
			PIECAL_DIR . 'vendor/php-rrule/RRule.php',
		);

		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				return $loaded = false;
			}
		}

		foreach ( $files as $file ) {
			require_once $file;
		}

		return $loaded = class_exists( 'RRule\RRule' );
	}

	/**
	 * Expand via Pie Calendar Pro's bundled php-rrule library, mirroring
	 * Piecal\Utils\RRuleUtil::expandOccurrencesWithinRange()'s RRULE
	 * construction exactly (DTSTART/FREQ/INTERVAL/UNTIL, plus BYDAY for the
	 * "exact weekday position" monthly case).
	 */
	protected static function expand_rrule_dates_via_library( $anchor_ts, $freq, $interval, $exact, $until_ts ) {
		$dtstart = date( 'Y-m-d\TH:i:s', $anchor_ts );
		$until   = date( 'Y-m-d\TH:i:s', $until_ts );

		$params = array(
			'DTSTART'  => $dtstart,
			'FREQ'     => $freq ?: 'DAILY',
			'INTERVAL' => max( 1, $interval ),
			'UNTIL'    => $until,
		);

		if ( $exact && 'MONTHLY' === $freq ) {
			$params['BYDAY'] = self::interpret_exact_monthly_weekday_position( $dtstart );
		}

		try {
			$rrule       = new \RRule\RRule( $params );
			$occurrences = $rrule->getOccurrencesBetween( $dtstart, $until );
		} catch ( \Exception $e ) {
			return array();
		}

		$dates = array();
		foreach ( $occurrences as $occurrence ) {
			$ts = $occurrence->getTimestamp();
			// The base occurrence (DTSTART itself) is always included by
			// the library; we add that separately elsewhere, so skip it
			// here to avoid a duplicate (dedupe_event_occurrences() would
			// also catch this, but skipping is cheaper).
			if ( $ts === $anchor_ts ) {
				continue;
			}
			$dates[] = $ts;
		}

		return $dates;
	}

	/**
	 * Port of Piecal\Utils\RRuleUtil::interpretExactMonthlyWeekdayPosition() —
	 * builds an RFC5545 BYDAY value like "3TH" (3rd Thursday) for the "same
	 * weekday & position" monthly recurrence option.
	 */
	protected static function interpret_exact_monthly_weekday_position( $date_string ) {
		$nthdate  = new \DateTime( $date_string );
		$firstday = new \DateTime( $nthdate->format( 'Y-m-01' ) );

		$translator = array(
			'0' => 'SU',
			'1' => 'MO',
			'2' => 'TU',
			'3' => 'WE',
			'4' => 'TH',
			'5' => 'FR',
			'6' => 'SA',
		);

		$occurrence_count = 0;
		while ( $firstday <= $nthdate ) {
			if ( $firstday->format( 'w' ) === $nthdate->format( 'w' ) ) {
				++$occurrence_count;
			}
			$firstday->add( new \DateInterval( 'P1D' ) );
		}

		return $occurrence_count . $translator[ $nthdate->format( 'w' ) ];
	}

	/**
	 * Fallback RRULE expansion used only when Pie Calendar Pro's bundled
	 * php-rrule library isn't available (e.g. Pie Calendar Pro isn't
	 * active, or a future version relocates the vendor bundle). Our own
	 * approximation of RFC5545 DAILY/WEEKLY/MONTHLY/YEARLY expansion — not
	 * verified against a real generated event, since the library path
	 * covers every case we've been able to test against.
	 */
	protected static function expand_rrule_dates_fallback( $anchor_ts, $freq, $interval, $exact, $until_ts ) {
		$dates        = array();
		$current_ts   = $anchor_ts;
		$safety_count = 0;

		while ( $safety_count < self::MAX_RRULE_OCCURRENCES ) {
			$next_ts = self::step_rrule_date( $current_ts, $freq, $interval, $exact, $anchor_ts );

			if ( ! $next_ts ) {
				break;
			}

			if ( $until_ts && $next_ts > $until_ts ) {
				break;
			}

			$dates[] = $next_ts;
			$current_ts = $next_ts;
			++$safety_count;
		}

		return $dates;
	}

	/**
	 * Advance one RRULE interval from $current_ts. $base_ts anchors the
	 * day-of-month / weekday-position so repeated monthly/yearly steps
	 * don't drift (e.g. Jan 31 -> Feb 28 -> Mar 28 instead of Mar 31).
	 */
	protected static function step_rrule_date( $current_ts, $freq, $interval, $exact, $base_ts ) {
		switch ( $freq ) {
			case 'DAILY':
				return strtotime( '+' . $interval . ' days', $current_ts );
			case 'WEEKLY':
				return strtotime( '+' . ( $interval * 7 ) . ' days', $current_ts );
			case 'MONTHLY':
				return $exact
					? self::add_months_exact_weekday( $current_ts, $interval, $base_ts )
					: self::add_months_clamped( $current_ts, $interval, $base_ts );
			case 'YEARLY':
				return self::add_months_clamped( $current_ts, $interval * 12, $base_ts );
			default:
				return false;
		}
	}

	/**
	 * Add $months to $current_ts's month, keeping $base_ts's day-of-month
	 * (clamped to the target month's last day if it's shorter, e.g. day 31
	 * in a 30-day month).
	 */
	protected static function add_months_clamped( $current_ts, $months, $base_ts ) {
		$base_day = (int) date( 'j', $base_ts );
		$year     = (int) date( 'Y', $current_ts );
		$month    = (int) date( 'n', $current_ts );

		$month += $months;
		while ( $month > 12 ) {
			$month -= 12;
			++$year;
		}
		while ( $month < 1 ) {
			$month += 12;
			--$year;
		}

		$days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$day           = min( $base_day, $days_in_month );

		return strtotime( sprintf( '%04d-%02d-%02d %s', $year, $month, $day, date( 'H:i:s', $base_ts ) ) );
	}

	/**
	 * Add $months to $current_ts's month, landing on the same weekday +
	 * ordinal position within the month as $base_ts (e.g. "3rd Tuesday").
	 * Falls back to the last matching weekday in the month if that
	 * position doesn't exist (e.g. base was the 5th Friday of its month).
	 */
	protected static function add_months_exact_weekday( $current_ts, $months, $base_ts ) {
		$weekday = (int) date( 'w', $base_ts );
		$ordinal = (int) ceil( (int) date( 'j', $base_ts ) / 7 );

		$year  = (int) date( 'Y', $current_ts );
		$month = (int) date( 'n', $current_ts );

		$month += $months;
		while ( $month > 12 ) {
			$month -= 12;
			++$year;
		}
		while ( $month < 1 ) {
			$month += 12;
			--$year;
		}

		$target_ts = self::nth_weekday_of_month( $year, $month, $weekday, $ordinal );
		if ( ! $target_ts ) {
			$target_ts = self::nth_weekday_of_month( $year, $month, $weekday, 'last' );
		}

		if ( ! $target_ts ) {
			return false;
		}

		return strtotime( date( 'Y-m-d', $target_ts ) . ' ' . date( 'H:i:s', $base_ts ) );
	}

	/**
	 * Find the Nth (or 'last') occurrence of a weekday within a month.
	 * $weekday matches date('w') — 0 (Sunday) through 6 (Saturday).
	 */
	protected static function nth_weekday_of_month( $year, $month, $weekday, $ordinal ) {
		$days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );

		if ( 'last' === $ordinal ) {
			for ( $day = $days_in_month; $day >= 1; $day-- ) {
				$ts = mktime( 0, 0, 0, $month, $day, $year );
				if ( (int) date( 'w', $ts ) === $weekday ) {
					return $ts;
				}
			}
			return false;
		}

		$count = 0;
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$ts = mktime( 0, 0, 0, $month, $day, $year );
			if ( (int) date( 'w', $ts ) === $weekday ) {
				++$count;
				if ( $count === $ordinal ) {
					return $ts;
				}
			}
		}

		return false;
	}

	/**
	 * Parse an EXDATE's date (and optional endDate) into a [start, end]
	 * timestamp range for exclusion matching. A single date with no
	 * endDate becomes a one-day range. Note: EXDATE uses "endDate", not
	 * "dateEnd" like RDATE — confirmed against Pie Calendar Pro's source.
	 */
	protected static function parse_exdate_range( $exdate ) {
		$date = $exdate['date'] ?? '';
		if ( empty( $date ) ) {
			return null;
		}

		$start_ts = strtotime( $date );
		if ( ! $start_ts ) {
			return null;
		}

		$end_ts = ! empty( $exdate['endDate'] ) ? strtotime( $exdate['endDate'] ) : false;
		if ( ! $end_ts || $end_ts < $start_ts ) {
			$end_ts = strtotime( date( 'Y-m-d', $start_ts ) . ' 23:59:59' );
		}

		return array( strtotime( date( 'Y-m-d 00:00:00', $start_ts ) ), $end_ts );
	}

	/**
	 * Read Pie Calendar Pro's global "Blackout Dates" setting and turn its
	 * EXRULE/EXDATE entries into a flat list of [start, end] timestamp
	 * ranges. Computed once per get_events() call and applied to every
	 * event, matching Piecal\Pro\BlackoutDates — these are site-wide, not
	 * tied to any one event.
	 */
	protected static function get_global_blackout_exclusions() {
		$entries = get_option( 'piecal_blackout_dates', array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$exclusions = array();

		foreach ( $entries as $entry ) {
			$type = $entry['type'] ?? '';

			if ( 'EXDATE' === $type ) {
				$range = self::parse_exdate_range( $entry['EXDATE'] ?? array() );
				if ( $range ) {
					$exclusions[] = $range;
				}
			} elseif ( 'EXRULE' === $type ) {
				$exclusions = array_merge( $exclusions, self::parse_exrule_ranges( $entry['EXRULE'] ?? array() ) );
			}
		}

		return $exclusions;
	}

	/**
	 * Expand an EXRULE (recurring blackout) entry into a list of one-day
	 * [start, end] ranges, one per generated occurrence including its own
	 * dtstart (an RRULE library includes the anchor date itself if it
	 * matches the pattern, which it always does by construction here).
	 */
	protected static function parse_exrule_ranges( $rule ) {
		$dtstart = $rule['dtstart'] ?? '';
		if ( empty( $dtstart ) ) {
			return array();
		}

		$anchor_ts = strtotime( $dtstart );
		if ( ! $anchor_ts ) {
			return array();
		}

		$freq     = strtoupper( $rule['freq'] ?? 'DAILY' );
		$interval = max( 1, intval( $rule['interval'] ?? 1 ) );
		$exact    = ! empty( $rule['exact'] );
		$until_ts = ! empty( $rule['until'] ) ? strtotime( $rule['until'] ) : false;

		$dates = array_merge(
			array( $anchor_ts ),
			self::expand_rrule_dates( $anchor_ts, $freq, $interval, $exact, $until_ts )
		);

		$ranges = array();
		foreach ( $dates as $ts ) {
			$ranges[] = array(
				strtotime( date( 'Y-m-d 00:00:00', $ts ) ),
				strtotime( date( 'Y-m-d 23:59:59', $ts ) ),
			);
		}

		return $ranges;
	}

	/**
	 * Drop any occurrence whose start date falls within one of the given
	 * [start, end] blackout ranges.
	 */
	protected static function exclude_dates( $occurrences, $exclusions ) {
		return array_values(
			array_filter(
				$occurrences,
				function ( $occurrence ) use ( $exclusions ) {
					$start_ts = strtotime( $occurrence->pcl_start );
					if ( ! $start_ts ) {
						return true;
					}
					foreach ( $exclusions as $range ) {
						if ( $start_ts >= $range[0] && $start_ts <= $range[1] ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Drop duplicate occurrences of the same event that ended up with the
	 * same start time — e.g. an RDATE that happens to land on a date an
	 * RRULE would also generate.
	 */
	protected static function dedupe_event_occurrences( $occurrences ) {
		$seen   = array();
		$result = array();

		foreach ( $occurrences as $occurrence ) {
			$key = $occurrence->ID . '|' . $occurrence->pcl_start;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$result[]     = $occurrence;
		}

		return $result;
	}

	/**
	 * Keep only occurrences whose start is in the future ('upcoming'), the
	 * past ('past'), or return them all ('all').
	 */
	protected static function filter_by_time( $occurrences, $time ) {
		if ( 'all' === $time ) {
			return $occurrences;
		}

		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		return array_values(
			array_filter(
				$occurrences,
				function ( $occurrence ) use ( $time, $now ) {
					$start_ts = strtotime( $occurrence->pcl_start );
					if ( ! $start_ts ) {
						return false;
					}
					return 'past' === $time ? $start_ts < $now : $start_ts >= $now;
				}
			)
		);
	}

	/**
	 * Sort occurrences by start date/time.
	 */
	protected static function sort_by_start( $occurrences, $order ) {
		$direction = ( 'DESC' === strtoupper( $order ) ) ? -1 : 1;

		usort(
			$occurrences,
			function ( $a, $b ) use ( $direction ) {
				$a_ts = strtotime( $a->pcl_start );
				$b_ts = strtotime( $b->pcl_start );
				return ( $a_ts <=> $b_ts ) * $direction;
			}
		);

		return $occurrences;
	}
}
