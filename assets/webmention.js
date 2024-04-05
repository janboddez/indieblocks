( ( element, components, i18n, data, coreData, plugins, editPost, apiFetch ) => {
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

	registerPlugin( 'indieblocks-webmention-panel', {
		render: () => {
			const { postId, postType } = useSelect( ( select ) => {
				return {
					postId: select( 'core/editor' ).getCurrentPostId(),
					postType: select( 'core/editor' ).getCurrentPostType()
				}
			 }, [] );

			const [ meta, setMeta ] = coreData.useEntityProp( 'postType', postType, 'meta' );

			return el( PluginDocumentSettingPanel, {
					name: 'indieblocks-webmention-panel',
					title: __( 'Webmention', 'indieblocks' ),
				},
				el( 'p', {},
					__( 'No endpoints found.', 'indieblocks' )
				),
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
