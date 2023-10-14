jQuery( document ).ready( function ( $ ) {
	$( '.indieblocks-delete-preview-card' ).click( function() {
		var button = $( this );
		var data   = {
			'action': 'indieblocks_delete_preview_card',
			'post_id': $( '[name="post_ID"]' ).val(), // Current post ID.
			'_wp_nonce': button.data( 'nonce' ), // Nonce.
		};

		$.post( ajaxurl, data, function( response ) {
			// Bit lazy, but 'kay.
			// location.reload();
		} );
	} );
} );
