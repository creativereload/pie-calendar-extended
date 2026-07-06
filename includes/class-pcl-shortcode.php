<?php
/**
 * Registers the [piecal_layouts] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Shortcode {

	public function __construct() {
		add_shortcode( 'piecal_layouts', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * Example:
	 * [piecal_layouts layout="compact" post_type="event" limit="6" time="upcoming"]
	 * [piecal_layouts post_id="425" time="upcoming"] (only that event's occurrences)
	 */
	public function render( $atts ) {
		$atts = self::normalize_atts( $atts );

		$events = PCL_Query::get_events(
			array(
				'post_type' => $atts['post_type'],
				'post_id'   => $atts['post_id'],
				'limit'     => $atts['limit'],
				'time'      => $atts['time'],
				'order'     => $atts['order'],
			)
		);

		return PCL_Render::render( $events, $atts );
	}

	/**
	 * Normalize and sanitize shortcode/block attributes into one shared shape.
	 *
	 * @param array $atts Raw attributes.
	 * @return array Normalized attributes.
	 */
	public static function normalize_atts( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'             => 'list',
				'post_type'          => '',
				'post_id'            => 0,
				'limit'              => 10,
				'time'               => 'upcoming',
				'order'              => 'ASC',
				'show_image'         => 'yes',
				'show_excerpt'       => 'yes',
				'excerpt_length'     => 20,
				'date_format'        => '',
				'link_text'          => __( 'View Event', 'pie-calendar-extended' ),
				'show_button'        => 'yes',
				'columns'              => 3,
				'card_border_width'    => '',
				'badge_border_width'   => '',
				'button_border_width'  => '',
				'card_radius_tl'       => '',
				'card_radius_tr'       => '',
				'card_radius_br'       => '',
				'card_radius_bl'       => '',
				'image_radius_tl'      => '',
				'image_radius_tr'      => '',
				'image_radius_br'      => '',
				'image_radius_bl'      => '',
				'badge_radius_tl'      => '',
				'badge_radius_tr'      => '',
				'badge_radius_br'      => '',
				'badge_radius_bl'      => '',
				'button_radius_tl'     => '',
				'button_radius_tr'     => '',
				'button_radius_br'     => '',
				'button_radius_bl'     => '',
				'card_bg_color'      => '',
				'text_color'         => '',
				'link_color'         => '',
				'link_hover_color'   => '',
				'border_color'       => '',
				'button_bg_color'           => '',
				'button_text_color'         => '',
				'button_bg_hover_color'     => '',
				'button_text_hover_color'   => '',
				'button_border_color'       => '',
				'button_border_hover_color' => '',
				'badge_bg_color'     => '',
				'badge_text_color'   => '',
				'badge_border_color' => '',
			),
			$atts,
			'piecal_layouts'
		);

		$atts['limit']              = intval( $atts['limit'] );
		$atts['excerpt_length']     = intval( $atts['excerpt_length'] );
		$atts['show_image']         = self::to_bool( $atts['show_image'] );
		$atts['show_excerpt']       = self::to_bool( $atts['show_excerpt'] );
		$atts['show_button']        = self::to_bool( $atts['show_button'] );
		$atts['columns']            = max( 1, min( 3, intval( $atts['columns'] ) ) );

		foreach ( array(
			'card_border_width',
			'badge_border_width',
			'button_border_width',
			'card_radius_tl',
			'card_radius_tr',
			'card_radius_br',
			'card_radius_bl',
			'image_radius_tl',
			'image_radius_tr',
			'image_radius_br',
			'image_radius_bl',
			'badge_radius_tl',
			'badge_radius_tr',
			'badge_radius_br',
			'badge_radius_bl',
			'button_radius_tl',
			'button_radius_tr',
			'button_radius_br',
			'button_radius_bl',
		) as $length_key ) {
			$atts[ $length_key ] = self::sanitize_radius( $atts[ $length_key ] );
		}

		$atts['post_type']          = sanitize_key( $atts['post_type'] );
		$atts['post_id']            = absint( $atts['post_id'] );
		$atts['layout']              = sanitize_key( $atts['layout'] );
		$atts['time']                = sanitize_key( $atts['time'] );
		$atts['order']               = ( 'DESC' === strtoupper( $atts['order'] ) ) ? 'DESC' : 'ASC';
		$atts['link_text']           = sanitize_text_field( $atts['link_text'] );
		$atts['date_format']         = sanitize_text_field( $atts['date_format'] );
		$atts['card_bg_color']       = self::sanitize_color( $atts['card_bg_color'] );
		$atts['text_color']          = self::sanitize_color( $atts['text_color'] );
		$atts['link_color']          = self::sanitize_color( $atts['link_color'] );
		$atts['link_hover_color']    = self::sanitize_color( $atts['link_hover_color'] );
		$atts['border_color']        = self::sanitize_color( $atts['border_color'] );
		$atts['button_bg_color']           = self::sanitize_color( $atts['button_bg_color'] );
		$atts['button_text_color']         = self::sanitize_color( $atts['button_text_color'] );
		$atts['button_bg_hover_color']     = self::sanitize_color( $atts['button_bg_hover_color'] );
		$atts['button_text_hover_color']   = self::sanitize_color( $atts['button_text_hover_color'] );
		$atts['button_border_color']       = self::sanitize_color( $atts['button_border_color'] );
		$atts['button_border_hover_color'] = self::sanitize_color( $atts['button_border_hover_color'] );
		$atts['badge_bg_color']      = self::sanitize_color( $atts['badge_bg_color'] );
		$atts['badge_text_color']    = self::sanitize_color( $atts['badge_text_color'] );
		$atts['badge_border_color']  = self::sanitize_color( $atts['badge_border_color'] );

		$atts = self::apply_global_color_defaults( $atts );

		return $atts;
	}

	/**
	 * Fill in any color left blank on this specific shortcode/block with
	 * the site-wide default set in Appearance > Customize > Pie Calendar
	 * Layouts, if one was set there. An explicit per-instance color always
	 * wins; this only fills gaps.
	 */
	protected static function apply_global_color_defaults( $atts ) {
		if ( ! class_exists( 'PCL_Customizer' ) ) {
			return $atts;
		}

		foreach ( array_keys( PCL_Customizer::color_fields() ) as $key ) {
			if ( empty( $atts[ $key ] ) ) {
				$global_value = PCL_Customizer::get_global_color( $key );
				if ( ! empty( $global_value ) ) {
					$atts[ $key ] = $global_value;
				}
			}
		}

		return $atts;
	}

	/**
	 * Normalize a border-radius value into a CSS pixel length, e.g. 8 or
	 * "8px" both become "8px". Returns '' when left blank so the default
	 * from frontend.css applies. Clamped to 0–60px.
	 */
	protected static function sanitize_radius( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$pixels = max( 0, min( 60, intval( $value ) ) );

		return $pixels . 'px';
	}

	/**
	 * Accepts yes/no, true/false, 1/0 (string or bool) and returns a real bool.
	 */
	protected static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( 'yes', 'true', '1' ), true );
	}

	/**
	 * Accepts a hex color, an rgb()/rgba()/hsl()/hsla() function, a CSS
	 * variable reference (e.g. "var(--accent)" — themes with a "Global
	 * Colors" palette, like GeneratePress, use these instead of hex), or a
	 * plain CSS color keyword. Returns '' if the value isn't recognized or
	 * contains characters that could break out of an inline style attribute.
	 */
	protected static function sanitize_color( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/[;{}<>"\']/', $value ) ) {
			return '';
		}

		$is_hex   = (bool) preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value );
		$is_func  = (bool) preg_match( '/^(?:rgb|rgba|hsl|hsla|var)\([^;{}<>]*\)$/i', $value );
		$is_named = (bool) preg_match( '/^[a-zA-Z]{3,30}$/', $value );

		if ( ! $is_hex && ! $is_func && ! $is_named ) {
			return '';
		}

		return $value;
	}
}
