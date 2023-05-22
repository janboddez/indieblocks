( function ( blocks, element, blockEditor, coreData, i18n, components ) {
	var el          = element.createElement;
	var interpolate = element.createInterpolateElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var __ = i18n.__;

	blocks.registerBlockType( 'indieblocks/location', {
		edit: function ( props ) {
			var [ meta ]    = coreData.useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );
			var [ options ] = coreData.useEntityProp( 'root', 'site', 'indieblocks_settings' );

			var includeWeather = props.attributes.includeWeather;
			var temp           = includeWeather && meta._indieblocks_weather && meta._indieblocks_weather.description && meta._indieblocks_weather.temperature
				? meta._indieblocks_weather.temperature
				: null;
			var tempUnit;

			if ( temp ) {
				temp = temp > 100 ? temp - 273.15 : temp; // Either degrees Celsius or Kelvin.

				if ( ! options || ! options.weather_units || 'metric' === options.weather_units ) {
					tempUnit = ' °C';
				} else {
					temp     = 32 + temp * 9 / 5;
					tempUnit = ' °F'
				}

				temp = Math.round( temp )
			}

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
						} )
					)
				),
				'undefined' !== typeof meta && meta.geo_address
					? el( 'span', { className: 'h-geo' },
						temp
							? interpolate( '<a>' + meta.geo_address + '</a> <b>•</b> <c>' + temp + tempUnit + ', ' + meta._indieblocks_weather.description.toLowerCase() + '</c>', {
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
