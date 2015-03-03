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

comment: A brief explanation of the test
setup: Data for setting up campaigns and banners in the database
context_and_outputs: Contexts to test the campaigns and banners from,
and expected output for each context.


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
automatically given default values (autolink, landing page, banner
body).


Issues
-----

* Tests of allocations may depend on the ordering of object
properties/array keys. This is typically predictable, so it works, but
it should be cleaned up.

* The enabled property should be munged to use boolean values in
fixture data, rather than integer values.
