const hide = mw.centralNotice.internal.hide;
QUnit.module( 'mw.centralNotice.internal.hide' );

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
