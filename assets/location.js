( function ( element, components, i18n, data, coreData, plugins, editPost, apiFetch, url ) {
	const el                         = element.createElement;
	const useEffect                  = element.useEffect;
	const useState                   = element.useState;
	const Flex                       = components.Flex;
	const FlexItem                   = components.FlexItem;
	const TextControl                = components.TextControl;
	const ToggleControl              = components.ToggleControl;
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
	const fetchLocation = ( postId, meta, setMeta ) => {
		if ( ! postId ) {
			return false;
		}

		// Like a time-out.
		const controller = new AbortController();
		const timeoutId  = setTimeout( function() {
			controller.abort();
		}, 6000 );

		apiFetch( {
			path: url.addQueryArgs( '/indieblocks/v1/location', { post_id: postId } ),
			signal: controller.signal, // That time-out thingy.
		} ).then( function( response ) {
			clearTimeout( timeoutId );

			if ( response.hasOwnProperty( 'name' ) ) {
				// This function does not do anything besides displaying a location name.
				setMeta( { ...meta, geo_address: response.name } );
			}
		} ).catch( function( error ) {
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

			// To be able to actually save post meta.
			const [ meta, setMeta ] = coreData.useEntityProp( 'postType', postType, 'meta' );

			// To keep track of the "should update" toggle.
			const [ enabled, setEnabled ] = useState( '1' === indieblocks_location_obj?.should_update ); // The `indieblocks_location_obj` object is populated server-side.

			// Run once, plus whenever `enabled` is updated.
			useEffect( () => {
				if ( meta?.geo_latitude || meta?.geo_longitude ) {
					// Not empty.
					return;
				}

				if ( ! navigator.geolocation ) {
					// Not supported.
					return;
				}

				navigator.geolocation.getCurrentPosition( ( position ) => {
					if ( ! enabled ) {
						return;
					}

					// Need to update both coords at once in order not to remove
					// one or the other.
					setMeta( { ...meta, geo_latitude: position.coords.latitude.toString(), geo_longitude: position.coords.longitude.toString() } );
				}, ( error ) => {
					// Do nothing.
					console.log( error );
				} );
			}, [ enabled, meta, setMeta ] );

			if ( doneSaving() ) {
				// Post was updated, location "name" is (still) empty.
				setTimeout( () => {
					// After a shortish delay, fetch and display the new name (if any).
					fetchLocation( postId, meta, setMeta );
				}, 1500 );

				setTimeout( () => {
					// Just in case. I thought of `setInterval()`, but if after
					// 15 seconds it's still not there, it's likely not going to
					// happen. Unless of course the "Delay" option is set to
					// something larger, but then there's no point in displaying
					// this type of feedback anyway.
					fetchLocation( postId, meta, setMeta );
				}, 15000 );
			}

			return el( PluginDocumentSettingPanel, {
					name: 'indieblocks-location-panel',
					title: __( 'Location', 'indieblocks' ),
				},
				el( Flex, {},
					el( FlexItem, {},
						el( TextControl, {
							label: __( 'Latitude', 'indieblocks' ),
							value: meta.geo_latitude ?? '',
							onChange: ( value ) => {
								setMeta( { ...meta, geo_latitude: value } );
							},
						} )
					),
					el( FlexItem, {},
						el( TextControl, {
							label: __( 'Longitude', 'indieblocks' ),
							value: meta.geo_longitude ?? '',
							onChange: ( value ) => {
								setMeta( { ...meta, geo_longitude: value } );
							},
						} )
					)
				),
				el( TextControl, {
					label: __( 'Location', 'indieblocks' ),
					value: meta.geo_address ?? '',
					onChange: ( value ) => {
						setMeta( { ...meta, geo_address: value } );
					},
				} ),
				el( ToggleControl, {
					label: __( 'Update location data?', 'indieblocks' ),
					checked: enabled,
					onChange: ( value ) => {
						setEnabled( value );
					},
				} )
			);
		},
	} );
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins, window.wp.editPost, window.wp.apiFetch, window.wp.url );
