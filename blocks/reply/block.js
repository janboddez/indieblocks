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

	blocks.registerBlockType( 'indieblocks/reply', {
		edit: function ( props ) {
			var url          = props.attributes.url;
			var customTitle  = props.attributes.customTitle;
			var title        = props.attributes.title || '';
			var customAuthor = props.attributes.customAuthor;
			var author       = props.attributes.author || '';

			function onChangeUrl( value ) {
				props.setAttributes( { url: value } );
			}

			function onChangeCustomTitle( value ) {
				props.setAttributes( { customTitle: value } );
			}

			function onChangeTitle( value ) {
				props.setAttributes( { title: value } );
			}

			function onChangeCustomAuthor( value ) {
				props.setAttributes( { customAuthor: value } );
			}

			function onChangeAuthor( value ) {
				props.setAttributes( { author: value } );
			}

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
					// Defaults.
					if ( ! response.name || '' === response.name ) {
						response.name = url;
					}

					if ( ! response.author || '' === response.author ) {
						response.author = null;
					}

					if ( ! customTitle ) {
						props.setAttributes( { title: response.name } );
					}

					if ( ! customAuthor ) {
						props.setAttributes( { author: response.author } );
					}

					clearTimeout(timeoutId);
				} ).catch( function( error ) {
					// The request timed out or otherwise failed.
					props.setAttributes( { title: url } );
					props.setAttributes( { author: null } );
				} );
			}

			var placeholderProps = {
				icon: 'format-status',
				label: __( 'Reply', 'indieblocks' ),
				isColumnLayout: true,
			};

			if ( ( ! url || 'undefined' === url ) && ( ! title || 'undefined' === title ) ) {
				placeholderProps.instructions = __( 'Add a URL and have WordPress automatically generate a correctly microformatted introductory paragraph.', 'indieblocks' );
			}

			var titleProps = {
				label: __( 'Name', 'indieblocks' ),
				value: title,
				onChange: onChangeTitle,
			};

			if ( ! customTitle ) {
				titleProps.readOnly = 'readonly';
			}

			var authorProps = {
				label: __( 'Author', 'indieblocks' ),
				value: author,
				onChange: onChangeAuthor,
			};

			if ( ! customAuthor ) {
				authorProps.readOnly = 'readonly';
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
									onBlur: updateMeta,
								} ),
								el( TextControl, titleProps ),
								el( CheckboxControl, {
									label: __( 'Customize title', 'indieblocks' ),
									checked: customTitle,
									onChange: onChangeCustomTitle,
								} ),
								el( TextControl, authorProps ),
								el( CheckboxControl, {
									label: __( 'Customize author', 'indieblocks' ),
									checked: customAuthor,
									onChange: onChangeCustomAuthor,
								} ),
							]
						)
						: el( 'div', {},
							( ! url || 'undefined' === url )
								? null // Return nothing.
								: el( 'p', {
										className: 'u-in-reply-to h-cite'
									},
									el( 'i', {},
										( ! author || 'undefined' === author || '' === author )
											? element.createInterpolateElement(
												// Add a period only if the "title" doesn't already end in one of these punctuation marks.
												sprintf( __( 'In reply to %s', 'indieblocks' ) + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + title + '</a>' ),
												{ a: el( 'a', { className: 'u-url', href: url } ) }
											)
											: element.createInterpolateElement(
												// Add a period only if the "title" doesn't already end in one of these punctuation marks.
												sprintf( __( 'In reply to %s by %s.', 'indieblocks' ), '<a>' + title + '</a>', '<span>' + author + '</span>' ),
												{
													a: el( 'a', { className: 'u-url', href: url } ),
													span: el( 'span', { className: 'p-author h-card' } ),
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
			var title  = props.attributes.title || url;
			var author = props.attributes.author;

			return el( 'div', blockProps,
				( ! url || 'undefined' === url )
					? null // Return nothing.
					: el( 'p', {
							className: 'u-in-reply-to h-cite'
						},
						el( 'i', {},
							( ! author || 'undefined' === author || '' === author )
								? element.createInterpolateElement(
									// Add a period only if the "title" doesn't already end in one of these punctuation marks.
									sprintf( __( 'In reply to %s', 'indieblocks' ) + ( ! title.match( /[.,:!?]$/ ) ? '.' : '' ), '<a>' + title + '</a>' ),
									{ a: el( 'a', { className: 'u-url', href: url } ) }
								)
								: element.createInterpolateElement(
									// Add a period only if the "title" doesn't already end in one of these punctuation marks.
									sprintf( __( 'In reply to %s by %s.', 'indieblocks' ), '<a>' + title + '</a>', '<span>' + author + '</span>' ),
									{
										a: el( 'a', { className: 'u-url', href: url } ),
										span: el( 'span', { className: 'p-author h-card' } ),
									}
								)
						)
					)
			);
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.apiFetch );
