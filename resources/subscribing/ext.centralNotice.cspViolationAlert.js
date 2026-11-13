/**
 * Show an alert on Content Security Policy violations
 * https://developer.mozilla.org/en-US/docs/Web/API/SecurityPolicyViolationEvent
 */
( function () {
	document.addEventListener( 'securitypolicyviolation', ( e ) => {
		if ( e.blockedURI !== 'inline' ) {
			const message = mw.message(
				'centralnotice-csp-violation-alert', e.blockedURI
			);
			// eslint-disable-next-line no-alert
			alert( message );
		}
	} );
}() );
