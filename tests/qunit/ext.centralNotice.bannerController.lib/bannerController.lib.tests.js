( function ( mw, $ ) {
	'use strict';

	var testFixtures = mw.centralNoticeTestFixtures,
		testCases = testFixtures.test_cases,
		numBuckets = testFixtures.mock_config_values.wgNoticeNumberOfBuckets,
		lib = mw.cnBannerControllerLib;

	// FIXME: fail hard if there is no fixture data

	QUnit.module( 'ext.centralNotice.bannerController.lib', QUnit.newMwEnvironment( {
		config: {
			// Make this config available in tests
			wgNoticeNumberOfBuckets: numBuckets
		}
	} ) );

	// Cycle through test cases, contexts and outputs, and buckets and set up
	// allocation tests. For JSLint-happiness, drizzle toasted closure sauce.
	$.each( testCases, function( testCaseName, testCase ) {
		$.each( testCase.contexts_and_outputs, function ( cAndOName, cAndO ) {

			var i, testName, allocationTestFunction;

			// Note: numBuckets isn't available via mw.config here, only in tests
			for (i = 0; i < numBuckets; i++ ) {
				testName = testCaseName + '/' + cAndOName + '/bucket_' + i;
				allocationTestFunction = makeAllocationTestFunction( cAndO, i );
				QUnit.test( testName, allocationTestFunction );
			}
		} );
	} );

	/**
	 * Return a function for an alloaction test, with the required values
	 * closured in.
	 */
	function makeAllocationTestFunction( cAndO, bucket ) {
		return function ( assert ) {
				var choices = cAndO.choices,
				expectedAllocations = cAndO.allocations[bucket],
				choice,
				expectedAllocationCount,
				j,
				allocatedBanner,
				roundedAllocation,
				roundedExpcetedAllocation;

			// Munge magic start and end properties into timestamps
			setChoicesStartEnd( cAndO.choices );

			// Set the bucket for all campaigns to the bucket we're on
			lib.bucketsByCampaign = {};
			for ( j = 0; j < choices.length; j++ ) {
				choice = choices[j];
				lib.bucketsByCampaign[choice.name] = { val: bucket };
			}

			// Set up context
			mw.centralNotice.data.country = cAndO.context.country;
			mw.centralNotice.data.device = cAndO.context.device;
			mw.centralNotice.data.anonymous =
				isAnonymousFromLoggedInStatus( cAndO.context.logged_in_status );

			// TODO: make separate tests for each method
			lib.setChoiceData( choices );
			lib.filterChoiceData();
			lib.makePossibleBanners();
			lib.calculateBannerAllocations();

			// Set expected number of allocations and test number of assertions
			expectedAllocationCount = Object.keys( expectedAllocations ).length;
			assert.expect( 1 + ( expectedAllocationCount * 2) );

			assert.strictEqual( lib.possibleBanners.length,
				expectedAllocationCount, 'Number of banners allocated.' );

			for ( j = 0; j < expectedAllocationCount; j++ ) {

				allocatedBanner = lib.possibleBanners[j];

				// Test that the banner has any allocation at all. This assertion
				// makes finding errors friendlier.
				assert.ok( expectedAllocations.hasOwnProperty( allocatedBanner.name ),
					'Allocated banner ' + allocatedBanner.name + ' was expected');

				// Test allocation ammount only up to 3 decimal points because
				// of innacuracies in real number arithmetic.
				roundedAllocation = allocatedBanner.allocation.toFixed( 3 );
				roundedExpcetedAllocation =
					expectedAllocations[allocatedBanner.name].toFixed( 3 );

				// Test as strings so failing results include banner name
				assert.strictEqual(
					roundedAllocation, roundedExpcetedAllocation,
					'Expected allocaiton for ' + allocatedBanner.name
				);
			}
		};
	}

	/**
	 * Prepare a test case for use in a test. Currently just substitutes UNIX
	 * timestamps for times in the fixtures, which are represented as offsets
	 * in days from the current time. Note: this logic is repeated in PHP for
	 * PHPUnit tests that use the same fixtures.
	 *
	 * @see CentralNoticeTestFixtures::setTestCaseStartEnd()
	 */
	function setChoicesStartEnd( choices ) {
		var i, choice,
			now = new Date();

		for ( i = 0; i < choices.length; i++ ) {
			choice = choices[i];
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

	function isAnonymousFromLoggedInStatus( loggedInStatus ) {
		switch ( loggedInStatus ) {
			case 'anonymous':
				return true;
			case 'logged_in':
				return false;
			default:
				throw 'Non-existent logged-in status.';
		}
	}

	// TODO: tests for bannerController.lib.chooseBanner()

} ( mediaWiki, jQuery ) );
