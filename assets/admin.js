document.querySelector( '#indieblocks-generate-secret' )?.addEventListener( 'click', () => {
	const chars = '0123456789abcdefghijklmnopqrstuvwxyz!@#$%^&*()ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	let pass = '';
	let rand = 0;

	for ( var i = 0; i <= 32; i++ ) {
		rand = Math.floor( Math.random() * chars.length );
		pass += chars.substring( rand, rand + 1 );
	}

	const secret = document.querySelector( '#indieblocks-image-proxy-secret' );
	if ( secret ) {
		secret.value = pass;
	}
} );
