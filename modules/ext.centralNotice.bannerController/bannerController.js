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
					banner: bannerName,
					campaign: campaign,
					userlang: mw.config.get( 'wgUserLanguage' ),
					sitename: mw.config.get( 'wgSiteName' ),
					project: mw.config.get( 'wgNoticeProject' ),
					country: mw.centralNotice.data.country
				};
				mw.centralNotice.loadScript( bannerPageQuery );
			},
			loadRandomBanner: function () {
				var RAND_MAX = 30;

				var bucket = $.cookie( 'centralnotice_bucket' );
				if ( bucket == null ) {
					bucket = Math.round( Math.random() );
					$.cookie(
						'centralnotice_bucket', bucket,
						{ expires: 7, path: '/' }
					);
				}

				var bannerDispatchQuery = {
					userlang: mw.config.get( 'wgUserLanguage' ),
					sitename: mw.config.get( 'wgSiteName' ),
					project: mw.config.get( 'wgNoticeProject' ),
					anonymous: mw.config.get( 'wgUserName' ) === null,
					bucket: bucket,
					country: mw.centralNotice.data.country,
					slot: Math.floor( Math.random() * RAND_MAX) + 1
				};
				mw.centralNotice.loadScript( bannerDispatchQuery );
			},
			loadScript: function ( queryParams ) {
				var scriptUrl = mw.config.get( 'wgCentralBannerDispatcher' )
					+ '?' + $.param( queryParams );
				var bannerScript = '<script src="' + mw.html.escape( scriptUrl ) + '"></script>';
				$( '#centralNotice' ).prepend( bannerScript );
			},
			getQueryStringVariables: function () {
				document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
					mw.centralNotice.data.getVars[decode( p1 )] = decode( p2 );
				} );
			},
			initialize: function () {
				// Prevent loading banners on Special pages
				if ( mw.config.get( 'wgNamespaceNumber' ) == -1 ) {
					return;
				}

				mw.centralNotice.data.country = Geo.country || 'XX';

				// Initialize the query string vars
				mw.centralNotice.getQueryStringVariables();

				$( '#siteNotice' ).prepend(
					'<div id="centralNotice"></div>'
				);

				if ( mw.centralNotice.data.getVars.banner ) {
					// if we're forcing one banner
					mw.centralNotice.loadBanner( mw.centralNotice.data.getVars.banner, 'none', 'testing' );
				} else {
					mw.centralNotice.loadRandomBanner();
				}
			},
		};
	} );

	// Function that actually inserts the banner into the page after it is retrieved
	// Has to be global because of compatibility with legacy code.
	// TODO: Migrate away from global functions
	window.insertBanner = function ( bannerJson ) {
		var url, targets;

		if ( document.cookie.indexOf( 'centralnotice_' + encodeURIComponent( bannerJson.bannerType ) + '=hide' ) != -1 ) {
			return;
		}

		$( 'div#centralNotice' )
			.attr( 'class', mw.html.escape( 'cn-' + bannerJson.bannerType ) )
			.prepend( bannerJson.bannerHtml );

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
