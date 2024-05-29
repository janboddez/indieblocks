( ( blocks, element, blockEditor, components, coreData, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el, RawHTML, useState } = element;
	const { BlockControls, InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, TextControl } = components;
	const { useEntityRecord } = coreData;
	const { __ } = i18n;

	const render = ( urls ) => {
		let output = '';

		urls.forEach( ( url ) => {
			output +=
				'<a class="u-syndication" href="' +
				encodeURI( url.value ) +
				'" target="_blank" rel="noopener noreferrer">' +
				url.name +
				'</a>, ';
		} );

		return output.replace( /[,\s]+$/, '' );
	};

	registerBlockType( 'indieblocks/syndication', {
		edit: ( props ) => {
			const prefix = props.attributes?.prefix ?? '';
			const suffix = props.attributes?.suffix ?? '';

			// We'd use `serverSideRender` but it doesn't support passing block
			// context to PHP. I.e., rendering in JS better reflects what the
			// block will look like on the front end.
			// @see https://github.com/WordPress/gutenberg/issues/40714
			const { record, isResolving } = useEntityRecord( 'postType', props.context.postType, props.context.postId );
			const [ mastodonUrl ] = useState( record?.share_on_mastodon?.url ?? '' );
			const [ pixelfedUrl ] = useState( record?.share_on_pixelfed?.url ?? '' );

			const urls = [];

			if ( mastodonUrl ) {
				urls.push( {
					name: __( 'Mastodon', 'indieblocks' ),
					value: mastodonUrl,
				} );
			}

			if ( pixelfedUrl ) {
				urls.push( {
					name: __( 'Pixelfed', 'indieblocks' ),
					value: pixelfedUrl,
				} );
			}

			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				props.isSelected
					? el(
							InspectorControls,
							{ key: 'inspector' },
							el(
								PanelBody,
								{
									title: __( 'Prefix and Suffix', 'indieblocks' ),
									initialOpen: true,
								},
								// @todo: Base these on "proper" `RichText` instances or something.
								el( TextControl, {
									label: __( 'Prefix', 'indieblocks' ),
									value: prefix,
									onChange: ( prefix ) => {
										props.setAttributes( { prefix } );
									},
								} ),
								el( TextControl, {
									label: __( 'Suffix', 'indieblocks' ),
									value: suffix,
									onChange: ( suffix ) => {
										props.setAttributes( { suffix } );
									},
								} )
							)
					  )
					: null,
				! props.context.postId
					? __( 'Syndication Links', 'indieblocks' )
					: urls.length
					? RawHTML( { children: prefix + render( urls ) + suffix } )
					: prefix + __( 'Syndication Links', 'indieblocks' ) + suffix
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
