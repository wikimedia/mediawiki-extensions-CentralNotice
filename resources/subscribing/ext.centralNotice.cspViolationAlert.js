/**
 * Show an alert on Content Security Policy violations
 * https://developer.mozilla.org/en-US/docs/Web/API/SecurityPolicyViolationEvent
 */
( function () {
	document.addEventListener( 'securitypolicyviolation', function ( e ) {
		var message = mw.message(
			'centralnotice-csp-violation-alert', e.blockedURI
		);
		// eslint-disable-next-line no-alert
		alert( message );
	} );
}() );
