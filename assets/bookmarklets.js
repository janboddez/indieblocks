( ( blocks, element, data, plugins, domReady ) => {
	const { createBlock } = blocks;
	const { useEffect } = element;
	const { dispatch } = data;
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
				domReady( () => { // May not be needed, but I mean, I don't know.
					useEffect( () => {
						const urlParams = new URLSearchParams( window.location.search );

						const bookmarkOf = urlParams?.get( 'indieblocks_bookmark_of' );
						const inReplyTo  = urlParams?.get( 'indieblocks_in_reply_to' );
						const likeOf     = urlParams?.get( 'indieblocks_like_of' );
						const repostOf   = urlParams?.get( 'indieblocks_repost_of' );

						let block = null;

						if ( bookmarkOf ) {
							block = createBlock( 'indieblocks/bookmark', { url: bookmarkOf } );
						} else if ( inReplyTo ) {
							block = createBlock( 'indieblocks/reply', { url: inReplyTo } );
						} else if ( likeOf ) {
							// By default, a new "like" already contains a Like block, so this would lead to a second
							// such block being inserted. Unless one were to override the like block template.
							block = createBlock( 'indieblocks/like', { url: likeOf } );
						} else if ( repostOf ) {
							block = createBlock( 'indieblocks/repost', { url: repostOf } );
						}

						if ( block ) {
							setTimeout( () => {
								dispatch( 'core/block-editor' ).insertBlock( block );
							}, 250 ); // I thought maybe `domReady` would remove the need for this, but ... guess it doesn't?
						}
					}, [] );
				} );
			}
		}
	);
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.data,
	window.wp.plugins,
	window.wp.domReady
);
