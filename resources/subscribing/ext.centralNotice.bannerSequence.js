/**
 * Banner sequence mixin. Displays banners in a sequence in which banners may repeat
 * for a set number of page views. Sequence steps may also include no banner display.
 * Allows flags in the user's browser to skip a step if it was seen before, or if a
 * different step that uses the same identifier (in this or another sequence) was seen
 * before.
 *
 * Page view counter may be reset with the URL parameter reset=1.
 *
 * For an explanation of the data structure for the sequences mixin parameter, please
 * see ext.centralNotice.adminUi.bannerSequence.js.
 */
( function () {
	'use strict';

	var multiStorageOption, days, SequenceManager, sequenceManager,
		preBannerHandler, postBannerOrFailHandler,
		cn = mw.centralNotice,
		mixin = new cn.Mixin( 'bannerSequence' ),

		// Flag to indicate that the current step shows no banner
		showingEmptyStep = false,

		// We also check identifiers from the large banner limit mixin.
		// Coordinate both storage keys with ext.centralNotice.largeBannerLimit.js.
		// TODO Maybe find a better way to do this?
		FLAG_STORAGE_KEY = 'banner_sequence_step_seen',
		LARGE_BANNER_LIMIT_STORAGE_KEY = 'large_banner_limit',

		PAGE_VIEW_STORAGE_KEY = 'banner_sequence_page_view',

		// TTL for sequence page view, in days
		PAGE_VIEW_STORAGE_TTL = 365;

	/**
	 * A class to manage banner sequence data. Encapsulates counting page views,
	 * determining the current and next steps, steps to skip, etc.
	 *
	 * @class SequenceManager
	 * @constructor
	 * @param {Array} sequence The sequence to follow
	 * @param {number} currentPageView The current page view in the sequence (before a
	 *   banner has been shown)
	 */
	SequenceManager = function ( sequence, currentPageView ) {

		var i;

		this.sequence = sequence;

		// The page view in the sequence that we will display. (This will be updated if
		// we have to skip this step or if the sequence length has been reduced and
		// we're beyond the new limit.)
		this.currentPageView = currentPageView;

		// Track how many steps have been skipped due to stored flags
		this.stepsSkipped = 0;

		// Total number of page views in the sequence
		this.seqTotalPageViews = 0;

		// Array with starting page view indexes for each step
		this.stepStarts = [];

		// These will be set as needed in processPageView()
		this.nextPageView = null;
		this.identifierToSet = null;

		// Find total page views in the sequence and the starting page views
		for ( i = 0; i < this.sequence.length; i++ ) {
			this.stepStarts[ i ] = this.seqTotalPageViews;
			this.seqTotalPageViews += this.sequence[ i ].numPageViews;
		}

		// If the current page view is beyond the sequence length, that means the sequence
		// has been shortened since last time we were here. In that case, start
		// over.
		this.currentPageView = ( this.currentPageView < this.seqTotalPageViews ) ?
			this.currentPageView : 0;

		// Find the current step
		for ( i = 0; i < this.sequence.length; i++ ) {

			if ( i === this.sequence.length - 1 ) {
				this.currentStep = i;
				break;
			}

			if ( ( this.currentPageView >= this.stepStarts[ i ] ) &&
				( this.currentPageView < this.stepStarts[ i + 1 ] ) ) {
				this.currentStep = i;
				break;
			}
		}
	};

	/**
	 * Get the identifier to check for the current step.
	 *
	 * @return {string}
	 */
	SequenceManager.prototype.identifierToCheck = function () {
		return this.sequence[ this.currentStep ].skipWithIdentifier;
	};

	/**
	 * Get the name of the banner to show on the current step, or null if this step has no
	 * banner.
	 *
	 * @return {string|null}
	 */
	SequenceManager.prototype.banner = function () {
		return this.sequence[ this.currentStep ].banner;
	};

	/**
	 * Skip to the next step and re-calculate current step and current page view. (Called
	 * when a flag to skip the current step has been found).
	 *
	 * @return {boolean} true if we successfully skipped to the next step, false if we've
	 *   already skipped all the steps in the sequence and can no longer show a step.
	 */
	SequenceManager.prototype.skipToNextStep = function () {

		this.stepsSkipped++;

		// Return false if we're out of steps
		if ( this.stepsSkipped === this.sequence.length ) {
			return false;
		}

		// Move one step ahead, wrapping around as needed
		this.currentStep = ( this.currentStep + 1 ) % this.sequence.length;
		this.currentPageView = this.stepStarts[ this.currentStep ];
		return true;
	};

	/**
	 * Process data as needed following a successful display of a banner (or no banner)
	 * within the sequence. Set this.nextPageView and, if needed, this.identifierToSet.
	 */
	SequenceManager.prototype.processPageView = function () {
		var nextStep = ( this.currentStep + 1 ) % this.sequence.length;

		this.nextPageView = ( this.currentPageView + 1 ) % this.seqTotalPageViews;

		// If we just finished a step, there might be an identifier to set
		if ( this.nextPageView === this.stepStarts[ nextStep ] ) {

			// If there is no identifier for this step, this will be null
			this.identifierToSet = this.sequence[ this.currentStep ].skipWithIdentifier;
		}
	};

	/**
	 * Get the current page view for this sequence, as stored in the browser.
	 * Note: The page view skips ahead if we skip a step because of an identifier, and
	 * rolls over when we re-start a sequence, so it's not an accurate count of page views
	 * in the campaign.
	 *
	 * @return {number}
	 */
	function getPageView() {
		return cn.kvStore.getItem(
			PAGE_VIEW_STORAGE_KEY,
			cn.kvStore.contexts.CAMPAIGN,
			multiStorageOption
		) || 0;
	}

	/**
	 * Store in the browser a new value for current page view in the sequence.
	 *
	 * @param {number} pageView
	 */
	function setPageView( pageView ) {

		cn.kvStore.setItem(
			PAGE_VIEW_STORAGE_KEY,
			pageView,
			cn.kvStore.contexts.CAMPAIGN,
			PAGE_VIEW_STORAGE_TTL,
			multiStorageOption
		);
	}

	/**
	 * Check if there is a flag with this identifier stored in the browser.
	 *
	 * @param {string} identifier
	 * @return {boolean}
	 */
	function getFlag( identifier ) {

		// Also check flags set by the large banner limit mixin...
		// TODO Refactor to unify code for setting flags for banners seen?
		return Boolean( cn.kvStore.getItem(
			FLAG_STORAGE_KEY + '_' + identifier,
			cn.kvStore.contexts.GLOBAL,
			multiStorageOption

		) ) || Boolean( cn.kvStore.getItem(
			LARGE_BANNER_LIMIT_STORAGE_KEY + '_' + identifier,
			cn.kvStore.contexts.GLOBAL,
			multiStorageOption
		) );
	}

	/**
	 * Store a flag with this identifier in the browser.
	 *
	 * @param {string} identifier
	 */
	function setFlag( identifier ) {

		// Value is timestamp for now. Flag expires as per the days mixin param.
		cn.kvStore.setItem(
			FLAG_STORAGE_KEY + '_' + identifier,
			Math.round( Date.now() / 1000 ),
			cn.kvStore.contexts.GLOBAL,
			days,
			multiStorageOption
		);
	}

	preBannerHandler = function ( mixinParams ) {

		var identifier, sequence, banner, pageView;

		// Campaign was already failed
		if ( cn.isCampaignFailed() ) {
			return;
		}

		// json parameters will be null if there was a decoding error server-side. In that
		// case, warn and bow out.
		// TODO Handle this on the server?
		if ( !mixinParams.sequences ) {
			mw.log.warn( 'Invalid sequences parameter received for banner sequence' );
			cn.failCampaign( 'jsonParamError' );
			return;
		}

		// Determine if, and if so, how we can store data in the browser. This will try
		// to use localStorage and will fall back to cookies if the campaign's category
		// allows it.
		multiStorageOption = cn.kvStore.getMultiStorageOption(
			cn.getDataProperty( 'campaignCategoryUsesLegacy' ) );

		// If there are no options for storing stuff, hide banner and bow out
		if ( multiStorageOption === cn.kvStore.multiStorageOptions.NO_STORAGE ) {
			cn.failCampaign( 'noStorage' );
			return;
		}

		days = mixinParams.days;
		sequence = mixinParams.sequences[ cn.getDataProperty( 'reducedBucket' ) ];

		// Get current page view, resetting if requested (for testing)
		// TODO Include a way to clear or ignore identifiers, too?
		pageView = mw.util.getParamValue( 'reset' ) === '1' ? 0 : getPageView();

		// Set up the sequence manager with the sequence for this bucket and the current
		// page view in the sequence
		sequenceManager = new SequenceManager( sequence, pageView );

		// Check for identifiers and skip steps, if necessary. Make no assumptions about
		// how many steps to skip.
		while ( true ) {
			identifier = sequenceManager.identifierToCheck();

			// If this step has no identifier, or there's no flag set with this
			// identifier, all good! Show the banner.
			if ( !identifier || !getFlag( identifier ) ) {
				break;
			}

			// Tell the sequence manager to skip to the next step. If we've already gone
			// through all the steps, don't show anything.
			if ( !sequenceManager.skipToNextStep() ) {
				cn.failCampaign( 'bannerSequenceAllStepsSkipped' );
				return;
			}
		}

		// See if there is a banner for this step
		banner = sequenceManager.banner();

		// banner is null if this is an empty step
		if ( banner === null ) {
			showingEmptyStep = true;
			cn.failCampaign( 'bannerSequenceEmptyStep' );
			return;
		}

		// Request the banner
		cn.requestBanner( banner );
	};

	postBannerOrFailHandler = function () {

		// If a banner was shown, or we showed no banner as part of an empty step, move to
		// the next page view in the sequence. If necessary, set a flag.
		if ( cn.isBannerShown() || showingEmptyStep ) {

			sequenceManager.processPageView();
			setPageView( sequenceManager.nextPageView );

			if ( sequenceManager.identifierToSet ) {
				setFlag( sequenceManager.identifierToSet );
			}
		}
	};

	// Register the handlers and mixin
	mixin.setPreBannerHandler( preBannerHandler );
	mixin.setPostBannerOrFailHandler( postBannerOrFailHandler );
	cn.registerCampaignMixin( mixin );

	// Exports are for use in unit tests only
	module.exports = { private: {
		SequenceManager: SequenceManager,
		PAGE_VIEW_STORAGE_KEY: PAGE_VIEW_STORAGE_KEY,
		FLAG_STORAGE_KEY: FLAG_STORAGE_KEY,
		LARGE_BANNER_LIMIT_STORAGE_KEY: LARGE_BANNER_LIMIT_STORAGE_KEY,
		preBannerHandler: preBannerHandler,
		postBannerOrFailHandler: postBannerOrFailHandler
	} };

}() );
