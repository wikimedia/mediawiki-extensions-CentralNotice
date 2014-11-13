Install
=======
    cd tests/browser
    bundler install

Fixtures
========
You must create the banners manually, for now.  Set up CentralNotice so
that there are three banners, which will display respectively when the
random variable is at .25, .5, and .75 .  The contents of each banner
should include:
    <div id="centralnotice_testbanner_name">{{{title}}}</div>
In each banner, the "title" variable should be set to the strings,
"one", "two", and "three".  This text is used to confirm the presence
of each banner.

Running tests
=============
    export MEDIAWIKI_URL=http://crm.dev/
    bundler exec cucumber
