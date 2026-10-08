/**
 * User edit count mixin - allows targeting a banner to users with a minimum of X edits
 */
( function () {
	'use strict';

	const cn = mw.centralNotice,
		mixin = new cn.Mixin( 'userEditCount' );

	mixin.setPreBannerHandler( ( mixinParams ) => {
		const editCount = mw.config.get( 'wgUserEditCount' ) || 0,
			min = mixinParams.minEdits, max = mixinParams.maxEdits;

		if (
			( min && editCount < min ) ||
			( max && editCount > max )
		) {
			cn.failCampaign( 'other' );
		}
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

}() );
