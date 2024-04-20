jQuery( document ).ready( function ( $ ) {
	$( '#indieblocks-webmention .indieblocks-resend-webmention' ).click( function () {
		var button = $( this );
		var type   = button.data( 'type' );
		var data   = {
			'action': 'indieblocks_resend_webmention',
			'type': type, // Post or comment.
			'obj_id': 'post' === type ? $( '[name="post_ID"]' ).val() : $( '[name="comment_ID"]' ).val(), // Current post or comment ID.
			'_wp_nonce': button.data( 'nonce' ), // Nonce.
		};

		$.post( ajaxurl, data, function ( response ) {
			button.parent().find( 'p' ).remove();
			button.parent().append( '<p style="margin: 6px 0;">' + indieblocks_webmention_legacy_obj.message + '</p>' );
			button.remove();
		} );
	} );

	$( '.indieblocks-delete-avatar' ).click( function () {
		var button = $( this );
		var data   = {
			'action': 'indieblocks_delete_avatar',
			'comment_id': $( '[name="comment_ID"]' ).val(), // Current comment ID.
			'_wp_nonce': button.data( 'nonce' ), // Nonce.
		};

		$.post( ajaxurl, data, function ( response ) {
			// Bit lazy, but 'kay.
			location.reload();
		} );
	} );
} );
