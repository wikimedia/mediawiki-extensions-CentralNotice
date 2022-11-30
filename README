CentralNotice allows central distribution of small bits of translatable content
(a.k.a banners) to subscribing wikis from one central infrastructure wiki.

Configuration
==========

* $wgNoticeProject is used for targeting campaigns to specific wikis. It
  should be overridden on each wiki with the appropriate value.
  Actual user language (wgUserLanguage) is used for banner localisation.
  Default: "wikipedia"

* $wgNoticeProjects: List of available projects.
  Default: []

* $wgNoticeInfrastructure: Enable the campaign hosting infrastructure on this wiki...
  Set to false for wikis that only use a sister site for the control.
  Default: true

* $wgCentralDBname: The name of the database which hosts the centralized
  campaign data.  If false, we will default to using the primary database.
  Default: false

* $wgCentralBannerRecorder: URL which is hit after a banner is loaded, for
  compatibility with analytics.
  Default: false

* $wgCentralNoticeSampleRate: Sample rate for recording impressions to the
  above URL.
  Default: 1 / 100

* $CentralNoticeImpressionEventSampleRate: Sample rate for recording impressions
  using EventLogging (meant to supersede custom URL for impression recording).
  Default: 0

* $wgCentralHost: Protocol and host name of the wiki that hosts the
  CentralNotice infrastructure, for example "//meta.wikimedia.org". This is
  used for DNS prefetching.
  Default: false

* $wgCentralNoticeApiUrl: The API path on the wiki that hosts the CentralNotice
  infrastructure, for example "http://meta.wikimedia.org/api.php".
  This must be set if you enable the selection of banners on the client and you
  don't have direct access to the infrastructure database (see
  $wgCentralDBname).  Note that when this is set, it will override your
  database settings.
  Default: false

* $wgCentralSelectedBannerDispatcher: URL for BannerLoader, for requests to
  fetch a banner that is already known (using the "banner" URL param). If
  false, it will default to Special:BannerLoader. Note: Ugly URL format is allowed,
  but no other URL parameters or fragment identifiers may be used.
  Default: false

* $wgCentralMobileSelectedBannerDispatcher: URL for BannerLoader on Mobile
  sites. See $wgCentralSelectedBannerDispatcher. If false, and if
  MobileFrontEnd is enabled for this site, this defaults to the same
  value as $wgCentralSelectedBannerDispatcher. (This is a temporary
  solution; see bug T156847.).
  Default: false

* $wgCentralNoticeLoader: Enable the loader itself
  Allows control over the loader visibility, without destroying infrastructure
  for cached content
  Default: true

* $wgNoticeBannerPreview: URL prefix where banner screenshots are stored. False
  if this feature is disabled.  meta.wikimedia.org CentralNotice banners are
  archived at "http://fundraising-archive.wmflabs.org/banner/".
  Default: false

* $wgNoticeCookieDomain: Domain to set global cookies for.
  Example: ".wikipedia.org"
  Default: ""

* $wgNoticeCookieDurations: How long to respect different types
  of banner hiding cookies, in seconds. bannerController.js selects one of
  these entries based on the  cookie's "reason" element and adds that to the
  cookie's "created" element to determine when to stop hiding the banner.

  Default: array(
	// The amount of time banners will be hidden by the close box.
	// Defaults to two weeks.
	'close' => 1209600,
	// Amount of time the banners will hide after a successful donation.
	// Defaults to one year.
	'donate' => 31536000
  );

* $wgCentralNoticeFallbackHideCookieDuration: Fallback hide cookie duration,
  for hide reasons without an entry in $wgNoticeCookieDurations, if no duration
  is specified in the request to Special:HideBanners.
  Note: This is just to keep things running in an unexpected edge case. It is
  recommended that this value not be intentionally relied on by banners.
  Default: 604800

* $wgNoticeHideUrls: Locations of Special:HideBanner targets to hit
  when a banner close button is pressed. The hides will then be specific to
  each domain specified by $wgNoticeCookieDomain on that wiki.

  If CentralNotice is only enabled on a single wiki, or if cross-wiki hiding is
  not desired, the leave this as array(). Page code will always hide a banner
  by setting a cookie for that wiki's domain.
  Default: array()

* $wgCentralNoticeHideBannersP3P: A string to use in a P3P privacy policy
  header set by Special:HideBanners.  The header is needed to make IE keep
  third-party cookies in default privacy mode. If this is set to false, a
  default invalid policy containing the URL of Special:HideBanners/P3P will be
  used, and that subpage will contain a short explanation.
  Default: false

* $wgNoticeBannerMaxAge: Server-side banner cache timeout, in seconds, for
  anonymous users.
  Default: 600

* $wgNoticeBannerReducedMaxAge: Reduced server-side banner cache timeout, in
  seconds, for anonymous users, when SpecialBannerLoader catches an exception.
  We lower the expiry in the hope that the error will go away the next time this
  resource is requested.

* $wgNoticeUseTranslateExtension: Whether to use the Translation extension for
  banner message translation
  Default: false

