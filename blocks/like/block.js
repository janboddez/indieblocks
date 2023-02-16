( function ( blocks, element, blockEditor, components, data, apiFetch, i18n ) {
	var createBlock = blocks.createBlock;

	var el          = element.createElement;
	var interpolate = element.createInterpolateElement;
	var useEffect   = element.useEffect;

	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var CheckboxControl = components.CheckboxControl;
	var TextControl     = components.TextControl;

	var useSelect = data.useSelect;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	/**
	 * Returns a "like context" `div`.
	 */
	function hCite( attributes ) {
		return el( 'div', { className: 'u-like-of h-cite' },
			el( 'p', {}, // Adding paragraphs this time around.
				el( 'i', {}, // Could've been `span`, with a `className` or something, but works well enough.
					( ! attributes.author || 'undefined' === attributes.author )
						? interpolate(
						/* translators: %s: Link to the "liked" page. */
						sprintf( __( 'Liked %s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>' ),
							{
								a: el( 'a', {
									className: attributes.title && attributes.url !== attributes.title
										? 'u-url p-name' // No title means no `p-name`.
										: 'u-url',
									href: attributes.url,
								} ),
							}
						)
						: interpolate(
							/* translators: %1$s: Link to the "liked" page. %2$s: Author of the "liked" page. */
							sprintf( __( 'Liked %1$s by %2$s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>', '<span>' + attributes.author + '</span>' ),
							{
								a: el( 'a', {
									className: attributes.title && attributes.url !== attributes.title
										? 'u-url p-name'
										: 'u-url',
									href: attributes.url,
								} ),
								span: el( 'span', { className: 'p-author' } ),
							}
						)
				)
			)
		);
	}

	blocks.registerBlockType( 'indieblocks/like', {
		edit: ( props ) => {
			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || '';
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';
			var empty        = true;

			function isValidUrl( string ) {
				try {
					new URL( string );
				} catch ( error ) {
					return false;
				}

				return true;
			}

			/**
			 * Calls a backend function that parses a URL for microformats and
			 * the like.
			 */
			function updateMeta() {
				if ( customTitle && customAuthor ) {
					// We're using custom values for both title and author;
					// nothing to do here.
					return;
				}

				if ( ! isValidUrl( url ) ) {
					return;
				}

				// Like a time-out.
				var controller = new AbortController();
				var timeoutId  = setTimeout( function() {
					controller.abort();
				}, 6000 );

				apiFetch( {
					path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
					signal: controller.signal, // That time-out thingy.
				} ).then( function( response ) {
					if ( ! customTitle && ( response.name || '' === response.name ) ) {
						// Got a, possibly empty, title.
						props.setAttributes( { title: response.name } );
					}

					if ( ! customAuthor && ( response.author.name || '' === response.author.name ) ) {
						// Got a, possibly empty, name.
						props.setAttributes( { author: response.author.name } );
					}

					clearTimeout( timeoutId );
				} ).catch( function( error ) {
					// The request timed out or otherwise failed. Leave as is.
				} );
			}

			var isSelected     = /*useSelect( ( select ) => select( 'core/block-editor' ).hasSelectedInnerBlock( props.clientId, true ) ) ||*/ props.isSelected;
			var parentClientId = useSelect( ( select ) => select( 'core/block-editor' ).getBlockHierarchyRootClientId( props.clientId ) );
			var innerBlocks    = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks( parentClientId ) );

			// To determine whether `.e-content` and `InnerBlocks.Content`
			// should be saved (and echoed).
			useEffect( () => {
				if ( innerBlocks.length > 1 ) {
					// More than one child block.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && 'undefined' !== typeof innerBlocks[0].attributes.content && innerBlocks[0].attributes.content.length ) {
					// A non-empty paragraph or heading. Empty paragraphs are
					// almost unavoidable, so it's important to get this right.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && 'undefined' !== typeof innerBlocks[0].attributes.href && innerBlocks[0].attributes.href.length ) {
					// A non-empty image.
					empty = false;
				}

				if ( 'undefined' !== typeof innerBlocks[0] && innerBlocks[0].innerBlocks.length ) {
					// A quote or gallery, empty or not.
					empty = false;
				}

				props.setAttributes( { empty } )
			}, [ innerBlocks ] );

			var placeholderProps = {
				icon: 'star-filled',
				label: __( 'Like', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ! url || 'undefined' === url ) {
				placeholderProps.instructions = __( 'Add a URL and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			var titleProps = {
				label: __( 'Title', 'indieblocks' ),
				value: title,
				onChange: ( value ) => { props.setAttributes( { title: value } ) },
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			var authorProps = {
				label: __( 'Author', 'indieblocks' ),
				value: author,
				onChange: ( value ) => { props.setAttributes( { author: value } ) },
			};

			if ( ! customAuthor ) {
				authorProps.readOnly = 'readonly';
			}

			return el( 'div', useBlockProps(),
				[
					el( blockEditor.BlockControls ),
					( isSelected || ! url || 'undefined' === url )
						? el( components.Placeholder, placeholderProps,
							[
								el( blockEditor.InspectorControls, { key: 'inspector' },
									el( components.PanelBody, {
											title: __( 'Title and Author' ),
											initialOpen: true,
										},
										el( TextControl, titleProps ),
										el( CheckboxControl, {
											label: __( 'Customize title', 'indieblocks' ),
											checked: customTitle,
											onChange: ( value ) => { props.setAttributes( { customTitle: value } ) },
										} ),
										el( TextControl, authorProps ),
										el( CheckboxControl, {
											label: __( 'Customize author', 'indieblocks' ),
											checked: customAuthor,
											onChange: ( value ) => { props.setAttributes( { customAuthor: value } ) },
										} ),
									),
								),
								el( TextControl, {
									label: __( 'URL', 'indieblocks' ),
									value: url,
									onChange: ( value ) => { props.setAttributes( { url: value } ) },
									onKeyDown: ( event ) => {
										if ( 13 === event.keyCode ) {
											updateMeta();
										}
									},
									onBlur: updateMeta,
								} ),
							]
						)
						: hCite( props.attributes ),
					el( InnerBlocks, {
						allowedBlocks: [ 'core/paragraph', 'core/heading', 'core/quote', 'core/image', 'core/gallery' ],
						template: [ [ 'core/paragraph' ] ],
						templateLock: false,
					} ), // Always **show** (editable) `InnerBlocks`.
				]
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(),
			( ! props.attributes.url || 'undefined' === props.attributes.url )
				? null // Can't do much without a URL.
				: props.attributes.empty
					? hCite( props.attributes )
					: [
						hCite( props.attributes ),
						el( 'div', { className: 'e-content' },
							el( InnerBlocks.Content )
						),
					]
		),
		transforms: {
			to: [
				{
					type: 'block',
					blocks: [ 'core/group' ],
					transform: ( attributes, innerBlocks ) => {
						return createBlock(
							'core/group',
							attributes,
							[
								createBlock( 'core/html', { content: element.renderToString( hCite( attributes ) ) } ),
								createBlock( 'core/group', { className: 'e-content' }, innerBlocks ),
							]
						);
					},
				},
			],
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.apiFetch, window.wp.i18n );
