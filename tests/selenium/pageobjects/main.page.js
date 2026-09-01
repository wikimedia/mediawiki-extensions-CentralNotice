import Page from 'wdio-mediawiki/Page.js';

class MainPage extends Page {
	get banner() {
		return $( '#centralnotice_testbanner' );
	}

	async open() {
		return super.openTitle( 'Main_Page' );
	}
}

export default new MainPage();
