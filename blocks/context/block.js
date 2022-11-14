( function ( blocks, element, blockEditor, components, i18n, apiFetch ) {
	var el = element.createElement;

	var BlockControls = blockEditor.BlockControls;
	var InnerBlocks   = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;

	var CheckboxControl = components.CheckboxControl;
	var Placeholder     = components.Placeholder;
	var RadioControl    = components.RadioControl;
	var TextControl     = components.TextControl;

	var __      = i18n.__;
	var sprintf = i18n.sprintf;

	var messages = {
		/* translators: %s: URL of the bookmarked page. */
		'u-bookmark-of': __( 'Bookmarked %s', 'indieblocks' ),
		/* translators: %s: URL of the "liked" page. */
		'u-like-of': __( 'Likes %s', 'indieblocks' ),
		/* translators: %s: URL of the page being replied to. */
		'u-in-reply-to': __( 'In reply to %s', 'indieblocks' ),
		/* translators: %s: URL of the "page" being reposted. */
		'u-repost-of': __( 'Reposted %s', 'indieblocks' ),
	};

	blocks.registerBlockType( 'indieblocks/context', {
		edit: function ( props ) {
			var url         = props.attributes.url;
			var customTitle = props.attributes.customTitle;
			var title       = props.attributes.title || '';
			var kind        = props.attributes.kind;

			function onChangeUrl( value ) {
				props.setAttributes( { url: value } );
			}

			function onChangeKind( value ) {
				props.setAttributes( { kind: value } );
			}

			function onChangeCustomTitle( value ) {
				props.setAttributes( { customTitle: value } );
			}

			function onChangeTitle( value ) {
				props.setAttributes( { title: value } );
			}

			function updateTitle() {
				if ( customTitle ) {
					return;
				}

				var controller = new AbortController();
				var timeoutId  = setTimeout( function() {
					controller.abort();
				}, 6000 );

				apiFetch( {
					path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
					signal: controller.signal
				} ).then( function( response ) {
					if ( ! response.name || '' === response.name ) {
						response.name = url;
					}

					props.setAttributes( { title: response.name } );

					clearTimeout(timeoutId);
				} ).catch( function( error ) {
					// The request timed out or otherwise failed.
					props.setAttributes( { title: url } );
				} );
			}

			var placeholderProps = {
				icon: 'format-status',
				label: __( 'Context', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ( ! url || 'undefined' === url ) && ( ! title || 'undefined' === title ) ) {
				placeholderProps.instructions = __( 'Add a URL and post type, and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			var titleProps = {
				label: __( 'Title', 'indieblocks' ),
				value: title,
				onChange: onChangeTitle,
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			return el( 'div', useBlockProps(),
				[
					el( BlockControls ),
					( props.isSelected || ! url || 'undefined' === url )
						? el( Placeholder, placeholderProps,
							[
								el( TextControl, {
									label: __( 'URL', 'indieblocks' ),
									value: url,
									onChange: onChangeUrl,
									onBlur: updateTitle,
								} ),
								el( TextControl, titleProps ),
								el( CheckboxControl, {
									label: __( 'Customize title', 'indieblocks' ),
									checked: customTitle,
									onChange: onChangeCustomTitle,
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
									onChange: onChangeKind,
								} ),
							]
						)
						: el( 'div', {},
							el( 'i', {},
								element.createInterpolateElement(
									// Add a period only if the "title" doesn't already end in one of these punctuation marks.
									sprintf( messages[ kind ] + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + ( '' !== title ? title : url ) + '</a>' ),
									{ a: el( 'a', { className: kind, href: url } ) }
								)
							)
						)
				]
			);
		},
		save: function ( props ) {
			var blockProps = useBlockProps.save();

			var url   = props.attributes.url;
			var title = props.attributes.title || url;
			var kind  = props.attributes.kind;

			return el( 'div', blockProps,
				( ! url || 'undefined' === url )
					? null // Return nothing.
					: el( 'i', {},
						element.createInterpolateElement(
							// Add a period only if the "title" doesn't already end in one of these punctuation marks.
							sprintf( messages[ kind ] + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + title + '</a>' ),
							{ a: el( 'a', { className: kind, href: url } ) }
						)
					)
			);
		},
		deprecated: [
			{
				save: function ( props ) {
					var url  = props.attributes.url;
					var kind = props.attributes.kind;

					var deprecatedMessages = {
						/* translators: %s: URL of the bookmarked page. */
						'u-bookmark-of': __( 'Bookmarked %s.', 'indieblocks' ),
						/* translators: %s: URL of the "liked" page. */
						'u-like-of': __( 'Liked %s.', 'indieblocks' ),
						/* translators: %s: URL of the page being replied to. */
						'u-in-reply-to': __( 'In reply to %s.', 'indieblocks' ),
						/* translators: %s: URL of the "page" being reposted. */
						'u-repost-of': __( 'Reposted %s.', 'indieblocks' ),
					};

					return el( 'div', useBlockProps.save(),
						el( 'i', {},
							element.createInterpolateElement( sprintf( deprecatedMessages[ kind ], '<a>' + url + '</a>' ), {
								a: el( 'a', {
									className: kind,
									href: url,
								} ),
							} )
						)
					);
				},
			},
		],
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.apiFetch );
