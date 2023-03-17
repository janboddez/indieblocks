( function ( blocks, element, blockEditor, components, i18n ) {
	var createBlock    = blocks.createBlock;
	var getSaveContent = blocks.getSaveContent;

	var el            = element.createElement;
	var interpolateEl = element.createInterpolateElement;

	var BlockControls = blockEditor.BlockControls;
	var useBlockProps = blockEditor.useBlockProps;

	var Placeholder  = components.Placeholder;
	var RadioControl = components.RadioControl;
	var TextControl  = components.TextControl;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	var messages = {
		/* translators: %s: Link to the bookmarked page. */
		'u-bookmark-of': __( 'Bookmarked %s.', 'indieblocks' ),
		/* translators: %s: Link to the "liked" page. */
		'u-like-of': __( 'Likes %s.', 'indieblocks' ),
		/* translators: %s: Link to the page being replied to. */
		'u-in-reply-to': __( 'In reply to %s.', 'indieblocks' ),
		/* translators: %s: Link to the "page" being reposted. */
		'u-repost-of': __( 'Reposted %s.', 'indieblocks' ),
	};

	function render( blockProps, url, kind ) {
		return el( 'div', blockProps,
			( ! url || 'undefined' === url )
				? null // Return nothing.
				: el( 'i', {},
					interpolateEl( sprintf( messages[ kind ], '<a>' + encodeURI( url ) + '</a>' ), {
						a: el( 'a', { className: kind, href: encodeURI( url ) } ),
					} )
				)
		);
	}

	blocks.registerBlockType( 'indieblocks/context', {
		edit: function ( props ) {
			var url  = props.attributes.url;
			var kind = props.attributes.kind;

			var placeholderProps = {
				icon: 'format-status',
				label: __( 'Context', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ! url || 'undefined' === url ) {
				placeholderProps.instructions = __( 'Add a URL and post type, and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			return el( 'div', useBlockProps(),
				el( BlockControls ),
				( props.isSelected || ! url || 'undefined' === url )
					? el( Placeholder, placeholderProps,
						[
							el( TextControl, {
								label: __( 'URL', 'indieblocks' ),
								value: url,
								onChange: ( value ) => { props.setAttributes( { url: value } ) },
							} ),
							el( RadioControl, {
								label: __( 'Type', 'indieblocks' ),
								selected: kind,
								options: [
									{ label: __( 'Bookmark', 'indieblocks' ), value: 'u-bookmark-of' },
									{ label: __( 'Like', 'indieblocks' ), value: 'u-like-of' },
									{ label: __( 'Reply', 'indieblocks' ), value: 'u-in-reply-to' },
									{ label: __( 'Repost', 'indieblocks' ), value: 'u-repost-of' },
								],
								onChange: ( value ) => { props.setAttributes( { kind: value } ) },
							} ),
						]
					)
					: render( {}, url, kind )
			);
		},
		save: function ( props ) {
			return render( useBlockProps.save(), props.attributes.url, props.attributes.kind );
		},
		deprecated: [
			{
				save: function ( props ) {
					var url  = props.attributes.url;
					var kind = props.attributes.kind;

					var messages = {
						/* translators: %s: Link to the bookmarked page. */
						'u-bookmark-of': __( 'Bookmarked %s.', 'indieblocks' ),
						/* translators: %s: Link to the "liked" page. */
						'u-like-of': __( 'Liked %s.', 'indieblocks' ),
						/* translators: %s: Link to the page being replied to. */
						'u-in-reply-to': __( 'In reply to %s.', 'indieblocks' ),
						/* translators: %s: Link to the "page" being reposted. */
						'u-repost-of': __( 'Reposted %s.', 'indieblocks' ),
					};

					return el( 'div', useBlockProps.save(),
						el( 'i', {},
							interpolateEl( sprintf( messages[ kind ], '<a>' + url + '</a>' ), {
								a: el( 'a', { className: kind, href: url } ),
							} )
						)
					);
				},
			},
		],
		transforms: {
			to: [
				{
					type: 'block',
					blocks: [ 'core/html' ],
					transform: ( attributes ) => {
						return createBlock( 'core/html', {
							content: getSaveContent( 'indieblocks/context', attributes ),
						} );
					},
				},
			],
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
