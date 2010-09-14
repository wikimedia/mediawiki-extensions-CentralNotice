<?php

/**
 * Generates Javascript file which controls banner selection on the client side
 */
class SpecialBannerController extends UnlistedSpecialPage {
	public $centralNoticeDB;
	protected $sharedMaxAge = 150; // Cache for ? minutes on the server side
	protected $maxAge = 150; // Cache for ? minutes on the client side
	protected $contentType = 'text/javascript';

	function __construct() {
		// Register special page
		parent::__construct( "BannerController" );
		$this->centralNoticeDB = new CentralNoticeDB();
	}

	function execute( $par ) {
		global $wgOut, $wgRequest;
		global $wgNoticeLang, $wgNoticeProject;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		$content = $this->getOutput();
		if ( strlen( $content ) == 0 ) {
			// Hack for IE/Mac 0-length keepalive problem, see RawPage.php
			echo "/* Empty */";
		} else {
			echo $content;
		}
		
	}
	
	/**
	 * Generate the HTTP response headers for the banner controller
	 */
	function sendHeaders() {
		header( "Content-type: $this->contentType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}

	/**
	 * Generate the body for a static Javascript file
	 */
	function getOutput() {
		$js = $this->getScriptFunctions() . $this->getToggleScripts();
		$js .= <<<EOT
( function( $ ) {
	$.centralNotice = {
		'data': {
			'getVars': {}
		},
		'fn': {
			'loadBanner': function( bannerName ) {
				// Get the requested banner
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
				var geoLocation = Geo.country; // pull the geo info
				var bannerListPage = 'Special:BannerListLoader?language='+wgContentLanguage+'&project='+wgNoticeProject+'&location='+geoLocation;
				bannerListURL = wgArticlePath.replace( '$1', bannerListPage );
				var request = $.ajax( {
					url: bannerListURL,
					dataType: 'json',
					success: $.centralNotice.fn.chooseBanner
				} );
			},
			'chooseBanner': function( bannerList ) {
				// Convert the json object to a true array
				bannerList = Array.prototype.slice.call( bannerList );
				
				// Make sure there are some banners to choose from
				if ( bannerList.length == 0 ) return false;
				
				var groomedBannerList = [];
				
				for( var i = 0; i < bannerList.length; i++ ) {
					// Only include this banner if it's inteded for the current user
					if( ( wgUserName && bannerList[i].display_account ) || ( !wgUserName && bannerList[i].display_anon == 1 ) ) {
						// add the banner to our list once per weight
						for( var j=0; j < bannerList[i].weight; j++ ) {
							groomedBannerList.push( bannerList[i] );
						}
					}
				}
				
				// Return if there's nothing left after the grooming
				if( groomedBannerList.length == 0 ) return false;
				
				// Load a random banner from our groomed list
				$.centralNotice.fn.loadBanner( 
					groomedBannerList[ Math.floor( Math.random() * groomedBannerList.length ) ].name
				);
			},
			'displayBanner': function( bannerHTML ) {
				// Inject the banner html into the page
				$( '#siteNotice' )
					.prepend( '<div id="centralNotice" class="' + ( wgNoticeToggleState ? 'expanded' : 'collapsed' ) + '">' + bannerHTML + '</div>' );
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
		// Initialize the query string vars
		$.centralNotice.fn.getQueryStringVariables();
		// Look for banners ready to go NOW
		$.centralNotice.fn.loadBannerList( );
	} ); //document ready
} )( jQuery );
EOT;
		return $js;
			
	}
	
	function getToggleScripts() {
		$showStyle = <<<END
<style type="text/css">
#centralNotice .siteNoticeSmall {display:none;}
#centralNotice.collapsed .siteNoticeBig {display:none;}
#centralNotice.collapsed .siteNoticeSmall {display:block;}
</style>
END;
		$encShowStyle = Xml::encodeJsVar( $showStyle );

		$script = "
var wgNoticeToggleState = (document.cookie.indexOf('hidesnmessage=1')==-1);
document.writeln($encShowStyle);\n\n";
		return $script;
	}

	function getScriptFunctions() {
		$script = "
function toggleNotice() {
	var notice = document.getElementById('centralNotice');
	if (!wgNoticeToggleState) {
		notice.className = notice.className.replace('collapsed', 'expanded');
		toggleNoticeCookie('0');
	} else {
		notice.className = notice.className.replace('expanded', 'collapsed');
		toggleNoticeCookie('1');
	}
	wgNoticeToggleState = !wgNoticeToggleState;
}
function toggleNoticeStyle(elems, display) {
	if(elems)
		for(var i=0;i<elems.length;i++)
			elems[i].style.display = display;
}
function toggleNoticeCookie(state) {
	var e = new Date();
	e.setTime( e.getTime() + (7*24*60*60*1000) ); // one week
	var work='hidesnmessage='+state+'; expires=' + e.toGMTString() + '; path=/';
	document.cookie = work;
}\n\n";
		return $script;
	}
	
}
