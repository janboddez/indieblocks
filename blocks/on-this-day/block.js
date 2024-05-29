( ( blocks, element, blockEditor, i18n ) => {
	const { registerBlockType } = blocks;
	const { createElement: el } = element;
	const { BlockControls, InnerBlocks, useBlockProps } = blockEditor;
	const { __ } = i18n;

	registerBlockType( 'indieblocks/on-this-day', {
		edit: ( props ) => {
			return el(
				'div',
				useBlockProps(),
				el( BlockControls ),
				el( InnerBlocks, {
					template: [
						[
							'core/heading',
							{
								content: __( 'On This Day', 'indieblocks' ),
							},
						],
						[ 'indieblocks/on-this-day-content' ],
					],
					templateLock: false,
				} )
			);
		},
		save: ( props ) => el( 'div', useBlockProps.save(), el( InnerBlocks.Content ) ),
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.i18n
);
