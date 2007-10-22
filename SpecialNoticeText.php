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
		return
			'wgNotice="' .
			strtr(
				Xml::escapeJsString( $this->getHtmlNotice() ),
				array_merge(
					array_map(
						array( $this, 'interpolateScroller' ),
						array(
							'$quote' => $this->getQuotes(),
						)
					),
					array_map(
						array( $this, 'interpolateStrings' ),
						array(
							'$headline' => $this->getHeadlines(),
							'$meter' => $this->getMeter(),
							'$target' => $this->getTarget(),
						)
					)
				)
			) .
			'";';
	}
	
	private function setLanguage( $par ) {
		// Strip extra ? bits if they've gotten in. Sigh.
		$bits = explode( '?', $par, 2 );
		$par = $bits[0];
		
		// Special:NoticeText/project/language
		$bits = explode( '/', $par );
		if( count( $bits ) == 2 ) {
			$this->project = $bits[0];
			$this->language = $bits[1];
		}
	}
	
	private function interpolateStrings( $data ) {
		if( is_array( $data ) ) {
			return $this->interpolateRandomSelector( $data );
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
				Xml::escapeJsString( '<marquee scrolldelay="100" scrollamount="3">' ) .
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
				'var p=Math.floor(Math.random()*s.length);' .
				'return s.slice(p,s.length).concat(s.slice(0,p)).join(" ");' .
			'}()';
	}
	
	function getHtmlNotice() {
		return $this->getMessage( 'centralnotice-template' );
	}
	
	private function getHeadlines() {
		return $this->splitListMessage( 'centralnotice-headlines' );
	}
	
	private function getQuotes() {
		return $this->splitListMessage( 'centralnotice-quotes',
		 	array( $this, 'wrapQuote' ) );
	}
	
	private function getMeter() {
		return $this->getMessage( 'centralnotice-meter' );
		return "<img src=\"http://upload.wikimedia.org/fundraising/2007/meter.png\" width='407' height='14' />";
	}
	
	private function getTarget() {
		return $this->getMessage( 'centralnotice-target' );
	}
	
	private function splitListMessage( $msg, $callback=false ) {
		$text = $this->getMessage( $msg );
		return $this->splitList( $text, $callback );
	}
	
	private function getMessage( $msg, $params=array() ) {
		$searchPath = array(
			"$msg/{$this->language}/{$this->project}",
			"$msg/{$this->language}",
			"$msg/{$this->project}",
			"$msg" );
		foreach( $searchPath as $rawMsg ) {
			$text = wfMsgForContent( $rawMsg, $params ? $params[0] : '' );
			if( !wfEmptyMsg( $rawMsg, $text ) ) {
				return $text;
			}
		}
		return false;
	}
	
	private function splitList( $text, $callback=false ) {
		$list = array_filter(
			array_map(
				array( $this, 'filterListLine' ),
				explode( "\n", $text ) ) );
		if( is_callable( $callback ) ) {
			return array_map( $callback, $list );
		} else {
			return $list;
		}
	}
	
	private function filterListLine( $line ) {
		if( substr( $line, 0, 1 ) == '#' ) {
			return '';
		} else {
			return $this->parse( trim( ltrim( $line, '*' ) ) );
		}
	}
	
	private function parse( $text ) {
		global $wgOut;
		return preg_replace(
			'/^<p>(.*)\n?<\/p>\n?$/sU',
			'$1',
		 	$wgOut->parse( $text ) );
	}
	
	function wrapQuote( $text ) {
		return "<span class='fundquote'>" .
			$this->getMessage(
				'centralnotice-quotes-format',
				array( $text ) ) .
			"</span>";
	}
}