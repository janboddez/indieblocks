( ( blocks, element, blockEditor, coreData, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el } = element;
	const { BlockControls, useBlockProps, __experimentalUseBorderProps } = blockEditor;
	const { useEntityRecord } = coreData;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/link-preview', {
		edit: ( props ) => {
			const { record, isResolving } = useEntityRecord( 'postType', props.context.postType, props.context.postId );

			const title = record?.indieblocks_link_preview?.title ?? '';
			const cardUrl = record?.indieblocks_link_preview?.url ?? '';
			const thumbnail = record?.indieblocks_link_preview?.thumbnail ?? '';

			const borderProps = __experimentalUseBorderProps( props.attributes );
			const bodyProps = { className: 'indieblocks-card-body' };
			if ( 'undefined' !== typeof borderProps.style && 'undefined' !== typeof borderProps.style.borderWidth ) {
				bodyProps.style = {
					width: 'calc(100% - 90px - ' + borderProps.style.borderWidth + ')',
				};
			}

			const blockProps = useBlockProps();

			return el(
				'div',
				{
					...blockProps,
					style: { ...blockProps.style, ...borderProps.style },
				},
				el( BlockControls ),
				title.length && cardUrl.length
					? el(
							'a',
							{ className: 'indieblocks-card' },
							el(
								'div',
								{
									className: 'indieblocks-card-thumbnail',
									style: {
										...borderProps.style,
										borderBlock: 'none',
										borderInlineStart: 'none',
										borderRadius: '0 !important',
									},
								},
								thumbnail
									? el( 'img', {
											src: thumbnail,
											width: 90,
											height: 90,
											alt: '',
									  } )
									: null
							),
							el(
								'div',
								bodyProps,
								el( 'strong', {}, title ),
								el( 'small', {}, new URL( cardUrl ).hostname.replace( /^www\./, '' ) )
							)
					  )
					: el(
							'div',
							{ className: 'indieblocks-card' },
							el( 'div', {
								className: 'indieblocks-card-thumbnail',
								style: {
									...borderProps.style,
									borderBlock: 'none',
									borderInlineStart: 'none',
									borderRadius: '0 !important',
								},
							} ),
							el(
								'div',
								bodyProps,
								el(
									'strong',
									{ style: { fontWeight: 'normal' } },
									props.context.postId
										? __( 'No link preview card', 'indieblocks' )
										: __( 'Link Preview', 'indieblocks' )
								)
							)
					  )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
