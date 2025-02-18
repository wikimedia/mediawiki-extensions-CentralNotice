/**
 * Allows setting a custom sample rate for the logging of impression events.
 */
( function () {

	const cn = mw.centralNotice,
		mixin = new cn.Mixin( 'impressionEventsSampleRate' );

	mixin.setPreBannerHandler( ( mixinParams ) => {
		cn.setMinImpressionEventSampleRate( mixinParams.rate );
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}() );
