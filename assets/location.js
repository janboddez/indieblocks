( ( element, components, i18n, data, coreData, plugins, editPost, apiFetch, url ) => {
	const el                         = element.createElement;
	const useEffect                  = element.useEffect;
	const useState                   = element.useState;
	const useRef                     = element.useRef;
	const Button                     = components.Button;
	const Flex                       = components.Flex;
	const FlexBlock                  = components.FlexBlock;
	const FlexItem                   = components.FlexItem;
	const TextControl                = components.TextControl;
	const __                         = i18n.__;
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

	// This doesn't yet work as intended, but the idea here was to fetch a
	// location name after it was set in the background. We must, however, make
	// sure that we don't overwrite it once more with, e.g., an empty string.
	const fetchLocation = ( postId, setMeta, stateRef ) => {
		if ( ! postId ) {
			return false;
		}

		// Like a time-out.
		const controller = new AbortController();
		const timeoutId  = setTimeout( () => {
			controller.abort();
		}, 6000 );

		apiFetch( {
			path: url.addQueryArgs( '/indieblocks/v1/location', { post_id: postId } ),
			signal: controller.signal, // That time-out thingy.
		} ).then( ( response ) => {
			clearTimeout( timeoutId );

			if ( response.hasOwnProperty( 'geo_address' ) && '' !== response.geo_address ) {
				// This function does not do anything besides displaying a location name.
				setMeta( { ...stateRef.current, geo_address: response.geo_address } );
			}
		} ).catch( ( error ) => {
			// The request timed out or otherwise failed. Leave as is.
		} );
	};

	registerPlugin( 'indieblocks-location-panel', {
		render: () => {
			const { postId, postType } = useSelect( ( select ) => {
				return {
					postId: select( 'core/editor' ).getCurrentPostId(),
					postType: select( 'core/editor' ).getCurrentPostType()
				}
			 }, [] );

			const [ meta, setMeta ] = coreData.useEntityProp( 'postType', postType, 'meta' );

			const latitude   = meta?.geo_latitude ?? '';
			const longitude  = meta?.geo_longitude ?? '';
			const geoAddress = meta?.geo_address ?? '';

			const stateRef   = useRef();
			stateRef.current = meta;

			// These are custom fields we *don't* want to be set by `setMeta()`.
			const { record, isResolving } = coreData.useEntityRecord( 'postType', postType, postId );

			// Update location name. Note: Doesn't work as it should, yet.
			if ( doneSaving() && '' === geoAddress ) {
				setTimeout( () => {
					fetchLocation( postId, setMeta, stateRef );
				}, 1500 );

				setTimeout( () => {
					fetchLocation( postId, setMeta, stateRef );
				}, 15000 );
			}

			// Runs once.
			useEffect( () => {
				if ( '' !== latitude ) {
					return;
				}

				if ( '' !== longitude ) {
					return;
				}

				const shouldUpdate = indieblocks_location_obj?.should_update ?? '0';
				if ( '1' !== shouldUpdate ) {
					return;
				}

				if ( ! navigator.geolocation ) {
					return;
				}

				navigator.geolocation.getCurrentPosition( updatePosition, ( error ) => {
					// Do nothing.
					console.log( error );
				} );
			}, [] );

			// `navigator.geolocation.getCurrentPosition` callback.
			const updatePosition = ( position ) => {
				setMeta( { ...stateRef.current, geo_latitude: position.coords.latitude.toString(), geo_longitude: position.coords.longitude.toString() } );
			};

			// useEffect( () => {
			// 	console.table( meta );
			// }, [ meta ] );

			return el( PluginDocumentSettingPanel, {
					name: 'indieblocks-location-panel',
					title: __( 'Location', 'indieblocks' ),
				},
				el( Flex, { align: 'end' },
					el( FlexBlock, {},
						el( TextControl, {
							label: __( 'Latitude', 'indieblocks' ),
							value: latitude,
							onChange: ( value ) => {
								setMeta( { ...meta, geo_latitude: value } );
							},
						} )
					),
					el( FlexBlock, {},
						el( TextControl, {
							label: __( 'Longitude', 'indieblocks' ),
							value: longitude,
							onChange: ( value ) => {
								setMeta( { ...meta, geo_longitude: value } );
							},
						} )
					),
					el( FlexItem, {},
						el( Button, {
							style: { height: 'auto', marginBottom: '8px', minHeight: '31px' },
							onClick: () => {
								if ( ! navigator.geolocation ) {
									return;
								}

								navigator.geolocation.getCurrentPosition( updatePosition, ( error ) => {
									// Do nothing.
									console.log( error );
								} );
							},
							variant: 'secondary',
						}, __( 'Fetch', 'indieblocks' )	),
					)
				),
				// To allow authors to manually override or pass on a location.
				el( TextControl, {
					label: __( 'Location', 'indieblocks' ),
					value: geoAddress,
					onChange: ( value ) => {
						setMeta( { ...meta, geo_address: value } );
					},
				} )
			);
		},
	} );
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins, window.wp.editPost, window.wp.apiFetch, window.wp.url );
