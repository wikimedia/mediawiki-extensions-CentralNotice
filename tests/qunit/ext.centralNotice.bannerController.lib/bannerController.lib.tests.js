( function( mw, $ ) {
	'use strict';

	QUnit.module( 'ext.centralNotice.bannerController.lib', QUnit.newMwEnvironment( {
		setup: function() {
			mw.centralNotice.data.country = 'XX';
			mw.centralNotice.data.device = 'desktop';
			mw.centralNotice.data.anonymous = true;
		}
	} ) );

	QUnit.test( 'allocations test cases', function( assert ) {

		var testFixtures = mw.centralNoticeTestFixtures,
			testCases = testFixtures.testCases,
			lib = mw.cnBannerControllerLib;

		QUnit.expect( Object.keys( testCases ).length );

		$.each( testCases, function( testCaseName, testCase ) {
			var choices,
				choice,
				i,
				allocatedBanner;

			// BOOM on priority case FIXME ???

			setTestCaseStartEnd( testCase );
			choices = testCase.choices;

			// Set per-campaign buckets to 0 for all campaigns
			// FIXME Allow testing of different buckets
			lib.bucketsByCampaign = {};
			for ( i = 0; i < choices.length; i++ ) {
				choice = choices[i];
				lib.bucketsByCampaign[choice.name] = { val: 0 };
			}

			// TODO: would like to declare individual tests here, but I
			// haven't been able to make that work, yet.
			lib.setChoiceData( choices );
			lib.filterChoiceData();
			lib.makePossibleBanners();
			lib.calculateBannerAllocations();

			// TODO: the errors will not reveal anything useful about
			// which case this is, and what happened.  So we throw
			// exceptions manually.  The horror!
			try {
				if ( lib.possibleBanners.length !== Object.keys( testCase.allocations ).length ) {
					throw 'Wrong number of banners allocated in "' + testCase.title + '".';
				}
				for ( i = 0; i < lib.possibleBanners.length; i++ ) {
					allocatedBanner = lib.possibleBanners[i];
					if ( Math.abs( allocatedBanner.allocation - testCase.allocations[allocatedBanner.name] ) > 0.001 ) {
						throw 'Banner ' + allocatedBanner.name + ' was misallocated in "' + testCase.title + '".';
					}
				}
			} catch ( error ) {
				assert.ok( false, error
					+ " expected: " + QUnit.jsDump.parse( testCase.allocations )
					+ ", actual: " + QUnit.jsDump.parse( lib.possibleBanners )
				);
				return;
			}
			assert.ok( true, 'Allocations match in "' + testCase.title + '"' );
		} );
	} );

	/**
	 * Prepare a test case for use in a test. Currently just substitutes UNIX
	 * timestamps for times in the fixtures, which are represented as offsets
	 * in days from the current time. Note: this logic is repeated in PHP for
	 * PHPUnit tests that use the same fixtures.
	 *
	 * @see CentralNoticeTestFixtures::setTestCaseStartEnd()
	 */
	function setTestCaseStartEnd( testCaseSpec ) {
		var i, choice,
			now = new Date();

		for ( i = 0; i < testCaseSpec.choices.length; i++ ) {
			choice = testCaseSpec.choices[i];
			choice.start = makeTimestamp( now, choice.startDaysFromNow );
			choice.end = makeTimestamp( now, choice.endDaysFromNow);

			// Remove these special properties from choices, to make the
			// choices data mirror the real data structure.
			delete choice.startDaysFromNow;
			delete choice.endDaysFromNow;
		}
	}

	/**
	 * Return a UNIX timestamp for refDate offset by the number of days
	 * indicated.
	 *
	 * @param refDate Date The date to calculate the offset from
	 * @param offsetInDays
	 * @return int
	 */
	function makeTimestamp( refDate, offsetInDays ) {
		var date = new Date();
		date.setDate( refDate.getDate() + offsetInDays );
		return Math.round( date.getTime() / 1000 );
	}

	// TODO: chooser tests

} ( mediaWiki, jQuery ) );
