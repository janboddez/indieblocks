( ( blocks, element, blockEditor, components, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el, RawHTML } = element;
	const { BlockControls, InspectorControls, useBlockProps, useSetting } = blockEditor;
	const { BaseControl, CheckboxControl, ColorPalette, PanelBody, RangeControl, ToggleControl } = components;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/facepile-content', {
		icon: el(
			'svg',
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			},
			el( 'path', {
				d: 'M12 4a8 8 0 0 0-8 8 8 8 0 0 0 6.64 7.883 8 8 0 0 0 .786.096A8 8 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8zm0 1.5a6.5 6.5 0 0 1 6.5 6.5 6.5 6.5 0 0 1-.678 2.875 12.5 9 0 0 0-4.576-.855 3.5 3.5 0 0 0 2.254-3.27 3.5 3.5 0 0 0-3.5-3.5 3.5 3.5 0 0 0-3.5 3.5 3.5 3.5 0 0 0 2.432 3.332 12.5 9 0 0 0-4.59 1.1A6.5 6.5 0 0 1 5.5 12 6.5 6.5 0 0 1 12 5.5z',
			} )
		),
		edit: ( props ) => {
			const avatarSize = props.attributes.avatarSize || 2;
			const bgColor = props.attributes.backgroundColor || 'transparent';
			const icons = props.attributes.icons;
			const color = props.attributes.color || '#000';
			const iconBgColor = props.attributes.iconBackgroundColor || '#fff';
			const countOnly = props.attributes.countOnly;
			const forceShow = props.attributes.forceShow;

			let type = props.attributes.type;

			const colors = useSetting( 'color.palette' );

			const imgProps = {
				src: indieblocks_common_obj.assets_url + 'mm.png', // "Fallback" avatar.
				className: 'avatar photo',
				width: avatarSize,
				height: avatarSize,
				alt: '',
				style: { backgroundColor: bgColor },
			};

			// SVG sprites.
			const html = `<svg style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
				<defs>
				<symbol id="indieblocks-icon-bookmark" viewBox="0 0 24 24">
					<path fill="currentColor" d="M8.1 5a2 2 0 0 0-2 2v12.1L12 15l5.9 4.1V7a2 2 0 0 0-2-2H8.1z"/>
				</symbol>
				<symbol id="indieblocks-icon-like" viewBox="0 0 24 24">
					<path fill="currentColor" d="M7.785 5.24A4.536 4.536 0 0 0 3.25 9.777C3.25 14.314 9 17 12 19.5c3-2.5 8.75-5.186 8.75-9.723a4.536 4.536 0 0 0-4.535-4.537c-1.881 0-3.54 1.15-4.215 2.781-.675-1.632-2.334-2.78-4.215-2.78z"/>
				</symbol>
				<symbol id="indieblocks-icon-repost" viewBox="0 0 24 24">
					<path fill="currentColor" d="M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z"/>
				</symbol>
				</defs>
			</svg>`;

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
							title: __( 'Comment Types', 'indieblocks' ),
							initialOpen: true,
						},
						el( CheckboxControl, {
							label: __( 'Bookmark', 'indieblocks' ),
							checked: type.includes( 'bookmark' ),
							onChange: ( checked ) => {
								// Remove `bookmark`.
								type = type.filter( value => value !== 'bookmark' );

								if ( checked ) {
									// Re-add only if checked.
									type.push( 'bookmark' );
									type = type.reverse();
								}

								// Save array.
								props.setAttributes( { type } );
							},
						} ),
						el( CheckboxControl, {
							label: __( 'Like', 'indieblocks' ),
							checked: type.includes( 'like' ),
							onChange: ( checked ) => {
								// Remove `like`.
								type = type.filter( value => value !== 'like' );

								if ( checked ) {
									// Re-add only if checked.
									type.push( 'like' );
									type = type.reverse();
								}

								// Save array.
								props.setAttributes( { type } );
							},
						} ),
						el( CheckboxControl, {
							label: __( 'Repost', 'indieblocks' ),
							checked: type.includes( 'repost' ),
							onChange: ( checked ) => {
								// Remove `repost`.
								type = type.filter( value => value !== 'repost' );

								if ( checked ) {
									// Re-add only if checked.
									type.push( 'repost' );
									type = type.reverse();
								}

								// Save array.
								props.setAttributes( { type } );
							},
						} ),
					),
					el(
						PanelBody,
						{
							title: __( 'Count Only', 'indieblocks' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'Show count only', 'indieblocks' ),
							checked: countOnly,
							onChange: ( value ) => {
								props.setAttributes( { countOnly: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Always show count', 'indieblocks' ),
							checked: forceShow,
							onChange: ( value ) => {
								props.setAttributes( { forceShow: value } );
							},
						} ),
					),
					el(
						PanelBody,
						{
							title: __( 'Avatars', 'indieblocks' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Avatar size', 'indieblocks' ),
							value: avatarSize,
							min: 1,
							max: 4,
							onChange: ( value ) => {
								props.setAttributes( { avatarSize: value } );
							},
						} ),
						el(
							BaseControl,
							{ label: __( 'Background color', 'indieblocks' ) },
							el( ColorPalette, {
								colors: [ { name: 'None', color: 'transparent' }, ...colors ],
								value: bgColor,
								onChange: ( value ) => {
									props.setAttributes( {
										backgroundColor: value,
									} );
								},
							} )
						),
						el( ToggleControl, {
							label: __( 'Show icons', 'indieblocks' ),
							checked: icons,
							onChange: ( value ) => {
								props.setAttributes( { icons: value } );
							},
						} ),
						el(
							BaseControl,
							{ label: __( 'Icon color', 'indieblocks' ) },
							el( ColorPalette, {
								colors: colors,
								value: color,
								onChange: ( value ) => {
									props.setAttributes( { color: value } );
								},
							} )
						),
						el(
							BaseControl,
							{
								label: __( 'Icon background color', 'indieblocks' ),
							},
							el( ColorPalette, {
								colors: [ { name: 'None', color: 'transparent' }, ...colors ],
								value: iconBgColor,
								onChange: ( value ) => {
									props.setAttributes( {
										iconBackgroundColor: value,
									} );
								},
							} )
						)
					)
				),
				countOnly
					? el(
						'div',
						{ className: 'indieblocks-count' },
						el(
								'svg',
								{
									className: 'icon indieblocks-icon-' + type.find( Boolean ),
									width: avatarSize,
									height: avatarSize,
									style: {
										backgroundColor: iconBgColor,
										color: color,
									},
								},
								el( 'use', {
									href: '#indieblocks-icon-' + type.find( Boolean ),
									xlinkHref: '#indieblocks-icon-repost' + type.find( Boolean ),
								} )
						),
						type.length
					)
					: el(
						'ul',
						{ className: 'indieblocks-avatar-size-' + avatarSize },
						type.includes( 'repost' )
							? el(
								'li',
								{},
								el(
									'span',
									{},
									el( 'img', imgProps ),
									icons
										? el(
												'svg',
												{
													className: 'icon indieblocks-icon-repost',
													width: avatarSize,
													height: avatarSize,
													style: {
														backgroundColor: iconBgColor,
														color: color,
													},
												},
												el( 'use', {
													href: '#indieblocks-icon-repost',
													xlinkHref: '#indieblocks-icon-repost',
												} )
										)
										: null
								)
							)
							: null,
						type.includes( 'bookmark' )
							? el(
								'li',
								{},
								el(
									'span',
									{},
									el( 'img', imgProps ),
									icons
										? el(
												'svg',
												{
													className: 'icon indieblocks-icon-bookmark',
													width: avatarSize,
													height: avatarSize,
													style: {
														backgroundColor: iconBgColor,
														color: color,
													},
												},
												el( 'use', {
													href: '#indieblocks-icon-bookmark',
													xlinkHref: '#indieblocks-icon-bookmark',
												} )
										)
										: null
								)
							)
							: null,
						type.includes( 'like' )
							? el(
								'li',
								{},
								el(
									'span',
									{},
									el( 'img', imgProps ),
									icons
										? el(
												'svg',
												{
													className: 'icon indieblocks-icon-like',
													width: avatarSize,
													height: avatarSize,
													style: {
														backgroundColor: iconBgColor,
														color: color,
													},
												},
												el( 'use', {
													href: '#indieblocks-icon-like',
													xlinkHref: '#indieblocks-icon-like',
												} )
										)
										: null
								)
							)
							: null,
					),
				RawHTML( {
					children: html,
				} )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
