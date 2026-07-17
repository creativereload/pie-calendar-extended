<?php
/**
 * Renders events into HTML for each supported layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Render {

	/**
	 * Build the full markup for a set of events.
	 *
	 * @param WP_Post[] $events Events from PCL_Query::get_events().
	 * @param array     $atts   Normalized display attributes.
	 * @return string HTML.
	 */
	public static function render( $events, $atts ) {
		wp_enqueue_style( 'pcl-frontend' );
		wp_enqueue_script( 'pcl-frontend' );

		if ( empty( $events ) ) {
			return sprintf(
				'<p class="pcl-empty">%s</p>',
				esc_html__( 'No upcoming events were found.', 'pie-calendar-extended' )
			);
		}

		$layout = in_array( $atts['layout'], array( 'list', 'compact', 'column' ), true )
			? $atts['layout']
			: 'list';

		$method = 'render_' . $layout;

		if ( ! method_exists( __CLASS__, $method ) ) {
			$method = 'render_list';
		}

		$instance_id = 'pcl-' . wp_unique_id();

		return self::$method( $events, $atts, $instance_id );
	}

	/**
	 * Build an inline `style="--pcl-x:...;"` attribute from any color (or
	 * other per-instance) overrides the user has set, leaving the rest to
	 * fall back to the defaults (or a site's own CSS) declared on .pcl-wrap.
	 */
	protected static function style_attr( $atts ) {
		$map = array(
			'card_bg_color'      => '--pcl-surface',
			'text_color'         => '--pcl-text',
			'link_color'         => '--pcl-link',
			'link_hover_color'   => '--pcl-link-hover',
			'border_color'       => '--pcl-border',
			'button_bg_color'           => '--pcl-button-bg',
			'button_text_color'         => '--pcl-button-text',
			'button_bg_hover_color'     => '--pcl-button-bg-hover',
			'button_text_hover_color'   => '--pcl-button-text-hover',
			'button_border_color'       => '--pcl-button-border',
			'button_border_hover_color' => '--pcl-button-border-hover',
			'badge_bg_color'     => '--pcl-badge-bg',
			'badge_text_color'   => '--pcl-badge-text',
			'badge_border_color' => '--pcl-badge-border',
			'columns'            => '--pcl-columns',

			// Border widths.
			'card_border_width'   => '--pcl-card-border-width',
			'badge_border_width'  => '--pcl-badge-border-width',
			'button_border_width' => '--pcl-button-border-width',

			// Per-corner radii (card / image / badge / button).
			'card_radius_tl'   => '--pcl-card-radius-tl',
			'card_radius_tr'   => '--pcl-card-radius-tr',
			'card_radius_br'   => '--pcl-card-radius-br',
			'card_radius_bl'   => '--pcl-card-radius-bl',
			'image_radius_tl'  => '--pcl-image-radius-tl',
			'image_radius_tr'  => '--pcl-image-radius-tr',
			'image_radius_br'  => '--pcl-image-radius-br',
			'image_radius_bl'  => '--pcl-image-radius-bl',
			'badge_radius_tl'  => '--pcl-badge-radius-tl',
			'badge_radius_tr'  => '--pcl-badge-radius-tr',
			'badge_radius_br'  => '--pcl-badge-radius-br',
			'badge_radius_bl'  => '--pcl-badge-radius-bl',
			'button_radius_tl' => '--pcl-button-radius-tl',
			'button_radius_tr' => '--pcl-button-radius-tr',
			'button_radius_br' => '--pcl-button-radius-br',
			'button_radius_bl' => '--pcl-button-radius-bl',
		);

		$declarations = array();
		foreach ( $map as $att_key => $css_var ) {
			if ( ! empty( $atts[ $att_key ] ) ) {
				$declarations[] = $css_var . ':' . $atts[ $att_key ];
			}
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return ' style="' . esc_attr( implode( ';', $declarations ) ) . '"';
	}

	/**
	 * Vertical stacked list layout: a larger version of the compact layout —
	 * a big date badge and title at the top, an excerpt underneath, and a
	 * large thumbnail on the other side, each event in its own card.
	 */
	protected static function render_list( $events, $atts, $instance_id ) {
		$out = '<div id="' . esc_attr( $instance_id ) . '" class="pcl-wrap pcl-layout-list"' . self::style_attr( $atts ) . '>';

		foreach ( $events as $event ) {
			$title     = get_the_title( $event );
			$permalink = get_permalink( $event );
			$badge     = self::get_date_badge( $event );
			$subtitle  = self::format_event_subtitle( $event );

			$out .= '<div class="pcl-list__row">';

			$out .= '<div class="pcl-list__content">';

			$out .= '<div class="pcl-list__top">';

			$out .= self::render_badge( $event, 'pcl-list__badge', $badge );

			$out .= '<div class="pcl-list__main">';
			$out .= '<' . $atts['heading_level'] . ' class="pcl-list__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></' . $atts['heading_level'] . '>';
			if ( $subtitle ) {
				$out .= '<p class="pcl-list__subtitle">' . self::subtitle_html( $event ) . '</p>';
			}
			$out .= '</div>'; // .pcl-list__main

			$out .= '</div>'; // .pcl-list__top

			if ( $atts['show_excerpt'] ) {
				$out .= '<p class="pcl-list__excerpt">' . esc_html( self::get_trimmed_excerpt( $event, $atts['excerpt_length'] ) ) . '</p>';
			}

			$out .= '</div>'; // .pcl-list__content

			if ( $atts['show_image'] && has_post_thumbnail( $event ) ) {
				$out .= '<a class="pcl-list__thumb" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
				$out .= get_the_post_thumbnail( $event, 'medium', array( 'loading' => 'lazy' ) );
				$out .= '</a>';
			}

			if ( $atts['show_button'] ) {
				$out .= '<a class="pcl-list__button" href="' . esc_url( $permalink ) . '">' . esc_html( $atts['link_text'] ) . '</a>';
			}

			$out .= '</div>'; // .pcl-list__row
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Compact layout: a dense row per event with a small "date badge"
	 * (month + day) on the side, a small optional thumbnail, and the title
	 * next to it — good for showing many events in a narrow space, like a
	 * sidebar or newsletter-style feed.
	 */
	protected static function render_compact( $events, $atts, $instance_id ) {
		$out = '<div id="' . esc_attr( $instance_id ) . '" class="pcl-wrap pcl-layout-compact"' . self::style_attr( $atts ) . '>';

		foreach ( $events as $event ) {
			$title     = get_the_title( $event );
			$permalink = get_permalink( $event );
			$badge     = self::get_date_badge( $event );

			$out .= '<div class="pcl-compact__row">';

			$out .= self::render_badge( $event, 'pcl-compact__badge', $badge );

			if ( $atts['show_image'] && has_post_thumbnail( $event ) ) {
				$out .= '<a class="pcl-compact__thumb" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
				$out .= get_the_post_thumbnail( $event, 'thumbnail', array( 'loading' => 'lazy' ) );
				$out .= '</a>';
			}

			$out .= '<div class="pcl-compact__body">';
			$out .= '<' . $atts['heading_level'] . ' class="pcl-compact__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></' . $atts['heading_level'] . '>';

			$subtitle = self::format_event_subtitle( $event );
			if ( $subtitle ) {
				$out .= '<p class="pcl-compact__time">' . self::subtitle_html( $event ) . '</p>';
			}

			if ( $atts['show_excerpt'] ) {
				$out .= '<p class="pcl-compact__excerpt">' . esc_html( self::get_trimmed_excerpt( $event, $atts['excerpt_length'] ) ) . '</p>';
			}

			$out .= '</div>'; // .pcl-compact__body

			if ( $atts['show_button'] ) {
				$out .= '<a class="pcl-compact__button" href="' . esc_url( $permalink ) . '">' . esc_html( $atts['link_text'] ) . '</a>';
			}

			$out .= '</div>'; // .pcl-compact__row
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Column layout: a responsive grid of large cards — a full-width
	 * thumbnail at the top with the date badge overlaid on it (or a
	 * standalone badge if there's no image), then title and subtitle, with
	 * a button anchored to the bottom.
	 */
	protected static function render_column( $events, $atts, $instance_id ) {
		$out = '<div id="' . esc_attr( $instance_id ) . '" class="pcl-wrap pcl-layout-column"' . self::style_attr( $atts ) . '>';

		foreach ( $events as $event ) {
			$title     = get_the_title( $event );
			$permalink = get_permalink( $event );
			$badge     = self::get_date_badge( $event );
			$subtitle  = self::format_event_subtitle( $event );

			$out .= '<div class="pcl-column__card">';

			$badge_markup = self::render_badge( $event, 'pcl-column__badge', $badge );

			if ( $atts['show_image'] && has_post_thumbnail( $event ) ) {
				$out .= '<div class="pcl-column__media">';
				$out .= '<a class="pcl-column__thumb" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
				$out .= get_the_post_thumbnail( $event, 'medium', array( 'loading' => 'lazy' ) );
				$out .= '</a>';
				$out .= $badge_markup;
				$out .= '</div>'; // .pcl-column__media
			} else {
				$out .= $badge_markup;
			}

			$out .= '<' . $atts['heading_level'] . ' class="pcl-column__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></' . $atts['heading_level'] . '>';

			if ( $subtitle ) {
				$out .= '<p class="pcl-column__subtitle">' . self::subtitle_html( $event ) . '</p>';
			}

			if ( $atts['show_excerpt'] ) {
				$out .= '<p class="pcl-column__excerpt">' . esc_html( self::get_trimmed_excerpt( $event, $atts['excerpt_length'] ) ) . '</p>';
			}

			if ( $atts['show_button'] ) {
				$out .= '<a class="pcl-column__button" href="' . esc_url( $permalink ) . '">' . esc_html( $atts['link_text'] ) . '</a>';
			}

			$out .= '</div>'; // .pcl-column__card
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Build a { weekday, month, day } set for a date badge, e.g.
	 * { weekday: 'THU', month: 'SEP', day: '02' }.
	 */
	protected static function get_date_badge( $event ) {
		if ( empty( $event->pcl_start ) ) {
			return array(
				'weekday' => '',
				'month'   => '',
				'day'     => '',
			);
		}

		$ts = strtotime( $event->pcl_start );
		if ( ! $ts ) {
			return array(
				'weekday' => '',
				'month'   => '',
				'day'     => '',
			);
		}

		return array(
			'weekday' => strtoupper( date_i18n( 'D', $ts ) ),
			'month'   => strtoupper( date_i18n( 'M', $ts ) ),
			'day'     => date_i18n( 'j', $ts ),
		);
	}

	/**
	 * Short "weekday, time range" subtitle used under the title in both the
	 * list and compact layouts, e.g. "Thu, 5:30 – 6:30 a.m." or "Thu, All day".
	 */
	protected static function format_event_subtitle( $event ) {
		if ( empty( $event->pcl_start ) ) {
			return '';
		}

		$start_ts = strtotime( $event->pcl_start );
		if ( ! $start_ts ) {
			return '';
		}

		$weekday = date_i18n( 'D', $start_ts );

		if ( $event->pcl_all_day ) {
			return sprintf(
				/* translators: %s: weekday abbreviation, e.g. "Thu" */
				__( '%s, All day', 'pie-calendar-extended' ),
				$weekday
			);
		}

		$time_format = get_option( 'time_format' );
		$start_label = date_i18n( $time_format, $start_ts );

		if ( ! empty( $event->pcl_end ) ) {
			$end_ts = strtotime( $event->pcl_end );
			if ( $end_ts && gmdate( 'Y-m-d', $end_ts ) === gmdate( 'Y-m-d', $start_ts ) ) {
				return sprintf(
					/* translators: 1: weekday abbreviation, 2: start time, 3: end time */
					__( '%1$s, %2$s – %3$s', 'pie-calendar-extended' ),
					$weekday,
					$start_label,
					date_i18n( $time_format, $end_ts )
				);
			}
		}

		return sprintf(
			/* translators: 1: weekday abbreviation, 2: start time */
			__( '%1$s, %2$s', 'pie-calendar-extended' ),
			$weekday,
			$start_label
		);
	}

	/**
	 * Machine-readable value for a <time datetime> attribute, taken verbatim
	 * from the stored local start (no timezone reinterpretation): "Y-m-d" for
	 * all-day events, "Y-m-dTH:i" otherwise. Returns '' if no start.
	 */
	protected static function datetime_attr( $event ) {
		if ( empty( $event->pcl_start ) ) {
			return '';
		}

		$start = str_replace( ' ', 'T', trim( (string) $event->pcl_start ) );

		return ! empty( $event->pcl_all_day ) ? substr( $start, 0, 10 ) : substr( $start, 0, 16 );
	}

	/**
	 * Full, localized date for screen readers, e.g. "Wednesday, September 2,
	 * 2026" — announced in place of the abbreviated badge.
	 */
	protected static function full_date_label( $event ) {
		if ( empty( $event->pcl_start ) ) {
			return '';
		}

		$ts = strtotime( $event->pcl_start );

		return $ts ? date_i18n( 'l, F j, Y', $ts ) : '';
	}

	/**
	 * Render the date badge as a semantic <time> element. The abbreviated
	 * month/day spans are hidden from assistive tech (aria-hidden) in favor of
	 * a visually hidden full-date label, so screen readers announce a single
	 * clean date instead of "SEP" then "02".
	 */
	protected static function render_badge( $event, $class_base, $badge ) {
		$datetime = self::datetime_attr( $event );
		$sr_label = self::full_date_label( $event );

		$html  = '<time class="' . esc_attr( $class_base ) . '"';
		$html .= '' !== $datetime ? ' datetime="' . esc_attr( $datetime ) . '"' : '';
		$html .= '>';
		$html .= '<span class="' . esc_attr( $class_base . '-month' ) . '" aria-hidden="true">' . esc_html( $badge['month'] ) . '</span>';
		$html .= '<span class="' . esc_attr( $class_base . '-day' ) . '" aria-hidden="true">' . esc_html( $badge['day'] ) . '</span>';
		if ( '' !== $sr_label ) {
			$html .= '<span class="pcl-sr-only">' . esc_html( $sr_label ) . '</span>';
		}
		$html .= '</time>';

		return $html;
	}

	/**
	 * The weekday/time subtitle wrapped in a <time datetime> element, so the
	 * date is machine-readable. Returns '' when there's no date.
	 */
	protected static function subtitle_html( $event ) {
		$label = self::format_event_subtitle( $event );
		if ( '' === $label ) {
			return '';
		}

		$datetime = self::datetime_attr( $event );
		if ( '' === $datetime ) {
			return esc_html( $label );
		}

		return '<time datetime="' . esc_attr( $datetime ) . '">' . esc_html( $label ) . '</time>';
	}

	/**
	 * Get a trimmed, plain-text excerpt for an event post.
	 */
	protected static function get_trimmed_excerpt( $event, $length ) {
		$length = max( 5, intval( $length ) );
		$text   = has_excerpt( $event ) ? $event->post_excerpt : $event->post_content;
		$text   = wp_strip_all_tags( strip_shortcodes( $text ) );
		$words  = preg_split( '/\s+/', trim( $text ) );

		if ( count( $words ) <= $length ) {
			return trim( $text );
		}

		return implode( ' ', array_slice( $words, 0, $length ) ) . '…';
	}
}
