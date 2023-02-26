var IndieBlocks = {
	/**
	 * Calls a backend function that parses a URL for microformats and the like,
	 * and sets attributes accordingly.
	 */
	updateMeta: function( props, apiFetch ) {
		var url = props.attributes.url;

		if ( props.attributes.customTitle && props.attributes.customAuthor ) {
			// We're using custom values for both title and author;/ nothing to
			// do here.
			return;
		}

		if ( ! IndieBlocks.isValidUrl( url ) ) {
			return;
		}

		// Like a time-out.
		var controller = new AbortController();
		var timeoutId  = setTimeout( function() {
			controller.abort();
		}, 6000 );

		apiFetch( {
			path: '/indieblocks/v1/meta?url=' + encodeURIComponent( url ),
			signal: controller.signal, // That time-out thingy.
		} ).then( function( response ) {
			if ( ! props.attributes.customTitle && ( response.name || '' === response.name ) ) {
				// Got a, possibly empty, title.
				props.setAttributes( { title: response.name } );
			}

			if ( ! props.attributes.customAuthor && ( response.author.name || '' === response.author.name ) ) {
				// Got a, possibly empty, name.
				props.setAttributes( { author: response.author.name } );
			}

			clearTimeout( timeoutId );
		} ).catch( function( error ) {
			// The request timed out or otherwise failed. Leave as is.
		} );
	},
	/**
	 * Validates a URL.
	 */
	isValidUrl: function( string ) {
		try {
			new URL( string );
		} catch ( error ) {
			return false;
		}

		return true;
	},
}
