/*
 * New Central Notice Javascript
 *
 * Mostly stubbed functionallity for central notice improvements
 * May or may not be used, definitely will be changed.
 * More of a sketch of what we think needs to be done.
 * 
 * QUESTIONS: 
 * 1. How do I determin if a user is logged in or not?
 *    A: See function pickTemplate() in SpecialNoticeText.php
 * 2. How do I determin a users location?
 *    A: The country value given by http://geoiplookup.wikimedia.org/
 */
( function( $ ) {
	$.centralNotice = {
		'data': {
			'getVars': {}
		},
		'fn': {
			'loadBanner': function( bannerName ) {
				// get the requested banner from /centralnotice/banners/<bannername>/<wgUserLanguage>.js
				var bannerPage = 'Special:BannerLoader?banner='+bannerName+'&userlang='+wgContentLanguage+'&sitename='+wgNoticeProject;
				var bannerURL = wgArticlePath.replace( '$1', bannerPage );
				var request = $.ajax( {
					url: wgArticlePath
					url: bannerURL,
					dataType: 'html',
					success: function( data ) {
						$.centralNotice.fn.displayBanner( data );
					}
				});
			},
			'loadBannerList': function( timestamp ) {
				var listURL;
				if ( timestamp ) {
					listURL = "TBD"
				} else {
					listURL = "/centralnotice/"+wgNoticeProject+"/"+wgContentLanguage+".js"
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
				$( '#siteNotice' ).prepend('<div id="centralnotice" class="'+(wgNoticeToggleState ? 'expanded' : 'collapsed')+'">'+bannerHTML+'</div>');
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
			$.centralNotice.fn.loadBannerList( $.centralNotice.data.getVars['forceTimestamp'] );	
		} else {
			// look for banners ready to go NOW
			$.centralNotice.fn.loadBannerList( );	
		}
	} ); //document ready
} )( jQuery );
