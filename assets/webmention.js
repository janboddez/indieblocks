( ( element, components, i18n, data, coreData, plugins, editPost ) => {
	const { createElement, createInterpolateElement, useEffect, useState } = element;
	const { Button, PanelBody } = components;
	const { __, sprintf } = i18n;
	const { useSelect } = data;
	const { registerPlugin } = plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = editPost;

	if ( '1' === indieblocks_webmention_obj.show_meta_box ) {
		// Gutenberg sidebar.
		registerPlugin( 'indieblocks-webmention-sidebar', {
			icon: createElement( 'svg', {
					xmlns: 'http://www.w3.org/2000/svg',
					viewBox: '0 0 24 24',
				}, createElement( 'path', {
					d: 'm13.91 18.089-1.894-5.792h-.032l-1.863 5.792H7.633L4.674 6.905h2.458L8.9 14.518h.032l1.94-5.793h2.302l1.91 5.886h.031L16.48 8.73l-1.778-.004L18.387 5.3l2.287 3.43-1.81-.001-2.513 9.36z',
				} )
			),
			render: () => {
				const { postId, postType } = useSelect( ( select ) => {
					return {
						postId: select( 'core/editor' ).getCurrentPostId(),
						postType: select( 'core/editor' ).getCurrentPostType()
					}
					}, [] );

				const { record, isResolving } = coreData.useEntityRecord( 'postType', postType, postId );
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
							line = sprintf( __( 'Sent to %1$s: %2$d.', 'indieblocks' ), '<a>' + value.endpoint + '</a>', value.code );
							line = createElement( 'p', {},
									createInterpolateElement( line, {
										a: createElement( 'a', {
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
								line = sprintf( __( 'Could not send webmention to %s.', 'indieblocks' ), value.endpoint );
								line = createElement( 'p', {},
										createInterpolateElement( line, {
											a: createElement( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
										} )
								);
								output.push( line );
							} else {
								line = sprintf( __( 'Could not send webmention to %s. Trying again soon.', 'indieblocks' ), value.endpoint );
								line = createElement( 'p', {},
										createInterpolateElement( line, {
											a: createElement( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
										} )
								);
								output.push( line );
							}
						}
					} );
				} else if ( 'scheduled' === webmention ) {
					line = createElement( 'p', {}, __( 'Webmention scheduled.', 'indieblocks' ) );
					output.push( line );
				}

				if ( ! output.length ) {
					// return;
					output.push( createElement( 'p', {}, __( 'No endpoints found.', 'indieblocks' ) ) );
				}

				return [
					createElement( PluginSidebarMoreMenuItem , { target: 'indieblocks-webmention-sidebar' }, __( 'Webmention', 'indieblocks' ) ),
					createElement( PluginSidebar, {
							icon: createElement( 'svg', {
									xmlns: 'http://www.w3.org/2000/svg',
									viewBox: '0 0 24 24',
								}, createElement( 'path', {
									d: 'm13.91 18.089-1.894-5.792h-.032l-1.863 5.792H7.633L4.674 6.905h2.458L8.9 14.518h.032l1.94-5.793h2.302l1.91 5.886h.031L16.48 8.73l-1.778-.004L18.387 5.3l2.287 3.43-1.81-.001-2.513 9.36z',
								} )
							),
							name: 'indieblocks-webmention-sidebar',
							title: __( 'Webmention', 'indieblocks' ),
						},
						createElement( PanelBody, {},
							output,
							createElement( 'p', {},
								createElement( Button, {
									onClick: () => {
										return;
									},
									variant: 'secondary',
								}, __( 'Resend', 'indieblocks' ) ),
							)
						)
					)
				];
			},
		} );
	}
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins, window.wp.editPost );
