( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	blocks.registerBlockType( 'indieblocks/facepile-content', {
		icon: el( 'svg', {
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
			}, el ( 'path', {
				d: 'M12 4a8 8 0 0 0-8 8 8 8 0 0 0 6.64 7.883 8 8 0 0 0 .786.096A8 8 0 0 0 12 20a8 8 0 0 0 8-8 8 8 0 0 0-8-8zm0 1.5a6.5 6.5 0 0 1 6.5 6.5 6.5 6.5 0 0 1-.678 2.875 12.5 9 0 0 0-4.576-.855 3.5 3.5 0 0 0 2.254-3.27 3.5 3.5 0 0 0-3.5-3.5 3.5 3.5 0 0 0-3.5 3.5 3.5 3.5 0 0 0 2.432 3.332 12.5 9 0 0 0-4.59 1.1A6.5 6.5 0 0 1 5.5 12 6.5 6.5 0 0 1 12 5.5z',
			} )
		),
		description: __( 'Outputs the actual “facepile” avatars.', 'indieblocks' ),
		edit: ( props ) => {
			var bookmark = props.attributes.bookmark;
			var like     = props.attributes.like;
			var repost   = props.attributes.repost;

			var imgProps = {
				src: indieblocks_common_obj.assets_url + 'mystery-man.jpg',
				className: 'avatar avatar-40 photo',
				width: 40,
				height: 40,
				alt: '',
			};

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				el( 'ul', {},
					el( 'li', {}, el( 'span', {},	el( 'img', imgProps ) ) ),
					el( 'li', {}, el( 'span', {},	el( 'img', imgProps ) ) ),
					el( 'li', {}, el( 'span', {},	el( 'img', imgProps ) ) )
				)
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