* $wgNoticeUseLanguageConversion: Whether to disable variant languages and use
  an automatically converted version of banners fetched from their parent
  language (zh for zh-cn, for example) instead.
  Default: false

* $wgNoticeProtectGroup: *** Deprecated, see $wgCentralNoticeMessageProtectRight.
  Default: false

* $wgNoticeTranslateDeployStates: When using the group review feature of the
  translate extension, only message groups with these group review states will
  be deployed -- e.g. copy from the CNBanners namespace to the MW namespace.
  This requires that anyone who can assign this state much have site-edit
  permissions.
  Default: [ "published" ]

* $wgNoticeNumberOfBuckets: Number of buckets that are provided to choose from--
  this must be a power of two! It must not also be greater than 9 unless a
  schema change is performed. Right now this column is tinyint(1)
  Default: 4

* $wgNoticeNumberOfControllerBuckets: We can tell the controller to only assign
  buckets from 0 .. to this variable. This allows us to serve banners only to
  people who meet certain criteria (ie: banners place people in certain buckets
  after events happen.)
  Default: 2

* $wgNoticeBucketExpiry: How the legacy global bucket cookie for legacy global
  buckets will last, in days.
  Default: 7

* $wgCentralNoticePerCampaignBucketExtension: Extra time to keep per-campaign
  buckets after a campaign has ended, in days.
  Default: 30

* $wgCentralNoticeCategoriesUsingLegacy: Temporary measure: Campaigns whose
  banners are all set to this category will use some legacy mechanisms
  (especially cookies instead of the KVStore).
  TODO Fix and remove!
  Default: [ "Fundraising", "fundraising" ]

* $wgCentralNoticeBannerMixins: Available banner mixins
  See https://www.mediawiki.org/wiki/Extension:CentralNotice/Banner_mixins
  Default: []

* $wgCentralNoticeCampaignMixins: Available campaign mixins. Mixins must
  declare at least a module and an i18n key for their name. They may also provide
  a module for custom admin UI elements. If no custom admin UI module is provided,
  input elements for all parameters are generated automatically in the admin UI. For such
  generated input elements, i18n messages for labels (set via labelMsg) and help text
  (helpMsg) should be added to the ext.centralNotice.adminUi.campaignManager module,
  in extension.json. Allowed parameter types are "string", "integer", "float" and
  "boolean". Additionally, the "json" parameter type is available for custom admin
  UI modules only.
  Default mixins (documented in context):
    - bannerHistoryLogger
    - legacySupport
	- impressionDiet
	- largeBannerLimit
	- bannerSequence

* $wgNoticeTabifyPages: Declare all pages that should be tabified as CN pages

* $wgCentralNoticeGeoIPBackgroundLookupModule: Optional name of a ResourceLoader
  module to perform a background GeoIP lookup via a third-party service. If no
  GeoIP cookie is found, the module is used to call the service. It should
  return geo via a promise. Here we provide an example implementation for
  freegeoip.net (ext.centralNotice.freegeoipLookup), but modules for other
  similar services should be easy to create. If this config setting is null, geo
  data will only be set from the GeoIP cookie. In the WMF production, this
  cookie should be set server-side from Varnish.
  Default: null

* $wgCentralNoticeContentSecurityPolicy: Optional setting to help detect banner
  content that may violate users' privacy. When this is set, its value will be
  used on banner preview pages as a content-security-policy header, and a small
  script will be added to detect content security policy violations and show an
  alert message. An example value that alerts on all third party requests but
  does not break mediawiki is:
    "default-src data: blob: 'unsafe-inline' 'unsafe-eval' 'self';"
  Default: false

* $wgCentralNoticeMaxCampaignFallback: The maximum number of times campaign chooser
  will iterate through available campaigns to choose one to display to the user.
  The purpose of setting a low value is to avoid a long-running loop through
  campaigns. To prevent campaign fallback, set to 1.
  Default: 5

* $wgCentralNoticeAdminGroup: This group will be granted centralnotice-admin
  rights. You can always specify additional groups in $wgGroupPermissions. A
  false-y value means only groups explicitly specified in $wgGroupPermissions
  will have the right.
  Default: sysop

* $wgCentralNoticeMessageProtectRight: This right will be used to apply cascading
  protection to banner messages. It will be added to $wgRestrictionLevels and
  $wgCascadingRestrictionLevels if not present. As a transitional measure, if
  any customized value has been set for $wgNoticeProtectGroup, this variable
  will take that value.
  Default: centralnotice-admin

* $wgCentralNoticeCampaignTypes: Campaign types available to be set. Types are available
  to opt-out of in user preferences. This variable is an associative array whose keys are
  the type identifiers and whose values are associative arrays with one key, "onForAll",
  with a boolean value. Set this value to true for types that logged-in users should not
  be able to opt out of. For each of these, an interface message should exist, whose
  key should be 'centralnotice-campaign-type-' followed by the identifier of the type.
  Messages are included in the code for types provided as default values for this
  conig variable.
