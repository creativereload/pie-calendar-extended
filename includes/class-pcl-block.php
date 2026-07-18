<?php
/**
 * Registers the "Pie Calendar Layouts" Gutenberg block.
 *
 * This is a dynamic block: the editor preview and the front end both call
 * through to the same render() method used by the shortcode, so layouts
 * never drift out of sync between the two.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PCL_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'pcl-block-editor',
			PCL_URL . 'assets/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-server-side-render',
			),
			PCL_VERSION,
			true
		);

		wp_register_style(
			'pcl-block-editor',
			PCL_URL . 'assets/css/frontend.css',
			array(),
			PCL_VERSION
		);

		register_block_type(
			'pie-calendar-layouts/events',
			array(
				'editor_script'   => 'pcl-block-editor',
				'editor_style'    => 'pcl-block-editor',
				'style'           => 'pcl-frontend',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'layout'        => array(
						'type'    => 'string',
						'default' => 'list',
					),
					'postType'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'postId'        => array(
						'type'    => 'number',
						'default' => 0,
					),
					'limit'         => array(
						'type'    => 'number',
						'default' => 10,
					),
					'time'          => array(
						'type'    => 'string',
						'default' => 'upcoming',
					),
					'order'         => array(
						'type'    => 'string',
						'default' => 'ASC',
					),
					'showImage'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showExcerpt'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'excerptLength' => array(
						'type'    => 'number',
						'default' => 20,
					),
					'linkText'      => array(
						'type'    => 'string',
						'default' => __( 'View Event', 'pie-calendar-extended' ),
					),
					'showButton'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'pagination'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'headingLevel'  => array(
						'type'    => 'string',
						'default' => 'h3',
					),
					'columns'       => array(
						'type'    => 'number',
						'default' => 3,
					),
					'cardBorderWidth'   => array( 'type' => 'number', 'default' => null ),
					'badgeBorderWidth'  => array( 'type' => 'number', 'default' => null ),
					'buttonBorderWidth' => array( 'type' => 'number', 'default' => null ),
					'cardRadiusTl'   => array( 'type' => 'number', 'default' => null ),
					'cardRadiusTr'   => array( 'type' => 'number', 'default' => null ),
					'cardRadiusBr'   => array( 'type' => 'number', 'default' => null ),
					'cardRadiusBl'   => array( 'type' => 'number', 'default' => null ),
					'imageRadiusTl'  => array( 'type' => 'number', 'default' => null ),
					'imageRadiusTr'  => array( 'type' => 'number', 'default' => null ),
					'imageRadiusBr'  => array( 'type' => 'number', 'default' => null ),
					'imageRadiusBl'  => array( 'type' => 'number', 'default' => null ),
					'badgeRadiusTl'  => array( 'type' => 'number', 'default' => null ),
					'badgeRadiusTr'  => array( 'type' => 'number', 'default' => null ),
					'badgeRadiusBr'  => array( 'type' => 'number', 'default' => null ),
					'badgeRadiusBl'  => array( 'type' => 'number', 'default' => null ),
					'buttonRadiusTl' => array( 'type' => 'number', 'default' => null ),
					'buttonRadiusTr' => array( 'type' => 'number', 'default' => null ),
					'buttonRadiusBr' => array( 'type' => 'number', 'default' => null ),
					'buttonRadiusBl' => array( 'type' => 'number', 'default' => null ),
					'cardBgColor'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'textColor'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'linkColor'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'linkHoverColor'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'borderColor'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBgColor'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonTextColor' => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBgHoverColor'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonTextHoverColor' => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBorderColor'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonBorderHoverColor' => array(
						'type'    => 'string',
						'default' => '',
					),
					'badgeBgColor'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'badgeTextColor'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'badgeBorderColor' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Render callback: maps camelCase block attributes to the shortcode's
	 * snake_case attributes and reuses the exact same rendering pipeline.
	 */
	public function render_block( $block_attrs ) {
		$mapped = array(
			'layout'         => $block_attrs['layout'] ?? 'list',
			'post_type'      => $block_attrs['postType'] ?? '',
			'post_id'        => $block_attrs['postId'] ?? 0,
			'limit'          => $block_attrs['limit'] ?? 10,
			'time'           => $block_attrs['time'] ?? 'upcoming',
			'order'          => $block_attrs['order'] ?? 'ASC',
			'show_image'     => ! empty( $block_attrs['showImage'] ) ? 'yes' : 'no',
			'show_excerpt'   => ! empty( $block_attrs['showExcerpt'] ) ? 'yes' : 'no',
			'excerpt_length' => $block_attrs['excerptLength'] ?? 20,
			'link_text'      => $block_attrs['linkText'] ?? __( 'View Event', 'pie-calendar-extended' ),
			'show_button'        => ! isset( $block_attrs['showButton'] ) || ! empty( $block_attrs['showButton'] ) ? 'yes' : 'no',
			'pagination'         => ! empty( $block_attrs['pagination'] ) ? 'yes' : 'no',
			'heading_level'      => $block_attrs['headingLevel'] ?? 'h3',
			'columns'            => $block_attrs['columns'] ?? 3,
			'card_border_width'    => $block_attrs['cardBorderWidth'] ?? '',
			'badge_border_width'   => $block_attrs['badgeBorderWidth'] ?? '',
			'button_border_width'  => $block_attrs['buttonBorderWidth'] ?? '',
			'card_radius_tl'   => $block_attrs['cardRadiusTl'] ?? '',
			'card_radius_tr'   => $block_attrs['cardRadiusTr'] ?? '',
			'card_radius_br'   => $block_attrs['cardRadiusBr'] ?? '',
			'card_radius_bl'   => $block_attrs['cardRadiusBl'] ?? '',
			'image_radius_tl'  => $block_attrs['imageRadiusTl'] ?? '',
			'image_radius_tr'  => $block_attrs['imageRadiusTr'] ?? '',
			'image_radius_br'  => $block_attrs['imageRadiusBr'] ?? '',
			'image_radius_bl'  => $block_attrs['imageRadiusBl'] ?? '',
			'badge_radius_tl'  => $block_attrs['badgeRadiusTl'] ?? '',
			'badge_radius_tr'  => $block_attrs['badgeRadiusTr'] ?? '',
			'badge_radius_br'  => $block_attrs['badgeRadiusBr'] ?? '',
			'badge_radius_bl'  => $block_attrs['badgeRadiusBl'] ?? '',
			'button_radius_tl' => $block_attrs['buttonRadiusTl'] ?? '',
			'button_radius_tr' => $block_attrs['buttonRadiusTr'] ?? '',
			'button_radius_br' => $block_attrs['buttonRadiusBr'] ?? '',
			'button_radius_bl' => $block_attrs['buttonRadiusBl'] ?? '',
			'card_bg_color'      => $block_attrs['cardBgColor'] ?? '',
			'text_color'         => $block_attrs['textColor'] ?? '',
			'link_color'         => $block_attrs['linkColor'] ?? '',
			'link_hover_color'   => $block_attrs['linkHoverColor'] ?? '',
			'border_color'       => $block_attrs['borderColor'] ?? '',
			'button_bg_color'           => $block_attrs['buttonBgColor'] ?? '',
			'button_text_color'         => $block_attrs['buttonTextColor'] ?? '',
			'button_bg_hover_color'     => $block_attrs['buttonBgHoverColor'] ?? '',
			'button_text_hover_color'   => $block_attrs['buttonTextHoverColor'] ?? '',
			'button_border_color'       => $block_attrs['buttonBorderColor'] ?? '',
			'button_border_hover_color' => $block_attrs['buttonBorderHoverColor'] ?? '',
			'badge_bg_color'     => $block_attrs['badgeBgColor'] ?? '',
			'badge_text_color'   => $block_attrs['badgeTextColor'] ?? '',
			'badge_border_color' => $block_attrs['badgeBorderColor'] ?? '',
		);

		$atts = PCL_Shortcode::normalize_atts( $mapped );

		return PCL_Shortcode::render_events( $atts );
	}
}
