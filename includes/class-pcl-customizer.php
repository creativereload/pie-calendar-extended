<?php
/**
 * Registers a "Pie Calendar Layouts" panel in the WordPress Customizer
 * with global default colors for the List/Compact/Column layouts, grouped
 * into sections (Colors, Button, Date Badge) that mirror the block's own
 * Styles tab organization.
 *
 * These are site-wide fallbacks only — any color set directly on a
 * [piecal_layouts] shortcode attribute or in the block's Styles tab still
 * takes priority for that specific instance. See
 * PCL_Shortcode::apply_global_color_defaults().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Customizer {

	public function __construct() {
		add_action( 'customize_register', array( $this, 'register' ) );
	}

	/**
	 * Sections and their color fields, in the same grouping as the block's
	 * Styles tab (Colors / Button / Date Badge panels).
	 */
	public static function sections() {
		return array(
			'pcl_colors' => array(
				'title'  => __( 'Colors', 'pie-calendar-extended' ),
				'fields' => array(
					'card_bg_color'    => __( 'Card Background', 'pie-calendar-extended' ),
					'text_color'       => __( 'Text', 'pie-calendar-extended' ),
					'link_color'       => __( 'Link', 'pie-calendar-extended' ),
					'link_hover_color' => __( 'Link (hover)', 'pie-calendar-extended' ),
					'border_color'     => __( 'Borders', 'pie-calendar-extended' ),
				),
			),
			'pcl_button' => array(
				'title'  => __( 'Button', 'pie-calendar-extended' ),
				'fields' => array(
					'button_bg_color'           => __( 'Background', 'pie-calendar-extended' ),
					'button_bg_hover_color'     => __( 'Background (hover)', 'pie-calendar-extended' ),
					'button_text_color'         => __( 'Text', 'pie-calendar-extended' ),
					'button_text_hover_color'   => __( 'Text (hover)', 'pie-calendar-extended' ),
					'button_border_color'       => __( 'Border', 'pie-calendar-extended' ),
					'button_border_hover_color' => __( 'Border (hover)', 'pie-calendar-extended' ),
				),
			),
			'pcl_badge'  => array(
				'title'  => __( 'Date Badge', 'pie-calendar-extended' ),
				'fields' => array(
					'badge_bg_color'     => __( 'Background', 'pie-calendar-extended' ),
					'badge_text_color'   => __( 'Text', 'pie-calendar-extended' ),
					'badge_border_color' => __( 'Border', 'pie-calendar-extended' ),
				),
			),
		);
	}

	/**
	 * Flat { color_key => label } map across all sections. Used by
	 * PCL_Shortcode::apply_global_color_defaults() to look up every global
	 * default without needing to know about the section grouping.
	 */
	public static function color_fields() {
		$fields = array();

		foreach ( self::sections() as $section ) {
			foreach ( $section['fields'] as $key => $label ) {
				$fields[ $key ] = $label;
			}
		}

		return $fields;
	}

	/**
	 * Register one panel, one section per group, and one native color
	 * control per field — same label/swatch control WordPress itself uses
	 * for background/header colors, so this looks like a standard part of
	 * the Customizer rather than a custom UI.
	 */
	public function register( $wp_customize ) {
		$wp_customize->add_panel(
			'pcl_panel',
			array(
				'title'    => __( 'Pie Calendar Layouts', 'pie-calendar-extended' ),
				'priority' => 160,
			)
		);

		foreach ( self::sections() as $section_id => $section ) {
			$wp_customize->add_section(
				$section_id,
				array(
					'title' => $section['title'],
					'panel' => 'pcl_panel',
				)
			);

			foreach ( $section['fields'] as $key => $label ) {
				$option_name = self::option_name( $key );

				$wp_customize->add_setting(
					$option_name,
					array(
						'type'              => 'option',
						'default'           => '',
						'sanitize_callback' => 'sanitize_hex_color',
						'transport'         => 'refresh',
					)
				);

				$wp_customize->add_control(
					new WP_Customize_Color_Control(
						$wp_customize,
						$option_name,
						array(
							'label'   => $label,
							'section' => $section_id,
						)
					)
				);
			}
		}
	}

	/**
	 * The option name a color key is stored under, e.g. "pcl_card_bg_color".
	 */
	public static function option_name( $key ) {
		return 'pcl_' . $key;
	}

	/**
	 * The site-wide global value for a color key, or '' if unset.
	 */
	public static function get_global_color( $key ) {
		return get_option( self::option_name( $key ), '' );
	}
}
