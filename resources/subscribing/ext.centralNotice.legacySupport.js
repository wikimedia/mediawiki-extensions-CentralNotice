/**
 * Provides features to support legacy campaigns. With this mixin, campaigns may:
 * - set a rate for Special:RecordImpression that's different from the default; and
 * - signal that banners are not guaranteed to display.
 */
( function () {

	var cn = mw.centralNotice,
		mixin = new cn.Mixin( 'legacySupport' );

	mixin.setPreBannerHandler( function ( mixinParams ) {

		if ( mixinParams.setSRISampleRate ) {
			cn.setMinRecordImpressionSampleRate( mixinParams.sriSampleRate );
		}

		if ( mixinParams.bannersNotGuaranteedToDisplay ) {
			cn.setBannersNotGuaranteedToDisplay();
		}
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}() );
