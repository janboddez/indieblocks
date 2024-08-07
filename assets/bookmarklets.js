( ( blocks, blockEditor, notices, element, data, plugins ) => {
	const { createBlock } = blocks;
	const { store: blockEditorStore } = blockEditor;
	const { store: noticesStore } = notices;
	const { useEffect } = element;
	const { useDispatch, useSelect } = data;
	const { registerPlugin } = plugins;

	/**
	 * Greatly inspired by ActivityPub's "Reply Handler," uhm, plugin.
	 *
	 * @link https://github.com/Automattic/wordpress-activitypub/blob/b240589e789eca5c94d9c06980063b2b859db8fb/src/reply-intent/plugin.js
	 */
	registerPlugin(
		'indieblocks-bookmarklets',
		{
			render: () => {
				const { getBlocks } = useSelect( blockEditorStore );
				const { removeAllNotices } = useDispatch( noticesStore );
				const { insertBlock, removeBlocks } = useDispatch( blockEditorStore );

				useEffect( () => {
					const urlParams = new URLSearchParams( window.location.search );

					const bookmarkOf = urlParams?.get( 'indieblocks_bookmark_of' );
					const inReplyTo = urlParams?.get( 'indieblocks_in_reply_to' );
					const likeOf = urlParams?.get( 'indieblocks_like_of' );
					const repostOf = urlParams?.get( 'indieblocks_repost_of' );

					let block = null;

					if ( bookmarkOf ) {
						block = createBlock( 'indieblocks/bookmark', { url: bookmarkOf } );
					} else if ( inReplyTo ) {
						block = createBlock( 'indieblocks/reply', { url: inReplyTo } );
					} else if ( likeOf ) {
						block = createBlock( 'indieblocks/like', { url: likeOf } );
					} else if ( repostOf ) {
						block = createBlock( 'indieblocks/repost', { url: repostOf } );
					}

					if ( block ) {
						removeAllNotices(); // Remove all notices.

						setTimeout( () => {
							const blockIds = getBlocks()?.map( block => block.clientId );
							insertBlock( block );
							removeBlocks( blockIds ); // Clear any earlier blocks.
						} ); // We don't actually need a delay, but we need to wait till render is done.
					}
				}, [] );
			}
		}
	);
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.notices,
	window.wp.element,
	window.wp.data,
	window.wp.plugins
);
