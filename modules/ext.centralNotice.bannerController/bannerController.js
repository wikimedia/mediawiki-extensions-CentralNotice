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
			$.cookie( 'GeoIP', '::::vx', { path: '/' } );
			return;
		}

		var parts = [
			window.Geo.country,
			window.Geo.city.replace( /[^a-z]/i, '_' ),
			window.Geo.lat,
			window.Geo.lon,
			( window.Geo.IP && window.Geo.IP.match(':') ) ? 'v6' : 'v4'
		];

		$.cookie( 'GeoIP', parts.join( ':' ), { path: '/' } );
	}

	window.Geo = ( function ( match, country, city, lat, lon, af ) {
		return {
			country: country,
			city: city,
			lat: lat && parseFloat( lat ),
			lon: lon && parseFloat( lon ),
			af: af
		};
	} ).apply( null, ( $.cookie( 'GeoIP' ) || '' ).match( /([^:]*):([^:]*):([^:]*):([^:]*):([^;]*)/ || [] ) );

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
			if ( mw.centralNotice.data.getVars.banner ) {
				// If we're forcing one banner
				mw.centralNotice.loadTestingBanner( mw.centralNotice.data.getVars.banner, 'none', 'testing' );
			} else {
				mw.centralNotice.loadRandomBanner();
			}
		},
		loadTestingBanner: function ( bannerName, campaign ) {
			var bannerPageQuery;

			mw.centralNotice.data.testing = true;

			// Get the requested banner
			bannerPageQuery = {
				title: 'Special:BannerLoader',
				banner: bannerName,
				campaign: campaign,
				uselang: mw.config.get( 'wgUserLanguage' ),
				db: mw.config.get( 'wgDBname' ),
				project: mw.config.get( 'wgNoticeProject' ),
				country: mw.centralNotice.data.country,
				device: mw.centralNotice.data.device,
				debug: mw.centralNotice.data.getVars.debug
			};

			$.ajax({
				url: mw.config.get( 'wgCentralPagePath' ) + '?' + $.param( bannerPageQuery ),
				dataType: 'script',
				cache: true
			});
		},
		loadRandomBanner: function () {
			var RAND_MAX = 30;
			var bannerDispatchQuery = {
				uselang: mw.config.get( 'wgUserLanguage' ),
				sitename: mw.config.get( 'wgSiteName' ),
				project: mw.config.get( 'wgNoticeProject' ),
				anonymous: mw.config.get( 'wgUserName' ) === null,
				bucket: mw.centralNotice.data.bucket,
				country: mw.centralNotice.data.country,
				device: mw.centralNotice.data.device,
				slot: Math.floor( Math.random() * RAND_MAX ) + 1,
				debug: mw.centralNotice.data.getVars.debug
			};
			var scriptUrl = mw.config.get( 'wgCentralBannerDispatcher' ) + '?' + $.param( bannerDispatchQuery );

			$.ajax({
				url: scriptUrl,
				dataType: 'script',
				cache: true
			});
		},
		// TODO: move function definitions once controller cache has cleared
		insertBanner: function( bannerJson ) {
			window.insertBanner( bannerJson );
		},
		toggleNotice: function () {
			window.toggleNotice();
		},
		hideBanner: function() {
			window.hideBanner();
		},
		// Record banner impression using old-style URL
		recordImpression: function( data ) {
			var url = mw.config.get( 'wgCentralBannerRecorder' ) + '?' + $.param( data );
			(new Image()).src = url;
		},
		loadQueryStringVariables: function () {
			document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
				mw.centralNotice.data.getVars[decode( p1 )] = decode( p2 );
			} );
		},
		getBucket: function() {
			var dataString = $.cookie( 'centralnotice_bucket' ) || '',
				bucket = dataString.split('-')[0],
				validity = dataString.split('-')[1],
				expValidity = mw.config.get( 'wgNoticeNumberOfBuckets' ) + '.' + mw.config.get( 'wgNoticeNumberOfControllerBuckets' );

			if ( ( bucket === null ) || ( validity !== expValidity ) ) {
				bucket = Math.floor(
					Math.random() * mw.config.get( 'wgNoticeNumberOfControllerBuckets' )
				);
				$.cookie(
					'centralnotice_bucket', bucket + '-' + expValidity,
					{ expires: mw.config.get( 'wgNoticeBucketExpiry' ), path: '/' }
				);
			}

			return bucket;
		},
		initialize: function () {
			// === Do not allow CentralNotice to be re-initialized. ===
			if ( mw.centralNotice.alreadyRan ) {
				return;
			}
			mw.centralNotice.alreadyRan = true;

			// === Attempt to load parameters from the query string ===
			mw.centralNotice.loadQueryStringVariables();

			// === Initialize things that don't come from MW itself ===
			mw.centralNotice.data.bucket = mw.centralNotice.getBucket();
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
	// TODO: Migrate away from global functions
	window.insertBanner = function ( bannerJson ) {
		var url, targets;

		var impressionData = {
			country: mw.centralNotice.data.country,
			uselang: mw.config.get( 'wgUserLanguage' ),
			project: mw.config.get( 'wgNoticeProject' ),
			db: mw.config.get( 'wgDBname' ),
			bucket: mw.centralNotice.data.bucket,
			anonymous: mw.config.get( 'wgUserName' ) === null,
			device: mw.centralNotice.data.device
		};

		// This gets prepended to the impressionData at the end
		var impressionResultData = null;

		if ( !bannerJson ) {
			// There was no banner returned from the server
			impressionResultData = {
				result: 'hide',
				reason: 'empty'
			};
		} else {
			// Ok, we have a banner! Get the banner type for more queryness
			mw.centralNotice.data.category = encodeURIComponent( bannerJson.category );

			if ( typeof mw.centralNotice.bannerData.preload === 'function'
					&& !mw.centralNotice.bannerData.preload() ) {
				impressionResultData = {
					result: 'hide',
					reason: 'preload'
				};
			} else if (
				mw.centralNotice.data.testing === false && /* And we want to see what we're testing! :) */
				(
					$.cookie( 'centralnotice_hide_' + mw.centralNotice.data.category ) === 'hide' ||
					/* Legacy for long duration fundraising cookies; remove after Oct 1, 2014 */
					$.cookie( 'centralnotice_' + mw.centralNotice.data.category ) === 'hide'
				)
			) {
				// The banner was hidden by a category hide cookie and we're not testing
				impressionResultData = {
					result: 'hide',
					reason: 'cookie'
				};
			} else {
				// All conditions fulfilled, inject the banner
				mw.centralNotice.bannerData.bannerName = bannerJson.bannerName;
				$( 'div#centralNotice' )
					.attr( 'class', mw.html.escape( 'cn-' + mw.centralNotice.data.category ) )
					.prepend( bannerJson.bannerHtml );

				// Create landing page links if required
				if ( bannerJson.autolink ) {
					url = mw.config.get( 'wgNoticeFundraisingUrl' );
					if ( ( bannerJson.landingPages !== null ) && bannerJson.landingPages.length ) {
						targets = String( bannerJson.landingPages ).split( ',' );
						if ( $.inArray( mw.centralNotice.data.country, mw.config.get( 'wgNoticeXXCountries' ) ) !== -1 ) {
							mw.centralNotice.data.country = 'XX';
						}
						url += "?" + $.param( {
							landing_page: targets[Math.floor( Math.random() * targets.length )].replace( /^\s+|\s+$/, '' ),
							utm_medium: 'sitenotice',
							utm_campaign: bannerJson.campaign,
							utm_source: bannerJson.bannerName,
							language: mw.config.get( 'wgUserLanguage' ),
							country: mw.centralNotice.data.country
						} );
						$( '#cn-landingpage-link' ).attr( 'href', url );
					}
				}

				// Query the initial impression state if the banner callback exists
				var bannerShown = true;
				if ( typeof mw.centralNotice.bannerData.alterImpressionData === 'function' ) {
					bannerShown = mw.centralNotice.bannerData.alterImpressionData( impressionData );
				}

				// eventually we want to unify the ordering here and always return
				// the result, banner, campaign in that order. presently this is not
				// possible without some rework of how the analytics scripts work.
				// ~~ as of 2012-11-27
				if ( bannerShown ) {
					impressionResultData = {
						banner: bannerJson.bannerName,
						campaign: bannerJson.campaign,
						result: 'show'
					};
				} else {
					impressionResultData = {
						result: 'hide'
					};
				}
			}
		}

		// Record whatever impression we made
		impressionResultData = $.extend( impressionResultData, impressionData );
		if ( !mw.centralNotice.data.testing ) {
			mw.centralNotice.recordImpression( impressionResultData );
		}
		mw.centralNotice.deferredObjs.bannerLoaded.resolve( impressionResultData );
	};

	// Function for hiding banners when the user clicks the close button
	// TODO: Make it call up to the special page for cross project hiding
	window.hideBanner = function () {
		var d = new Date(),
			expiry = mw.config.get( 'wgNoticeCookieShortExpiry' );

		// Immediately hide the banner on the page
		$( '#centralNotice' ).hide();

		// Set a local hide cookie for this banner category
		d.setSeconds( d.getSeconds() + expiry );
		$.cookie(
			'centralnotice_hide_' + mw.centralNotice.data.category,
			'hide',
			{ expires: d, path: '/' }
		);

		// Iterate over all configured URLs to hide this category of banner for all
		// wikis in a cluster
		$.each( mw.config.get( 'wgNoticeHideUrls' ), function( idx, value ) {
			(new Image()).src = value + '?' + $.param( {
				'duration': expiry,
				'category': mw.centralNotice.data.category
			} );
		} );


	};

	// This function is deprecated
	window.toggleNotice = function () {
		window.hideBanner();
	};

	// Initialize CentralNotice
	$( function() {
		mw.centralNotice.initialize();
	});
} )( jQuery, mediaWiki );
