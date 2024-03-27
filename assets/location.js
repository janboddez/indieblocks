( function ( element, components, i18n, data, coreData, plugins, editPost, apiFetch, url ) {
	const el                         = element.createElement;
	const useState                   = element.useState;
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

		const [ wasSaving, setWasSaving ] = useState( isSaving && ! isAutosaving && 'publish' === status ); // Ignore autosaves, and unpublished posts.

		if ( wasSaving ) {
			if ( ! isSaving ) {
				setWasSaving( false );
				return true;
			}
		} else if ( isSaving && ! isAutosaving && 'publish' === status ) {
			setWasSaving( true );
		}

		return false;
	};

	const fetchLocation = ( postId ) => {
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
				var locName = document.querySelector( '[name="indieblocks_address"]' );
				if ( locName ) {
					locName.value = response.name;
				}
			}
		} ).catch( function( error ) {
			// The request timed out or otherwise failed. Leave as is.
			console.debug( '[IndieBlocks] "Get location" request failed.' );
		} );
	};

	registerPlugin( 'indieblocks-location-panel', {
		render: function( props ) {
			const postId   = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
			const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );

			// To be able to actually save post meta (namely, `_share_on_mastodon` and `_share_on_mastodon_status`).
			const [ meta, setMeta ]       = coreData.useEntityProp( 'postType', postType, 'meta' );
			const [ enabled, setEnabled ] = useState( true ); // @todo: Make this dependent on the post date!

			if ( doneSaving() && enabled && '' === meta.geo_address ) {
				// Post was updated, location "name" is (still) empty.
				setTimeout( () => {
					// After a shortish delay, fetch and display the new name (if any).
					fetchLocation( postId );
				}, 1500 );

				setTimeout( () => {
					// Just in case. I thought of `setInterval()`, but if after
					// 15 seconds it's still not there, it's likely not going to
					// happen. Unless of course the "Delay" option is set to
					// something larger, but then there's no point in displaying
					// this type of feedback anyway.
					fetchLocation( postId );
				}, 15000 );
			}

			return el( PluginDocumentSettingPanel, {
					name: 'indieblocks-location-panel',
					title: __( 'IndieBlocks', 'indieblocks' ),
				},
				el( ToggleControl, {
					label: __( 'Update location data?', 'indieblocks' ),
					checked: enabled,
					onChange: ( value ) => {
						setEnabled( value );
					},
				} ),
				el( TextControl, {
					label: __( 'Latitude', 'indieblocks' ),
					value: meta.geo_latitude ?? '',
					onChange: ( value ) => {
						setMeta( { ...meta, geo_latitude: value } );
					},
				} ),
				el( TextControl, {
					label: __( 'Longitude', 'indieblocks' ),
					value: meta.geo_longitude ?? '',
					onChange: ( value ) => {
						setMeta( { ...meta, geo_longitude: value } );
					},
				} ),
				el( TextControl, {
					label: __( 'Location', 'indieblocks' ),
					value: meta.geo_address ?? '',
					onChange: ( value ) => {
						setMeta( { ...meta, geo_address: value } );
					},
				} ),
			);
		},
	} );
} )( window.wp.element, window.wp.components, window.wp.i18n, window.wp.data, window.wp.coreData, window.wp.plugins, window.wp.editPost, window.wp.apiFetch, window.wp.url );
