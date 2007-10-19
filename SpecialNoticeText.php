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
				array_map(
					array( $this, 'interpolateStrings' ),
					array(
						'$quote' => $this->getQuotes(),
						'$headline' => $this->getHeadlines(),
						'$meter' => $this->getMeter(),
						'$target' => $this->getTarget(),
					)
				)
			) .
			'";';
	}
	
	private function setLanguage( $par ) {
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
	
	function getHtmlNotice() {
		return $this->getMessage( 'centralnotice-template' );
	}
	
	private function getHeadlines() {
		return $this->splitListMessage( 'centralnotice-headlines' );
	}
	
	private function getQuotes() {
		return $this->splitListMessage( 'centralnotice-quotes' );
	}
	
	private function getMeter() {
		return $this->getMessage( 'centralnotice-meter' );
		return "<img src=\"http://upload.wikimedia.org/fundraising/2007/meter.png\" width='407' height='14' />";
	}
	
	private function getTarget() {
		return $this->getMessage( 'centralnotice-target' );
	}
	
	private function splitListMessage( $msg ) {
		$text = $this->getMessage( $msg );
		return $this->splitList( $text );
	}
	
	private function getMessage( $msg ) {
		$searchPath = array(
			"$msg/{$this->language}/{$this->project}",
			"$msg/{$this->language}",
			"$msg/{$this->project}",
			"$msg" );
		foreach( $searchPath as $rawMsg ) {
			$text = wfMsgForContent( $rawMsg );
			if( !wfEmptyMsg( $rawMsg, $text ) ) {
				return $text;
			}
		}
		return false;
	}
	
	private function splitList( $text ) {
		return array_filter(
			array_map(
				array( $this, 'filterListLine' ),
				explode( "\n", $text ) ) );
	}
	
	private function filterListLine( $line ) {
		if( substr( $line, 0, 1 ) == '#' ) {
			return '';
		} else {
			return trim( ltrim( $line, '*' ) );
		}
	}
}