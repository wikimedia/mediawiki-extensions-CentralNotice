'use strict';
const Page = require( 'wdio-mediawiki/Page' );

class MainPage extends Page {
	get banner() { return $( '#centralnotice_testbanner' ); }

	open() {
		super.openTitle( 'Main_Page' );
	}
}

module.exports = new MainPage();
