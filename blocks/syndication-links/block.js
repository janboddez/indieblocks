( function ( blocks, element, blockEditor, serverSideRender ) {
	var el               = element.createElement;
	var BlockControls    = blockEditor.BlockControls;
	var useBlockProps    = blockEditor.useBlockProps;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'indieblocks/syndication-links', {
		edit: function ( props ) {
			return el( 'div', useBlockProps(), [
				el( BlockControls ),
				el( ServerSideRender, {
					block: 'indieblocks/syndication-links',
					attributes: props.attributes,
				} )
			] );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender );
