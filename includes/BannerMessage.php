<?php

//TODO how to make "lang" arguments go away?
class BannerMessage {
	function __construct( $banner_name, $name ) {
		$this->banner_name = $banner_name;
		$this->name = $name;
	}

	function getTitle( $lang = null ) {
		return Title::newFromText( $this->getDbKey( $lang ), NS_MEDIAWIKI );
	}

	function getDbKey( $lang = null ) {
		return ( $lang === null || /*FIXME*/ $lang == 'en' )
			? "Centralnotice-{$this->banner_name}-{$this->name}"
			: "Centralnotice-{$this->banner_name}-{$this->name}/{$lang}";
	}

	function exists( $lang = null ) {
		return $this->getTitle( $lang )->exists();
	}
}
