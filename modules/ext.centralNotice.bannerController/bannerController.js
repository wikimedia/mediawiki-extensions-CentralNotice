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

	$.ajaxSetup({
		cache: true
	});
	mw.centralNotice = {
        /** -- Central Notice Required Data -- **/
		data: {
			getVars: {},
			bannerType: 'default',
			bucket: null,
			testing: false
		},

        /** -- Banner custom data; filled by the banner itself -- */
        bannerData: {},

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
			var bannerPageQuery, bannerScript, scriptUrl;

			mw.centralNotice.data.testing = true;

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
			scriptUrl = mw.config.get( 'wgCentralPagePath' ) + '?' + $.param( bannerPageQuery );
			bannerScript = '<script src="' + mw.html.escape( scriptUrl ) + '"></script>';
			$( '#centralNotice' ).prepend( bannerScript );
		},
		loadRandomBanner: function () {
			var RAND_MAX = 30;

			var bannerDispatchQuery = {
				userlang: mw.config.get( 'wgUserLanguage' ),
				sitename: mw.config.get( 'wgSiteName' ),
				project: mw.config.get( 'wgNoticeProject' ),
				anonymous: mw.config.get( 'wgUserName' ) === null,
				bucket: mw.centralNotice.data.bucket,
				country: mw.centralNotice.data.country,
				slot: Math.floor( Math.random() * RAND_MAX ) + 1
			};
			var scriptUrl = mw.config.get( 'wgCentralBannerDispatcher' )
				+ '?' + $.param( bannerDispatchQuery );
			var bannerScript = '<script src="' + mw.html.escape( scriptUrl ) + '"></script>';
			$( '#centralNotice' ).prepend( bannerScript );
		},
		// Record banner impression using old-style URL
		recordImpression: function( data ) {
			var url = mw.config.get( 'wgCentralBannerRecorder' ) + '?' + $.param( data );
			(new Image()).src = url;
		},
		getQueryStringVariables: function () {
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
		initialize: function () {
			// Prevent loading banners on Special pages
			if ( mw.config.get( 'wgNamespaceNumber' ) == -1 ) {
				return;
			}

            var dataString = $.cookie( 'centralnotice_bucket' ) || '';
            mw.centralNotice.data.bucket = dataString.split('-')[0];
            var bucketValidityString = dataString.split('-')[1];
            var expectedValidityString = mw.config.get( 'wgNoticeNumberOfBuckets' ) + '.' + mw.config.get( 'wgNoticeNumberOfControllerBuckets' );
            
			if ( ( mw.centralNotice.data.bucket === null ) ||
                 ( bucketValidityString !== expectedValidityString )
            ) {
				mw.centralNotice.data.bucket = Math.floor( Math.random() * mw.config.get( 'wgNoticeNumberOfControllerBuckets' ) );
				$.cookie(
					'centralnotice_bucket', mw.centralNotice.data.bucket + '-' + expectedValidityString,
					{ expires: mw.config.get( 'wgNoticeBucketExpiry' ), path: '/' }
				);
			}

			// Initialize the query string vars
			mw.centralNotice.getQueryStringVariables();

			$( '#siteNotice' ).prepend(
				'<div id="centralNotice"></div>'
			);

			mw.centralNotice.data.country = mw.centralNotice.data.getVars.country || Geo.country || 'XX';

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
            userlang: mw.config.get( 'wgUserLanguage' ),
            project: mw.config.get( 'wgNoticeProject' ),
            db: mw.config.get( 'wgDBname' ),
            sitename: mw.config.get( 'wgSiteName' ),        // legacy parameter for faulkner's scripts
            bucket: mw.centralNotice.data.bucket
        };

        // This gets prepended to the impressionData at the end
        var impressionResultData = null;

		if ( !bannerJson ) {
            // There was no banner returned from the server
            impressionResultData = {
                result: 'hide',
                reason: 'empty'
            }
		} else {
            // Ok, we have a banner! Get the banner type for more queryness
            mw.centralNotice.data.bannerType = ( bannerJson.fundraising ? 'fundraising' : 'default' );

            // Has the banner been hidden by cookie?
			if ( $.cookie( 'stopMobileRedirect' ) === 'true' ) {
			    impressionResultData = {
					result: 'hide',
					reason: 'mobile'
			    }
			} else if ( $.cookie( 'centralnotice_' + encodeURIComponent( mw.centralNotice.data.bannerType ) ) === 'hide' ) {
                // Yes
                impressionResultData = {
                    result: 'hide',
                    reason: 'cookie'
                }
            } else {
                // No, inject the banner
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
		if ( !mw.centralNotice.data.testing ) {
			mw.centralNotice.recordImpression($.extend( impressionResultData, impressionData ) );
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

} )( jQuery, mediaWiki );
