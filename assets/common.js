( ( element, i18n, apiFetch ) => {
	// (Global) object; holds some helper functions.
	window.IndieBlocks = {
		hCite: ( className, attributes, innerBlocks = null ) => {
			const { createElement, createInterpolateElement } = element;
			const { __, sprintf } = i18n;

			const messagesWithByline = {
				/* translators: %1$s: Link to the bookmarked page. %2$s: Author of the bookmarked page. */
				'u-bookmark-of': __( 'Bookmarked %1$s by %2$s.', 'indieblocks' ),
				/* translators: %1$s: Link to the "liked" page. %2$s: Author of the "liked" page. */
				'u-like-of': __( 'Likes %1$s by %2$s.', 'indieblocks' ),
				/* translators: %1$s: Link to the page being replied to. %2$s: Author of the page being replied to. */
				'u-in-reply-to': __( 'In reply to %1$s by %2$s.', 'indieblocks' ),
				/* translators: %1$s: Link to the "page" being reposted. %2$s: Author of the "page" being reposted. */
				'u-repost-of': __( 'Reposted %1$s by %2$s.', 'indieblocks' ),
			};

			const messages = {
				/* translators: %s: Link to the bookmarked page. */
				'u-bookmark-of': __( 'Bookmarked %s.', 'indieblocks' ),
				/* translators: %s: Link to the "liked" page. */
				'u-like-of': __( 'Likes %s.', 'indieblocks' ),
				/* translators: %s: Link to the page being replied to. */
				'u-in-reply-to': __( 'In reply to %s.', 'indieblocks' ),
				/* translators: %s: Link to the "page" being reposted. */
				'u-repost-of': __( 'Reposted %s.', 'indieblocks' ),
			};

			const message = ( ! attributes.author || 'undefined' === attributes.author )
				? messages[ className ]
				: messagesWithByline[ className ];

			const name = attributes.title || attributes.url;

			return createElement( 'div', { className: className + ' h-cite' },
				createElement( 'p', {}, // Adding paragraphs this time around.
					createElement( 'i', {}, // Could've been `span`, with a `className` or something, but works well enough.
						( ! attributes.author || 'undefined' === attributes.author )
							? createInterpolateElement(
								sprintf(  message, '<a>' + name + '</a>' ),
								{
									a: createElement( 'a', {
										className: attributes.title && attributes.url !== attributes.title
											? 'u-url p-name' // No title means no `p-name`.
											: 'u-url',
										href: attributes.url,
									} ),
								}
							)
							: createInterpolateElement(
								sprintf( message, '<a>' + name + '</a>', '<span>' + attributes.author + '</span>' ),
								{
									a: createElement( 'a', {
										className: attributes.title && attributes.url !== attributes.title
											? 'u-url p-name'
											: 'u-url',
										href: attributes.url,
									} ),
									span: createElement( 'span', { className: 'p-author' } ),
								}
							)
					),
				),
				'u-repost-of' === className && innerBlocks && ! attributes.empty
					? createElement( 'blockquote', { className: 'wp-block-quote e-content' },
						createElement( innerBlocks )
					)
					: null,
			);
		},
		/**
		 * Calls a backend function that parses a URL for microformats and the like,
		 * and sets attributes accordingly.
		 */
		updateMeta: ( props ) => {
			const url = props.attributes.url;

			if ( props.attributes.customTitle && props.attributes.customAuthor ) {
				// We're using custom values for both title and author; nothing
				// to do here.
				return;
			}

			if ( ! IndieBlocks.isValidUrl( url ) ) {
				return;
			}

			// Like a time-out.
			const controller = new AbortController();
			const timeoutId = setTimeout( () => {
				controller.abort();
			}, 6000 );

			apiFetch( {
				path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
				signal: controller.signal, // That time-out thingy.
			} ).then( ( response ) => {
				if ( ! props.attributes.customTitle && ( response.name || '' === response.name ) ) {
					// Got a, possibly empty, title.
					props.setAttributes( { title: response.name } );
				}

				if ( ! props.attributes.customAuthor && ( response.author.name || '' === response.author.name ) ) {
					// Got a, possibly empty, name.
					props.setAttributes( { author: response.author.name } );
				}

				clearTimeout( timeoutId );
			} ).catch( ( error ) => {
				// The request timed out or otherwise failed. Leave as is.
			} );
		},
		/**
		 * Validates a URL.
		 */
		isValidUrl: ( string ) => {
			try {
				new URL( string );
			} catch ( error ) {
				return false;
			}

			return true;
		},
	}
} )( window.wp.element, window.wp.i18n, window.wp.apiFetch )
