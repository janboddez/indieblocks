( function ( blocks, element, blockEditor, coreData, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/reactions', {
		edit: function ( props ) {
			return el( 'div', useBlockProps(),
				[
					el( BlockControls ),
					__( 'Reactions', 'indieblocks' ),
				]
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.coreData, window.wp.i18n );
