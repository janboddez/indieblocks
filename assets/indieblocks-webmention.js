jQuery( document ).ready( function ( $ ) {
	$( '#indieblocks-webmention .indieblocks-resend-webmention' ).click( function() {
		var button = $( this );
		var data   = {
			'action': 'indieblocks_resend_webmention',
			'post_id': $( '[name="post_ID"]' ).val(), // Current post ID.
			'_wp_nonce': button.data( 'nonce' ), // Nonce.
		};

		$.post( ajaxurl, data, function( response ) {
			button.parent().find( 'p' ).remove();
			button.parent().append( '<p style="margin: 0 0 6px;">' + indieblocks_webmention_obj.message + '</p>' );
			button.remove();
		} );
	} );

	$( '.indieblocks-delete-avatar' ).click( function() {
		var button = $( this );
		var data   = {
			'action': 'indieblocks_delete_avatar',
			'comment_id': $( '[name="comment_ID"]' ).val(), // Current comment ID.
			'_wp_nonce': button.data( 'nonce' ), // Nonce.
		};

		$.post( ajaxurl, data, function( response ) {
			// Bit lazy, but 'kay.
			location.reload();
		} );
	} );
} );
