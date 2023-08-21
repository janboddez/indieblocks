( function ( blocks, element, blockEditor, coreData, i18n ) {
	const el       = element.createElement;
	const useState = element.useState;

	const BlockControls = blockEditor.BlockControls;
	const useBlockProps = blockEditor.useBlockProps;

	const __      = i18n.__;
	const sprintf = i18n.sprintf;

	function render( urls ) {
		let output = '';

		urls.forEach( function( url ) {
			output += '<a class="u-syndication" href="' + encodeURI( url.value ) + '" target="_blank" rel="noopener noreferrer">' + url.name + '</a>, ';
		} );

		/* translators: %s: plain-text "list" of links. */
		return sprintf( __( 'Also on %s', 'indieblocks' ), output.replace( /[,\s]+$/, '' ) );
	}

	blocks.registerBlockType( 'indieblocks/syndication', {
		edit: function ( props ) {
			// We'd use `serverSideRender` but it doesn't support passing block
			// context to PHP. I.e., rendering in JS better reflects what the
			// block will look like on the front end.
			// @see https://github.com/WordPress/gutenberg/issues/40714
			const { record, isResolving } = coreData.useEntityRecord( 'postType', props.context.postType, props.context.postId );
			const [ mastodonUrl ]         = useState( record?.share_on_mastodon?.url ?? '' );
			const [ pixelfedUrl ]         = useState( record?.share_on_pixelfed?.url ?? '' );

			const urls = [];

			if ( '' !== mastodonUrl ) {
				urls.push( { name: __( 'Mastodon', 'indieblocks' ), value: mastodonUrl } );
			}

			if ( '' !== pixelfedUrl ) {
				urls.push( { name: __( 'Pixelfed', 'indieblocks' ), value: pixelfedUrl } );
			}

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				urls.length
					? element.RawHTML( { children: render( urls ) } )
					: props.context.postId
						? __( 'No syndication links', 'indieblocks' )
						: __( 'Syndication Links', 'indieblocks' ),
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
