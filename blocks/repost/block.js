( function ( blocks, element, blockEditor, components, data, i18n, IndieBlocks ) {
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

	blocks.registerBlockType( 'indieblocks/repost', {
		icon: el( 'svg', {
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			}, el ( 'path', {
				d: 'M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z',
			} )
		),
		description: __( 'Use the Repost block to “reblog” another (short) post verbatim while still giving credit.', 'indieblocks' ),
		edit: ( props ) => {
			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || ''; // May not be present in the saved HTML, so we need a fallback value even when `block.json` contains a default.
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';

			function updateEmpty( empty ) {
				props.setAttributes( { empty } );
			}

			var parentClientId = useSelect( ( select ) => select( 'core/block-editor' ).getBlockHierarchyRootClientId( props.clientId ) );
			var innerBlocks    = useSelect( ( select ) => select( 'core/block-editor' ).getBlocks( parentClientId ) );

			// To determine whether `.e-content` and `InnerBlocks.Content`
			// should be saved (and echoed).
			useEffect( () => {
				var empty = true;

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

				updateEmpty( empty );
			}, [ innerBlocks, updateEmpty ] );

			var placeholderProps = {
				icon: el( 'svg', {
						xmlns: 'http://www.w3.org/2000/svg',
						viewBox: '0 0 24 24',
					}, el ( 'path', {
						d: 'M7.25 6a2 2 0 0 0-2 2v6.1l-3-.1 4 4 4-4-3 .1V8h6.25l2-2zM16.75 9.9l-3 .1 4-4 4 4-3-.1V16a2 2 0 0 1-2 2H8.5l2-2h6.25z',
					} )
				),
				label: __( 'Repost', 'indieblocks' ),
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
				el( blockEditor.BlockControls ),
				( props.isSelected || ! url || 'undefined' === url )
					? el( components.Placeholder, placeholderProps,
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
									IndieBlocks.updateMeta( props );
								}
							},
							onBlur: () => { IndieBlocks.updateMeta( props ) },
						} ),
					)
					: IndieBlocks.hCite( 'u-repost-of', props.attributes ),
				el( 'blockquote', { className: 'wp-block-quote e-content' },
					el( InnerBlocks, {
						template: [ [ 'core/paragraph' ] ],
						templateLock: false,
					} ), // Always **show** (editable) `InnerBlocks`.
				),
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(),
			( ! props.attributes.url || 'undefined' === props.attributes.url )
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
						return createBlock(
							'core/group',
							attributes,
							[
								createBlock( 'core/html', { content: element.renderToString( IndieBlocks.hCite( 'u-repost-of', attributes ) ) } ),
								createBlock( 'core/quote', { className: 'e-content' }, innerBlocks ),
							]
						);
					},
				},
			],
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.i18n, window.IndieBlocks );