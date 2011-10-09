<?php

/**
 * Generates Javascript file which controls banner selection on the client side
 */
class SpecialBannerController extends UnlistedSpecialPage {
	protected $sharedMaxAge = 300; // Cache for 5 minutes on the server side
	protected $maxAge = 300; // Cache for 5 minutes on the client side

	function __construct() {
		// Register special page
		parent::__construct( "BannerController" );
	}

	function execute( $par ) {
		global $wgOut;
		
		$wgOut->disable();
		$this->sendHeaders();
		
		$content = $this->getOutput();
		echo $content;
	}
	
	/**
	 * Generate the HTTP response headers
	 */
	function sendHeaders() {
		global $wgJsMimeType;
		header( "Content-type: $wgJsMimeType; charset=utf-8" );
		header( "Cache-Control: public, s-maxage=$this->sharedMaxAge, max-age=$this->maxAge" );
	}

	/**
	 * Generate the body for the Javascript file
	 *
	 * We use a jsonp scheme for actual delivery of the banner so that they can be served from meta.
	 * In order to circumvent the normal squid cache override we add '/cn.js' to the bannerlist URL.
	 */
	function getOutput() {
		global $wgCentralPagePath, $wgContLang;
		
		$js = $this->getAllBannerLists() . $this->getScriptFunctions() . $this->getToggleScripts();
		$js .= <<<JAVASCRIPT
( function( $ ) {
	$.ajaxSetup({ cache: true });
	$.centralNotice = {
		'data': {
			'getVars': {},
			'bannerType': 'default'
		},
		'fn': {
			'loadBanner': function( bannerName, campaign, bannerType ) {
				// Store the bannerType in case we need to set a banner hiding cookie later
				$.centralNotice.data.bannerType = bannerType;
				// Get the requested banner
				var bannerPageQuery = $.param( { 
					'banner': bannerName, 'campaign': campaign, 'userlang': wgUserLanguage, 
					'db': wgDBname, 'sitename': wgSiteName, 'country': Geo.country
				} );
				var bannerPage = '?title=Special:BannerLoader&' + bannerPageQuery;
JAVASCRIPT;
		$js .= "\n\t\t\t\tvar bannerScript = '<script type=\"text/javascript\" src=\"" . 
			Xml::escapeJsString( $wgCentralPagePath ) .
			"' + bannerPage + '\"></script>';\n";
		$js .= <<<JAVASCRIPT
				if ( document.cookie.indexOf( 'centralnotice_'+bannerType+'=hide' ) == -1 ) {
					$( '#siteNotice' ).prepend( '<div id="centralNotice" class="' + 
						( wgNoticeToggleState ? 'expanded' : 'collapsed' ) + 
						' cn-' + bannerType + '">'+bannerScript+'</div>' );
				}
			},
			'loadBannerList': function( geoOverride ) {
				if ( geoOverride ) {
					var geoLocation = geoOverride; // override the geo info
				} else {
					var geoLocation = Geo.country; // pull the geo info
				}
				var bannerList = $.parseJSON( wgBannerList[geoLocation] );
				$.centralNotice.fn.chooseBanner( bannerList );
			},
			'chooseBanner': function( bannerList ) {
				// Convert the json object to a true array
				bannerList = Array.prototype.slice.call( bannerList );
				// Make sure there are some banners to choose from
				if ( bannerList.length == 0 ) return false;
				
				var groomedBannerList = [];
				
				for( var i = 0; i < bannerList.length; i++ ) {
					// Only include this banner if it's intended for the current user
					if( ( wgUserName && bannerList[i].display_account ) || 
						( !wgUserName && bannerList[i].display_anon == 1 ) ) 
					{
						// add the banner to our list once per weight
						for( var j=0; j < bannerList[i].weight; j++ ) {
							groomedBannerList.push( bannerList[i] );
						}
					}
				}
				
				// Return if there's nothing left after the grooming
				if( groomedBannerList.length == 0 ) return false;
				
				// Choose a random key
				var pointer = Math.floor( Math.random() * groomedBannerList.length );
				
				// Load a random banner from our groomed list
				$.centralNotice.fn.loadBanner( 
					groomedBannerList[pointer].name,
					groomedBannerList[pointer].campaign,
					( groomedBannerList[pointer].fundraising ? 'fundraising' : 'default' )
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
	// Initialize the query string vars
	$.centralNotice.fn.getQueryStringVariables();
	if ( Geo.country ) {
		// We know the user's country so go ahead and load everything
		if( $.centralNotice.data.getVars['banner'] ) {
			// We're forcing one banner
			$.centralNotice.fn.loadBanner( $.centralNotice.data.getVars['banner'] );
		} else {
			// Look for banners ready to go NOW
			$.centralNotice.fn.loadBannerList( $.centralNotice.data.getVars['country'] );
		}
	} else {
		// We don't know the user's country yet, so we have to wait for the GeoIP lookup
		$( document ).ready( function () {
			if( $.centralNotice.data.getVars['banner'] ) {
				// We're forcing one banner
				$.centralNotice.fn.loadBanner( $.centralNotice.data.getVars['banner'] );
			} else {
				// Look for banners ready to go NOW
				$.centralNotice.fn.loadBannerList( $.centralNotice.data.getVars['country'] );
			}
		} ); //document ready
	}
} )( jQuery );
JAVASCRIPT;
		return $js;
			
	}
	
	function getToggleScripts() {
		$script = "var wgNoticeToggleState = (document.cookie.indexOf('hidesnmessage=1')==-1);\n\n";
		return $script;
	}

	function getScriptFunctions() {
		global $wgNoticeFundraisingUrl;
		$script = <<<JAVASCRIPT
function insertBanner( bannerJson ) {
	jQuery( 'div#centralNotice' ).prepend( bannerJson.bannerHtml );
	if ( bannerJson.autolink ) {
JAVASCRIPT;
	$script .= "\n\t\tvar url = '" . 
	Xml::escapeJsString( $wgNoticeFundraisingUrl ) . "';\n";
	$script .= <<<JAVASCRIPT
		if ( ( bannerJson.landingPages !== null ) && bannerJson.landingPages.length ) {
			targets = String( bannerJson.landingPages ).split(',');
			url += "?" + jQuery.param( {
				'landing_page': targets[Math.floor( Math.random() * targets.length )].replace( /^\s+|\s+$/, '' )
			} );
			url += "&" + jQuery.param( {
				'utm_medium': 'sitenotice', 'utm_campaign': bannerJson.campaign, 
				'utm_source': bannerJson.bannerName, 'language': wgUserLanguage, 
				'country': Geo.country
			} );
			jQuery( '#cn-landingpage-link' ).attr( 'href', url );
		}
	}
}
function hideBanner() {
	$( '#centralNotice' ).hide(); // Hide current banner
	var bannerType = $.centralNotice.data.bannerType;
	if ( bannerType === undefined ) bannerType = 'default';
	setBannerHidingCookie( bannerType ); // Hide future banners of the same type
}
function setBannerHidingCookie( bannerType ) {
	var e = new Date();
	e.setTime( e.getTime() + (7*24*60*60*1000) ); // one week
	var work='centralnotice_'+bannerType+'=hide; expires=' + e.toGMTString() + '; path=/';
	document.cookie = work;
}
// This function is deprecated
function toggleNotice() {
	var notice = document.getElementById('centralNotice');
	if (!wgNoticeToggleState) {
		notice.className = notice.className.replace('collapsed', 'expanded');
		toggleNoticeCookie('0'); // Expand banners
	} else {
		notice.className = notice.className.replace('expanded', 'collapsed');
		toggleNoticeCookie('1'); // Collapse banners
	}
	wgNoticeToggleState = !wgNoticeToggleState;
}
// This function is deprecated
function toggleNoticeCookie(state) {
	var e = new Date();
	e.setTime( e.getTime() + (7*24*60*60*1000) ); // one week
	var work='hidesnmessage='+state+'; expires=' + e.toGMTString() + '; path=/';
	document.cookie = work;
}

JAVASCRIPT;
		return $script;
	}
	
	/**
	 * Generate all the banner lists for all the countries
	 */
	function getAllBannerLists() {
		$script = "var wgBannerList = new Array();\r\n";
		$countriesList = CentralNoticeDB::getCountriesList();
		foreach ( $countriesList as $countryCode => $countryName ) {
			$script .= "wgBannerList['$countryCode'] = '".$this->getBannerList( $countryCode )."';\r\n";
		}
		return $script;
	}
	
	/**
	 * Generate JSON banner list for a given country
	 */
	function getBannerList( $country ) {
		global $wgNoticeProject, $wgNoticeLang;
		$banners = array();
		
		// See if we have any preferred campaigns for this language and project
		$campaigns = CentralNoticeDB::getCampaigns( $wgNoticeProject, $wgNoticeLang, null, 1, 1, $country );
		
		// Quick short circuit to show preferred campaigns
		if ( $campaigns ) {
			// Pull banners
			$banners = CentralNoticeDB::getCampaignBanners( $campaigns );
		}

		// Didn't find any preferred banners so do an old style lookup
		if ( !$banners )  {
			$banners = CentralNoticeDB::getBannersByTarget( $wgNoticeProject, $wgNoticeLang, $country );
		}
		
		return FormatJson::encode( $banners );
	}

}
