( ( blocks, element, blockEditor, components, data, i18n, IndieBlocks ) => {
	const { createBlock, registerBlockType } = blocks;
	const { createElement: el, renderToString, useEffect } = element;
	const { BlockControls, InnerBlocks, InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, Placeholder, ToggleControl, TextControl } = components;
	const { useSelect } = data;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/repost', {
		icon: el(
			'svg',
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			},
			el( 'path', {
				d: 'M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z',
			} )
		),
		edit: ( props ) => {
			const url = props.attributes.url;
			const customTitle = props.attributes.customTitle;
			const title = props.attributes.title || ''; // May not be present in the saved HTML, so we need a fallback value even when `block.json` contains a default.
			const customAuthor = props.attributes.customAuthor;
			const author = props.attributes.author || '';

			const updateEmpty = ( empty ) => {
				props.setAttributes( { empty } );
			};

			const { parentClientId, innerBlocks } = useSelect( ( select ) => {
				const parentClientId = select( 'core/block-editor' ).getBlockHierarchyRootClientId( props.clientId );

				return {
					parentClientId: parentClientId,
					innerBlocks: select( 'core/block-editor' ).getBlocks( parentClientId ),
				};
			}, [] );

			// To determine whether `.e-content` and `InnerBlocks.Content`
			// should be saved (and echoed).
			useEffect( () => {
				let empty = true;

				if ( innerBlocks.length > 1 ) {
					// More than one child block.
					empty = false;
				}

				if (
					'undefined' !== typeof innerBlocks[ 0 ] &&
					'undefined' !== typeof innerBlocks[ 0 ].attributes.content &&
					innerBlocks[ 0 ].attributes.content.length
				) {
					// A non-empty paragraph or heading. Empty paragraphs are
					// almost unavoidable, so it's important to get this right.
					empty = false;
				}

				if (
					'undefined' !== typeof innerBlocks[ 0 ] &&
					'undefined' !== typeof innerBlocks[ 0 ].attributes.href &&
					innerBlocks[ 0 ].attributes.href.length
				) {
					// A non-empty image.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[ 0 ] && innerBlocks[ 0 ].innerBlocks.length ) {
					// A quote or gallery, empty or not.
					empty = false;
				}

				updateEmpty( empty );
			}, [ innerBlocks, updateEmpty ] );

			const placeholderProps = {
				icon: el(
					'svg',
					{
						xmlns: 'http://www.w3.org/2000/svg',
						viewBox: '0 0 24 24',
					},
					el( 'path', {
						d: 'M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z',
					} )
				),
				label: __( 'Repost', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ! url || 'undefined' === url ) {
				placeholderProps.instructions = __(
					'Add a URL and have WordPress automatically generate a correctly microformatted introductory paragraph.',
					'indieblocks'
				);
			}

			const titleProps = {
				label: __( 'Title', 'indieblocks' ),
				value: title,
				onChange: ( value ) => {
					props.setAttributes( { title: value } );
				},
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			const authorProps = {
				label: __( 'Author', 'indieblocks' ),
				value: author,
				onChange: ( value ) => {
					props.setAttributes( { author: value } );
				},
			};

			if ( ! customAuthor ) {
				authorProps.readOnly = 'readonly';
			}

			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				props.isSelected || ! url || 'undefined' === url
					? el(
							Placeholder,
							placeholderProps,
							el(
								InspectorControls,
								{ key: 'inspector' },
								el(
									PanelBody,
									{
										title: __( 'Title and Author' ),
										initialOpen: true,
									},
									el( TextControl, titleProps ),
									el( ToggleControl, {
										label: __( 'Customize title', 'indieblocks' ),
										checked: customTitle,
										onChange: ( value ) => {
											props.setAttributes( {
												customTitle: value,
											} );
										},
									} ),
									el( TextControl, authorProps ),
									el( ToggleControl, {
										label: __( 'Customize author', 'indieblocks' ),
										checked: customAuthor,
										onChange: ( value ) => {
											props.setAttributes( {
												customAuthor: value,
											} );
										},
									} )
								)
							),
							el( TextControl, {
								label: __( 'URL', 'indieblocks' ),
								value: url,
								onChange: ( value ) => {
									props.setAttributes( { url: value } );
								},
								onKeyDown: ( event ) => {
									if ( 13 === event.keyCode ) {
										IndieBlocks.updateMeta( props );
									}
								},
								onBlur: () => {
									IndieBlocks.updateMeta( props );
								},
							} )
					  )
					: IndieBlocks.hCite( 'u-repost-of', props.attributes ),
				el(
					'blockquote',
					{ className: 'wp-block-quote is-layout-flow wp-block-quote-is-layout-flow e-content' },
					el( InnerBlocks, {
						template: [ [ 'core/paragraph' ] ],
						templateLock: false,
					} ) // Always **show** (editable) `InnerBlocks`.
				)
			);
		},
		save: ( props ) =>
			el(
				'div',
				useBlockProps.save(),
				! props.attributes.url || 'undefined' === props.attributes.url
					? null // Can't do much without a URL.
					: IndieBlocks.hCite( 'u-repost-of', props.attributes, InnerBlocks.Content )
			),
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'indieblocks/context' ],
					transform: ( { url } ) => {
						return createBlock( 'indieblocks/repost', { url } );
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/group' ],
					transform: ( attributes, innerBlocks ) => {
						return createBlock( 'core/group', attributes, [
							createBlock( 'core/html', {
								content: renderToString( IndieBlocks.hCite( 'u-repost-of', attributes ) ),
							} ),
							createBlock( 'core/quote', { className: 'e-content' }, innerBlocks ),
						] );
					},
				},
			],
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.i18n,
	window.IndieBlocks
);
