import MainPage from '../pageobjects/main.page.js';

describe( 'CentralNotice', () => {

	it( 'banner is displayed on Main Page', async () => {
		await MainPage.open();

		await expect( MainPage.banner ).toExist();
	} );

} );
