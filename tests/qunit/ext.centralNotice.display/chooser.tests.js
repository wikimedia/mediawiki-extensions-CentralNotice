( function () {
	'use strict';

	const testFixtures = mw.centralNoticeTestFixtures,
		testCases = testFixtures.test_cases,
		numBuckets = testFixtures.mock_config_values.NoticeNumberOfBuckets,
		chooser = mw.centralNotice.internal.chooser;

	// FIXME: fail hard if there is no fixture data

	QUnit.module( 'ext.centralNotice.display.chooser', QUnit.newMwEnvironment() );

	// Cycle through test cases, contexts and outputs, and buckets and set up
	// allocation tests. For JSLint-happiness, drizzle toasted closure sauce.
	// eslint-disable-next-line no-jquery/no-each-util
	$.each( testCases, ( testCaseName, testCase ) => {
		// eslint-disable-next-line no-jquery/no-each-util
		$.each( testCase.contexts_and_outputs,
			( contextAndOutputName, contextAndOutput ) => {

				// Note: numBuckets isn't available via mw.config here, only in tests
				for ( let i = 0; i < numBuckets; i++ ) {
					const testName = testCaseName + '/' + contextAndOutputName + '/bucket_' + i;

					// Use a deep copy of contextAndOutput for each test, since
					// properties get added in tests
					const allocationTestFunction = makeAllocationTestFunction(
						$.extend( true, {}, contextAndOutput ), i
					);

					QUnit.test( testName, allocationTestFunction );
				}
			}
		);
	} );

	/**
	 * Return a function for an allocation test, with the required values
	 * closured in.
	 *
	 * @param contextAndOutput
	 * @param bucket
	 */
	function makeAllocationTestFunction( contextAndOutput, bucket ) {
		return function ( assert ) {
			const choices = contextAndOutput.choices,
				context = contextAndOutput.context,
				expectedAllocations = contextAndOutput.allocations;

			// Calculate how many assertions to expect:
			// 3 per campaign (existence, allocation and number of banners)
			// plus 2 per banner in each campaign (existence and allocation)
			// plus 1 (number of campaigns)... except if a campaign expects
			// 0 allocation, in which case just 2 assertion per campaign.
			let expectedAssertCount = 1;
			let expectedBanners;
			// eslint-disable-next-line no-jquery/no-each-util
			$.each( expectedAllocations, ( key, camp ) => {

				if ( camp.allocation === 0 ) {
					expectedAssertCount += 2;
				} else {
					expectedBanners = camp.banners[ bucket ];
					expectedAssertCount +=
						3 + ( 2 * Object.keys( expectedBanners ).length );
				}
			} );

			assert.expect( expectedAssertCount );

			// Munge magic start and end properties into timestamps
			setChoicesStartEnd( choices );

			const anonymous =
				isAnonymousFromLoggedInStatus( context.logged_in_status );

			const availableCampaigns = chooser.makeAvailableCampaigns(
				choices,
				context.country,
				context.region,
				anonymous,
				context.device,
				0
			);

			// Instead of testing the return value of this method, we'll test
			// the allocation properties that are set on the campaigns in
			// choices.
			// TODO Test return value, too.
			chooser.chooseCampaign(
				availableCampaigns,
				0
			);

			let allocatedCampaignsCount = 0;

			// Cycle through the campaigns in choices and test expected allocation
			for ( let j = 0; j < choices.length; j++ ) {
				const campaign = choices[ j ];
				const campaignName = campaign.name;

				// Continue if this campaign wasn't allocated
				if ( !( 'allocation' in campaign ) ) {
					continue;
				}

				allocatedCampaignsCount++;

				// Test that the campaign was expected and was correctly allocated
				assert.ok(
					campaignName in expectedAllocations,
					'Allocated campaign ' + campaignName + ' was expected.'
				);

				const expectedCampaign = expectedAllocations[ campaignName ];

				assert.strictEqual(
					campaign.allocation.toFixed( 3 ),
					expectedCampaign.allocation.toFixed( 3 ),
					'Expected allocation for campaign ' + campaignName
				);

				// By not testing banner allocation for unallocated campaigns,
				// we make test fixtures more readable :)
				if ( expectedCampaign.allocation === 0 ) {
					continue;
				}

				// As above, instead of testing the return value of this method,
				// we'll test the allocation properties that are set on the
				// banners in campaign.
				// TODO Test return value, too.
				chooser.chooseBanner(
					campaign,

					// bucket argument mocks internal.bucketer.getReducedBucket()
					bucket % campaign.bucket_count,
					anonymous,
					context.device,
					0
				);

				expectedBanners = expectedCampaign.banners[ bucket ];

				let allocatedBannersCount = 0;

				for ( let k = 0; k < campaign.banners.length; k++ ) {

					const banner = campaign.banners[ k ];
					const bannerName = banner.name;

					// Continue if this banner wasn't allocated
					if ( !( 'allocation' in banner ) ) {
						continue;
					}

					allocatedBannersCount++;

					// Test the banner was expected and was correctly allocated
					assert.ok(
						bannerName in expectedBanners,
						'Allocated banner ' + bannerName + ' was expected.'
					);

					assert.strictEqual(
						banner.allocation.toFixed( 3 ),
						expectedBanners[ bannerName ].toFixed( 3 ),
						'Expected allocation for banner ' + bannerName
					);
				}

				// Test that the expected number of banners for this campaign
				// were allocated
				assert.strictEqual(
					allocatedBannersCount,
					Object.keys( expectedBanners ).length,
					'Number of banners allocated.'
				);

			}

			// Test that the expected number of campaigns were allocated
			assert.strictEqual(
				allocatedCampaignsCount,
				Object.keys( expectedAllocations ).length,
				'Number of campaigns allocated.'
			);
		};
	}

	/**
	 * Prepare a test case for use in a test. Currently just substitutes UNIX
	 * timestamps for times in the fixtures, which are represented as offsets
	 * in days from the current time. Note: this logic is repeated in PHP for
	 * PHPUnit tests that use the same fixtures.
	 *
	 * @param choices
	 * @see CentralNoticeTestFixtures::setTestCaseStartEnd()
	 */
	function setChoicesStartEnd( choices ) {
		const now = new Date();

		for ( let i = 0; i < choices.length; i++ ) {
			const choice = choices[ i ];

			choice.start = makeTimestamp( now, choice.start_days_from_now );
			choice.end = makeTimestamp( now, choice.end_days_from_now );

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
	 * @param {Date} refDate The date to calculate the offset from
	 * @param {number} offsetInDays
	 * @return {number}
	 */
	function makeTimestamp( refDate, offsetInDays ) {
		const date = new Date();
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
				throw new Error( 'Non-existent logged-in status.' );
		}
	}
}() );
