var IndieBlocks = {
	hCite: function( className, attributes, innerBlocks = null ) {
		var el          = window.wp.element.createElement;
		var interpolate = window.wp.element.createInterpolateElement;

		var __      = window.wp.i18n.__;
		var sprintf = window.wp.i18n.sprintf;

		var messagesWithByline = {
			/* translators: %1$s: Link to the bookmarked page. %2$s: Author of the bookmarked page. */
			'u-bookmark-of': __( 'Bookmarked %1$s by %2$s.', 'indieblocks' ),
			/* translators: %1$s: Link to the "liked" page. %2$s: Author of the "liked" page. */
			'u-like-of': __( 'Likes %1$s by %2$s.', 'indieblocks' ),
			/* translators: %1$s: Link to the page being replied to. %2$s: Author of the page being replied to. */
			'u-in-reply-to': __( 'In reply to %1$s by %2$s.', 'indieblocks' ),
			/* translators: %1$s: Link to the "page" being reposted. %2$s: Author of the "page" being reposted. */
			'u-repost-of': __( 'Reposted %1$s by %2$s.', 'indieblocks' ),
		};

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

		var message = ( ! attributes.author || 'undefined' === attributes.author )
			? messages[ className ]
			: messagesWithByline[ className ];

		var name = attributes.title || attributes.url;

		return el( 'div', { className: className + ' h-cite' },
			el( 'p', {}, // Adding paragraphs this time around.
				el( 'i', {}, // Could've been `span`, with a `className` or something, but works well enough.
					( ! attributes.author || 'undefined' === attributes.author )
						? interpolate(
							sprintf(  message, '<a>' + name + '</a>' ),
							{
								a: el( 'a', {
									className: attributes.title && attributes.url !== attributes.title
										? 'u-url p-name' // No title means no `p-name`.
										: 'u-url',
									href: attributes.url,
								} ),
							}
						)
						: interpolate(
							sprintf( message, '<a>' + name + '</a>', '<span>' + attributes.author + '</span>' ),
							{
								a: el( 'a', {
									className: attributes.title && attributes.url !== attributes.title
										? 'u-url p-name'
										: 'u-url',
									href: attributes.url,
								} ),
								span: el( 'span', { className: 'p-author' } ),
							}
						)
				),
			),
			'u-repost-of' === className && innerBlocks && ! attributes.empty
				? el( 'blockquote', { className: 'wp-block-quote e-content' },
					el( innerBlocks )
				)
				: null,
		);
	},
	/**
	 * Calls a backend function that parses a URL for microformats and the like,
	 * and sets attributes accordingly.
	 */
	updateMeta: function( props ) {
		var url = props.attributes.url;

		if ( props.attributes.customTitle && props.attributes.customAuthor ) {
			// We're using custom values for both title and author;/ nothing to
			// do here.
			return;
		}

		if ( ! IndieBlocks.isValidUrl( url ) ) {
			return;
		}

		// Like a time-out.
		var controller = new AbortController();
		var timeoutId  = setTimeout( function() {
			controller.abort();
		}, 6000 );

		window.wp.apiFetch( {
			path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
			signal: controller.signal, // That time-out thingy.
		} ).then( function( response ) {
			if ( ! props.attributes.customTitle && ( response.name || '' === response.name ) ) {
				// Got a, possibly empty, title.
				props.setAttributes( { title: response.name } );
			}

			if ( ! props.attributes.customAuthor && ( response.author.name || '' === response.author.name ) ) {
				// Got a, possibly empty, name.
				props.setAttributes( { author: response.author.name } );
			}

			clearTimeout( timeoutId );
		} ).catch( function( error ) {
			// The request timed out or otherwise failed. Leave as is.
		} );
	},
	/**
	 * Validates a URL.
	 */
	isValidUrl: function( string ) {
		try {
			new URL( string );
		} catch ( error ) {
			return false;
		}

		return true;
	},
}
