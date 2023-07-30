( function ( blocks, element, blockEditor, components, coreData, i18n ) {
	var el          = element.createElement;
	var interpolate = element.createInterpolateElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var Radio      = components.__experimentalRadio;
	var RadioGroup = components.__experimentalRadioGroup;

	var __ = i18n.__;

	blocks.registerBlockType( 'indieblocks/location', {
		edit: function ( props ) {
			var [ options ]    = coreData.useEntityProp( 'root', 'site', 'indieblocks_settings' );
			var includeWeather = props.attributes.includeWeather;

			const { record, isResolving } = coreData.useEntityRecord( 'postType', props.context.postType, props.context.postId );

			var geoAddress  = record?.indieblocks_location?.geo_address ?? '';
			var description = record?.indieblocks_location?.weather?.description ?? '';
			var temp        = includeWeather
				? ( record?.indieblocks_location?.weather?.temperature ?? null )
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
				'' !== geoAddress
					? el( 'span', { className: 'h-geo' },
						temp
							? interpolate( '<a>' + geoAddress + '</a><b>' + sep + '</b><c>' + temp + tempUnit + ', ' + description.toLowerCase() + '</c>', {
								a: el( 'span', { className: 'p-name' } ),
								b: el( 'span', { className: 'sep',  'aria-hidden': 'true' } ),
								c: el( 'span', {} )
							} )
							: el( 'span', { className: 'p-name' },
								geoAddress
							),
					)
					: props.context.postId
						? __( 'No location', 'indieblocks' )
						: __( 'Location', 'indieblocks' ),
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.coreData, window.wp.i18n );
