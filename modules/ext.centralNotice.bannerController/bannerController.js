/* global Geo */
( function ( $, mw ) {

	mw.loader.using( 'mediawiki.util', function () {
		$.ajaxSetup({
			cache: true
		});
		mw.centralNotice = {
			data: {
				getVars: {},
				bannerType: 'default'
			},
			initialize: function() {
				window.loadBanner( window.banner );
			},
		};
	} );

	// Function that actually inserts the banner into the page after it is retrieved
	// Has to be global because of compatibility with legacy code.
	// TODO: Migrate away from global functions
	window.insertBanner = function ( banner ) {
		window.banner = banner;
	};

	window.loadBanner = function ( bannerJson ) {
		var url, targets;

		$( 'div#siteNotice' )
			.append( '<div id="centralNotice" class="' + mw.html.escape( 'cn-' + bannerJson.bannerType ) + '">' )
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
					country: Geo.country
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
