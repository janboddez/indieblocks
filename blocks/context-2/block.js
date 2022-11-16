( function ( blocks, element, blockEditor, components, i18n, apiFetch ) {
	var el = element.createElement;

	var BlockControls     = blockEditor.BlockControls;
	var InnerBlocks       = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps     = blockEditor.useBlockProps;

	var CheckboxControl = components.CheckboxControl;
	var PanelBody       = components.PanelBody;
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

	var messagesBy = {
		/* translators: %s: URL of the bookmarked page. */
		'u-bookmark-of': __( 'Bookmarked %s by %s', 'indieblocks' ),
		/* translators: %s: URL of the "liked" page. */
		'u-like-of': __( 'Likes %s by %s', 'indieblocks' ),
		/* translators: %s: URL of the page being replied to. */
		'u-in-reply-to': __( 'In reply to %s by %s', 'indieblocks' ),
		/* translators: %s: URL of the "page" being reposted. */
		'u-repost-of': __( 'Reposted %s by %s', 'indieblocks' ),
	};

	blocks.registerBlockType( 'indieblocks/context-2', {
		edit: function ( props ) {
			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || '';
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';
			var kind         = props.attributes.kind;

			function updateMeta() {
				if ( customTitle && customAuthor ) {
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
					if ( ! customTitle && response.name ) {
						props.setAttributes( { title: response.name } );
					}

					if ( ! customAuthor && response.author.name ) {
						props.setAttributes( { author: response.author.name } );
					}

					clearTimeout(timeoutId);
				} ).catch( function( error ) {
					// The request timed out or otherwise failed.
				} );
			}

			var placeholderProps = {
				icon: 'format-status',
				label: __( 'IndieBlocks Context', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ! url || 'undefined' === url ) {
				placeholderProps.instructions = __( 'Add a URL and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			var titleProps = {
				label: __( 'Title', 'indieblocks' ),
				value: title,
				onChange: function( value ) { props.setAttributes( { title: value } ) },
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			var authorProps = {
				label: __( 'Author', 'indieblocks' ),
				value: author,
				onChange: ( value ) => { props.setAttributes( { author: value } ) },
			};

			if ( ! customAuthor ) {
				authorProps.readOnly = 'readonly';
			}

			return el( 'div', useBlockProps(),
				[
					el( BlockControls ),
					( props.isSelected || ! url || 'undefined' === url )
						? [
							el( InspectorControls, { key: 'inspector' },
								el( PanelBody, {
										title: __( 'Title and Author' ),
										initialOpen: true,
									},
									el( TextControl, titleProps ),
									el( CheckboxControl, {
										label: __( 'Customize title', 'indieblocks' ),
										checked: customTitle,
										onChange: ( value ) => { props.setAttributes( { customTitle: value } ) },
									} ),
									el( TextControl, authorProps ),
									el( CheckboxControl, {
										label: __( 'Customize author', 'indieblocks' ),
										checked: customAuthor,
										onChange: ( value ) => { props.setAttributes( { customAuthor: value } ) },
									} ),
								),
							),
							el( Placeholder, placeholderProps,
								[
									el( TextControl, {
										label: __( 'URL', 'indieblocks' ),
										value: url,
										onChange: ( value ) => { props.setAttributes( { url: value } ) },
										onBlur: updateMeta,
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
							),
						]
						: el( 'div', {},
							( ! url || 'undefined' === url )
								? null // Return nothing.
								: el( 'div', {
										className: 'u-in-reply-to h-cite'
									},
									el( 'i', {},
										( ! author || 'undefined' === author )
											? element.createInterpolateElement(
												// Add a period only if the "title" doesn't already end in one of these punctuation marks.
												sprintf( messages[ kind ] + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + ( title || url ) + '</a>' ),
												{
													a: el( 'a', { className: 'u-url', href: url } ),
												}
											)
											: element.createInterpolateElement(
												// Add a period only if the "title" doesn't already end in one of these punctuation marks.
												sprintf( messagesBy[ kind ], '<a>' + ( title || url ) + '</a>', '<b>' + author + '</b>' ),
												{
													a: el( 'a', { className: 'u-url', href: url } ),
													b: el( 'span', { className: 'p-author h-card' } ),
												}
											)
									)
								)
						)
				]
			);
		},
		save: function ( props ) {
			var blockProps = useBlockProps.save();

			var url    = props.attributes.url;
			var title  = props.attributes.title;
			var author = props.attributes.author;
			var kind   = props.attributes.kind;

			return el( 'div', blockProps,
				( ! url || 'undefined' === url )
					? null // Return nothing.
					: el( 'div', {
							className: kind + ' h-cite'
						},
						el( 'i', {},
							( ! author || 'undefined' === author )
								? element.createInterpolateElement(
									// Add a period only if the "title" doesn't already end in one of these punctuation marks.
									sprintf( messages[ kind ] + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + ( title || url ) + '</a>' ),
									{
										a: el( 'a', { className: 'u-url', href: url } )
									}
								)
								: element.createInterpolateElement(
									// Add a period only if the "title" doesn't already end in one of these punctuation marks.
									sprintf( messagesBy[ kind ], '<a>' + ( title || url ) + '</a>', '<span>' + author + '</span>' ),
									{
										a: el( 'a', { className: 'u-url', href: url } ),
										span: el( 'span', { className: 'p-author' } ),
									}
								)
						)
					)
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.apiFetch );
