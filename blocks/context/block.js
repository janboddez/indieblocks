( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var Placeholder  = components.Placeholder;
	var RadioControl = components.RadioControl;
	var TextControl  = components.TextControl;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	var messages = {
		/* translators: %s: URL of the bookmarked page. */
		'u-bookmark-of': __( 'Bookmarked %s.', 'indieblocks' ),
		/* translators: %s: URL of the "liked" page. */
		'u-like-of':     __( 'Likes %s.', 'indieblocks' ),
		/* translators: %s: URL of the page being replied to. */
		'u-in-reply-to': __( 'In reply to %s.', 'indieblocks' ),
		/* translators: %s: URL of the "page" being reposted. */
		'u-repost-of':   __( 'Reposted %s.', 'indieblocks' ),
	};

	blocks.registerBlockType( 'indieblocks/context', {
		edit: function ( props ) {
			var blockProps = {
				icon: 'format-status',
				label: __( 'Context', 'indieblocks' ),
				instructions: __( 'Add a URL and post type, and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' ),
				isColumnLayout: true,
			};

			var url  = props.attributes.url;
			var kind = props.attributes.kind;

			function onChangeUrl( newUrl ) {
				props.setAttributes( { url: newUrl } );
			}

			function onChangeKind( newKind ) {
				props.setAttributes( { kind: newKind } );
			}

			return el( 'div', useBlockProps(),
				[
					el( BlockControls ),
					( props.isSelected || '' === url || undefined === url || 'undefined' === url )
						? el( Placeholder, blockProps,
							[
								el( TextControl, {
									label: 'URL',
									value: url,
									onChange: onChangeUrl,
								} ),
								el( RadioControl, {
									label: 'Type',
									selected: kind,
									options: [
										{ label: 'Bookmark', value: 'u-bookmark-of' },
										{ label: 'Like', value: 'u-like-of' },
										{ label: 'Reply', value: 'u-in-reply-to' },
										{ label: 'Repost', value: 'u-repost-of' },
									],
									onChange: onChangeKind,
								} ),
							]
						)
						: el( 'div', blockProps,
							el( 'i', {},
								element.createInterpolateElement( sprintf( messages[ kind ], '<a>' + url + '</a>' ), {
									a: el( 'a', {
										className: kind,
										href: url,
									} ),
								} )
							)
						)
				]
			);
		},

		save: function ( props ) {
			var blockProps = useBlockProps.save();

			var url  = props.attributes.url;
			var kind = props.attributes.kind;

			return el( 'div', blockProps,
				el( 'i', {},
					element.createInterpolateElement( sprintf( messages[ kind ], '<a>' + url + '</a>' ), {
						a: el( 'a', {
							className: kind,
							href: url,
						} ),
					} )
				)
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
