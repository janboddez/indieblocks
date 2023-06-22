( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el = element.createElement;

	var BlockControls  = blockEditor.BlockControls;
	var useBlockProps  = blockEditor.useBlockProps;
	var useBorderProps = blockEditor.__experimentalUseBorderProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/link-preview', {
		edit: function ( props ) {
			// We'd use `serverSideRender` but it doesn't support passing block
			// context to PHP. I.e., rendering in JS better reflects what the
			// block will look like on the front end.
			// @see https://github.com/WordPress/gutenberg/issues/40714
			var [ meta ] = coreData.useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );

			var title     = '';
			var url       = '';
			var thumbnail = '';

			if ( 'undefined' !== typeof meta && meta.hasOwnProperty( '_indieblocks_link_preview' ) ) {
				var card = meta._indieblocks_link_preview;

				if ( card.hasOwnProperty( 'title' ) && card.title.length ) {
					title = card.title;
				}

				if ( card.hasOwnProperty( 'url' ) && card.url.length ) {
					url = card.url;
				}

				if ( card.hasOwnProperty( 'thumbnail' ) && card.thumbnail.length ) {
					thumbnail = card.thumbnail;
				}
			}

			var borderProps = useBorderProps( props.attributes );
			var bodyProps = { className: 'indieblocks-card-body' };
			if ( 'undefined' !== typeof borderProps.style && 'undefined' !== typeof borderProps.style.borderWidth ) {
				bodyProps.style = { width: 'calc(100% - 90px - ' + borderProps.style.borderWidth + ')' };
			}

			var blockProps = useBlockProps();

			return el( 'div', { ...blockProps, style: borderProps.style },
				el( BlockControls ),
				title.length && url.length
					? el( 'a', { className: 'indieblocks-card' },
						el( 'div', { className: 'indieblocks-card-thumbnail', style: { ...borderProps.style, borderBlock: 'none', borderInlineStart: 'none', borderRadius: '0 !important' } },
							thumbnail
								? el( 'img', { src: thumbnail, width: 90, height: 90, alt: '' } )
								: null
						),
						el( 'div', bodyProps,
							el( 'strong', {}, title ),
							el( 'small', {}, ( new URL( url ) ).hostname.replace( /^www\./, '' ) )
						)
					)
					: el( 'div', { className: 'indieblocks-card' },
						el( 'div', { className: 'indieblocks-card-thumbnail', style: { ...borderProps.style, borderBlock: 'none', borderInlineStart: 'none', borderRadius: '0 !important' } } ),
						el( 'div', bodyProps,
							el( 'strong', { style: { fontWeight: 'normal' } }, props.context.postId ? __( 'No link preview card', 'indieblocks' ) : __( 'Link Preview', 'indieblocks' ) )
						)
					)
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
