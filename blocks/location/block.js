( ( blocks, element, blockEditor, components, coreData, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el, createInterpolateElement: interpolate } = element;
	const { BlockControls, InspectorControls, useBlockProps } = blockEditor;
	const {
		BaseControl,
		PanelBody,
		ToggleControl,
		__experimentalRadio: Radio,
		__experimentalRadioGroup: RadioGroup,
	} = components;
	const { useEntityProp, useEntityRecord } = coreData;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/location', {
		edit: ( props ) => {
			const [ options ] = useEntityProp( 'root', 'site', 'indieblocks_settings' );
			const includeWeather = props.attributes.includeWeather;

			const { record, isResolving } = useEntityRecord( 'postType', props.context.postType, props.context.postId );

			const geoAddress = record?.indieblocks_location?.geo_address ?? '';
			const description = record?.indieblocks_location?.weather?.description ?? '';

			let temp = includeWeather ? record?.indieblocks_location?.weather?.temperature ?? null : null;
			let tempUnit;

			if ( temp ) {
				temp = temp > 100 ? temp - 273.15 : temp; // Either degrees Celsius or Kelvin.

				if (
					'undefined' === typeof options ||
					! options.hasOwnProperty( 'weather_units' ) ||
					'metric' === options.weather_units
				) {
					tempUnit = ' °C';
				} else {
					temp = 32 + ( temp * 9 ) / 5;
					tempUnit = ' °F';
				}

				temp = Math.round( temp );
			}

			const sep = props.attributes.separator;

			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{
							title: __( 'Location', 'indieblocks' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'Display weather information', 'indieblocks' ),
							checked: includeWeather,
							onChange: ( value ) => {
								props.setAttributes( {
									includeWeather: value,
								} );
							},
						} ),
						el(
							BaseControl,
							{},
							el( BaseControl.VisualLabel, { style: { display: 'block' } }, __( 'Separator' ) ),
							el(
								RadioGroup,
								{
									label: __( 'Separator', 'indieblocks' ),
									checked: sep,
									onChange: ( value ) => {
										props.setAttributes( {
											separator: value,
										} );
									},
								},
								el(
									Radio,
									{
										value: ' • ',
										style: { paddingInline: '1.25em' },
									},
									'•'
								),
								el(
									Radio,
									{
										value: ' | ',
										style: { paddingInline: '1.25em' },
									},
									'|'
								),
								el(
									Radio,
									{
										value: ', ',
										style: { paddingInline: '1.25em' },
									},
									','
								),
								el(
									Radio,
									{
										value: '; ',
										style: { paddingInline: '1.25em' },
									},
									';'
								)
							)
						)
					)
				),
				'' !== geoAddress
					? el(
							'span',
							{ className: 'h-geo' },
							temp
								? interpolate(
										'<a>' +
											geoAddress +
											'</a><b>' +
											sep +
											'</b><c>' +
											temp +
											tempUnit +
											', ' +
											description.toLowerCase() +
											'</c>',
										{
											a: el( 'span', {
												className: 'p-name',
											} ),
											b: el( 'span', {
												className: 'sep',
												'aria-hidden': 'true',
											} ),
											c: el( 'span', {} ),
										}
								  )
								: el(
										'span',
										{
											className: 'p-name',
										},
										geoAddress
								  )
					  )
					: props.context.postId
					? __( 'No location', 'indieblocks' )
					: __( 'Location', 'indieblocks' )
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.coreData,
	window.wp.i18n
);
