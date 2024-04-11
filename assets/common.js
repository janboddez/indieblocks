// Renders an "IndieBlocks" sidebar for other modules and plugins to hook into.
( ( element, components, plugins, editPost, i18n ) => {
	const { createElement, Fragment } = element;
	const { SlotFillProvider, Slot } = components;
	const { registerPlugin } = plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = editPost;
	const { __ } = i18n;
	const { PluginArea } = plugins;

	const IndieBlocksSlotComponent = () => {
		return createElement( SlotFillProvider, {},
			createElement( Slot, { name: 'IndieBlocksSidebarPanelSlot' } ),
			createElement( PluginArea, { scope: 'my-custom-scope' } )
		)
	};

	// IndieBlocks sidebar. Use `addFilter( 'IndieBlocks.SidebarPanels',
	// 'indieblocks/sidebar-panels', yourFunction )` to add additional panels.
	// If IndieBlocks is active, of course.
	registerPlugin( 'indieblocks-sidebar', {
		icon: 'block-default',
		render: () => {
			return createElement( Fragment, {},
				createElement( PluginSidebarMoreMenuItem , { target: 'indieblocks-sidebar' }, __( 'IndieBlocks', 'indieblocks' ) ),
				createElement( PluginSidebar, {
						icon: 'block-default',
						name: 'indieblocks-sidebar',
						title: __( 'IndieBlocks', 'indieblocks' ),
					},
					createElement( IndieBlocksSlotComponent )
				)
			);
		},
	} );
} )( window.wp.element, window.wp.components, window.wp.plugins, window.wp.editPost, window.wp.i18n );

// (Global) class that holds some helper functions.
var IndieBlocks = {
	hCite: ( className, attributes, innerBlocks = null ) => {
		const { createElement, createInterpolateElement } = window.wp.element;
		const { __, sprintf }                             = window.wp.i18n;

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
			// We're using custom values for both title and author;/ nothing to
			// do here.
			return;
		}

		if ( ! IndieBlocks.isValidUrl( url ) ) {
			return;
		}

		// Like a time-out.
		const controller = new AbortController();
		const timeoutId  = setTimeout( function() {
			controller.abort();
		}, 6000 );

		window.wp.apiFetch( {
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
