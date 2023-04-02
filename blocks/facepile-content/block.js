( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	// var ColorPicker   = components.ColorPicker;
	// var NumberControl   = components.__experimentalNumberControl;
	var ToggleControl = components.ToggleControl;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/facepile-content', {
		icon: el( 'svg', {
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			}, el ( 'path', {
				d: 'M12 4a8 8 0 0 0-8 8 8 8 0 0 0 6.64 7.883 8 8 0 0 0 .786.096A8 8 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8zm0 1.5a6.5 6.5 0 0 1 6.5 6.5 6.5 6.5 0 0 1-.678 2.875 12.5 9 0 0 0-4.576-.855 3.5 3.5 0 0 0 2.254-3.27 3.5 3.5 0 0 0-3.5-3.5 3.5 3.5 0 0 0-3.5 3.5 3.5 3.5 0 0 0 2.432 3.332 12.5 9 0 0 0-4.59 1.1A6.5 6.5 0 0 1 5.5 12 6.5 6.5 0 0 1 12 5.5z',
			} )
		),
		description: __( 'Outputs the actual “facepile” avatars.', 'indieblocks' ),
		edit: ( props ) => {
			var avatarSize = props.attributes.avatarSize || 40;
			var color      = props.attributes.color || '#000';
			var icons      = props.attributes.icons;

			var imgProps = {
				src: indieblocks_common_obj.assets_url + 'mystery-man.jpg',
				className: 'avatar avatar-' + avatarSize + ' photo',
				width: avatarSize,
				height: avatarSize,
				alt: '',
			};

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( blockEditor.InspectorControls, { key: 'inspector' },
					el( components.PanelBody, {
							title: __( 'Avatars', 'indieblocks' ),
							initialOpen: true,
						},
						// el( NumberControl, {
						// 	label: __( 'Avatar size', 'indieblocks' ),
						// 	value: avatarSize,
						// 	onChange: ( value ) => { props.setAttributes( { avatarSize: value } ) },
						// } ),
						el( ToggleControl, {
							label: __( 'Show icons', 'indieblocks' ),
							checked: icons,
							onChange: ( value ) => { props.setAttributes( { icons: value } ) },
						} ),
						// el( ColorPicker, {
						// 	label: __( 'Icon color', 'indieblocks' ),
						// 	color: color,
						// 	onChange: ( value ) => { props.setAttributes( { color: value } ) },
						// } ),
					)
				),
				el( 'ul', {},
					el( 'li', {}, el( 'span', {}, el( 'img', imgProps ) ) ),
					el( 'li', {}, el( 'span', {},
						el( 'img', imgProps ),
						icons
							? el( 'img', {
								src: indieblocks_common_obj.assets_url + 'bookmark.svg',
								className: 'icon',
								width: 16,
								height: 16,
							} )
							: null
					) ),
					el( 'li', {}, el( 'span', {},
						el( 'img', imgProps ),
						icons
							? el( 'img', {
								src: indieblocks_common_obj.assets_url + 'like.svg',
								className: 'icon',
								width: 16,
								height: 16,
							} )
							: null
					) )
				)
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
