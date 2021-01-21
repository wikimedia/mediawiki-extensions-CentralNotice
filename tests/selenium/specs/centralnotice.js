'use strict';
const assert = require( 'assert' ),
	MainPage = require( '../pageobjects/main.page.js' );

describe( 'CentralNotice', function () {

	it( 'banner is displayed on Main Page', function () {
		MainPage.open();

		MainPage.banner.waitForExist();
		assert( MainPage.banner.isExisting() );
	} );

} );
