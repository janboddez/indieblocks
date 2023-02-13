( function ( blocks, element, blockEditor, components, data, apiFetch, i18n ) {
	var createBlock = blocks.createBlock;

	var el            = element.createElement;
	var interpolateEl = element.createInterpolateElement;

	var BlockControls     = blockEditor.BlockControls;
	var InnerBlocks       = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps     = blockEditor.useBlockProps;

	var CheckboxControl = components.CheckboxControl;
	var PanelBody       = components.PanelBody;
	var Placeholder     = components.Placeholder;
	var TextControl     = components.TextControl;

	var useSelect = data.useSelect;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	/**
	 * Returns a microformatted introductory paragraph.
	 */
	function renderPara( attributes ) {
		return  el( 'p', {},
			el( 'i', {},
				( ! attributes.author || 'undefined' === attributes.author )
					? interpolateEl(
						/* translators: %s: Link to the "page" being reposted. */
						sprintf( __( 'Reposted %s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>' ),
						{
							a: el( 'a', {
								className: attributes.title || attributes.name !== attributes.url ? 'u-url p-name' : 'u-url',
								href: attributes.url,
							} ),
						}
					)
					: interpolateEl(
						/* translators: %1$s: Link to the "page" being reposted. %2$s: Author of the web page being replied to. */
						sprintf( __( 'Reposted %1$s by %2$s.', 'indieblocks' ), '<a>' + ( attributes.title || attributes.url ) + '</a>', '<span>' + attributes.author + '</span>' ),
						{
							a: el( 'a', {
								className: attributes.title || attributes.name !== attributes.url ? 'u-url p-name' : 'u-url',
								href: attributes.url,
							} ),
							span: el( 'span', { className: 'p-author' } ),
						}
					)
			)
		);
	}

	/**
	 * Returns `div.h-cite`, with or without inner `e-content`.
	 */
	function hCite( attributes, save = false ) {
		return el( 'div', { className: 'u-repost-of h-cite' },
			save
				? [
					renderPara( attributes ),
					el ( InnerBlocks.Content ), // By default contains one Quote block with `e-content` class.
				]
				: renderPara( attributes )
		);
	}

	/**
	 * Renders our block, including typography classes and whatnot, with or
	 * without inner `e-content`.
	 */
	function render( blockProps, attributes, save = false ) {
		return el( 'div', blockProps,
			( ! attributes.url || 'undefined' === attributes.url )
				? null // Can't do much without a URL.
				: hCite( attributes, save )
		);
	}

	blocks.registerBlockType( 'indieblocks/repost', {
		edit: function ( props ) {
			function updateMeta() {
				if ( customTitle && customAuthor ) {
					return;
				}

				var controller = new AbortController();
				var timeoutId  = setTimeout( function() {
					controller.abort();
				}, 6000 );

				apiFetch( {
					path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
					signal: controller.signal
				} ).then( function( response ) {
					if ( ! customTitle && ( response.name || '' === response.name ) ) {
						props.setAttributes( { title: response.name } );
					}

					if ( ! customAuthor && ( response.author.name || '' === response.author.name ) ) {
						props.setAttributes( { author: response.author.name } );
					}

					clearTimeout( timeoutId );
				} ).catch( function( error ) {
					// The request timed out or otherwise failed. Leave as is.
				} );
			}

			var is_inner_block_selected = useSelect(
				( select ) => select( 'core/block-editor' ).hasSelectedInnerBlock( props.clientId, true )
			);

			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || '';
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';

			var placeholderProps = {
				icon: 'update',
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
				[
					el( BlockControls ),
					( props.isSelected || is_inner_block_selected || ! url || 'undefined' === url )
						? el( Placeholder, placeholderProps,
							[
								el( InspectorControls, { key: 'inspector' },
									el( PanelBody, {
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
									onBlur: updateMeta,
								} ),
							]
						)
						: render( {}, props.attributes ),
					el( InnerBlocks, {
						template: [ [ 'core/quote', { className: 'e-content' } ] ],
					} ),
				]
			);
		},
		save: function ( props ) {
			return render( useBlockProps.save(), props.attributes, true );
		},
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
								createBlock( 'core/quote', { className: 'e-content' }, innerBlocks ),
							]
						);
					},
				},
			],
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.apiFetch, window.wp.i18n );
