( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el = element.createElement;

	var BlockControls  = blockEditor.BlockControls;
	var useBlockProps  = blockEditor.useBlockProps;
	var useBorderProps = blockEditor.__experimentalUseBorderProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/link-preview', {
		edit: function ( props ) {
			const { record, isResolving } = coreData.useEntityRecord( 'postType', props.context.postType, props.context.postId );

			var title     = record?.indieblocks_link_preview?.title ?? '';
			var cardUrl   = record?.indieblocks_link_preview?.url ?? '';
			var thumbnail = record?.indieblocks_link_preview?.thumbnail ?? '';

			var borderProps = useBorderProps( props.attributes );
			var bodyProps   = { className: 'indieblocks-card-body' };
			if ( 'undefined' !== typeof borderProps.style && 'undefined' !== typeof borderProps.style.borderWidth ) {
				bodyProps.style = { width: 'calc(100% - 90px - ' + borderProps.style.borderWidth + ')' };
			}

			var blockProps = useBlockProps();

			return el( 'div', { ...blockProps, style: { ...blockProps.style, ...borderProps.style } },
				el( BlockControls ),
				title.length && cardUrl.length
					? el( 'a', { className: 'indieblocks-card' },
						el( 'div', { className: 'indieblocks-card-thumbnail', style: { ...borderProps.style, borderBlock: 'none', borderInlineStart: 'none', borderRadius: '0 !important' } },
							thumbnail
								? el( 'img', { src: thumbnail, width: 90, height: 90, alt: '' } )
								: null
						),
						el( 'div', bodyProps,
							el( 'strong', {}, title ),
							el( 'small', {}, ( new URL( cardUrl ) ).hostname.replace( /^www\./, '' ) )
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
