( function( $ ) {
	// Returns into a bit field (See BannerRenderer.php).

	if ( location.search.match( /\breset=1/ ) ) {
		$.cookie( '{{{hide-cookie-name}}}', 0, { expires: 365, path: '/' } );
		return 1;
	}
	var cookieCount = parseInt( $.cookie( '{{{hide-cookie-name}}}' ), 10 ) | 0;

	if ( cookieCount < parseInt( '{{{hide-cookie-max-count}}}', 10 ) ) {
		$.cookie( '{{{hide-cookie-name}}}', cookieCount + 1, { expires: 365, path: '/' } );
		return 1;
	} else {
		return 0;
	}
} )( jQuery );
