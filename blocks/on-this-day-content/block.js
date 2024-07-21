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
				el( 'ul', {},
					el( 'li', {},
						el( 'strong', {}, __( '… in Post Year', 'indieblocks' ) ),
						el( 'ul', {},
							el( 'li', {},
								el( 'p', { className: 'entry-excerpt' }, __( 'Post Excerpt', 'indieblocks' ) ),
								el( 'span', { className: 'has-small-font-size' },
									el( 'a', { href: '#indieblocks-on-this-day-pseudo-link' }, __( 'Post Title', 'indieblocks' ) ),
								),
							),
						),
					),
				),
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.i18n
);
