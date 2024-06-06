'use strict';
const assert = require( 'assert' ),
	MainPage = require( '../pageobjects/main.page.js' );

describe( 'CentralNotice', () => {

	it( 'banner is displayed on Main Page', async () => {
		await MainPage.open();

		await MainPage.banner.waitForExist();
		assert( await MainPage.banner.isExisting() );
	} );

} );
