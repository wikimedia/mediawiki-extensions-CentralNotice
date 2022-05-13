( function () {
	var realIdleCallback = mw.requestIdleCallback;

	QUnit.module( 'ext.centralNotice.startUp', QUnit.newMwEnvironment( {
		afterEach: function () {
			var key, i = localStorage.length;
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

	QUnit.test( 'maintenance', function ( assert ) {
		var kvStore = mw.centralNotice.kvStore,
			context = kvStore.contexts.GLOBAL,
			done = assert.async();
		// Mock requestIdleCallback so it always returns 10 seconds left.
		// Mostly copied from shim in mediawiki core file
		// resources/src/startup/mediawiki.requestIdleCallback.js
		mw.requestIdleCallback = function ( callback ) {
			setTimeout( function () {
				callback( {
					didTimeout: false,
					timeRemaining: function () {
						// Plenty of time left!
						return 10000;
					}
				} );
			}, 1 );
		};
		kvStore.setItem( 'unittest-New', 'x', context, 1 );
		kvStore.setItem( 'unittest-Old', 'x', context, -2 );
		kvStore.setItem( 'unittest-Older', 'x', context, -3 );

		assert.notStrictEqual(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-New' ),
			null,
			'item "New" found in storage'
		);
		assert.notStrictEqual(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-Old' ),
			null,
			'item "Old" found in storage'
		);
		assert.notStrictEqual(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-Older' ),
			null,
			'item "Older" found in storage'
		);

		mw.centralNotice.kvStoreMaintenance.doMaintenance().then( function () {
			assert.notStrictEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-New' ),
				null,
				'item "New" kept in storage'
			);
			assert.strictEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-Old' ),
				null,
				'item "Old" removed from storage'
			);
			assert.strictEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-Older' ),
				null,
				'item "Older" removed from storage'
			);

			done();
		} );
	} );

}() );
