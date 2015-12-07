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
		useCookies,
		mixin = new cn.Mixin( 'largeBannerLimit' ),
		KV_STORE_KEY = 'large_banner_limit';

	function isLarge() {
		var currentBucket = cn.internal.bucketer.getBucket();
		return ( currentBucket <= 1 );
	}

	mixin.setPreBannerHandler( function( mixinParams ) {
		var newBucket;

		useCookies = cn.getDataProperty( 'campaignCategoryUsesLegacy' );

		// Forced URL param or banner hidden already prevents bucket switch
		if ( forced || cn.isBannerCanceled() ) {
			return;
		}

		// No need to switch if we're already on a non-large-banner buckets
		if ( !isLarge() ) {
			return;
		}

		// Summary of the following conditionals: don't switch if there's no
		// flag indicating the user already saw a large banner.

		// When using cookies: don't switch if cookies are enabled but there's
		// no cookie indicating a large banner was seen before.
		if ( useCookies && cn.cookiesEnabled() &&
			!mw.cookie.get( mixinParams.identifier, '' ) ) {
			return;
		}

		// When not using cookies: don't switch if KV storage is available, and
		// there's no KV storage item indicating a large banner was seen before.
		if ( !useCookies && cn.kvStore.isAvailable() ) {

			// Note that we store the item differently depending on whether an
			// identifier was provided.

			if ( mixinParams.identifier &&
				!cn.kvStore.getItem(
					KV_STORE_KEY + '_' + mixinParams.identifier,
					cn.kvStore.contexts.GLOBAL
				)
			) {
				return;
			}

			if ( !mixinParams.identifier && !cn.kvStore.getItem(
					KV_STORE_KEY,
					cn.kvStore.contexts.CATEGORY
			) ) {
				return;
			}
		}

		// In all other cases, move reader immediately into small banner bucket
		if ( mixinParams.randomize ) {
			newBucket = Math.floor( Math.random() * 2 )  +  2;
		} else {
			newBucket = cn.internal.bucketer.getBucket() + 2;
		}

		cn.setBucket( newBucket );

	} );

	mixin.setPostBannerHandler( function( mixinParams ) {

		if ( isLarge() && !forced && cn.internal.state.isBannerShown() ) {

			// Set a flag to remember the reader has just seen a large banner.
			// The next time they are set to see one, they will be moved into a
			// small banner bucket by the pre-banner handler.

			if ( useCookies ) {
				mw.cookie.set( mixinParams.identifier, Date.now(), {
					expires: mixinParams.days * 24 * 60 * 60,
					path: '/',
					prefix: '' // Do not prefix cookie with wiki name
				} );

			} else if ( mixinParams.identifier ) {
				cn.kvStore.setItem(
					KV_STORE_KEY + '_' + mixinParams.identifier,
					Date.now(),
					cn.kvStore.contexts.GLOBAL,
					mixinParams.days
				);

			} else {
				cn.kvStore.setItem(
					KV_STORE_KEY,
					Date.now(),
					cn.kvStore.contexts.CATEGORY,
					mixinParams.days
				);
			}
		}
	} );

	// Register the mixin
	cn.registerCampaignMixin( mixin );

} )( mediaWiki );
