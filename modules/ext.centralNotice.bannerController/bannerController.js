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

	mw.centralNotice = {
		/**
		 * Central Notice Required Data
		 */
		data: {
			getVars: {},
			bannerType: 'default',
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
				device: mw.centralNotice.data.getVars.device || mw.config.get( 'wgMobileDeviceName', 'desktop' )
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
				device: mw.config.get( 'wgMobileDeviceName', 'desktop' ),
				slot: Math.floor( Math.random() * RAND_MAX ) + 1
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
		waitForCountry: function () {
			if ( Geo.country ) {
				mw.centralNotice.data.country = Geo.country;
				mw.centralNotice.loadBanner();
			} else {
				mw.centralNotice.data.waitCycle++;
				if ( mw.centralNotice.data.waitCycle < 10 ) {
					window.setTimeout( mw.centralNotice.waitForCountry, 100 );
				} else {
					mw.centralNotice.data.country = 'XX';
					mw.centralNotice.loadBanner();
				}
			}
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

			// === Initialize things that don't come from MW itself ===
			mw.centralNotice.data.bucket = mw.centralNotice.getBucket();
			mw.centralNotice.data.country = mw.centralNotice.data.getVars.country || Geo.country || 'XX';
			mw.centralNotice.isPreviewFrame = (mw.config.get( 'wgCanonicalSpecialPageName' ) === 'BannerPreview');

			// === Attempt to load parameters from the query string ===
			mw.centralNotice.loadQueryStringVariables();

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

			// If the user has no counry assigned, we try a new lookup via
			// geoiplookup.wikimedia.org. This hostname has no IPv6 address,
			// so will force dual-stack users to fall back to IPv4.
			if ( mw.centralNotice.data.country === 'XX' ) {
				$( 'body' ).append( '<script src="//geoiplookup.wikimedia.org/"></script>' );
				mw.centralNotice.data.waitCycle = 0;
				mw.centralNotice.waitForCountry();
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
		var url, targets, data;

		var impressionData = {
			country: mw.centralNotice.data.country,
			uselang: mw.config.get( 'wgUserLanguage' ),
			project: mw.config.get( 'wgNoticeProject' ),
			db: mw.config.get( 'wgDBname' ),
			bucket: mw.centralNotice.data.bucket,
			anonymous: mw.config.get( 'wgUserName' ) === null,
			device: mw.config.get( 'wgMobileDeviceName', 'desktop' )
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
			mw.centralNotice.data.bannerType = ( bannerJson.fundraising ? 'fundraising' : 'default' );

			if ( typeof mw.centralNotice.bannerData.preload === 'function'
					&& !mw.centralNotice.bannerData.preload() ) {
				impressionResultData = {
					result: 'hide',
					reason: 'preload'
				}
			} else if ( $.cookie( 'stopMobileRedirect' ) === 'true' ) {
				// We do not show banners to mobile devices browsing the desktop site. It's not
				// guaranteed how they will react.
				impressionResultData = {
					result: 'hide',
					reason: 'mobile'
				}
			} else if (
				$.cookie( 'centralnotice_' + encodeURIComponent( mw.centralNotice.data.bannerType ) ) === 'hide' &&
				!mw.centralNotice.data.testing
			) {
				// The banner was hidden by a bannertype hide cookie and we're not testing
				impressionResultData = {
					result: 'hide',
					reason: 'cookie'
				}
			} else {
				// All conditions fulfilled, inject the banner
				mw.centralNotice.bannerData.bannerName = bannerJson.bannerName;
				$( 'div#centralNotice' )
					.attr( 'class', mw.html.escape( 'cn-' + mw.centralNotice.data.bannerType ) )
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
	window.hideBanner = function () {
		// Hide current banner
		$( '#centralNotice' ).hide();

		// Get the type of the current banner (e.g. 'fundraising')
		var bannerType = mw.centralNotice.data.bannerType || 'default';

		// Set the banner hiding cookie to hide future banners of the same type
		var d = new Date();
		d.setSeconds( d.getSeconds() + mw.config.get( 'wgNoticeCookieShortExpiry' ) );
		$.cookie(
			'centralnotice_' + encodeURIComponent( bannerType ),
			'hide',
			{ expires: d, path: '/' }
		);
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
