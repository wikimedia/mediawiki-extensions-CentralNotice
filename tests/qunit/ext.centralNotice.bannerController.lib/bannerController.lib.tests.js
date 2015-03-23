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
		$.each( testCase.contexts_and_outputs,
			function ( contextAndOutputName, contextAndOutput ) {

			var i, testName, allocationTestFunction;

			// Note: numBuckets isn't available via mw.config here, only in tests
			for (i = 0; i < numBuckets; i++ ) {
				testName = testCaseName + '/' + contextAndOutputName + '/bucket_' + i;
				allocationTestFunction = makeAllocationTestFunction( contextAndOutput, i );
				QUnit.test( testName, allocationTestFunction );
			}
		} );
	} );

	/**
	 * Return a function for an alloaction test, with the required values
	 * closured in.
	 */
	function makeAllocationTestFunction( contextAndOutput, bucket ) {
		return function ( assert ) {
				var choices = contextAndOutput.choices,
				expectedAssertCount,
				j, expectedBanners,
				expectedAllocations = contextAndOutput.allocations,
				campaign, expectedCampaign, campaignName,
				context = contextAndOutput.context,
				k, banner, bannerName;

			// Calculate how many assertions to expect:
			// 3 per campaign (existence, allocation and number of banners)
			// plus 2 per banner in each campaign (existence and allocation)
			// plus 1 (number of campaigns)... except if a campaign expects
			// 0 allocation, in which case just 2 assertion per campaign.
			expectedAssertCount = 1;
			$.each( expectedAllocations, function( key, campaign ) {

				if ( campaign.allocation === 0) {
					expectedAssertCount += 2;
				} else {
					expectedBanners = campaign.banners[bucket];
					expectedAssertCount +=
						3 + ( 2 * Object.keys( expectedBanners ).length );
				}
			} );

			assert.expect( expectedAssertCount );

			// Munge magic start and end properties into timestamps
			setChoicesStartEnd( choices );

			// Set the bucket for all campaigns to the bucket we're on
			lib.bucketsByCampaign = {};
			for ( j = 0; j < choices.length; j++ ) {
				campaignName = choices[j].name;
				lib.bucketsByCampaign[campaignName] = { val: bucket };
			}

			// Set up context
			mw.centralNotice.data.country = context.country;
			mw.centralNotice.data.device = context.device;
			mw.centralNotice.data.anonymous =
				isAnonymousFromLoggedInStatus( context.logged_in_status );

			// Set up and filter choices, and allocate campaigns
			lib.setChoiceData( choices );

			// The methods we're testing operate on lib.choiceData. That's where
			// we'll look for the results to test.
			// TODO: make separate tests for each method
			lib.setChoiceData( choices );
			lib.filterChoiceData();
			lib.calculateCampaignAllocations();

			// Test that the expected number of campaigns are in choices
			assert.strictEqual( lib.choiceData.length,
				Object.keys( expectedAllocations ).length,
				'Number of campaigns allocated.' );

			// Cycle through the campaigns in choices and test expected allocation
			for ( j = 0; j < lib.choiceData.length; j++ ) {
				campaign = lib.choiceData[j];
				campaignName = campaign.name;

				// Test that the campaign was expected and was correctly allocated
				assert.ok( campaignName in expectedAllocations,
					'Allocated campaign ' + campaignName + ' was expected.');

				expectedCampaign = expectedAllocations[campaignName];

				assert.strictEqual(
					campaign.allocation.toFixed( 3 ),
					expectedCampaign.allocation.toFixed( 3 ),
					'Expected allocation for campaign ' + campaignName
				);

				// By not testing banner allocation for unallocated campaigns,
				// we make test fixtures more readable :)
				if ( expectedCampaign.allocation === 0) {
					continue;
				}

				// "Choose" this campaign, make a list of possible banners, and
				// allocate them.
				lib.setCampaign( campaign );
				lib.makePossibleBanners();
				lib.calculateBannerAllocations();

				expectedBanners = expectedCampaign.banners[bucket];

				// Test that the expected number of banners for this campaign
				// are in possibleBanners.
				assert.strictEqual( lib.possibleBanners.length,
					Object.keys( expectedBanners ).length,
					'Number of banners allocated.' );

				for ( k = 0; k < lib.possibleBanners.length; k++ ) {

					banner = lib.possibleBanners[k];
					bannerName = banner.name;

					// Test the banner was expected and was correctly allocated
					assert.ok( bannerName in expectedBanners,
						'Allocated banner ' + bannerName + ' was expected.');

					assert.strictEqual(
						banner.allocation.toFixed( 3 ),
						expectedBanners[bannerName].toFixed( 3 ),
						'Expected allocation for banner ' + bannerName
					);
				}
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
