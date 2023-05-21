( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var __ = i18n.__;

	blocks.registerBlockType( 'indieblocks/location', {
		edit: function ( props ) {
			var [ meta ] = coreData.useEntityProp( 'postType', props.context.postType, 'meta', props.context.postId );
			var urls     = [];

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				'undefined' !== typeof meta && meta.geo_address
					? el( 'span', { className: 'h-geo' },
						el( 'span', { className: 'p-name' },
							meta.geo_address
						)
					)
					: props.context.postId
						? __( 'No location', 'indieblocks' )
						: __( 'Location', 'indieblocks' ),
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
