<?php

class SpecialNoticeText extends NoticePage {
	var $project = 'wikipedia';
	var $language = 'en';

	function __construct() {
		parent::__construct( "NoticeText" );
	}

	/**
	 * Clients can cache this as long as they like -- if it changes,
	 * we'll be bumping things at the loader level, bringing a new URL.
	 *
	 * Let's say a week.
	 */
	protected function maxAge() {
		return 86400 * 7;
	}

	function getJsOutput( $par ) {
		$this->setLanguage( $par );
		// need to return all site notices here
		$templates = CentralNotice::selectNoticeTemplates( $this->project, $this->language );
		$templateNames = array_keys( $templates );

		$templateTexts = array_map(
			array( $this, 'getHtmlNotice' ),
			$templateNames );
		$weights = array_values( $templates );
		return
			$this->getScriptFunctions() .
			$this->getToggleScripts() .
			'wgNotice=pickTemplate(' .
				Xml::encodeJsVar( $templateTexts ) .
				"," .
				Xml::encodeJsVar( $weights ) .
				");\n";
	}

	function getHtmlNotice( $noticeName ) {
		$this->noticeName = $noticeName;
		return preg_replace_callback(
			'/{{{(.*?)}}}/',
			array( $this, 'getNoticeField' ),
			$this->getNoticeTemplate() );
	}

	function getToggleScripts() {
		$showStyle = <<<END
<style type="text/css">.siteNoticeSmall{display:none;}</style>
END;
		$hideStyle = <<<END
<style type="text/css">.siteNoticeBig{display:none;}</style>
END;
		$hideToggleStyle = <<<END
<style type="text/css">.siteNoticeToggle{display:none;}</style>
END;
		$encShowStyle = Xml::encodeJsVar( $showStyle );
		$encHideStyle = Xml::encodeJsVar( $hideStyle );
		$encHideToggleStyle = Xml::encodeJsVar( $hideToggleStyle );

		$script = "
var wgNoticeToggleState = (document.cookie.indexOf('hidesnmessage=1')==-1);
document.writeln(
	wgNoticeToggleState
	? $encShowStyle
	: $encHideStyle);
document.writeln($encHideToggleStyle);\n\n";
		return $script;
	}

