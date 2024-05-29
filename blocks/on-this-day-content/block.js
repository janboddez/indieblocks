( ( blocks, element, blockEditor, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el } = element;
	const { BlockControls, useBlockProps } = blockEditor;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/on-this-day-content', {
		edit: ( props ) => {
			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				__( 'On This Day Content', 'indieblocks' )
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.i18n
);
