/* global Geo */
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

	mw.loader.using( 'mediawiki.util', function () {
		$.ajaxSetup({
			cache: true
		});
		mw.centralNotice = {
			data: {
				getVars: {},
				bannerType: 'default'
			},
			loadBanner: function ( bannerName, campaign, bannerType ) {
				var bannerPageQuery, bannerScript;

				// Store the bannerType in case we need to set a banner hiding cookie later
				mw.centralNotice.data.bannerType = bannerType;
				// Get the requested banner
				bannerPageQuery = {
					title: 'Special:BannerLoader',
					banner: bannerName,
					campaign: campaign,
					userlang: mw.config.get( 'wgUserLanguage' ),
					db: mw.config.get( 'wgDBname' ),
					sitename: mw.config.get( 'wgSiteName' ),
					country: mw.centralNotice.data.country
				};
				bannerScript = '<script src="' +
					mw.html.escape(
						mw.config.get( 'wgCentralPagePath' ) + '?' + $.param( bannerPageQuery )
					) +
					'"></script>';
				if ( document.cookie.indexOf( 'centralnotice_' + encodeURIComponent( bannerType ) + '=hide' ) === -1 ) {
					$( '#siteNotice' ).prepend(
						'<div id="centralNotice" class="' + mw.html.escape( 'cn-' + bannerType ) + '">' +
							// The bannerScript is inserted raw since .html() strips out <script> tags
							bannerScript +
							'</div>'
					);
				}
			},
			loadBannerList: function ( geoOverride ) {
				var geoLocation;

				if ( geoOverride ) {
					geoLocation = geoOverride; // override the geo info
				} else {
					geoLocation = mw.centralNotice.data.country; // pull the geo info
				}

				var bucket = $.cookie( 'centralnotice_bucket' );
				if ( bucket === null ) {
					bucket = Math.round( Math.random() );
					$.cookie(
						'centralnotice_bucket', bucket,
						{ expires: 7, path: '/' }
					);
				}

				// Prevent loading banners on Special pages
				if ( mw.config.get( 'wgNamespaceNumber' ) !== -1 ) {
					$.ajax( {
						url: mw.util.wikiScript(),
						data: {
							bcache: '1',
							title: mw.config.get( 'wgNoticeBannerListLoader' ),
							language: mw.config.get( 'wgContentLanguage' ),
							project: mw.config.get( 'wgNoticeProject' ),
							country: geoLocation,
							anonymous: mw.config.get( 'wgUserName' ) === null,
							bucket: bucket
						},
						dataType: 'json'
					}).done( mw.centralNotice.chooseBanner );
				}
			},
			chooseBanner: function ( bannerList ) {
				var i, idx, count, rnd;

				if ( bannerList.centralnoticeallocations
					&& bannerList.centralnoticeallocations.banners )
				{
					bannerList = bannerList.centralnoticeallocations.banners;
				}

				if ( bannerList.length < 1 ) {
					return;
				}

				// Obtain randomness
				rnd = Math.random();

				// Obtain banner index
				count = 0;
				// default to the last banner...
				idx = bannerList.length - 1;
				for (i = 0; i < bannerList.length; i++ ) {
					count += bannerList[i].allocation;
					if ( rnd <= count ) {
						idx = i;
						break;
					}
				}

				// Load a random banner from our groomed list
				mw.centralNotice.loadBanner(
					bannerList[idx].name,
					bannerList[idx].campaign,
					( bannerList[idx].fundraising ? 'fundraising' : 'default' )
				);
			},
			getQueryStringVariables: function () {
				document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
					mw.centralNotice.data.getVars[decode( p1 )] = decode( p2 );
				} );
			},
			initialize: function () {
				mw.centralNotice.data.country = Geo.country || 'XX';

				// Initialize the query string vars
				mw.centralNotice.getQueryStringVariables();
				if ( mw.centralNotice.data.getVars.banner ) {
					// if we're forcing one banner
					mw.centralNotice.loadBanner( mw.centralNotice.data.getVars.banner, 'none', 'testing' );
				} else {
					// Look for banners ready to go NOW
					mw.centralNotice.loadBannerList( mw.centralNotice.data.getVars.country );
				}
			}
		};
	} );

	// Function that actually inserts the banner into the page after it is retrieved
	// Has to be global because of compatibility with legacy code.
	// TODO: Migrate away from global functions
	window.insertBanner = function ( bannerJson ) {
		var url, targets;

		$( 'div#centralNotice' ).prepend( bannerJson.bannerHtml );
		if ( bannerJson.autolink ) {
			url = mw.config.get( 'wgNoticeFundraisingUrl' );
			if ( ( bannerJson.landingPages !== null ) && bannerJson.landingPages.length ) {
				targets = String( bannerJson.landingPages ).split( ',' );
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
	};

	// Function for hiding banners when the user clicks the close button
	window.hideBanner = function () {
		// Hide current banner
		$( '#centralNotice' ).hide();

		// Get the type of the current banner (e.g. 'fundraising')
		var bannerType = mw.centralNotice.data.bannerType || 'default';

		// Set the banner hiding cookie to hide future banners of the same type
		var d = new Date();
		d.setTime( d.getTime() + ( 14 * 24 * 60 * 60 * 1000 ) ); // two weeks
		document.cookie = 'centralnotice_' + encodeURIComponent( bannerType ) + '=hide; expires=' + d.toGMTString() + '; path=/';
	};

	// This function is deprecated
	window.toggleNotice = function () {
		window.hideBanner();
	};

} )( jQuery, mediaWiki );
