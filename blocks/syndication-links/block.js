( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el             = element.createElement;
	var interpolateEl  = element.createInterpolateElement;
	var renderToString = element.renderToString;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var useEntityProp = coreData.useEntityProp;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	function render( urls ) {
		var output = '';

		urls.forEach( function( url ) {
			output += renderToString(
				interpolateEl( '<a>' + url.name + '</a>', {
					a: el( 'a', { className: 'u-syndication', href: encodeURI( url.value ) } ),
				}
			) ) + ', ';
		} );

		output = output.replace( /[,\s]+$/, '' );

		return sprintf( __( 'Also on %s', 'indieblocks' ), output );
	}

	blocks.registerBlockType( 'indieblocks/syndication-links', {
		edit: function ( props ) {
			var [ meta ] = useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );
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
				[
					el( BlockControls ),
					urls.length
						? element.RawHTML( { children: render( urls ) } )
						: props.context.postId
							? __( 'No syndication links', 'indieblocks' )
							: __( 'Syndication Links', 'indieblocks' ),
				]
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
