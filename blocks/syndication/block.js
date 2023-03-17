( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	function render( urls ) {
		var output = '';

		urls.forEach( function( url ) {
			output += '<a class="u-syndication" href="' + encodeURI( url.value ) + '">' + url.name + '</a>, ';
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
			var [ meta ] = coreData.useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );
			var urls     = [];

			if ( 'undefined' !== typeof meta ) {
				if ( meta._share_on_mastodon_url ) {
					urls.push( { name: __( 'Mastodon', 'indieblocks' ), value: meta._share_on_mastodon_url } );
				}

				if ( meta._share_on_pixelfed_url ) {
					urls.push( { name: __( 'Pixelfed', 'indieblocks' ), value: meta._share_on_pixelfed_url } );
				}
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
