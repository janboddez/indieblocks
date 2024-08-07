( ( blocks, blockEditor, notices, element, data, plugins, escapeHtml ) => {
	const { createBlock } = blocks;
	const { store: blockEditorStore } = blockEditor;
	const { store: noticesStore } = notices;
	const { useEffect } = element;
	const { useDispatch, useSelect } = data;
	const { registerPlugin } = plugins;
	const { escapeHTML } = escapeHtml;

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
				const { insertBlock, removeBlocks, replaceInnerBlocks } = useDispatch( blockEditorStore );

				useEffect( () => {
					const urlParams = new URLSearchParams( window.location.search );

					const bookmarkOf = urlParams?.get( 'indieblocks_bookmark_of' );
					const inReplyTo = urlParams?.get( 'indieblocks_in_reply_to' );
					const likeOf = urlParams?.get( 'indieblocks_like_of' );
					const repostOf = urlParams?.get( 'indieblocks_repost_of' );
					const selectedText = urlParams?.get( 'indieblocks_selected_text' );

					let block = null;
					let paras = [];

					if ( selectedText ) {
						// const lines = selectedText.split( /\r\n|(?!\r\n)[\n-\r\x85\u2028\u2029]/g ).filter( Boolean );

						// Looks like newlines are somehow stripped from `selectedText` (or maybe they aren't, but lets
						// assume we convert them to `<br />` in our actual bookmarklets).
						// .replace( /(?:\r\n|\r|\n)/g, '<br />' )
						const lines = selectedText.split( /<br ?\/?>/g ).filter( Boolean ); // The `filter()` removes any empty strings.
						lines.forEach( line => {
							paras.push( createBlock( 'core/paragraph', { content: escapeHTML( line ) } ) ); // Using `escapeHTML()` from `wp-escape-html` because unline most stuff in React, `content` is not automatically escaped.
						} );
					}

					if ( bookmarkOf ) {
						block = createBlock( 'indieblocks/bookmark', { url: bookmarkOf }, [
							createBlock( 'core/quote', {}, paras ),
						] );
					} else if ( inReplyTo ) {
						block = createBlock( 'indieblocks/reply', { url: inReplyTo }, [
							createBlock( 'core/quote', {}, paras ),
						] );
					} else if ( likeOf ) {
						block = createBlock( 'indieblocks/like', { url: likeOf }, [
							createBlock( 'core/quote', {}, paras ),
						] );
					} else if ( repostOf ) {
						block = createBlock( 'indieblocks/repost', { url: repostOf }, paras ); // The Repost block itself renders a quote.
					}

					if ( block ) {
						removeAllNotices(); // Remove all notices.

						setTimeout( () => {
							const blockIds = getBlocks()?.map( block => block.clientId );
							// console.log( block );

							insertBlock( block );

							if ( blockIds ) {
								removeBlocks( blockIds ); // Clear any earlier blocks.
							}

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
	window.wp.plugins,
	window.wp.escapeHtml
);
