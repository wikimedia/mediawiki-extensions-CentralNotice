/**
 * Local blackout times mixin. Allows setting a timespan during which no
 * banners from this campaign will be shown.
 */
( function () {
	'use strict';

	const cn = mw.centralNotice,
		mixin = new cn.Mixin( 'localBlackoutTimes' );

	mixin.setPreBannerHandler( ( mixinParams ) => {
		const now = new Date().toTimeString().slice( 0, 5 ),
			start = mixinParams.startTime,
			end = mixinParams.endTime;

		if ( end > start ) {
			// Blackout time does not cross midnight, e.g. start = 18:00 and end = 22:00,
			// so we check if now is between start and end
			if ( now > start && now < end ) {
				cn.failCampaign( 'other' );
			}
		} else {
			// Blackout time crosses midnight, e.g. start = 22:00 and end = 04:00,
			// so we check if now is before end or after start
			if ( now < end || now > start ) {
				cn.failCampaign( 'other' );
			}
		}
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}() );
