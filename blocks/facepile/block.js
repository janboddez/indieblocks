( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/facepile', {
		icon: el( 'svg', {
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			}, el ( 'path', {
				d: 'M17 4.75 15 7H7a1 1 0 0 0-1 1v.5H4.5V13H6v.5a1 1 0 0 0 1 1h2l2 5h2.5v-5H15l2 2.25h2v-12h-2z',
			} )
		),
		edit: ( props ) => {
			var title        = props.attributes.title || '';
			var titleElement = props.attributes.titleElement || '';

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( InnerBlocks, {
					template: [
						[ 'core/heading', { content: __( 'Likes, Bookmarks, and Reposts', 'indieblocks' ) } ],
						[ 'indieblocks/facepile-content' ]
					],
					templateLock: false,
				} )
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(),
			el( InnerBlocks.Content )
		),
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
