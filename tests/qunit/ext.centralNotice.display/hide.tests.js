const hide = mw.centralNotice.internal.hide,
	testFixtures = mw.centralNoticeTestFixtures,
	cookieDurations = testFixtures.mock_config_values.NoticeCookieDurations;

QUnit.module( 'mw.centralNotice.internal.hide', QUnit.newMwEnvironment( {
	beforeEach: function () {
		const expirationDate = new Date( 2000, 0, 1 ).toUTCString();
		// What a weird way to delete cookies! Probably can use the cookieStore API for tests...
		document.cookie = 'centralnotice_hide_fundraising=;expires=' + expirationDate;
		document.cookie = 'centralnotice_hide_photography=;expires=' + expirationDate;
		mw.storage.remove( 'centralnotice_donation_date' );
	},
	config: {
		wgNoticeCookieDurations: cookieDurations
	}
} ) );

QUnit.test( 'fetchHideUrls', ( assert ) => {
	let imgs = hide.fetchHideUrls( [], 100, 'category', 'a good reason' );
	assert.strictEqual( imgs.length, 0, 'No requests generated' );

	imgs = hide.fetchHideUrls(
		[
			'/foo/relative', 'https://en.wikipedia.org/img.gif'
		],
		100,
		'category',
		'a good reason'
	);
	assert.strictEqual( imgs.length, 2, 'Requests generated' );
} );

QUnit.test( 'retrieveDonateDateWithNoCookie', ( assert ) => {
	const date = hide.getDonationDate();
	assert.strictEqual( date, null );
} );

QUnit.test( 'setDonateCookieAndRetrieveDate', ( assert ) => {
	hide.setCategory( 'fundraising' );
	hide.setHideCookies( 'donate', 1000 );
	const date = hide.getDonationDate();
	// Should be within 2 seconds (the date of the cookie is always rounded down to the nearest second)
	assert.closeTo( date.getTime(), Date.now(), 2000 );
} );

QUnit.test( 'setCloseCookieAndRetrieveDate', ( assert ) => {
	hide.setCategory( 'fundraising' );
	hide.setHideWithCloseButtonCookies();
	const date = hide.getDonationDate();
	assert.strictEqual( date, null );
} );

QUnit.test( 'setNonFundraisingDonateCookieAndRetrieveDate', ( assert ) => {
	hide.setCategory( 'photography' );
	hide.setHideCookies( 'donate', 1000 );
	const date = hide.getDonationDate();
	assert.strictEqual( date, null );
} );

QUnit.test( 'setDonateThenCloseCookieAndRetrieveDate', ( assert ) => {
	hide.setCategory( 'fundraising' );
	hide.setHideCookies( 'donate', 1000 );
	// This will overwrite the reason=donate cookie but should stash the donation date in localSettings
	hide.setHideWithCloseButtonCookies();
	const date = hide.getDonationDate();
	// Should be within 2 seconds (the date of the cookie is always rounded down to the nearest second)
	assert.closeTo( date.getTime(), Date.now(), 2000 );
} );
