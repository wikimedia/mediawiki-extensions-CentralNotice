<?php

/**
 * Generated parameters:
 *  lang=en
 *  dir=ltr
 *  count=10,549 # localized
 *  percent=10.549 # percentage
 *
 * Template defaults could be, eg:
 *  progress=<tspan class="count">{{{count}}}</tspan> have donated
 *  headline=<tspan class="you">You</tspan> can help Wikipedia change the world!
 *  button=» Donate now!
 *  font=Gill Sans
 *  boldfont=Gill Sans Ultra Bold
 *  
 * Per-language overrides for each...
 */

class NoticeRender {
	// svg-o-matic!
	
	function __construct() {
		// fixme :D
		$this->languageCode = 'en';
		$this->languageObj = Language::factory( $this->languageCode );
	}
	
	function expandTemplate( $template ) {
		$count = 10550;
		$max = 100000;
		$params = array(
			// Locale information
			'lang' => $this->languageCode,
			'dir' => $this->languageObj->isRTL() ? 'rtl' : 'ltr',
			
			// Progress information...
			'rawcount' => $count,
			'count' => $this->languageObj->formatNum( $count ),
			'percent' => strval( $count / $max * 100.0 ),
			
			// And the localized text...
			// @fixme
		);
		
		global $wgParser;
		$wgParser->firstCallInit();
		$parser = clone( $wgParser );
		// a damn dirty hack
		$parser->clearState();
		//$parser->setOutputType( OT_PREPROCESS );
		$parser->setOutputType( OT_HTML );
		$parser->mOptions = new ParserOptions();
		$parser->mTitle = Title::makeTitle( NS_MEDIAWIKI, 'Centralnotice-svg-template' );
		// and go!
		return $parser->replaceVariables( $template, $params );
	}
}

?>