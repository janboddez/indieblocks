( ( blocks, element, blockEditor, coreData, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el, RawHTML, useState } = element;
	const { BlockControls, useBlockProps } = blockEditor;
	const { useEntityRecord } = coreData;
	const { __, sprintf } = i18n;

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

		/* translators: %s: plain-text "list" of links. */
		return sprintf( __( 'Also on %s', 'indieblocks' ), output.replace( /[,\s]+$/, '' ) );
	};

	registerBlockType( 'indieblocks/syndication', {
		edit: ( props ) => {
			// We'd use `serverSideRender` but it doesn't support passing block
			// context to PHP. I.e., rendering in JS better reflects what the
			// block will look like on the front end.
			// @see https://github.com/WordPress/gutenberg/issues/40714
			const { record, isResolving } = useEntityRecord( 'postType', props.context.postType, props.context.postId );
			const [ mastodonUrl ] = useState( record?.share_on_mastodon?.url ?? '' );
			const [ pixelfedUrl ] = useState( record?.share_on_pixelfed?.url ?? '' );

			const urls = [];

			if ( '' !== mastodonUrl ) {
				urls.push( {
					name: __( 'Mastodon', 'indieblocks' ),
					value: mastodonUrl,
				} );
			}

			if ( '' !== pixelfedUrl ) {
				urls.push( {
					name: __( 'Pixelfed', 'indieblocks' ),
					value: pixelfedUrl,
				} );
			}

			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				urls.length
					? RawHTML( { children: render( urls ) } )
					: props.context.postId
					? __( 'No syndication links', 'indieblocks' )
					: __( 'Syndication Links', 'indieblocks' )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
