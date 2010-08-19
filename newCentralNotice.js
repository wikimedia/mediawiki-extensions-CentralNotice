/*
 * New Central Notice Javascript
 *
 * Mostly stubbed functionallity for central notice improvements
 * May or may not be used, definitely will be changed.
 * More of a sketch of what we think needs to be done.
 * 
 * QUESTIONS: 
 * 1. How do I determin if a user is logged in or not?
 * 2. How do I determin a users location?
 * 
 */
( function( $ ) {
	$.centralNotice = {
		'data': {
			'getVars': {}
		},
		'fn': {
			'loadBanner': function( bannerName ) {
				// get the requested banner from /centralnotice/banners/<bannername>/<wgUserLanguage>.js
				var request = $.ajax( {
					url: 'response.html',
					dataType: 'html',
					success: function( data ) {
						$.centralNotice.fn.displayBanner( data );
					}
				});
			},
			'loadCampaign': function( timestamp ) {
				var listURL;
				if ( timestamp ) {
					listURL = "TBD"
				} else {
					listURL = "/centralnotice/<project type>/<wgContentLanguage>.js"
				}
				var request = $.ajax( {
					url: listURL,
					dataType: 'json',
					success: function( data ) {
						$.centralNotice.fn.chooseBanner( data );
					}
				} );
			},
			'chooseBanner': function( bannerList ) {
				// pick a banner based on logged-in status and geotargetting
				var bannerHTML = bannerList[0].html;
				$.centralNotice.fn.displayBanner( bannerHTML );
			},
			'displayBanner': function( bannerHTML ) {
				// inject the banner html into the page
				$( '#centralNotice' ).replaceWith( bannerHTML );
			},
			'getQueryStringVariables': function() {
				document.location.search.replace( /\??(?:([^=]+)=([^&]*)&?)/g, function () {
					function decode( s ) {
						return decodeURIComponent( s.split( "+" ).join( " " ) );
					}
					$.centralNotice.data.getVars[decode( arguments[1] )] = decode( arguments[2] );
				} );
			}
		}
	}
	$( document ).ready( function () {
		// initialize the query string vars
		$.centralNotice.fn.getQueryStringVariables();
		if( $.centralNotice.data.getVars['forceBanner'] ) {
			// if we're forcing one banner
			$.centralNotice.fn.loadBanner( $.centralNotice.data.getVars['forceBanner'] );
		} else if ( $.centralNotice.data.getVars['forceTimestamp'] ) {
			// if we're forcing a future campaign time
			$.centralNotice.fn.loadCampaign( $.centralNotice.data.getVars['forceTimestamp'] );	
		} else {
			// look for banners ready to go NOW
			$.centralNotice.fn.loadCampaign( );	
		}
	} ); //document ready
} )( jQuery );
