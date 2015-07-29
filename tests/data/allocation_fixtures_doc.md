Allocation Fixtures Documentation
=============================

Documentation for AllocationFixtures.json

This file defines test fixtures for a variety of banner and campaign
allocation scenarios. The fixtures are used by both PHP and QUnit
tests.


Mock Config Values
----------------

Configuration values needed by test using the fixtures.


Test Cases
---------

Some commented snippets of this data structure:

// This key is the name of the whole test case
"single_full_anon": {

    // A brief explanation (JS comments aren't allowed in JSON)
    "comment": "A single, unthrottled, non-geotargeted campaign...",

    // Data for setting up campaigns and banners in the test database
    "setup": {
        ...
    },

    // Contexts to test the campaigns and banners from, and expected
    // output for each context
    "contexts_and_outputs": {

        // This key is the name of this context
        "as_targeted": {

            // An explanation of this specific context
            "comment": "Context targeted by the campaign...",

            // Required user input values to determine banner and campaign
            // selection (except buckets)
            "context": {
                ...
            },

            // Expected data from ChoiceDataProvider in this context.
            // This data is also the input for client-side tests.
            "choices": {
                ...
            },

            // Expected banner and campaign allocations
            "allocations": {

                // The name of a campaign that is expected as an
                // allocation result
                "campaign1": {

                    // The campaign's expected allocation (to 3 decimal points)
                    "allocation": 0.5,

                    // The banners expected to be allocated for this campaign
                    // for each possible bucket. The index of each element in
                    // the array is the number of the bucket that the banners
                    // that the element contains are expected for.
                    "banners": [
                        {

                            // Key is banner expected to be allocated for this
                            // campaign, and the value is the expected
                            // allocation (to 3 decimal points)
                            "banner1": 1
                        }
                    ]
                }
            }
        }
    }
}


Special Date Properties
--------------------

In both setup and expceted choices output, special properties are used
to set up and test campaign start and end times relative to the present.

These properties, start_days_from_now and end_days_from_now, generate
timestamps offset from the present by the indicated number of days.


Default Setup Properties
------------------------

For non-geotargeted campaigns, a default, empty countries property is
added. Some properties that are not relevant to these tests are
automatically given default values (bannerbody, campaign mixins for
server-side tests).


Issues
-----

* Tests of allocations may depend on the ordering of object
properties/array keys. This is typically predictable, so it works, but
it should be cleaned up.

* The enabled property should be munged to use boolean values in
fixture data, rather than integer values.
