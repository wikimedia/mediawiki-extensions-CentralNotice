'use strict';
const assert = require( 'assert' ),
	MainPage = require( '../pageobjects/main.page.js' );

describe( 'CentralNotice', function () {

	it( 'banner is displayed on Main Page', async function () {
		await MainPage.open();

		await MainPage.banner.waitForExist();
		assert( await MainPage.banner.isExisting() );
	} );

} );
