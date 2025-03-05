( function () {
	const realIdleCallback = mw.requestIdleCallback;

	QUnit.module( 'ext.centralNotice.kvStore', QUnit.newMwEnvironment( {
		afterEach: function () {
			let key, i = localStorage.length;
			// Loop backwards since removal affects the key index,
			// causing items to consistently be skipped over
			while ( i-- > 0 ) {
				key = localStorage.key( i );
				if ( /^CentralNoticeKV.+\|unittest/.test( key ) ) {
					localStorage.removeItem( key );
				}
			}
			mw.requestIdleCallback = realIdleCallback;
		}
	} ) );

	QUnit.test( 'getItem', ( assert ) => {
		const kvStore = mw.centralNotice.kvStore,
			context = kvStore.contexts.GLOBAL;
		kvStore.setItem( 'unittest-New', 'x', context, 1 );
		kvStore.setItem( 'unittest-Old', 'x', context, -2 );

		assert.strictEqual( kvStore.getError(), null, 'no errors' );
		assert.strictEqual( kvStore.getItem( 'unittest-New', context ), 'x', 'retrieve valid item' );
		// Verify that expiry is verified at run-time regardless of kvStoreMaintenance
		assert.strictEqual( kvStore.getItem( 'unittest-Old', context ), null, 'ignore expired item' );
	} );
}() );
