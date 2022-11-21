jQuery( document ).ready( function ( $ ) {
	$( '#indieblocks-webmention .indieblocks-webmention-resend' ).click( function() {
		var button = $( this );
		var data   = {
			'action': 'indieblocks_webmention_resend',
			'post_id': button.data( 'post-id' ), // Current post ID.
			'indieblocks_webmention_nonce': $( '#indieblocks_webmention_nonce' ).val() // Nonce.
		};

		$.post( ajaxurl, data, function( response ) {
			button.parent().append( '<p style="margin: 0 0 6px;">' + indieblocks_webmention_obj.message + '</p>' );
			button.parent().find( 'ul' ).remove();
			button.remove();
		} );
	} );
} );
