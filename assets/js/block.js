( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	// Renamed from __experimentalUseSetting in newer Gutenberg; support both.
	var useSetting = blockEditor.useSetting || blockEditor.__experimentalUseSetting;
	var PanelBody = components.PanelBody;
	var BaseControl = components.BaseControl;
	var Dropdown = components.Dropdown;
	var ColorPalette = components.ColorPalette;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var ServerSideRender = serverSideRender;

	var ROW_STYLE = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'space-between',
		padding: '10px 4px',
		borderBottom: '1px solid #e0e0e0',
	};

	var LABEL_STYLE = { fontSize: '13px' };

	var SWATCHES_STYLE = { display: 'flex', gap: '6px' };

	function swatchStyle( value ) {
		return {
			width: '28px',
			height: '28px',
			borderRadius: '50%',
			border: '1px solid rgba(0, 0, 0, 0.15)',
			background: value || '#f0f0f0',
			cursor: 'pointer',
			padding: 0,
		};
	}

	// One customizer-style row: a label on the left and one or more clickable
	// color swatches on the right, each opening a color palette popover.
	// `entries` is an array of { value, onChange } — pass more than one to
	// show several swatches under one label (e.g. a link's normal + hover
	// colors together). Alpha is enabled so semi-transparent colors work.
	function colorRow( label, entries, palette ) {
		return el(
			'div',
			{ style: ROW_STYLE },
			el( 'span', { style: LABEL_STYLE }, label ),
			el(
				'div',
				{ style: SWATCHES_STYLE },
				entries.map( function ( entry, index ) {
					return el( Dropdown, {
						key: index,
						popoverProps: { placement: 'left-start' },
						renderToggle: function ( toggleProps ) {
							return el( 'button', {
								type: 'button',
								onClick: toggleProps.onToggle,
								'aria-expanded': toggleProps.isOpen,
								'aria-label': label,
								style: swatchStyle( entry.value ),
							} );
						},
						renderContent: function () {
							return el(
								'div',
								{ style: { padding: '8px' } },
								el( ColorPalette, {
									colors: palette,
									value: entry.value || undefined,
									enableAlpha: true,
									onChange: function ( newColor ) {
										entry.onChange( newColor || '' );
									},
								} )
							);
						},
					} );
				} )
			)
		);
	}

	// A single number (px) input. Empty clears the value back to the CSS
	// default (shown via placeholder).
	function numberField( label, value, onChange, placeholder ) {
		return el( TextControl, {
			label: label,
			type: 'number',
			min: 0,
			max: 100,
			placeholder: placeholder,
			value: ( value === undefined || value === null ) ? '' : String( value ),
			onChange: function ( v ) {
				onChange( '' === v ? undefined : parseInt( v, 10 ) );
			},
		} );
	}

	// Four per-corner border-radius inputs for one element. `prefix` is the
	// attribute prefix, e.g. 'card' → cardRadiusTl / …Tr / …Br / …Bl.
	function radiusControl( prefix, attributes, setAttributes, placeholder ) {
		function corner( suffix, cornerLabel ) {
			var attr = prefix + suffix;
			return numberField(
				cornerLabel,
				attributes[ attr ],
				function ( v ) {
					var patch = {};
					patch[ attr ] = v;
					setAttributes( patch );
				},
				placeholder
			);
		}
		return el(
			BaseControl,
			{ label: __( 'Border radius (px)', 'pie-calendar-layouts' ) },
			el(
				'div',
				{ style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' } },
				corner( 'RadiusTl', __( 'Top left', 'pie-calendar-layouts' ) ),
				corner( 'RadiusTr', __( 'Top right', 'pie-calendar-layouts' ) ),
				corner( 'RadiusBl', __( 'Bottom left', 'pie-calendar-layouts' ) ),
				corner( 'RadiusBr', __( 'Bottom right', 'pie-calendar-layouts' ) )
			)
		);
	}

	blocks.registerBlockType( 'pie-calendar-layouts/events', {
		title: __( 'Pie Calendar Layouts', 'pie-calendar-layouts' ),
		description: __(
			'Display Pie Calendar events as a list, compact feed, or a grid of cards.',
			'pie-calendar-layouts'
		),
		icon: 'calendar-alt',
		category: 'widgets',
		keywords: [ 'events', 'calendar', 'pie calendar' ],
		attributes: {
			layout: { type: 'string', default: 'list' },
			postType: { type: 'string', default: '' },
			postId: { type: 'number', default: 0 },
			limit: { type: 'number', default: 10 },
			time: { type: 'string', default: 'upcoming' },
			order: { type: 'string', default: 'ASC' },
			showImage: { type: 'boolean', default: true },
			showExcerpt: { type: 'boolean', default: true },
			excerptLength: { type: 'number', default: 20 },
			linkText: { type: 'string', default: __( 'View Event', 'pie-calendar-layouts' ) },
			showButton: { type: 'boolean', default: true },
			columns: { type: 'number', default: 3 },
			cardBorderWidth: { type: 'number' },
			badgeBorderWidth: { type: 'number' },
			buttonBorderWidth: { type: 'number' },
			cardRadiusTl: { type: 'number' },
			cardRadiusTr: { type: 'number' },
			cardRadiusBr: { type: 'number' },
			cardRadiusBl: { type: 'number' },
			imageRadiusTl: { type: 'number' },
			imageRadiusTr: { type: 'number' },
			imageRadiusBr: { type: 'number' },
			imageRadiusBl: { type: 'number' },
			badgeRadiusTl: { type: 'number' },
			badgeRadiusTr: { type: 'number' },
			badgeRadiusBr: { type: 'number' },
			badgeRadiusBl: { type: 'number' },
			buttonRadiusTl: { type: 'number' },
			buttonRadiusTr: { type: 'number' },
			buttonRadiusBr: { type: 'number' },
			buttonRadiusBl: { type: 'number' },
			cardBgColor: { type: 'string', default: '' },
			textColor: { type: 'string', default: '' },
			linkColor: { type: 'string', default: '' },
			linkHoverColor: { type: 'string', default: '' },
			borderColor: { type: 'string', default: '' },
			buttonBgColor: { type: 'string', default: '' },
			buttonTextColor: { type: 'string', default: '' },
			buttonBgHoverColor: { type: 'string', default: '' },
			buttonTextHoverColor: { type: 'string', default: '' },
			buttonBorderColor: { type: 'string', default: '' },
			buttonBorderHoverColor: { type: 'string', default: '' },
			badgeBgColor: { type: 'string', default: '' },
			badgeTextColor: { type: 'string', default: '' },
			badgeBorderColor: { type: 'string', default: '' },
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var palette = useSetting ? useSetting( 'color.palette' ) : undefined;

			function setColor( key ) {
				return function ( value ) {
					var patch = {};
					patch[ key ] = value;
					setAttributes( patch );
				};
			}

			return el(
				'div',
				blockProps,
				// Settings tab (the default InspectorControls group — what
				// to show and where the data comes from).
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Layout', 'pie-calendar-layouts' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout style', 'pie-calendar-layouts' ),
							value: attributes.layout,
							options: [
								{ label: __( 'List', 'pie-calendar-layouts' ), value: 'list' },
								{ label: __( 'Compact', 'pie-calendar-layouts' ), value: 'compact' },
								{ label: __( 'Column', 'pie-calendar-layouts' ), value: 'column' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						attributes.layout === 'column' &&
							el( SelectControl, {
								label: __( 'Columns', 'pie-calendar-layouts' ),
								value: String( attributes.columns || 3 ),
								options: [
									{ label: '1', value: '1' },
									{ label: '2', value: '2' },
									{ label: '3', value: '3' },
								],
								onChange: function ( value ) {
									setAttributes( { columns: parseInt( value, 10 ) } );
								},
							} )
					),
					el(
						PanelBody,
						{ title: __( 'Query', 'pie-calendar-layouts' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Only show this event (Post ID)', 'pie-calendar-layouts' ),
							help: __( 'Leave blank to show all events. Find the ID in the event\'s edit-screen URL (post=123). Shows that one event\'s own occurrences, including any recurrences.', 'pie-calendar-layouts' ),
							type: 'number',
							value: attributes.postId ? String( attributes.postId ) : '',
							onChange: function ( value ) {
								setAttributes( { postId: parseInt( value, 10 ) || 0 } );
							},
						} ),
						! attributes.postId &&
							el( TextControl, {
								label: __( 'Limit to post type (optional)', 'pie-calendar-layouts' ),
								help: __( 'Leave blank to include events of any post type.', 'pie-calendar-layouts' ),
								value: attributes.postType,
								onChange: function ( value ) {
									setAttributes( { postType: value } );
								},
							} ),
						el( SelectControl, {
							label: __( 'Which events', 'pie-calendar-layouts' ),
							value: attributes.time,
							options: [
								{ label: __( 'Upcoming', 'pie-calendar-layouts' ), value: 'upcoming' },
								{ label: __( 'Past', 'pie-calendar-layouts' ), value: 'past' },
								{ label: __( 'All', 'pie-calendar-layouts' ), value: 'all' },
							],
							onChange: function ( value ) {
								setAttributes( { time: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Order', 'pie-calendar-layouts' ),
							value: attributes.order,
							options: [
								{ label: __( 'Soonest first', 'pie-calendar-layouts' ), value: 'ASC' },
								{ label: __( 'Latest first', 'pie-calendar-layouts' ), value: 'DESC' },
							],
							onChange: function ( value ) {
								setAttributes( { order: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of events', 'pie-calendar-layouts' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 50,
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Content', 'pie-calendar-layouts' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Show featured image', 'pie-calendar-layouts' ),
							checked: attributes.showImage,
							onChange: function ( value ) {
								setAttributes( { showImage: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show excerpt', 'pie-calendar-layouts' ),
							checked: attributes.showExcerpt,
							onChange: function ( value ) {
								setAttributes( { showExcerpt: value } );
							},
						} ),
						attributes.showExcerpt &&
							el( RangeControl, {
								label: __( 'Excerpt length (words)', 'pie-calendar-layouts' ),
								value: attributes.excerptLength,
								onChange: function ( value ) {
									setAttributes( { excerptLength: value } );
								},
								min: 5,
								max: 100,
							} ),
						el( ToggleControl, {
							label: __( 'Show "View Event" button', 'pie-calendar-layouts' ),
							checked: attributes.showButton,
							onChange: function ( value ) {
								setAttributes( { showButton: value } );
							},
						} ),
						attributes.showButton &&
							el( TextControl, {
								label: __( 'Button text', 'pie-calendar-layouts' ),
								value: attributes.linkText,
								onChange: function ( value ) {
									setAttributes( { linkText: value } );
								},
							} )
					)
				),
				// Styles tab (the "styles" InspectorControls group). Grouped
				// per element so each has its own colors, border, and radius.
				// On older WordPress versions without tabbed support this just
				// appears alongside the Settings panels instead.
				el(
					InspectorControls,
					{ group: 'styles' },
					el(
						PanelBody,
						{ title: __( 'Card', 'pie-calendar-layouts' ), initialOpen: true },
						colorRow( __( 'Background', 'pie-calendar-layouts' ), [ { value: attributes.cardBgColor, onChange: setColor( 'cardBgColor' ) } ], palette ),
						colorRow( __( 'Border', 'pie-calendar-layouts' ), [ { value: attributes.borderColor, onChange: setColor( 'borderColor' ) } ], palette ),
						numberField( __( 'Border width (px)', 'pie-calendar-layouts' ), attributes.cardBorderWidth, setColor( 'cardBorderWidth' ), '1' ),
						radiusControl( 'card', attributes, setAttributes, '10' )
					),
					el(
						PanelBody,
						{ title: __( 'Text & Links', 'pie-calendar-layouts' ), initialOpen: false },
						colorRow( __( 'Text', 'pie-calendar-layouts' ), [ { value: attributes.textColor, onChange: setColor( 'textColor' ) } ], palette ),
						colorRow(
							__( 'Link', 'pie-calendar-layouts' ),
							[
								{ value: attributes.linkColor, onChange: setColor( 'linkColor' ) },
								{ value: attributes.linkHoverColor, onChange: setColor( 'linkHoverColor' ) },
							],
							palette
						)
					),
					el(
						PanelBody,
						{ title: __( 'Image', 'pie-calendar-layouts' ), initialOpen: false },
						radiusControl( 'image', attributes, setAttributes, '10' )
					),
					el(
						PanelBody,
						{ title: __( 'Date Badge', 'pie-calendar-layouts' ), initialOpen: false },
						colorRow( __( 'Background', 'pie-calendar-layouts' ), [ { value: attributes.badgeBgColor, onChange: setColor( 'badgeBgColor' ) } ], palette ),
						colorRow( __( 'Text', 'pie-calendar-layouts' ), [ { value: attributes.badgeTextColor, onChange: setColor( 'badgeTextColor' ) } ], palette ),
						colorRow( __( 'Border', 'pie-calendar-layouts' ), [ { value: attributes.badgeBorderColor, onChange: setColor( 'badgeBorderColor' ) } ], palette ),
						numberField( __( 'Border width (px)', 'pie-calendar-layouts' ), attributes.badgeBorderWidth, setColor( 'badgeBorderWidth' ), '1' ),
						radiusControl( 'badge', attributes, setAttributes, '10' )
					),
					attributes.showButton &&
						el(
							PanelBody,
							{ title: __( 'Button', 'pie-calendar-layouts' ), initialOpen: false },
							colorRow(
								__( 'Background', 'pie-calendar-layouts' ),
								[
									{ value: attributes.buttonBgColor, onChange: setColor( 'buttonBgColor' ) },
									{ value: attributes.buttonBgHoverColor, onChange: setColor( 'buttonBgHoverColor' ) },
								],
								palette
							),
							colorRow(
								__( 'Text', 'pie-calendar-layouts' ),
								[
									{ value: attributes.buttonTextColor, onChange: setColor( 'buttonTextColor' ) },
									{ value: attributes.buttonTextHoverColor, onChange: setColor( 'buttonTextHoverColor' ) },
								],
								palette
							),
							colorRow(
								__( 'Border', 'pie-calendar-layouts' ),
								[
									{ value: attributes.buttonBorderColor, onChange: setColor( 'buttonBorderColor' ) },
									{ value: attributes.buttonBorderHoverColor, onChange: setColor( 'buttonBorderHoverColor' ) },
								],
								palette
							),
							numberField( __( 'Border width (px)', 'pie-calendar-layouts' ), attributes.buttonBorderWidth, setColor( 'buttonBorderWidth' ), '2' ),
							radiusControl( 'button', attributes, setAttributes, '8' )
						)
				),
				el( ServerSideRender, {
					block: 'pie-calendar-layouts/events',
					attributes: attributes,
				} )
			);
		},

		// Rendering happens in PHP (render_callback), so nothing to save here.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
