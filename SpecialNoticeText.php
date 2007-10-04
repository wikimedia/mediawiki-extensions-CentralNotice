<?php

class SpecialNoticeText extends NoticePage {
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
	
	function getJsOutput() {
		return 'wgNotice="' .
			str_replace( '$quote',
				'"+' . $this->getJsQuoteSelector() . '+"',
					Xml::escapeJsString( $this->getHtmlNotice() ) )
			. '";';
	}
	
	function getJsQuoteSelector() {
		$quotes = $this->getQuotes();
		return
			'function(){' .
				'var quotes=' . Xml::encodeJsVar( $quotes ) . ';' .
				'return quotes[Math.floor(Math.random()*quotes.length)];' .
			'}()';
	}
	
	function getHtmlNotice() {
		$headline = $this->getHeadline();
		$meter = $this->getMeter();
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
				<a href="http://fundraising.wikimedia.org/">$headline</a>
			</div>
			<div class='fundraiser-quote'>
				<a href="http://fundraising.wikimedia.org/">\$quote</a>
			</div>
			<div class='fundraiser-meter'>
				<a href="http://fundraising.wikimedia.org/">$meter</a>
			</div>
		</td>
		<td width="109" height="75">
			<a href="http://fundraising.wikimedia.org/"><img src="http://upload.wikimedia.org/wikipedia/commons/a/ab/Movie.png" alt="Video" /></a>
		</td>
	</tr>
</table>
EOT;
	}
	
	function getHeadline() {
		return "What you didn't know about Wikipedia . . . <small>(See more)</small>";
	}
	
	function getQuotes() {
		return array(
			"Anonymous: Well Done!",
			"Anonymous: What on earth did we do...",
		);
	}
	
	function getMeter() {
		return "<img src=\"http://upload.wikimedia.org/fundraising/2007/meter.png\" width='407' height='14' />";
	}
}