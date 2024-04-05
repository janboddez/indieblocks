( ( element, components, i18n, data, coreData, plugins, editPost, apiFetch ) => {
	const el                         = element.createElement;
	const interpolate                = element.createInterpolateElement;
	const useEffect                  = element.useEffect;
	const useState                   = element.useState;
	const useRef                     = element.useRef;
	const Button                     = components.Button;
	const Flex                       = components.Flex;
	const FlexBlock                  = components.FlexBlock;
	const FlexItem                   = components.FlexItem;
	const TextControl                = components.TextControl;
	const __                         = i18n.__;
	const sprintf                    = i18n.sprintf;
	const useSelect                  = data.useSelect;
	const registerPlugin             = plugins.registerPlugin;
	const PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;

	// @link https://wordpress.stackexchange.com/questions/362975/admin-notification-after-save-post-when-ajax-saving-in-gutenberg
	const doneSaving = () => {
		const { isSaving, isAutosaving, status } = useSelect( ( select ) => {
			return {
				isSaving: select( 'core/editor' ).isSavingPost(),
				isAutosaving: select( 'core/editor' ).isAutosavingPost(),
				status: select( 'core/editor' ).getEditedPostAttribute( 'status' ),
			};
		} );

		const [ wasSaving, setWasSaving ] = useState( isSaving && ! isAutosaving ); // Ignore autosaves.

		if ( wasSaving ) {
			if ( ! isSaving ) {
				setWasSaving( false );
				return true;
			}
		} else if ( isSaving && ! isAutosaving ) {
			setWasSaving( true );
		}

		return false;
	};

	registerPlugin( 'indieblocks-webmention-panel', {
		render: () => {
			const { postId, postType } = useSelect( ( select ) => {
				return {
					postId: select( 'core/editor' ).getCurrentPostId(),
					postType: select( 'core/editor' ).getCurrentPostType()
				}
			 }, [] );

			const { record, isResolving }       = coreData.useEntityRecord( 'postType', postType, postId );
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
						line = sprintf( __( 'Sent to %1$s on %2$s. Response code: %3$d.', 'indieblocks' ), '<a>' + value.endpoint + '</a>', value.sent, value.code );
						line = interpolate( '<p>' + line + '</p>', {
							p: el( 'p' ),
							a: el( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
						} );
						output.push( line );
					} else if ( value.retries ) {
						if ( value.retries >= 3 ) {
							line = sprintf( __( 'Could not send webmention to %s.', 'indieblocks' ), value.endpoint );
							line = interpolate( '<p>' + line + '</p>', {
								p: el( 'p' ),
								a: el( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
							} );
							output.push( line );
						} else {
							line = sprintf( __( 'Could not send webmention to %s. Trying again soon.', 'indieblocks' ), value.endpoint );
							line = el( 'p', {},
								interpolate(
									line,
									{
										a: el( 'a', { href: encodeURI( value.endpoint ), target: '_blank', rel: 'noreferrer noopener' } ),
									}
								)
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

			return el( PluginDocumentSettingPanel, {
					name: 'indieblocks-webmention-panel',
					title: __( 'Webmention', 'indieblocks' ),
				},
				output,
				el( Button, {
					onClick: () => {
						return;
					},
					variant: 'secondary',
				}, __( 'Resend', 'indieblocks' )	),
			);
		},
	} );
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins, window.wp.editPost, window.wp.apiFetch );
