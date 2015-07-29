/**
 * JS that is loaded onto every MW page to load banners.
 *
 * Requires the Geo global object; e.g. from geoiplookup.wikimedia.org
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
( function ( $, mw ) {

	var rPlus = /\+/g;

	function decode( s ) {
		try {
			// decodeURIComponent can throw an exception for unknown char encodings.
			return decodeURIComponent( s.replace( rPlus, ' ' ) );
		} catch ( e ) {
			return '';
		}
	}

	function synthesizeGeoCookie() {
		if ( !window.Geo || !window.Geo.country ) {
			$.cookie( 'GeoIP', ':::::vx', { path: '/' } );
			return;
		}

		var parts = [
			window.Geo.country,
			window.Geo.region,
			window.Geo.city.replace( /[^a-z]/i, '_' ),
			window.Geo.lat,
			window.Geo.lon,
			( window.Geo.IP && window.Geo.IP.match(':') ) ? 'v6' : 'v4'
		];

		$.cookie( 'GeoIP', parts.join( ':' ), { path: '/' } );
	}

	function sanitizeGeoCookie( match, country, region, city, lat, lon, af ) {
		if ( typeof country !== 'string' || ( country.length !== 0 && country.length !== 2 ) ) {
		    // 'country' is neither empty nor a country code (string of
		    // length 2), so something is wrong with the cookie, and we
		    // cannot rely on its value.
		    $.cookie( 'GeoIP', null, { path: '/' } );
		    country = '';
		    region = '';
		    city = '';
		    lat = '';
		    lon = '';
		    af = 'vx';
		}

		return {
			country: country,
			region: region,
			city: city,
			lat: lat && parseFloat( lat ),
			lon: lon && parseFloat( lon ),
			af: af
		};
	}

	function parseGeoCookie() {
		var geoCookie = $.cookie( 'GeoIP' ) || '',
			geoCookieMatches = geoCookie.match( /([^:]*):([^:]*):([^:]*):([^:]*):([^:]*):([^;]*)/ )
				|| geoCookie.match( /([^:]*):([^:]*):([^:]*):([^:]*):([^;]*)/ )
				|| [];

		// When encountering the old cookie format, convert it to the new one with an empty region
		if ( geoCookieMatches.length === 6 ) {
			// Region field is missing, insert an empty one
			geoCookieMatches = geoCookieMatches.slice( 0, 2 ).concat( [ '' ] ).concat( geoCookieMatches.slice( 2 ) );
		}

		return sanitizeGeoCookie.apply( null, geoCookieMatches );
	}

	window.Geo = parseGeoCookie();

	// FIXME Following the switch to client-side banner selection, it would
	// make more sense for this to be defined in bannerController.lib. Before
	// changing it and moving these methods to an inner property thereof,
	// let's make very sure that won't cause problems, here or elsewhere.
	mw.centralNotice = {
		/**
		 * Central Notice Required Data
		 */
		data: {
			getVars: {},
			category: 'default',
			bucket: null,
			testing: false
		},

		/**
		 * Custom data that the banner can play with
		 */
		bannerData: {},

		/**
		 * Data that will be returned with the RecordImpression request
		 */
		impressionData: {
			result: 'show'
		},

		/**
		 * Turn on sampling for calls to Special:RecordImpression.
		 * If true, randomly select a sample at the rate indicated by
		 * $wgCentralNoticeSampleRate. If false, make the call on every request.
		 */
		onlySampleRI: false,

		/**
		 * Contains promise objects that other things can hook into
		 */
		events: {},

		/**
		 * State variable used in initialize() to prevent it from running more than once
		 * @private
		 */
		alreadyRan: false,

		/**
		 * Deferred objects that link into promises in mw.centralNotice.events
		 */
		deferredObjs: {},

		/** -- Functions! -- **/
		loadBanner: function () {
			// If we're forcing a banner for testing, just load that.
			if ( mw.centralNotice.data.getVars.banner ) {
				// Sending an empty string as the campaign name will make the
				// server skip checks on campaign validity.
				mw.centralNotice.loadTestingBanner(
					mw.centralNotice.data.getVars.banner, '' );
			} else {
				mw.centralNotice.loadRandomBanner();
			}
		},
		loadTestingBanner: function ( bannerName, campaign ) {
			var bannerPageQuery, url;

			mw.centralNotice.data.testing = true;

			// Get the requested banner
			bannerPageQuery = {
				banner: bannerName,
				campaign: campaign,
				uselang: mw.config.get( 'wgUserLanguage' ),
				debug: mw.centralNotice.data.getVars.debug
			};

			url = new mw.Uri( mw.config.get( 'wgCentralSelectedBannerDispatcher' ) );
			url.extend( bannerPageQuery );

			$.ajax({
				url:  url.toString(),
				dataType: 'script',
				cache: true
			});
		},
		loadRandomBanner: function () {

			var fetchBannerQueryParams,
				scriptUrl;

			mw.centralNotice.chooseRandomBanner();

			if ( mw.centralNotice.data.banner ) {

				// A banner was chosen! Let's fetch it.

				fetchBannerQueryParams = {
					banner: mw.centralNotice.data.banner,
					campaign: mw.centralNotice.data.campaign,
					uselang: mw.config.get( 'wgUserLanguage' ),
					debug: mw.centralNotice.data.getVars.debug
				};

				scriptUrl = new mw.Uri( mw.config.get( 'wgCentralSelectedBannerDispatcher' ) );
				scriptUrl.extend( fetchBannerQueryParams );

				// The returned javascript will call insertBanner() after the
				// banner is retrieved.
				$.ajax( {
					url: scriptUrl.toString(),
					dataType: 'script',
					cache: true
				} );

			} else {

				// No banner for this user!

				// Set the onlySampleRI flag to true to sample empty results
				// and return them via Special:RecordImpression.
				// TODO Refactor
				mw.centralNotice.onlySampleRI = true;
				mw.centralNotice.insertBanner( false );
			}
		},
		/**
		 * Choose a banner or no banner randomly (or based on the
		 * randomcampaign and randombanner dev-only URL parameters, which may
		 * be sent to force random values for development purposes). If a banner
		 * is chosen its name will be put in mw.centralNotice.data.banner and
		 * the campaign name will be put in mw.centralNotice.data.campaign.
		 */
		chooseRandomBanner: function () {

			var randomCampaign, randomBanner;

			// Sanity check, and don't stop the show if this happens
			if ( !mw.cnBannerControllerLib.isChoiceDataSet() ) {
				mw.log( 'No choice data received for CentralNotice banner selection.' );
				return;
			}

			// If there are no choices, bow out
			if ( !mw.cnBannerControllerLib.choicesAvailable() ) {
				return;
			}

			// The server did send one or more choices: let the processing begin!

			// Filter choiceData on country and device. Only campaigns that
			// target the user's country and have at least one banner for
			// the user's logged-in status and device pass this filter.
			mw.cnBannerControllerLib.filterChoiceData();

			// Again check if there are choices available. This result may
			// have changed following the above call to filterChoiceData().
			if ( !mw.cnBannerControllerLib.choicesAvailable() ) {
				return;
			}

			// Calculate the user's probability of getting each campaign
			mw.cnBannerControllerLib.calculateCampaignAllocations();

			// Get a random seed or use the dev-only randomcampaign
			// parameter from the URL, and choose the campaign.
			randomCampaign =
				mw.centralNotice.data.getVars.randomcampaign || Math.random();

			mw.cnBannerControllerLib.chooseCampaign( randomCampaign );

			// Check if a campaign was selected (we might have chosen an
			// unallocated block).
			if ( !mw.cnBannerControllerLib.isAnyCampaignChosen() ) {
				return;
			}

			// Run campaign mixins pre-banner hooks for this campaign
			mw.cnBannerControllerLib.runMixinsPreBannerHooks();

			// Do all things bucket. Retrieve or generate a bucket for this
			// campaign. Then, update expiry dates and remove expired buckets as
			// necessary.
			mw.cnBannerControllerLib.processBuckets();

			// Create a list of possible banners available in this campaign for
			// the user's bucket, logged-in status and device, and calculate
			// allocations.
			mw.cnBannerControllerLib.makePossibleBanners();

			// Because of our wonky domain model, it's possible to have banners
			// with different criteria (logged-in status, device) in different
			// buckets in the same campaign. So we might be in a bucket with no
			// banners for this user, which would mean no possible banners.
			if ( !mw.cnBannerControllerLib.bannersAvailable() ) {
				return;
			}

			// Calculate banner allocations.
			mw.cnBannerControllerLib.calculateBannerAllocations();

			// Get another random seed or use the randombanner= parameter
			// from the URL, and choose the banner.
			randomBanner =
				mw.centralNotice.data.getVars.randombanner || Math.random();

			mw.cnBannerControllerLib.chooseBanner( randomBanner );
		},
		// Record banner impression using old-style URL
		recordImpression: function( data ) {

			var sampleRate,
				url = new mw.Uri( mw.config.get( 'wgCentralBannerRecorder' ) );

			if ( mw.centralNotice.onlySampleRI ) {

				sampleRate = mw.config.get( 'wgCentralNoticeSampleRate' );

				if ( Math.random() > sampleRate ) {
					return;
				}

			} else {
				sampleRate = 1;
			}

			// Record the sample rate to ensure the resulting data can be
			// interpreted.
			$.extend( data, { sampleRate: sampleRate } );
			url.extend( data );

			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( url.toString() );
			} else {
				setTimeout( function () {
					document.createElement( 'img' ).src = url.toString();
				}, 0 );
			}
		},
		loadQueryStringVariables: function () {
			document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
				mw.centralNotice.data.getVars[decode( p1 )] = decode( p2 );
			} );
		},
		/**
		 * Legacy function for getting the legacy global bucket. Left here
		 * for paranoid JS-breakage avoidance debugging fun fun.
		 * @deprecated
		 */
		getBucket: function() {
			mw.log( 'Legacy mw.bannerController.getBucket() is deprecated no-op.' );
			return 0;
		},
		/**
		 * Legacy function for storing the legacy global bucket Left here
		 * for paranoid JS-breakage avoidance.
		 * @deprecated
		 */
		storeBucket: function() {
			mw.log( 'Legacy mw.bannerController.storeBucket() is deprecated no-op.' );
		},
		initialize: function () {
			// === Do not allow CentralNotice to be re-initialized. ===
			if ( mw.centralNotice.alreadyRan ) {
				return;
			}
			mw.centralNotice.alreadyRan = true;

			// === Attempt to load parameters from the query string ===
			mw.centralNotice.loadQueryStringVariables();

			// === Initialize some data ===
			mw.centralNotice.data.anonymous = ( mw.config.get( 'wgUserName' ) === null );
			mw.centralNotice.data.country = mw.centralNotice.data.getVars.country || window.Geo.country || 'XX';
			mw.centralNotice.data.addressFamily = ( window.Geo.IPv6 || window.Geo.af === 'v6' ) ? 'IPv6' : 'IPv4';
			mw.centralNotice.isPreviewFrame = (mw.config.get( 'wgCanonicalSpecialPageName' ) === 'BannerPreview');
			mw.centralNotice.data.device = mw.centralNotice.data.getVars.device || mw.config.get( 'wgMobileDeviceName', 'desktop' );

			// === Do not actually load a banner on a special page ===
			//     But we keep this after the above initialization for CentralNotice pages
			//     that do banner previews.
			if ( mw.config.get( 'wgNamespaceNumber' ) == -1 && !mw.centralNotice.isPreviewFrame ) {
				return;
			}

			// === Create Deferred and Promise Objects ===
			mw.centralNotice.deferredObjs.bannerLoaded = $.Deferred();
			mw.centralNotice.events.bannerLoaded = mw.centralNotice.deferredObjs.bannerLoaded.promise();

			// === Final prep to loading banner ===
			// Add the CentralNotice div so that insert banner has something to latch on to.
			$( '#siteNotice' ).prepend(
				'<div id="centralNotice"></div>'
			);

			// If the user has no country assigned, we try a new lookup via
			// geoiplookup.wikimedia.org. This hostname has no IPv6 address,
			// so will force dual-stack users to fall back to IPv4.
			if ( mw.centralNotice.data.country === 'XX' ) {
				$.ajax( {
					url: '//geoiplookup.wikimedia.org/',
					dataType: 'script',
					cache: true
				} ).always( function () {
					if ( window.Geo && window.Geo.country ) {
						mw.centralNotice.data.country = window.Geo.country;
					} else {
						mw.centralNotice.data.country = 'XX';
					}
					// Set a session cookie so that subsequent page views neither trigger
					// an IP lookup in Varnish nor an AJAX request to geoiplookup.
					synthesizeGeoCookie();
					mw.centralNotice.loadBanner();
				} );
			} else {
				mw.centralNotice.loadBanner();
			}
		}
	};

	// Function that actually inserts the banner into the page after it is retrieved
	// Has to be global because of compatibility with legacy code.
	//
	// Will query the DOM to see if mw.centralNotice.bannerData.alterImpressionData()
	// exists in the banner. If it does it is expected to return true if the banner was
	// shown, The alterImpressionData function is called with the impressionData variable
	// filled below which can be altered at will by the function (thought it is recommended
	// to only add variables, not remove/alter them as this may have effects on upstream
	// analytics.)
	//
	// Regardless of impression state however, if this is a testing call, ie: the
	// banner was specifically requested via banner= the record impression call
	// will NOT be made.
	//
	mw.centralNotice.insertBanner = function ( bannerJson ) {
		var durations, cookieName, cookieVal, deleteOld, now,
			parsedCookie, bucket;

		var impressionData = $.extend( mw.centralNotice.impressionData, {
			country: mw.centralNotice.data.country,
			uselang: mw.config.get( 'wgUserLanguage' ),
			project: mw.config.get( 'wgNoticeProject' ),
			db: mw.config.get( 'wgDBname' ),
			anonymous: mw.centralNotice.data.anonymous,
			device: mw.centralNotice.data.device
		} );

		if ( !bannerJson ) {
			// There was no banner returned from the server
			impressionData.result = 'hide';
			impressionData.reason = 'empty';

		} else {
			// Ok, we have a banner!
			impressionData.banner = bannerJson.bannerName;
			impressionData.campaign = bannerJson.campaign;

			// Bucket stuff varies depending on where we're choosing banners:

			// Set bucket info the impression data, unless a banner is being
			// forced via the banner URL param.
			if ( !mw.centralNotice.data.getVars.banner ) {
				bucket = mw.cnBannerControllerLib.bucketsByCampaign[impressionData.campaign];
				impressionData.bucket = bucket.val;
				impressionData.bucketStart = bucket.start;
				impressionData.bucketEnd = bucket.end;
			}

			// Get the banner type for more queryness
			mw.centralNotice.data.category = encodeURIComponent( bannerJson.category );

			// Do some builtin hide cookie things, unless we're testing.
			// TODO: Push this into a mixin.
			if ( impressionData.result !== 'hide' && mw.centralNotice.data.testing === false ) {
				cookieName = 'centralnotice_hide_' + mw.centralNotice.data.category;
				cookieVal = $.cookie( cookieName );
				durations = mw.config.get( 'wgNoticeCookieDurations' );
				now = new Date().getTime() / 1000;
				deleteOld = ( now > mw.config.get( 'wgNoticeOldCookieApocalypse' ) );

				if ( cookieVal === 'hide' && deleteOld ) {
					// Delete old-style cookie
					$.cookie( cookieName, null, { path: '/' } );
				} else if ( cookieVal === 'hide' ) {
					// We'll hide the banner because of a legacy hide cookie.
					impressionData.result = 'hide';
					impressionData.reason = 'cookie';
					// Or 'donate'? Legacy 'close' cookies are gone by now
				} else if ( cookieVal !== null && cookieVal.indexOf( '{' ) === 0 ) {
					parsedCookie = JSON.parse( cookieVal );
					if ( durations[parsedCookie.reason]
						&& now < parsedCookie.created + durations[parsedCookie.reason]
					) {
						// We'll hide the banner because of a cookie with a reason
						impressionData.result = 'hide';
						impressionData.reason = parsedCookie.reason;
					}
				}
			}

			if ( impressionData.result === 'show' ) {
				// Not hidden yet, inject the banner
				mw.centralNotice.bannerData.bannerName = bannerJson.bannerName;
				$( 'div#centralNotice' )
					.attr( 'class', mw.html.escape( 'cn-' + mw.centralNotice.data.category ) )
					.prepend( '<!--googleoff: all-->' + bannerJson.bannerHtml + '<!--googleon: all-->' );

				// Query the initial impression state if the banner callback exists
				var bannerShown = true;
				if ( typeof mw.centralNotice.bannerData.alterImpressionData === 'function' ) {
					bannerShown = mw.centralNotice.bannerData.alterImpressionData( impressionData );
				} else {
					impressionData.alterFunctionMissing = true;
				}

				// eventually we want to unify the ordering here and always return
				// the result, banner, campaign in that order. presently this is not
				// possible without some rework of how the analytics scripts work.
				// ~~ as of 2012-11-27
				if ( !bannerShown ) {
					impressionData.result = 'hide';
					// alterImpressionData should set a reason, but we'll set a
					// default if it didn't
					if ( !impressionData.reason ) {
						impressionData.reason = 'alterImpressionData';
					}
				}
			}
		}

		if ( !mw.centralNotice.data.testing ) {
			mw.centralNotice.recordImpression( impressionData );
		}
		mw.centralNotice.deferredObjs.bannerLoaded.resolve( impressionData );
	};

	// Function for hiding banners when the user clicks the close button
	mw.centralNotice.hideBanner = function () {
		var d = new Date(),
			cookieVal = {
				v: 1,
				created: Math.floor( d.getTime() / 1000 ),
				reason: 'close'
			},
			duration = mw.config.get( 'wgNoticeCookieDurations' ).close;

		// Immediately hide the banner on the page
		$( '#centralNotice' ).hide();

		// Set a local hide cookie for this banner category
		d.setSeconds( d.getSeconds() + duration );

		$.cookie(
			'centralnotice_hide_' + mw.centralNotice.data.category,
			JSON.stringify( cookieVal ),
			{ expires: d, path: '/' }
		);

		// Iterate over all configured URLs to hide this category of banner for all
		// wikis in a cluster
		// FIXME: can we rely on mw.Uri here?
		$.each( mw.config.get( 'wgNoticeHideUrls' ), function( idx, value ) {
			(new Image()).src = value + '?' + $.param( {
				'duration': duration,
				'category': mw.centralNotice.data.category,
				'reason' : 'close'
			} );
		} );


	};

	// This function is deprecated
	mw.centralNotice.toggleNotice = function () {
		mw.centralNotice.hideBanner();
	};

	// Deprecation notices:
	mw.log.deprecate( window, 'insertBanner', mw.centralNotice.insertBanner, 'Use mw.centralNotice method instead' );
	mw.log.deprecate( window, 'hideBanner', mw.centralNotice.hideBanner, 'Use mw.centralNotice method instead' );
	mw.log.deprecate( window, 'toggleNotice', mw.centralNotice.toggleNotice, 'Use mw.centralNotice method instead' );

	// Initialize CentralNotice
	$( function() {
		mw.centralNotice.initialize();
	});
} )( jQuery, mediaWiki );
