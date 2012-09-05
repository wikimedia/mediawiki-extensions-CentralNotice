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
					country: Geo.country
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
					geoLocation = Geo.country; // pull the geo info
				}
				// Prevent loading banners on Special pages
				if ( mw.config.get( 'wgNamespaceNumber' ) !== -1 ) {
					$.ajax( {
						url: mw.util.wikiScript(),
						data: {
							cache: '/cn.js',
							title: mw.config.get( 'wgNoticeBannerListLoader' ),
							language: mw.config.get( 'wgContentLanguage' ),
							project: mw.config.get( 'wgNoticeProject' ),
							country: geoLocation
						},
						dataType: 'json'
					}).done( mw.centralNotice.chooseBanner );
				}
			},
			chooseBanner: function ( bannerList ) {
				var i, idx, count, rnd,
					groomedBannerList = [],
					campaignWeights = {},
					numFilteredCampaigns = 0,
					zLevel = 0,
					anonymous = ( mw.config.get( 'wgUserName' ) === null );

				// Find the highest campaign Z level that fulfills filter constraints
				for ( i = 0; i < bannerList.length; i++ ) {
					if ( ( bannerList[i].campaign_z_index >= zLevel ) &&
						( ( !anonymous && bannerList[i].display_account === 1 ) ||
							( anonymous && bannerList[i].display_anon === 1 )
						)
					) {
						zLevel = bannerList[i].campaign_z_index;
					}
				}

				// Iterate through all banners; filtering for: user/anon and z level
				// Also determine weight counts per campaign
				for (i = 0; i < bannerList.length; i++ ) {
					if ( ( bannerList[i].campaign_z_index === zLevel ) &&
						( ( !anonymous && bannerList[i].display_account === 1 ) ||
							( anonymous && bannerList[i].display_anon === 1 )
						)
					) {

						if ( bannerList[i].campaign in campaignWeights ) {
							campaignWeights[ bannerList[i].campaign ] += bannerList[i].weight;
						} else {
							campaignWeights[ bannerList[i].campaign ] = bannerList[i].weight;
							numFilteredCampaigns += 1;
						}

						groomedBannerList.push( bannerList[i] );
					}
				}

				// Make sure there are some banners to choose from
				if ( groomedBannerList.length === 0 ) {
					return false;
				}

				// Apply normalized weight to selected banners (campaigns have equal weight)
				count = 0.0;
				for ( i = 0; i < groomedBannerList.length; i++ ) {
					groomedBannerList[i].weight =
						( groomedBannerList[i].weight / campaignWeights[ groomedBannerList[i].campaign ] );
					groomedBannerList[i].weight *= ( 1 / numFilteredCampaigns );

					count += groomedBannerList[i].weight;
				}

				// Make sure we add to 1.0
				groomedBannerList[ groomedBannerList.length - 1 ].weight += ( 1.0 - count );

				// Obtain randomness
				rnd = Math.random();

				// Obtain banner index
				count = 0;
				idx = -1;
				for (i = 0; i < groomedBannerList.length; i++ ) {
					if ( rnd < count + groomedBannerList[i].weight ) {
						idx = i;
						break;
					} else {
						count += groomedBannerList[i].weight;
					}
				}

				// Load a random banner from our groomed list
				mw.centralNotice.loadBanner(
					groomedBannerList[idx].name,
					groomedBannerList[idx].campaign,
					( groomedBannerList[idx].fundraising ? 'fundraising' : 'default' )
				);
			},
			getQueryStringVariables: function () {
				document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function ( str, p1, p2 ) {
					mw.centralNotice.data.getVars[decode( p1 )] = decode( p2 );
				} );
			},
			initialize: function () {
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
