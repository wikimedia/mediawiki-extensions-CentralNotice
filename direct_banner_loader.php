<?php

// XXX this is a prototype which stands in for a Varnish extension.
// ln -s direct_banner_loader.php $IP/

// FIXME dubious:
header( "Content-type: text/javascript; charset=utf-8" );
header( "Cache-Control: public, s-maxage=0, max-age=0" );

		/* XXX
		$request = $this->getRequest();
		$this->language = $request->getText( 'userlang', 'en' );
		$this->siteName = $request->getText( 'sitename', 'Wikipedia' );
		$this->campaign = $request->getText( 'campaign', 'undefined' );
		*/

		//if ( $request->getText( 'banner' ) ) {
?>

var banner = {
	bannerName: 'ban',
	bannerHtml: '<b>BAN CONTENT</b>',
	bannerType: 'default',
	campaign: 'camp',
	fundraising: 'true',
	autolink: 'false',
	landingPages: [],
};
insertBanner(banner);
