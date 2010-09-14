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
				// get the requested banner
				var bannerPage = 'Special:BannerLoader?banner='+bannerName+'&userlang='+wgContentLanguage+'&sitename='+wgNoticeProject;
				var bannerURL = wgArticlePath.replace( '$1', bannerPage );
				var request = $.ajax( {
					url: bannerURL,
					dataType: 'html',
					success: function( data ) {
						$.centralNotice.fn.displayBanner( data );
					}
				});
			},
			'loadBannerList': function( timestamp ) {
				var bannerListURL;
				if ( timestamp ) {
					bannerListURL = "TBD"
				} else {
					// http://geoiplookup.wikimedia.org/
					var geoLocation = 'US'; // Hard-coding for now
					var bannerListPage = 'Special:BannerListLoader?language='+wgContentLanguage+'&project='+wgNoticeProject+'&location='+geoLocation;
					bannerListURL = wgArticlePath.replace( '$1', bannerListPage );
				}
				var request = $.ajax( {
					url: bannerListURL,
					dataType: 'json',
					success: $.centralNotice.fn.chooseBanner
				} );
			},
			'chooseBanner': function( bannerList ) {
				// convert the json object to a true array
				bannerList = Array.prototype.slice.call( bannerList );
				
				// Make sure there are some banners to choose from
				if ( bannerList.length == 0 ) return false;
				
				var totalWeight = 0;
				for( var i = 0; i < bannerList.length; i++ ) {
					totalWeight += bannerList[i].weight;
				}
				var pointer = Math.floor( Math.random() * totalWeight ),
					selectedBanner = bannerList[0],
					w = 0;
				for( var i = 0; i < bannerList.length; i++ ) {
					w += bannerList[i].weight;
					if( w < pointer ) {
						selectedBanner = bannerList[i];
						break;
					}
				}
				$.centralNotice.fn.loadBanner( 
					selectedBanner.name
				 );
			},
			'displayBanner': function( bannerHTML ) {
				// inject the banner html into the page
				$( '#siteNotice' )
					.prepend( '<div id="centralnotice" class="' + ( wgNoticeToggleState ? 'expanded' : 'collapsed' ) + '">' + bannerHTML + '</div>' );
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
