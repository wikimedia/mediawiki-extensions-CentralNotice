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
					)
				)
			) .
			'";';
	}
	
	private function setLanguage( $par ) {
		$bits = explode( '/', $par );
		if( count( $bits == 2 ) ) {
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
		return <<<EOT
<style type="text/css">
.fundraiser-box {
	margin-top: 12px;
}
.fundraiser-box a {
	color: black;
	text-effect: none;
}
.fundraiser-text {
	not-width: 400px; /* 420 - 8*2 - 2*2 */
	height: 75px;
	padding: 0px 8px;
	background: #fdece5;
	border: solid 2px #f3e4dd;
	text-align: left;
}
.fundraiser-headline {
	font-size: 14px;
	margin-top: 0px;
	padding: 0px;
}
.fundraiser-quote {
	font-family: Monaco, monospace;
	font-size: 11px;
	background: white;
	
	not-width: 387px; /* 407 - 8*2 - 2*2 */
	height: 1.5em;
	padding: 2px 8px;
	border: solid 2px #efedee;
	
	overflow: hidden;
}
.fundraiser-meter {
	margin-top: 5px;
	margin-bottom: 0px;
}
</style>
<table class="fundraiser-box" align="center">
	<tr>
		<td class="fundraiser-text">
			<div class="fundraiser-headline">
				<a href="http://fundraising.wikimedia.org/">\$headline</a>
			</div>
			<div class='fundraiser-quote'>
				<a href="http://fundraising.wikimedia.org/">\$quote</a>
			</div>
			<div class='fundraiser-meter'>
				<a href="http://fundraising.wikimedia.org/">\$meter</a>
			</div>
		</td>
		<td width="109" height="75">
			<a href="http://fundraising.wikimedia.org/"><img src="http://upload.wikimedia.org/wikipedia/commons/a/ab/Movie.png" alt="Video" /></a>
		</td>
	</tr>
</table>
EOT;
	}
	
	private function getHeadlines() {
		return array(
			"What you didn't know about us . . . <small>(See more)</small>",
		);
		/*
		return $this->splitListMessage( 'centralnotice-headlines' );
		*/
	}
	
	private function getQuotes() {
		return array(
			"Anonymous: Well Done!",
			"Anonymous: What on earth did we do...",
		);
		/*
		return $this->splitListMessage( 'centralnotice-quotes' );
		*/
	}
	
	private function splitListMessage( $msg ) {
		$text = wfMsg( $msg );
		if( wfEmptyMsg( $msg, $text ) ) {
			return array();
		} else {
			return $this->splitList( $text );
		}
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
	
	function getMeter() {
		return "<img src=\"http://upload.wikimedia.org/fundraising/2007/meter.png\" width='407' height='14' />";
	}
}