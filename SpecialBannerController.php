<?php

/**
 * Generates Javascript file which controls banner selection on the client side
 */
class SpecialBannerController extends UnlistedSpecialPage {
	protected $sharedMaxAge = 7200; // Cache for 2 hours on the server side
	protected $maxAge = 7200; // Cache for 2 hours on the client side

	function __construct() {
		// Register special page
		parent::__construct( "BannerController" );
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
		global $wgJsMimeType;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}

	/**
	 * Generate the body for a static Javascript file
	 */
	function getOutput() {
		global $wgCentralPagePath;
		
		$js = $this->getScriptFunctions() . $this->getToggleScripts();
		$js .= <<<EOT
( function( $ ) {
	$.ajaxSetup({ cache: true });
	$.centralNotice = {
		'data': {
			'getVars': {}
		},
		'fn': {
			'loadBanner': function( bannerName ) {
				// Get the requested banner
				var bannerPage = 'Special:BannerLoader?banner='+bannerName+'&userlang='+wgUserLanguage+'&contentlang='+wgContentLanguage+'&sitename='+wgSiteName+'&country='+Geo.country;
EOT;
		$js .= "\n\t\t\t\tvar bannerScript = '<script type=\"text/javascript\" src=\"".Xml::escapeJsString( $wgCentralPagePath )."' + bannerPage + '\"></script>';\n";
		$js .= <<<EOT
				$( '#siteNotice' ).prepend( '<div id="centralNotice" class="' + ( wgNoticeToggleState ? 'expanded' : 'collapsed' ) + '">'+bannerScript+'</div>' );
			},
			'loadBannerList': function( geoOverride ) {
				var bannerListURL;
				if ( geoOverride ) {
					var geoLocation = geoOverride; // override the geo info
				} else {
					var geoLocation = Geo.country; // pull the geo info
				}
				var bannerListPage = 'Special:BannerListLoader?language='+wgContentLanguage+'&project='+wgNoticeProject+'&country='+geoLocation;
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
				
				var totalWeight = 0;
				// run through the bannerlist and sum the weights of all banners
				for( var i = 0; i < bannerList.length; i++ ) {
					totalWeight += bannerList[i].weight;
				}
				
				// Select a random integer between 0 and our total weight
				var pointer = Math.floor( Math.random() * totalWeight ),
					selectedBanner = bannerList[0],
					w = 0;
				// Run through the banner list and start accumulating weights
				for( var i = 0; i < bannerList.length; i++ ) {
					w += bannerList[i].weight;
					// when the weight tally exceeds the random integer, return the banner and stop the loop
					if( w > pointer ) {
						selectedBanner = bannerList[i];
						break;
					}
				}
				// Return our selected banner
				$.centralNotice.fn.loadBanner( 
					selectedBanner.name
				);
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
		if( $.centralNotice.data.getVars['banner'] ) {
			// if we're forcing one banner
			$.centralNotice.fn.loadBanner( $.centralNotice.data.getVars['banner'] );
		} else {
			// Look for banners ready to go NOW
			$.centralNotice.fn.loadBannerList( $.centralNotice.data.getVars['country'] );
		}
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
function insertBanner(bannerJson) {
	jQuery('div#centralNotice').prepend( bannerJson.banner );
}
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
