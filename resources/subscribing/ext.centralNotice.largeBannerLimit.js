/**
 * Large banner limit mixin. Shows readers a large banner once, then switches
 * to small banners for a configurable number of days.
 *
 * Requires a campaign with four buckets. Buckets A and B are assumed to contain
 * large banners, and buckets C and D small banners.
 *
 * Replaces the following scripts:
 * https://meta.wikimedia.org/wiki/MediaWiki:FR2014/Resources/ShowHideCheckFullscreen.js
 * https://meta.wikimedia.org/wiki/MediaWiki:FR2014/Resources/ChangeBucket-AtoC-BtoD.js
 */
( function ( mw ) {
	'use strict';

	var cn = mw.centralNotice,
		forced = mw.util.getParamValue( 'force' ),
		mixin = new cn.Mixin( 'largeBannerLimit' );

	function isLarge() {
		var currentBucket = cn.internal.bucketer.getBucket();
		return ( currentBucket <= 1 );
	}

	function cookiesEnabled() {
		var enabled;
		// Set a cookie, then try to read it back
		mw.cookie.set( 'cookieTest', 'lbl' );
		enabled = ( mw.cookie.get( 'cookieTest' ) === 'lbl' );
		// Clear it out
		mw.cookie.set( 'cookieTest', null );
		return enabled;
	}

	mixin.setPreBannerHandler( function( mixinParams ) {
		var newBucket;

		if ( forced ) {
			return;
		}

		if ( isLarge() &&
				( !cookiesEnabled() || mw.cookie.get( mixinParams.cookieName, '' ) )
			) {
			// Move reader immediately into small banner bucket
			if ( mixinParams.randomize ) {
				newBucket = Math.floor( Math.random() * 2 )  +  2;
			} else {
				newBucket = cn.internal.bucketer.getBucket() + 2;
			}
			cn.setBucket( newBucket );
		}
	} );

	mixin.setPostBannerHandler( function( mixinParams ) {
		if ( isLarge() && !forced && cn.internal.state.isBannerShown() ) {
			// Set a cookie to remember that the reader has just seen a large banner
			// The next time they are set to see one, they will be moved into a
			// small banner bucket by the pre-banner handler.
			var options = {
				expires: mixinParams.days * 24 * 60 * 60,
				path: '/',
				prefix: '' // Do not prefix cookie with wiki name
			};
			mw.cookie.set( mixinParams.cookieName, Date.now(), options );
		}
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );
} )( mediaWiki );
