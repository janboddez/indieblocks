( function ( blocks, element, blockEditor, coreData, i18n, components ) {
	var el          = element.createElement;
	var interpolate = element.createInterpolateElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var Radio      = components.__experimentalRadio;
	var RadioGroup = components.__experimentalRadioGroup;

	var __ = i18n.__;

	blocks.registerBlockType( 'indieblocks/location', {
		edit: function ( props ) {
			var [ meta ]    = coreData.useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );
			var [ options ] = coreData.useEntityProp( 'root', 'site', 'indieblocks_settings' );

			var includeWeather = props.attributes.includeWeather;
			var temp           = includeWeather && 'undefined' !== typeof meta && meta.hasOwnProperty( '_indieblocks_weather' ) && meta._indieblocks_weather.hasOwnProperty( 'temperature' )
				? meta._indieblocks_weather.temperature
				: null;
			var tempUnit;

			if ( temp ) {
				temp = temp > 100 ? temp - 273.15 : temp; // Either degrees Celsius or Kelvin.

				if ( 'undefined' === typeof options || ! options.hasOwnProperty( 'weather_units' ) || 'metric' === options.weather_units ) {
					tempUnit = ' °C';
				} else {
					temp     = 32 + temp * 9 / 5;
					tempUnit = ' °F'
				}

				temp = Math.round( temp )
			}

			var sep = props.attributes.separator;

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( blockEditor.InspectorControls, { key: 'inspector' },
					el( components.PanelBody, {
							title: __( 'Location', 'indieblocks' ),
							initialOpen: true,
						},
						el( components.ToggleControl, {
							label: __( 'Display weather information', 'indieblocks' ),
							checked: includeWeather,
							onChange: ( value ) => { props.setAttributes( { includeWeather: value } ) },
						} ),
						el( components.BaseControl, {},
							el( components.BaseControl.VisualLabel, { style: { display: 'block' } }, __( 'Separator' ) ),
							el( RadioGroup, {
									label: __( 'Separator', 'indieblocks' ),
									checked: sep,
									onChange: ( value ) => { props.setAttributes( { separator: value } ) },
								},
								el( Radio, { value: ' • ', style: { paddingInline: '1.25em' }  }, '•' ),
								el( Radio, { value: ' | ', style: { paddingInline: '1.25em' } }, '|' ),
								el( Radio, { value: ', ', style: { paddingInline: '1.25em' } }, ',' ),
								el( Radio, { value: '; ', style: { paddingInline: '1.25em' } }, ';' )
							)
						)
					)
				),
				'undefined' !== typeof meta && meta.hasOwnProperty( 'geo_address' ) && meta.geo_address.length
					? el( 'span', { className: 'h-geo' },
						temp
							? interpolate( '<a>' + meta.geo_address + '</a><b>' + sep + '</b><c>' + temp + tempUnit + ', ' + meta._indieblocks_weather.description.toLowerCase() + '</c>', {
								a: el( 'span', { className: 'p-name' } ),
								b: el( 'span', { className: 'sep',  'aria-hidden': 'true' } ),
								c: el( 'span', {} )
							} )
							: el( 'span', { className: 'p-name' },
								meta.geo_address
							),
					)
					: props.context.postId
						? __( 'No location', 'indieblocks' )
						: __( 'Location', 'indieblocks' ),
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n, window.wp.components );
