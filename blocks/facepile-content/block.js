( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	// var ColorPicker = components.ColorPicker;
	var RangeControl  = components.RangeControl;
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
			var avatarSize = props.attributes.avatarSize || 2;
			var color      = props.attributes.color || '#000';
			var icons      = props.attributes.icons;

			var imgProps = {
				src: indieblocks_common_obj.assets_url + 'mm.png',
				className: 'avatar photo',
				width: avatarSize,
				height: avatarSize,
				alt: '',
			};

			// SVG sprites.
			var html = `<svg style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
				<defs>
				<symbol id="indieblocks-icon-bookmark" viewBox="0 0 24 24">
					<path d="M8.1 5a2 2 0 0 0-2 2v12.1L12 15l5.9 4.1V7a2 2 0 0 0-2-2H8.1z"/>
				</symbol>
				<symbol id="indieblocks-icon-like" viewBox="0 0 24 24">
					<path d="M7.785 5.24A4.536 4.536 0 0 0 3.25 9.777C3.25 14.314 9 17 12 19.5c3-2.5 8.75-5.186 8.75-9.723a4.536 4.536 0 0 0-4.535-4.537c-1.881 0-3.54 1.15-4.215 2.781-.675-1.632-2.334-2.78-4.215-2.78z"/>
				</symbol>
				<symbol id="indieblocks-icon-repost" viewBox="0 0 24 24">
					<path d="M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z"/>
				</symbol>
				</defs>
			</svg>`;

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( blockEditor.InspectorControls, { key: 'inspector' },
					el( components.PanelBody, {
							title: __( 'Avatars', 'indieblocks' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Avatar size', 'indieblocks' ),
							value: avatarSize,
							min: 1,
							max: 4,
							onChange: ( value ) => { props.setAttributes( { avatarSize: value } ) },
						} ),
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
				el( 'ul', { className: 'indieblocks-avatar-size-' + avatarSize },
					el( 'li', {}, el( 'span', {},
						el( 'img', imgProps ),
						icons
							? el( 'svg', {
									className: 'icon indieblocks-icon-repost',
									width: avatarSize,
									height: avatarSize,
								},
								el( 'use', {
									href: '#indieblocks-icon-repost',
									xlinkHref: '#indieblocks-icon-repost',
								} )
							)
							: null
						) ),
					el( 'li', {}, el( 'span', {},
						el( 'img', imgProps ),
						icons
							? el( 'svg', {
									className: 'icon indieblocks-icon-bookmark',
									width: avatarSize,
									height: avatarSize,
								},
								el( 'use', {
									href: '#indieblocks-icon-bookmark',
									xlinkHref: '#indieblocks-icon-bookmark',
								} )
							)
							: null
					) ),
					el( 'li', {}, el( 'span', {},
						el( 'img', imgProps ),
						icons
							? el( 'svg', {
									className: 'icon indieblocks-icon-like',
									width: avatarSize,
									height: avatarSize,
								},
								el( 'use', {
									href: '#indieblocks-icon-like',
									xlinkHref: '#indieblocks-icon-like',
								} )
							)
							: null
					) )
				),
				element.RawHTML( {
					children: html,
				} )
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
