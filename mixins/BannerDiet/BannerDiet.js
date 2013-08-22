(function () {
	if ( window.location.search.match( /\breset=1/ ) ) {
		$.cookie( '{{{hide-cookie-name}}}', 0, { expires: 365, path: '/' } );
		return true;
	}
	var cookieCount = parseInt( $.cookie( '{{{hide-cookie-name}}}' ), 10 ) | 0;

	if ( cookieCount < parseInt( '{{{hide-cookie-max-count}}}', 10 ) ) {
		$.cookie( '{{{hide-cookie-name}}}', cookieCount + 1, { expires: 365, path: '/' } );
		return true;
	} else {
		return false;
	}
})();
