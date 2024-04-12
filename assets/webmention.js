( ( element, components, i18n, data, coreData, plugins ) => {
	const { createElement, createInterpolateElement, useState } = element;
	const { Button, Fill, PanelBody, PanelRow } = components;
	const { __, sprintf } = i18n;
	const { useSelect } = data;
	const { registerPlugin } = plugins;

	registerPlugin( 'indieblocks-webmention-panel', {
		render: () => {
			const { postId, postType } = useSelect( ( select ) => {
				return {
					postId: select( 'core/editor' ).getCurrentPostId(),
					postType: select( 'core/editor' ).getCurrentPostType()
				}
			 }, [] );

			const { record, isResolving } = coreData.useEntityRecord( 'postType', postType, postId );
			const [ webmention, setWebmention ] = useState( record?.indieblocks_webmention ?? [] );

			let output = [];

			if ( typeof webmention === 'object' ) {
				Object.keys( webmention ).forEach( ( key ) => {
					const value = webmention[ key ];

					if ( ! value.endpoint ) {
						return;
					}

					let line = '';

					if ( value.sent ) {
						line = sprintf( __( 'Sent to %1$s: %2$d.', 'indieblocks' ), '<a>' + value.endpoint + '</a>', value.code );
						line = createElement( PanelRow, {},
							createElement( 'p', {},
								createInterpolateElement( line, {
									a: createElement( 'a', {
										href: encodeURI( value.endpoint ),
										title: value.sent,
										target: '_blank',
										rel: 'noreferrer noopener',
									} ),
								} )
							)
						);
						output.push( line );
					} else if ( value.retries ) {
						if ( value.retries >= 3 ) {
							line = sprintf( __( 'Could not send webmention to %s.', 'indieblocks' ), value.endpoint );
							line = createElement( PanelRow, {},
								createElement( 'p', {},
									createInterpolateElement( line, {
										a: createElement( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
									} )
								)
							);
							output.push( line );
						} else {
							line = sprintf( __( 'Could not send webmention to %s. Trying again soon.', 'indieblocks' ), value.endpoint );
							line = createElement( PanelRow, {},
								createElement( 'p', {},
									createInterpolateElement( line, {
										a: createElement( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
									} )
								)
							);
							output.push( line );
						}
					}
				} );
			} else if ( 'scheduled' === webmention ) {
				line = createElement( PanelRow, {},
					createElement( 'p', {}, __( 'Webmention scheduled.', 'indieblocks' ) )
				);
				output.push( line );
			}

			if ( ! output.length ) {
				// return;
				output.push( createElement( PanelRow, {}, __( 'No endpoints found.', 'indieblocks' ) ) );
			}

			return createElement( Fill, { name: 'IndieBlocksSidebarPanelSlot' },
				createElement( PanelBody, { title: __( 'Webmention', 'indieblocks' ) },
					output,
					createElement( PanelRow, {},
						createElement( Button, {
							onClick: () => {
								return;
							},
							variant: 'secondary',
						}, __( 'Resend', 'indieblocks' ) ),
					)
				)
			);
		},
		scope: 'my-custom-scope',
	} );
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins );
