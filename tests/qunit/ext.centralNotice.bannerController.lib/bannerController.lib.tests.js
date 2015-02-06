( function( mw, $ ) {
	'use strict';

	QUnit.module( 'ext.centralNotice.bannerController.lib', QUnit.newMwEnvironment( {
		setup: function() {
			mw.centralNotice.data.country = 'XX';
			mw.centralNotice.data.device = 'desktop';
			mw.centralNotice.data.anonymous = true;
		}
	} ) );

	var testFixtures = mw.centralNoticeTestFixtures,
		testCases = testFixtures.test_cases,
		lib = mw.cnBannerControllerLib;

	// FIXME: fail hard if there is no fixture data

	$.each( testCases, function( testCaseName, testCase ) {
		QUnit.test( testCaseName, 1, function( assert ) {
			var choices,
				choice,
				expectedAllocationCount,
				i,
				allocatedBanner,
				roundedAllocation,
				roundedExpcetedAllocation;

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

			// Set expected number of allocations
			expectedAllocationCount = Object.keys( testCase.allocations ).length;
			assert.expect( 1 + expectedAllocationCount );

			assert.strictEqual( lib.possibleBanners.length, expectedAllocationCount );

			for ( i = 0; i < expectedAllocationCount; i++ ) {

				allocatedBanner = lib.possibleBanners[i];

				// Test up to 3 decimal points because of innacuracies in
				// real number arithmetic
				roundedAllocation = allocatedBanner.allocation.toFixed( 3 );
				roundedExpcetedAllocation =
					testCase.allocations[allocatedBanner.name].toFixed( 3 );

				// Test as strings so failing results include banner name
				assert.strictEqual(
					allocatedBanner.name + ':' + roundedAllocation,
					allocatedBanner.name + ':' + roundedExpcetedAllocation
				);
			}
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
			choice.start = makeTimestamp( now, choice.start_days_from_now );
			choice.end = makeTimestamp( now, choice.end_days_from_now);

			// Remove these special properties from choices, to make the
			// choices data mirror the real data structure.
			delete choice.start_days_from_now;
			delete choice.end_days_from_now;
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
