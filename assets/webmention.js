( ( element, components, i18n, data, coreData, plugins, editPost ) => {
	const { createElement: el, createInterpolateElement: interpolate, useState } = element;
	const { Button, PanelBody } = components;
	const { __, sprintf } = i18n;
	const { useSelect } = data;
	const { useEntityRecord } = coreData;
	const { registerPlugin } = plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = editPost;

	const reschedule = ( postId, setWebmention ) => {
		if ( ! postId ) {
			return false;
		}

		// Like a time-out.
		const controller = new AbortController();
		const timeoutId = setTimeout( () => {
			controller.abort();
		}, 6000 );

		try {
			fetch( indieblocks_webmention_obj.ajaxurl, {
				signal: controller.signal, // That time-out thingy.
				method: 'POST',
				body: new URLSearchParams( {
					action: 'indieblocks_resend_webmention',
					type: 'post',
					obj_id: postId,
					_wp_nonce: indieblocks_webmention_obj.nonce,
				} ),
			} )
				.then( ( response ) => {
					clearTimeout( timeoutId );
					setWebmention( 'scheduled' ); // So as to trigger a re-render.
				} )
				.catch( ( error ) => {
					// The request timed out or otherwise failed. Leave as is.
					throw new Error( 'The "Resend" request failed.' );
				} );
		} catch ( error ) {
			return false;
		}

		return true;
	};

	if ( '1' === indieblocks_webmention_obj.show_meta_box ) {
		// Gutenberg sidebar.
		registerPlugin( 'indieblocks-webmention-sidebar', {
			render: () => {
				const { postId, postType } = useSelect( ( select ) => {
					return {
						postId: select( 'core/editor' ).getCurrentPostId(),
						postType: select( 'core/editor' ).getCurrentPostType(),
					};
				}, [] );

				const { record, isResolving } = useEntityRecord( 'postType', postType, postId );
				const [ webmention, setWebmention ] = useState( record?.indieblocks_webmention ?? null );

				let output = [];

				// console.table( webmention );

				if ( typeof webmention === 'object' ) {
					Object.keys( webmention ).forEach( ( key ) => {
						const value = webmention[ key ];

						if ( ! value.endpoint ) {
							return;
						}

						let line = '';

						if ( value.sent ) {
							line = sprintf(
								/* translators: %1$s: Webmention endpoint. %2$s: HTTP response code. */
								__( 'Sent to %1$s: %2$d.', 'indieblocks' ),
								'<a>' + value.endpoint + '</a>',
								value.code
							);
							line = el(
								'p',
								{},
								interpolate( line, {
									a: el( 'a', {
										href: encodeURI( value.endpoint ),
										title: value.sent,
										target: '_blank',
										rel: 'noreferrer noopener',
									} ),
								} )
							);
							output.push( line );
						} else if ( value.retries ) {
							if ( value.retries >= 3 ) {
								line = sprintf(
									__( 'Could not send webmention to %s.', 'indieblocks' ),
									value.endpoint
								);
								line = el(
									'p',
									{},
									interpolate( line, {
										a: el( 'a', {
											href: encodeURI( value.endpoint ),
											target: '_blank',
											rel: 'noreferrer noopener',
										} ),
									} )
								);
								output.push( line );
							} else {
								line = sprintf(
									__( 'Could not send webmention to %s. Trying again soon.', 'indieblocks' ),
									value.endpoint
								);
								line = el(
									'p',
									{},
									interpolate( line, {
										a: el( 'a', {
											href: encodeURI( value.endpoint ),
											target: '_blank',
											rel: 'noreferrer noopener',
										} ),
									} )
								);
								output.push( line );
							}
						}
					} );
				} else if ( 'scheduled' === webmention ) {
					line = el( 'p', {}, __( 'Webmention scheduled.', 'indieblocks' ) );
					output.push( line );
				}

				if ( ! output.length ) {
					// return;
					output.push( el( 'p', {}, __( 'No endpoints found.', 'indieblocks' ) ) );
				}

				return [
					el(
						PluginSidebarMoreMenuItem,
						{ target: 'indieblocks-webmention-sidebar' },
						__( 'Webmention', 'indieblocks' )
					),
					el(
						PluginSidebar,
						{
							icon: el(
								'svg',
								{
									xmlns: 'http://www.w3.org/2000/svg',
									viewBox: '0 0 24 24',
								},
								el( 'path', {
									d: 'm13.91 18.089-1.894-5.792h-.032l-1.863 5.792H7.633L4.674 6.905h2.458L8.9 14.518h.032l1.94-5.793h2.302l1.91 5.886h.031L16.48 8.73l-1.778-.004L18.387 5.3l2.287 3.43-1.81-.001-2.513 9.36z',
								} )
							),
							name: 'indieblocks-webmention-sidebar',
							title: __( 'Webmention', 'indieblocks' ),
						},
						el(
							PanelBody,
							{},
							output,
							el(
								'p',
								{},
								el(
									Button,
									{
										onClick: () => {
											if ( confirm( __( 'Reschedule webmentions?', 'indieblocks' ) ) ) {
												reschedule( postId, setWebmention );
											}
										},
										variant: 'secondary',
									},
									__( 'Resend', 'indieblocks' )
								)
							)
						)
					),
				];
			},
		} );
	}
} )(
	window.wp.element,
	window.wp.components,
	window.wp.i18n,
	window.wp.data,
	window.wp.coreData,
	window.wp.plugins,
	window.wp.editPost
);
