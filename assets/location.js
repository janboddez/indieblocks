( ( element, components, i18n, data, coreData, apiFetch, plugins, editor ) => {
	const { createElement: el, useEffect, useState, useRef } = element;
	const { Button, Flex, FlexBlock, FlexItem, TextControl, PanelRow } = components;
	const { __ } = i18n;
	const { useSelect } = data;
	const { useEntityProp } = coreData;
	const { registerPlugin } = plugins;
	const { PluginDocumentSettingPanel } = editor;

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

	registerPlugin( 'indieblocks-location-panel', {
		render: ( props ) => {
			const { postId, postType } = useSelect( ( select ) => {
				return {
					postId: select( 'core/editor' ).getCurrentPostId(),
					postType: select( 'core/editor' ).getCurrentPostType(),
				};
			}, [] );

			const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

			const latitude = meta?.geo_latitude ?? '';
			const longitude = meta?.geo_longitude ?? '';
			const geoAddress = meta?.geo_address ?? '';

			const stateRef = useRef();
			stateRef.current = meta;

			// This seems superfluous, but let's leave it in place, in case our
			// server's "too slow" to immediately return a location name.

			// The idea here was to fetch a location name after it is set in the
			// background. So that if the OpenStreetMap geolocation API is "too slow"
			// we'll still get an "up-to-date" location.
			const fetchLocation = () => {
				if ( ! postId ) {
					return;
				}

				if ( ! postType ) {
					return;
				}

				if ( stateRef.current.geo_address ) {
					return;
				}

				// Like a time-out.
				const controller = new AbortController();
				const timeoutId = setTimeout( () => {
					controller.abort();
				}, 6000 );

				apiFetch( {
					path: '/wp/v2/' + postType + '/' + postId,
					signal: controller.signal, // That time-out thingy.
				} )
					.then( ( response ) => {
						clearTimeout( timeoutId );

						if ( response.indieblocks_location && response.indieblocks_location.geo_address ) {
							// This function does not do anything besides displaying a location name.
							setMeta( {
								...stateRef.current,
								geo_address: response.indieblocks_location.geo_address,
							} );
						}
					} )
					.catch( ( error ) => {
						// The request timed out or otherwise failed. Leave as is.
					} );
			};

			if ( doneSaving() && ! geoAddress ) {
				setTimeout( () => {
					fetchLocation();
				}, 1500 );

				setTimeout( () => {
					fetchLocation();
				}, 15000 );
			}

			// `navigator.geolocation.getCurrentPosition` callback.
			const updatePosition = ( position ) => {
				setMeta( {
					...stateRef.current,
					geo_latitude: position.coords.latitude.toString(),
					geo_longitude: position.coords.longitude.toString(),
				} );
			};

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
			}, [ updatePosition ] );

			return el(
				PluginDocumentSettingPanel,
				{
					name: 'indieblocks-location-panel',
					title: __( 'Location', 'indieblocks' ),
				},
				el(
					PanelRow,
					{},
					el(
						Flex,
						{ align: 'end' },
						el(
							FlexBlock,
							{},
							el( TextControl, {
								label: __( 'Latitude', 'indieblocks' ),
								value: latitude,
								onChange: ( value ) => {
									setMeta( { ...meta, geo_latitude: value } );
								},
							} )
						),
						el(
							FlexBlock,
							{},
							el( TextControl, {
								label: __( 'Longitude', 'indieblocks' ),
								value: longitude,
								onChange: ( value ) => {
									setMeta( {
										...meta,
										geo_longitude: value,
									} );
								},
							} )
						),
						el(
							FlexItem,
							{},
							el(
								Button,
								{
									className: 'indieblocks-location__fetch-button',
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
								},
								__( 'Fetch', 'indieblocks' )
							)
						)
					)
				),
				el(
					PanelRow,
					{},
					// To allow authors to manually override or pass on a location.
					el( TextControl, {
						className: 'indieblocks-location__address-field',
						label: __( 'Location', 'indieblocks' ),
						value: geoAddress,
						onChange: ( value ) => {
							setMeta( { ...meta, geo_address: value } );
						},
					} )
				)
			);
		},
	} );
} )(
	window.wp.element,
	window.wp.components,
	window.wp.i18n,
	window.wp.data,
	window.wp.coreData,
	window.wp.apiFetch,
	window.wp.plugins,
	window.wp.editor
);