	function getScriptFunctions() {
		$script = "
function toggleNotice() {
	var big = getElementsByClassName(document,'div','siteNoticeBig');
	var small = getElementsByClassName(document,'div','siteNoticeSmall');
	if (!wgNoticeToggleState) {
		toggleNoticeStyle(big,'block');
		toggleNoticeStyle(small,'none');
		toggleNoticeCookie('0');
	} else {
		toggleNoticeStyle(big,'none');
		toggleNoticeStyle(small,'block');
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
}
function pickTemplate(templates, weights) {
	var weightedTemplates = new Array();
	var currentTemplate = 0;
	var totalWeight = 0;

	if (templates.length == 0)
		return '';

	while (currentTemplate < templates.length) {
		totalWeight += weights[currentTemplate];
		for (i=0; i<weights[currentTemplate]; i++) {
			weightedTemplates[weightedTemplates.length] = templates[currentTemplate];
		}
		currentTemplate++;
	}
	
	if (totalWeight == 0)
		return '';

	var randomnumber=Math.floor(Math.random()*totalWeight);
	return weightedTemplates[randomnumber];
}\n\n";
		return $script;
	}

	private function formatNum( $num ) {
		$lang = Language::factory( $this->language );
		return $lang->formatNum( $num );
	}

	private function setLanguage( $par ) {
		// Strip extra ? bits if they've gotten in. Sigh.
		$bits = explode( '?', $par, 2 );
		$par = $bits[0];

		// Special:NoticeText/project/language
		$bits = explode( '/', $par );
		if ( count( $bits ) >= 2 ) {
			$this->project = $bits[0];
			$this->language = $bits[1];
		}
	}
	/*
	private function interpolateStrings( $data ) {
		if( is_array( $data ) ) {
			if( count( $data ) == 1 ) {
				return Xml::escapeJsString( $data[0] );
			} else {
				return $this->interpolateRandomSelector( $data );
			}
		} else {
			return Xml::escapeJsString( $data );
		}
	}

	private function interpolateRandomSelector( $strings ) {
		return '"+' . $this->randomSelector( $strings ) . '+"';
	}

	private function randomSelector( $strings ) {
		return
			'function(){' .
				'var s=' . Xml::encodeJsVar( $strings ) . ';' .
				'return s[Math.floor(Math.random()*s.length)];' .
			'}()';
	}

	private function interpolateScroller( $strings ) {
		global $wgNoticeScroll;
		if( $wgNoticeScroll ) {
			return
				Xml::escapeJsString( '<marquee scrolldelay="20" scrollamount="2" width="384">' ) .
				'"+' .
				$this->shuffleStrings( $strings ) .
				'+"' .
				Xml::escapeJsString( '</marquee>' );
		} else {
			return $this->interpolateStrings( $strings );
		}
	}

	private function shuffleStrings( $strings ) {
		return
			'function(){' .
				'var s=' . Xml::encodeJsVar( $strings ) . ';' .
				# Get a random array of orderings... (array_index,random)
				'var o=[];' .
				'for(var i=0;i<s.length;i++){' .
					'o.push([i,Math.random()]);' .
				'}' .
				'o.sort(function(x,y){return y[1]-x[1];});' .
				# Reorder the array...
				'var r=[];' .
				'for(var i=0;i<o.length;i++){' .
					'r.push(s[o[i][0]]);' .
				'}' .
				# And return a joined string
				'return r.join(" ");' .
			'}()';
	}
	*/

	function chooseTemplate ( $notice ) {
		 $dbr = wfGetDB( DB_SLAVE );
		 /*
		  * This select statement is really wrong, and needs to be fixed.
		  * What's wrong is the use of just id instead of not_id, tmp_id or asn_id
		  */
		 $res = $dbr->select( 'cn_assignments',
			array( 'not_name', 'not_weight' ),
			array( 'not_name' => $notice, 'not_id = id' ),
			__METHOD__,
			array( 'ORDER BY' => 'id' )
		);
		$templates = array();
	  	while ( $row = $dbr->fetchObject( $res ) ) {
			 push ( $templates, $row->name );
		}

	}
	function getNoticeTemplate() {
		return $this->getMessage( "centralnotice-template-{$this->noticeName}" );
	}

	function getNoticeField( $matches ) {
		$field = $matches[1];
		$params = array();
		if ( $field == 'amount' ) {
			$params = array( $this->formatNum( $this->getDonationAmount() ) );
		}
		$message = "centralnotice-{$this->noticeName}-$field";
		$source = $this->getMessage( $message, $params );
		return $source;
	}

	/*
	private function getHeadlines() {
		return $this->splitListMessage( 'centralnotice-headlines' );
	}

	private function getQuotes() {
		return $this->splitListMessage( 'centralnotice-quotes',
		 	array( $this, 'wrapQuote' ) );
	}

	private function getMeter() {
		return $this->getMessage( 'centralnotice-meter' );
		#return "<img src=\"http://upload.wikimedia.org/fundraising/2007/meter.png\" width='407' height='14' />";
	}

	private function getTarget() {
		return $this->getMessage( 'centralnotice-target' );
	}

	private function splitListMessage( $msg, $callback=false ) {
		$text = $this->getMessage( $msg );
		return $this->splitList( $text, $callback );
	}
	*/

	private function getMessage( $msg, $params = array() ) {
		// A god-damned dirty hack! :D
		global $wgSitename;
		$old = array();
		$old['wgSitename'] = $wgSitename;
		$wgSitename = $this->projectName();

		$options = array(
			'language' => $this->language,
			'parsemag',
		);
		array_unshift( $params, $options );
		array_unshift( $params, $msg );
		$out = call_user_func_array( 'wfMsgExt', $params );

		// Restore globals
		$wgSitename = $old['wgSitename'];

		return $out;
	}

	private function projectName() {
		global $wgConf, $IP;

		// This is a damn dirty hack
		if ( file_exists( "$IP/InitialiseSettings.php" ) ) {
			require_once "$IP/InitialiseSettings.php";
		}

		// Special cases for commons and meta who have no lang
		if ( $this->project == 'commons' )
			return "Commons";
		else if ( $this->project == 'meta' )
			return "Wikimedia";

		// Guess dbname since we don't have it atm
		$dbname = $this->language .
			( ( $this->project == 'wikipedia' ) ? "wiki" : $this->project );
		$name = $wgConf->get( 'wgSitename', $dbname, $this->project,
			array( 'lang' => $this->language, 'site' => $this->project ) );

		if ( $name ) {
			return $name;
		} else {
			global $wgLang;
			return $wgLang->ucfirst( $this->project );
		}
	}

	/*
	function wrapQuote( $text ) {
		return "<span class='fundquote'>" .
			$this->getMessage(
				'centralnotice-quotes-format',
				array( $text ) ) .
			"</span>";
	}
	*/

	private function getDonorCount() {
		global $wgNoticeCounterSource, $wgMemc;
		$count = intval( $wgMemc->get( 'centralnotice:counter' ) );
		if ( !$count ) {
			$count = intval( @file_get_contents( $wgNoticeCounterSource ) );
			if ( !$count ) {
				// nooooo
				return $this->getFallbackDonorCount();
			}

			$wgMemc->set( 'centralnotice:counter', $count, 60 );
			$wgMemc->set( 'centralnotice:counter:fallback', $count ); // no expiry
		}

		return $count;
	}

	private function getDonationAmount() {
		return 2543454;
	}

	private function getFallbackDonorCount() {
		global $wgMemc;
		$count = intval( $wgMemc->get( 'centralnotice:counter:fallback' ) );
		if ( !$count ) {
			return 16672; // number last i saw... dirty hack ;)
		}
		return $count;
	}

	/*
	private function getBlog() {
		$url = $this->getMessage( 'centralnotice-blog-url' );
		$entry = $this->getCachedRssEntry( $url );
		if( $entry ) {
			list( $link, $title ) = $entry;
			return $this->parse(
				$this->getMessage( 'centralnotice-blog',
					array( $link, wfEscapeWikiText( $title ) ) ) );
		} else {
			return '';
		}
	}

	private function getCachedRssEntry( $url ) {
		global $wgMemc;
		$key = 'centralnotice:rss:' . md5( $url );
		$cached = $wgMemc->get( $key );
		if( !is_string( $cached ) ) {
			$title = $this->getFirstRssEntry( $url );
			if( $title ) {
				$wgMemc->set( $key, $title, 600 ); // 10-minute
			} else {
				$wgMemc->set( $key, array(), 30 ); // negative cache for a little bit...
			}
		}
		return $title;
	}
	*/

	/**
	 * Fetch the first link and title from an RSS feed
	 * @return array
	 */
	/*
	private function getFirstRssEntry( $url ) {
		wfSuppressWarnings();
		$feed = simplexml_load_file( $url );
		$title = $feed->channel[0]->item[0]->title;
		$link = $feed->channel[0]->item[0]->link;
		wfRestoreWarnings();

		if( is_object( $title ) && is_object( $link ) ) {
			return array( (string)$link, (string)$title );
		} else {
			return array();
		}
	}
	*/

}
