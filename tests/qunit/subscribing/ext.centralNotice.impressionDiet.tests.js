( function () {
	'use strict';

	const { impressionDietHandler } = require( 'ext.centralNotice.impressionDiet' ).private;

	const CLOCK_HOUR = 1 * 3600 * 1000;
	const CLOCK_DAY = 24 * 3600 * 1000;

	QUnit.module( 'ext.centralNotice.impressionDiet', {
		beforeEach: function () {
			sinon.replace( mw.centralNotice.internal.state, 'data', {} );

			const store = this.store = {};
			sinon.replace( mw.centralNotice.kvStore, 'getItem', ( key ) => store[ key ] ? JSON.parse( store[ key ] ) : null );
			sinon.replace( mw.centralNotice.kvStore, 'setItem', ( key, value ) => {
				store[ key ] = JSON.stringify( value );
			} );

			// Sinon does not mock timezone, so, because impressionDiet acts on local time,
			// this fake time is also without trailing "Z" and thus in local time
			this.clock = sinon.useFakeTimers( { now: new Date( '2011-04-01T09:15:00' ) } );
		}
	} );

	QUnit.test.each( 'impressionDietHandler', {
		'maximumSeen simple': {
			mixinParams: {
				maximumSeen: 3,
				skipInitial: 0,
				restartCycleDelay: 0
			},
			sequence: [
				{ hide: undefined },
				{ hide: undefined },
				{ hide: undefined },
				{ hide: 'waitdate' },
				{ hide: 'waitdate' }
			]
		},
		'maximumSeen with skipInitial': {
			mixinParams: {
				maximumSeen: 3,
				skipInitial: 2,
				restartCycleDelay: 0
			},
			sequence: [
				{ hide: 'waitimps' },
				{ hide: 'waitimps' },
				{ hide: undefined },
				{ hide: undefined },
				{ hide: undefined },
				{ hide: 'waitdate' },
				{ hide: 'waitdate' }
			]
		},
		'maximumSeen with restartCycleDelay and skipInitial': {
			mixinParams: {
				maximumSeen: 3,
				skipInitial: 1,
				restartCycleDelay: 24 * 3600
			},
			sequence: [
				{ hide: 'waitimps' },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: 'waitdate' },
				{ tick: CLOCK_DAY, hide: 'waitimps' },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: undefined },
				{ tick: CLOCK_HOUR, hide: 'waitdate' },
				{ tick: CLOCK_HOUR, hide: 'waitdate' }
			]
		}
	}, function ( assert, { mixinParams, sequence } ) {
		for ( const [ i, item ] of Object.entries( sequence ) ) {
			mw.centralNotice.internal.state.data = {};
			mw.centralNotice.internal.state.setUpForTestingBanner();
			if ( item.tick ) {
				this.clock.tick( item.tick );
			}
			impressionDietHandler( mixinParams );
			const num = Number( i ) + 1;
			assert.strictEqual(
				mw.centralNotice.internal.state.data.status,
				item.hide ? 'banner_canceled' : 'banner_chosen',
				'status for pageview ' + num + '\n' + this.store.impression_diet
			);
			assert.strictEqual(
				mw.centralNotice.internal.state.data.bannerCanceledReason,
				item.hide,
				'bannerCanceledReason for pageview ' + num
			);
		}
	} );

}() );
