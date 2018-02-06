/**
 * Allows setting a custom sample rate for the logging of impression events.
 */
( function ( $, mw ) {

	var cn = mw.centralNotice,
		mixin = new cn.Mixin( 'impressionEventsSampleRate' );

	mixin.setPreBannerHandler( function ( mixinParams ) {
		cn.setImpressionEventSampleRate( mixinParams.rate );
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}( jQuery, mediaWiki ) );
