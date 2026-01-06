/**
 * Custom UI for banner sequence campaign mixin administration.
 *
 * Code layout and separation of concerns
 * --------------------------------------
 *
 * The code for this part of the UI roughly follows an MVC pattern. Here are the
 * components and rules for separation of concerns:
 *
 * - Controller (BannerSequenceUiController):
 *   - Controls most interactions among components and with other parts of the UI (managed
 *     by ext.centralNotice.adminUi.campaignManager). The main exceptions to this rule
 *     are: widgets notify the controller of updates, may request certain information from
 *     the controller, and manage their contained widgets.
 *   - The controller instantiates the main container widget.
 *   - It provides methods to handle changes to the model.
 *   - To act on smaller widgets contained within the main container widget, the
 *     controller either goes through the main container, or receives a reference to the
 *     contained widget.
 *
 * - Model (BannerSequenceUiModel):
 *   - The model is passive, and acts only on itself.
 *   - It is responsible for validation, providing default values, and updating the data.
 *
 * - View widgets (BannerSequenceWidget, BucketSeqContainerWidget, BucketSeqWidget,
 *   StepWidget):
 *   - Widgets receive a model on instantiation (either the full model, in the case of
 *     the main container widget, or submodels that correspond to the data they set).
 *   - Widgets provide updateFromModel() methods, in which they update their values, add
 *     or remove contained widgets as needed, and tell contained widgets to update their
 *     values.
 *   - Widgets can read from their models, but cannot alter on the models directly; to
 *     update the data, widgets go through the controller.
 *   - Widgets receive user interactions directly, manage their own state, display
 *     validation error messages, keep track of contained widgets, and handle events from
 *     contained widgets.
 *
 * Data structure for sequences
 * ----------------------------
 *
 * The same sequences data structure is used internally by BannerSequenceUiModel and
 * externally for the mixin parameters sent to the server and received by the
 * mixin's subscribing module, ext.centralNotice.bannerSequence. Here is the structure:
 *
 *   // Outer array; each element is a sequence for a bucket. The element's index
 *   // corresponds to the bucket number.
 *   [
 *
 *     // Inner arrays are sequences; each element corresponds to a step in the sequence.
 *     [
 *
 *       // The elements of the inner arrays are objects, whose properties control
 *       // the functioning of the step they represent.
 *       {
 *
 *         // {string} The name of the banner to display, or null to display no
 *         // banner during this step.
 *         'banner': 'TheNameOfABanner',
 *
 *         // {number} The number of page views to display this step.
 *         'numPageViews': 4,
 *
 *         // {string} An identifier to use for a flag in the browser. If a flag with the
 *         // identifier is present, the step will be skipped. If not, the step will be
 *         // shown, and when it completes, a flag with this identifier will be set. If
 *         // this property is null, the step will always show.
 *         'skipWithIdentifier': null
 *       }
 *     ]
 *   ]
 */

// TODO: Maybe on submit, we should verify that the data shown in the widgets is the same
// as what we're submitting? Given the complexity of this code, it's not inconceivable
// that a bug could cause different data to display than what be submitted.

( function () {

	let BannerSequenceUiController = null, BannerSequenceUiModel = null,
		BannerSequenceWidget = null, BucketSeqContainerWidget = null, BucketSeqWidget = null,
		StepWidget = null;
	const campaignManager = require( 'ext.centralNotice.adminUi.campaignManager' );

	/* BannerSequenceUiController */

	/**
	 * Singleton controller for the banner sequence administration UI.
	 *
	 * @class BannerSequenceUiController
	 * @constructor
	 */
	BannerSequenceUiController = function () {
		BannerSequenceUiController.super.call( this );
	};

	OO.inheritClass(
		BannerSequenceUiController,
		campaignManager.MixinCustomUiController
	);

	// This allows JS for the rest of the UI to find this class following registration
	// with campaignManager.mixinCustomUiControllerFactory
	BannerSequenceUiController.static.name = 'bannerSequence';

	/**
	 * Setup to be called immediately after instantiation.
	 *
	 * @param {Object} [data] Current banner sequence settings received from the server.
	 */
	BannerSequenceUiController.prototype.init = function ( data ) {

		// Don't assume that data has been provided
		data = data || {};

		// Facility for widgets to get a unique (within this page view) key, used for
		// tracking error states
		this.errorStateKeyAutoIncrement = 0;

		/**
		 * The data model
		 *
		 * @property {BannerSequenceUiModel}
		 */
		this.model = new BannerSequenceUiModel(
			data,
			campaignManager.getNumBuckets()
		);

		// On instantiation, the widget will create subwidgets in accordance with the model
		/**
		 * The enclosing view widget
		 *
		 * @property {BannerSequenceWidget}
		 */
		this.widget = new BannerSequenceWidget( this, this.model );

		/**
		 * Access point for the view widget's element
		 *
		 * @property {jQuery}
		 */
		this.$widgetElement = this.widget.$element;

		// Banners might no longer be valid if the mixin was enabled, disabled and
		// re-enabled, and banner assignments were modified while it was disabled (since
		// re-enabled mixins remember their previous settings).
		this.verifyAndFixBanners();

		// Set input fields for form submission (uses data from the model)
		this.setSequencesInputParam();
		this.setDaysInputParam();

		// Subscribe to external events
		campaignManager.eventBus.connect( this, {
			'bucket-change': this.onBucketChange
		} );

		campaignManager.eventBus.connect( this, {
			'assigned-banners-change': this.onAssignedBannersChange
		} );
	};

	/**
	 * Get the list of banners currently available for a bucket.
	 *
	 * @param {number} bucket
	 * @return {string[]}
	 */
	BannerSequenceUiController.prototype.getBannersForBucket = function ( bucket ) {
		return campaignManager.getAssignedBanners( bucket );
	};

	/**
	 * Get the human-readable alphabetic label for a bucket.
	 *
	 * @param {number} bucket
	 * @return {string}
	 */
	BannerSequenceUiController.prototype.getBucketLabel = function ( bucket ) {
		return campaignManager.getBucketLabel( bucket );
	};

	BannerSequenceUiController.prototype.onBucketChange = function ( numBuckets ) {
		this.model.updateNumBuckets( numBuckets );
		this.widget.updateFromModel();

		// The banners available for different buckets may have changed
		this.verifyAndFixBanners();
		this.setSequencesInputParam();
	};

	BannerSequenceUiController.prototype.onAssignedBannersChange = function () {
		this.verifyAndFixBanners();
		this.setSequencesInputParam();
	};

	/**
	 * Check that all banners currently set in sequence steps are available for the
	 * sequence's bucket. If any are not available, update the model and widgets and set
	 * errors accordingly.
	 */
	BannerSequenceUiController.prototype.verifyAndFixBanners = function () {

		let bucket, bannersForBucket, stepsWithMissingBanners;

		// Iterate over active buckets
		for ( bucket = 0; bucket < campaignManager.getNumBuckets(); bucket++ ) {

			bannersForBucket = this.getBannersForBucket( bucket );

			// Ask the model to check itself, and get an array of steps with issues
			stepsWithMissingBanners = this.model.verifyAndFixBannersForBucket(
				bucket,
				bannersForBucket
			);

			// If there were any problem steps in this bucket, tell the widget to update
			// and set error messages as needed
			if ( stepsWithMissingBanners.length > 0 ) {
				this.widget.updateFromModelForBucket( bucket );
				this.widget.setMissingBannerErrorsForBucket(
					bucket, stepsWithMissingBanners );
			}

			// Tell the widget to update the options in the banner drop-downs
			this.widget.updateBannersForDropdownsForBucket( bucket, bannersForBucket );
		}
	};

	/**
	 * Get a unique (within this page view) key, for tracking error states
	 *
	 * @return {string}
	 */
	BannerSequenceUiController.prototype.getErrorStateKey = function () {
		return String( this.errorStateKeyAutoIncrement++ );
	};

	/**
	 * Add a new step with default values at the end of the sequence for this bucket,
	 * and update the widget.
	 *
	 * @param {number} bucket
	 */
	BannerSequenceUiController.prototype.addStep = function ( bucket ) {
		this.model.addStep( bucket );
		this.widget.updateFromModelForBucket( bucket );
		this.setSequencesInputParam();
	};

	/**
	 * Remove the indicated step in the sequence for the indicated bucket, and update the
	 * widget.
	 *
	 * @param {number} bucket
	 * @param {number} stepNum
	 */
	BannerSequenceUiController.prototype.removeStep = function ( bucket, stepNum ) {
		this.model.removeStep( bucket, stepNum );
		this.setSequencesInputParam();
		this.widget.removeStepForBucket( bucket, stepNum );

		// Updating from model ensures widget state is all good (for example, add step
		// button state)
		this.widget.updateFromModelForBucket( bucket );
	};

	/**
	 * Set the global error state of the banner sequence controls. This is called when
	 * a widget error state changes. The ID provided is used in the ID for the event
	 * emitted (which will be used by a global error state tracker).
	 *
	 * @param {string} errorStateKey Unique key for this error state
	 * @param {boolean} state true sets an error for this key, and false clear it
	 */
	BannerSequenceUiController.prototype.setErrorState =
		function ( errorStateKey, state ) {
			// Broadcast an event to the rest of the UI
			campaignManager.eventBus.emit(
				'error-state',
				'banner-sequence-' + errorStateKey,
				state
			);
		};

	/**
	 * Move a step to a new location, within the sequence of the bucket indicated.
	 * Note: Does not update widgets from model, as it's not necessary.
	 *
	 * @param {number} bucket Bucket number
	 * @param {number} newStepNum Index at which to place the step, according to
	 *   how steps would be indexed before the step is removed from its current
	 *   location.
	 * @param {number} oldStepNum Current step index.
	 */
	BannerSequenceUiController.prototype.moveStepNoWidgetUpdate =
		function ( bucket, newStepNum, oldStepNum ) {
			this.model.moveStep( bucket, newStepNum, oldStepNum );
			this.setSequencesInputParam();
		};

	BannerSequenceUiController.prototype.setBanner =
		function ( bucket, stepNum, banner, stepWidget ) {
			this.model.setBanner( bucket, stepNum, banner );
			this.setSequencesInputParam();
			stepWidget.model = this.model.getBktSequences()[ bucket ][ stepNum ];
			stepWidget.updateFromModel();
		};

	BannerSequenceUiController.prototype.setNumPageViews =
		function ( bucket, stepNum, numPageViews, stepWidget ) {
			this.model.setNumPageViews( bucket, stepNum, numPageViews );
			this.setSequencesInputParam();
			stepWidget.model = this.model.getBktSequences()[ bucket ][ stepNum ];
			stepWidget.updateFromModel();
			this.widget.updateTotalPageViewsForBucket( bucket );
		};

	BannerSequenceUiController.prototype.setSkipWithIdentifier =
		function ( bucket, stepNum, skipWithIdentifier, stepWidget ) {
			this.model.setSkipWithIdentifier( bucket, stepNum, skipWithIdentifier );
			this.setSequencesInputParam();
			stepWidget.model = this.model.getBktSequences()[ bucket ][ stepNum ];
			stepWidget.updateFromModel();
		};

	BannerSequenceUiController.prototype.setDays = function ( days ) {
		this.model.setDays( days );
		this.setDaysInputParam();
	};

	BannerSequenceUiController.prototype.validateSkipWithIdentifier =
		function ( identifier ) {
			return this.model.validateSkipWithIdentifier( identifier );
		};

	BannerSequenceUiController.prototype.canAddAStep = function ( bucket ) {
		return this.model.canAddAStep( bucket );
	};

	BannerSequenceUiController.prototype.canRemoveAStep = function ( bucket ) {
		return this.model.canRemoveAStep( bucket );
	};

	BannerSequenceUiController.prototype.canMoveSteps = function ( bucket ) {
		return this.model.canMoveSteps( bucket );
	};

	/**
	 * Set the value of the hidden form input for the sequences mixin parameter
	 *
	 * @private
	 */
	BannerSequenceUiController.prototype.setSequencesInputParam = function () {
		this.setParam( 'sequences', this.model.sequencesAsJSON() );
	};

	/**
	 * Set the value of the hidden form input for the days mixin parameter
	 *
	 * @private
	 */
	BannerSequenceUiController.prototype.setDaysInputParam = function () {
		this.setParam( 'days', this.model.getDays() );
	};

	/* BannerSequenceUiModel */

	/**
	 * Singleton model for the banner sequence administration UI.
	 *
	 * @class BannerSequenceUiModel
	 * @constructor
	 * @param {Array} data Array of banner sequences, by bucket
	 * @param {number} numBuckets The number of buckets currently active (may be different
	 *   from the number of sequences in data).
	 */
	BannerSequenceUiModel = function ( data, numBuckets ) {

		// Initialize bucket sequences as a deep copy of data, or empty if not provided
		if ( data && data.sequences ) {

			// Even though validateAndFix() also checks for an array, we need to do so
			// now, too, before extending
			if ( Array.isArray( data.sequences ) ) {

				/**
				 * Sequences by bucket (index corresponds to bucket number)
				 *
				 * @property {Array}
				 */
				this.bucketSequences = $.extend( true, [], data.sequences );

			} else {
				mw.log.warn( 'Received banner sequence data is not an array.' );
				this.bucketSequences = [];
			}

		} else {
			this.bucketSequences = [];
		}

		// Number of days that identifiers to skip steps should last, or default
		if ( data && data.days ) {
			this.days = data.days;
		} else {
			this.days = this.constructor.static.DEFAULT_DAYS_PARAM;
		}

		// Validate and, if necessary, repair the data received
		this.validateAndFix();

		// Set the number of bucket and adjust data, if necessary.
		// (If no initial data was provided, this will create the correct number of
		// default sequences.)
		this.updateNumBuckets( numBuckets );
	};

	OO.initClass( BannerSequenceUiModel );

	// TODO Check this is the right number
	/**
	 * Maximum number of steps allowed in a sequence
	 *
	 * @private
	 * @static
	 */
	BannerSequenceUiModel.static.MAX_SEQ_STEPS = 20;

	/**
	 * Default duration of identifiers to skip steps (in days)
	 *
	 * @private
	 * @static
	 */
	BannerSequenceUiModel.static.DEFAULT_DAYS_PARAM = 250;

	/**
	 * Export the contents of the model as a JSON string.
	 *
	 * @return {string}
	 */
	BannerSequenceUiModel.prototype.sequencesAsJSON = function () {
		// Only export valid data
		this.validateAndFix();
		return JSON.stringify( this.getBktSequences() );
	};

	BannerSequenceUiModel.prototype.getDays = function () {
		return this.days;
	};

	BannerSequenceUiModel.prototype.setDays = function ( days ) {
		this.days = days;
	};

	BannerSequenceUiModel.prototype.updateNumBuckets = function ( numBuckets ) {

		/**
		 * Number of buckets in the model
		 *
		 * @property {number}
		 */
		this.numBuckets = numBuckets;

		// It's OK if there are more sequences in bucketSequences than there are active
		// buckets, since getBktSequences() outputs only sequences for active buckets.

		// Create default sequences as necessary
		for ( let i = 0; i < this.numBuckets; i++ ) {
			if ( !this.bucketSequences[ i ] ) {
				this.bucketSequences[ i ] = this.defaultBktSeq();
			}
		}
	};

	BannerSequenceUiModel.prototype.addStep = function ( bucket ) {
		const bucketSequence = this.bucketSequences[ bucket ];

		bucketSequence.push( this.defaultStep() );
	};

	BannerSequenceUiModel.prototype.setBanner = function ( bucket, stepNum, banner ) {
		this.bucketSequences[ bucket ][ stepNum ].banner = banner;
	};

	BannerSequenceUiModel.prototype.setNumPageViews =
		function ( bucket, stepNum, numPageViews ) {
			this.bucketSequences[ bucket ][ stepNum ].numPageViews = numPageViews;
		};

	BannerSequenceUiModel.prototype.setSkipWithIdentifier =
		function ( bucket, stepNum, skipWithIdentifier ) {
			this.bucketSequences[ bucket ][ stepNum ].skipWithIdentifier = skipWithIdentifier;
		};

	BannerSequenceUiModel.prototype.removeStep = function ( bucket, stepNum ) {
		this.bucketSequences[ bucket ].splice( stepNum, 1 );
	};

	/**
	 * Move a step to a new location, within the sequence of the bucket indicated.
	 *
	 * @param {number} bucket Bucket number
	 * @param {number} newStepNum Index at which to place the step, according to
	 *   how steps would be indexed before the step is removed from its current
	 *   location.
	 * @param {number} oldStepNum Current step index.
	 */
	BannerSequenceUiModel.prototype.moveStep =
		function ( bucket, newStepNum, oldStepNum ) {
			const step = this.bucketSequences[ bucket ][ oldStepNum ];

			newStepNum = ( newStepNum > oldStepNum ) ? newStepNum - 1 : newStepNum;

			this.bucketSequences[ bucket ].splice( oldStepNum, 1 );
			this.bucketSequences[ bucket ].splice( newStepNum, 0, step );
		};

	/**
	 * Get an array of sequences for all currently active buckets.
	 *
	 * @return {Object[]}
	 */
	BannerSequenceUiModel.prototype.getBktSequences = function () {
		// Sliced in case it has data on buckets that have been de-activated
		return this.bucketSequences.slice( 0, this.numBuckets );
	};

	BannerSequenceUiModel.prototype.canAddAStep = function ( bucket ) {
		return this.bucketSequences[ bucket ].length <
			this.constructor.static.MAX_SEQ_STEPS;
	};

	BannerSequenceUiModel.prototype.canRemoveAStep = function ( bucket ) {
		return ( this.bucketSequences[ bucket ].length > 1 );
	};

	BannerSequenceUiModel.prototype.canMoveSteps = function ( bucket ) {
		return ( this.bucketSequences[ bucket ].length > 1 );
	};

	/**
	 * @private
	 * @return {Object[]}
	 */
	BannerSequenceUiModel.prototype.defaultBktSeq = function () {
		return [ this.defaultStep() ];
	};

	/**
	 * @private
	 * @return {Object}
	 */
	BannerSequenceUiModel.prototype.defaultStep = function () {
		return {
			banner: this.defaultBanner(),
			numPageViews: this.defaultNumPageViews(),
			skipWithIdentifier: this.defaultSkipWithIdentifier()
		};
	};

	/**
	 * @private
	 * @return {Object|null}
	 */
	BannerSequenceUiModel.prototype.defaultBanner = function () {
		return null;
	};

	/**
	 * @private
	 * @return {number}
	 */
	BannerSequenceUiModel.prototype.defaultNumPageViews = function () {
		return 1;
	};

	/**
	 * @private
	 * @return {string|null}
	 */
	BannerSequenceUiModel.prototype.defaultSkipWithIdentifier = function () {
		return null;
	};

	/**
	 * Validate this.bucketSequences and replace any data that's invalid.
	 *
	 * @private
	 */
	BannerSequenceUiModel.prototype.validateAndFix = function () {
		// First, check for an array
		if ( !Array.isArray( this.bucketSequences ) ) {
			mw.log.warn( 'Bucket sequences should be an array.' );
			this.bucketSequences = [];
			return;
		}

		// Check the sequences in the array
		for ( let i = 0; i < this.bucketSequences.length; i++ ) {

			if ( !this.validateBktSeq( this.bucketSequences[ i ] ) ) {
				mw.log.warn( 'Invalid data in sequence for bucket ' + i );
				this.bucketSequences[ i ] = this.defaultBktSeq();
			}
		}

		// Check the days parameter
		if ( !this.validateDays( this.days ) ) {
			this.days = this.constructor.static.DEFAULT_DAYS_PARAM;
		}
	};

	/**
	 * @param {Array} seq
	 * @private
	 * @return {boolean}
	 */
	BannerSequenceUiModel.prototype.validateBktSeq = function ( seq ) {
		// Check size limits
		if ( !Array.isArray( seq ) ||
			( seq.length > this.constructor.static.MAX_SEQ_STEPS ) ||
			( seq.length < 1 ) ) {

			return false;
		}

		// Check the steps in the sequence
		for ( let i = 0; i < seq.length; i++ ) {
			if ( !this.validateStep( seq[ i ] ) ) {
				return false;
			}
		}

		return true;
	};

	/**
	 * @param {Object} step
	 * @private
	 * @return {boolean}
	 */
	BannerSequenceUiModel.prototype.validateStep = function ( step ) {
		const hasOwn = Object.prototype.hasOwnProperty;

		// Check the step object
		if ( ( step === null ) || ( typeof step !== 'object' ) ) {
			return false;
		}

		// Check that the properties exist and validate their values

		if ( !hasOwn.call( step, 'banner' ) || !this.validateBanner( step.banner ) ) {
			return false;
		}

		if ( !hasOwn.call( step, 'numPageViews' ) ||
				!this.validateNumPageViews( step.numPageViews ) ) {

			return false;
		}

		if ( !hasOwn.call( step, 'skipWithIdentifier' ) ||
			!this.validateSkipWithIdentifier( step.skipWithIdentifier ) ) {

			return false;
		}

		return true;
	};

	// Validation methods for individual fields are not marked private, since they might
	// be called by the controller for the benefit of widgets (though, in practice, this
	// only happens with validateSkipWithIdentifier()).

	BannerSequenceUiModel.prototype.validateBanner = function ( banner ) {
		// Note: regex should coordinate with Banner::isValidBannerName() in Banner.php
		return ( typeof banner === 'string' && /^[A-Za-z0-9_]{1,230}$/.test( banner ) ) ||
			banner === null;
	};

	BannerSequenceUiModel.prototype.validateNumPageViews = function ( numPageViews ) {
		return this.validateIntOneOrGreater( numPageViews );
	};

	BannerSequenceUiModel.prototype.validateSkipWithIdentifier = function ( id ) {
		return ( typeof id === 'string' && !id.includes( '|' ) ) || id === null;
	};

	BannerSequenceUiModel.prototype.validateDays = function ( days ) {
		return this.validateIntOneOrGreater( days );
	};

	/**
	 * @param {any} n
	 * @return {boolean}
	 * @private
	 */
	BannerSequenceUiModel.prototype.validateIntOneOrGreater = function ( n ) {
		return typeof n === 'number' &&
			isFinite( n ) &&
			Math.floor( n ) === n &&
			n > 0;
	};

	/**
	 * For all the steps in a sequence, check that any selected banners are included in
	 * the provided list of assigned banners. If a step's selected banner is not in the
	 * list, reset it to default and include the step's index in the returned array.
	 *
	 * @param {number} bucket The bucket whose sequence to check
	 * @param {string[]} assignedBanners An array of the names of banners assigned to
	 *   this bucket
	 * @return {number[]} An array with the indexes of steps whose selected banners were
	 *   not found in assignedBanners
	 */
	BannerSequenceUiModel.prototype.verifyAndFixBannersForBucket = function (
		bucket,
		assignedBanners
	) {
		const sequence = this.bucketSequences[ bucket ],
			stepsWithMissingBanners = [];

		for ( let i = 0; i < sequence.length; i++ ) {
			const banner = sequence[ i ].banner;

			if ( banner !== null && !assignedBanners.includes( banner ) ) {
				stepsWithMissingBanners.push( i );
				sequence[ i ].banner = this.defaultBanner();
			}
		}

		return stepsWithMissingBanners;
	};

	/**
	 * Global container widget for the banner sequence administration UI.
	 *
	 * @param {BannerSequenceUiController} controller
	 * @param {BannerSequenceUiModel} model
	 * @class BannerSequenceWidget
	 * @constructor
	 */
	BannerSequenceWidget = function ( controller, model ) {

		/**
		 * @property {BannerSequenceUiController}
		 */
		this.controller = controller;

		/**
		 * @property {BannerSequenceUiModel}
		 */
		this.model = model;

		// Call parent constructor
		BannerSequenceWidget.super.call( this, controller );

		// Set up days widget and field layout

		this.daysInput = new OO.ui.NumberInputWidget( {
			min: 1,
			isInteger: true,
			classes: [ 'centralNoticeBannerSeqDays' ]
		} );

		this.daysLayout = new OO.ui.FieldLayout( this.daysInput, {
			label: mw.message( 'centralnotice-banner-sequence-days' ).text(),
			align: 'left',
			classes: [ 'centralNoticeBannerSeqDaysLayout' ]
		} );

		// Prepend help text and days input so they come before $group, in reverse order
		this.$element.prepend(
			$( '<div>' )
				.addClass( 'htmlform-help' )
				.text( mw.message( 'centralnotice-banner-sequence-days-help' ).text() )
		);

		this.$element.prepend( this.daysLayout.$element );

		// Append help text for sequences, so it comes after $group
		this.$element.append(
			$( '<div>' )
				.addClass( 'centralNoticeBannerSeqHelpContainer' )
				.append(
					$( '<div>' )
						.addClass( 'htmlform-help' )
						.text( mw.message( 'centralnotice-banner-sequence-detailed-help' ).text() )
				)
		);

		// Create widgets and set values based on data from the model
		this.updateFromModel();

		// Flag for suppressing some actions in change handlers when changes are not due to
		// user intervention
		this.updating = false;

		// Change handler for days
		this.daysInput.connect(
			this,
			{ change: function () {

				// TODO Create a bug requesting public getValidity() on NumberInputWidget
				// (same note below for numPageViewsInput in StepWidget)
				this.daysInput.input.getValidity().done( () => {

					// If the value passes validation, send it to the controller and
					// clear any errors

					if ( !this.updating ) {
						this.controller.setDays( parseInt( this.daysInput.getValue(), 10 ) );
					}

					this.daysLayout.setErrors( [] );
					this.controller.setErrorState( 'days', false );

				} ).fail( () => {

					// If the value fails validation, set errors

					this.daysLayout.setErrors( [
						mw.message( 'centralnotice-banner-sequence-days-error' ).text()
					] );

					this.controller.setErrorState( 'days', true );

				} );
			} }
		);
	};

	OO.inheritClass( BannerSequenceWidget, campaignManager.MixinCustomWidget );

	/**
	 * Add or remove contained widgets, and update their values, based on the model.
	 */
	BannerSequenceWidget.prototype.updateFromModel = function () {
		const numSequences = this.model.getBktSequences().length;

		this.updating = true;

		// Ensure BucketSeqContainerWidget widgets with correct values
		for ( let b = 0; b < numSequences; b++ ) {
			this.updateFromModelForBucket( b );
		}

		// Remove inactive bucket widgets
		if ( this.getItemCount() > numSequences ) {
			this.removeItems( this.getItems().slice( numSequences ) );
		}

		// Update days input
		this.daysInput.setValue( String( this.model.getDays() ) );

		this.updating = false;
	};

	/**
	 * Possibly create a widget, and update its values, based on the model, for the
	 * sequence for a specific bucket.
	 *
	 * @param {number} bucket
	 */
	BannerSequenceWidget.prototype.updateFromModelForBucket = function ( bucket ) {

		const sequence = this.model.getBktSequences()[ bucket ],
			seqContainer = this.findItemFromData( bucket );

		// If we don't have a sequence container for this bucket it, create it
		if ( !seqContainer ) {
			this.addItems(
				[ new BucketSeqContainerWidget(
					this.controller,

					// The model for the sequence container is just the array of step, as
					// provided by the general model
					sequence,
					{ data: bucket }
				) ],
				bucket
			);

		} else {

			// If there already was a sequence container, reset its model and tell it to
			// update
			seqContainer.model = sequence;
			seqContainer.updateFromModel();
		}
	};

	/**
	 * Tell the sequence container for a bucket to remove a step
	 *
	 * @param {Object} bucket
	 * @param {number} stepNum
	 */
	BannerSequenceWidget.prototype.removeStepForBucket = function ( bucket, stepNum ) {
		const seqContainerWidget = this.findItemFromData( bucket );

		seqContainerWidget.removeStep( stepNum );
	};

	/**
	 * Tell the sequence container for a bucket to set missing banner errors for one or
	 * more steps.
	 *
	 * @param {number} bucket
	 * @param {number[]} stepsWithMissingBanners
	 */
	BannerSequenceWidget.prototype.setMissingBannerErrorsForBucket = function (
		bucket,
		stepsWithMissingBanners
	) {
		this.findItemFromData( bucket ).setMissingBannerErrors( stepsWithMissingBanners );
	};

	/**
	 * Tell the sequence container for a bucket to update banners in drop-down inputs.
	 *
	 * @param {Object} bucket
	 * @param {string[]} banners
	 */
	BannerSequenceWidget.prototype.updateBannersForDropdownsForBucket = function (
		bucket,
		banners
	) {
		this.findItemFromData( bucket ).updateBannersForDropdowns( banners );
	};

	/**
	 * Tell the sequence container for a bucket to re-calculate total page views in the
	 * sequence.
	 *
	 * @param {Object} bucket
	 */
	BannerSequenceWidget.prototype.updateTotalPageViewsForBucket = function ( bucket ) {
		this.findItemFromData( bucket ).updateTotalPageViews();
	};

	/* BucketSeqContainerWidget */

	/**
	 * Container widget for a sequence for a bucket and related controls (heading and add
	 * step button).
	 *
	 * @param {BannerSequenceUiController} controller
	 * @param {BannerSequenceUiModel} model
	 * @param {Object} config
	 * @class BucketSeqContainerWidget
	 * @constructor
	 */
	BucketSeqContainerWidget = function ( controller, model, config ) {

		// Properties
		this.controller = controller;
		this.model = model;
		this.bucket = config.data;
		this.totalPageViews = null;

		// This is a contained widget that has only the sequence. It is lightweight; its
		// state and model are managed from BucketSeqContainerWidget.
		this.bucketSeqWidget = new BucketSeqWidget();

		// Add step button
		this.addStepButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'centralnotice-banner-sequence-bucket-add-step' ).text(),
			icon: 'add'
		} );

		// Heading text
		this.$heading = $( '<div>' )
			.addClass( 'centralNoticeBannerSeqBucketSeqTitle' );

		// Add stuff to config before calling parent constructor
		config = Object.assign( {}, config, {
			$content: this.$heading,
			classes: [ 'centralNoticeBannerSeqBucketSeqContainer' ]
		} );

		// Call parent constructor
		BucketSeqContainerWidget.super.call( this, config );

		// Call mixin constructor
		OO.ui.mixin.GroupElement.call(
			this, Object.assign( {}, config, { $group: this.$element } ) );

		// Add widgets
		this.addItems( [ this.bucketSeqWidget, this.addStepButton ] );

		// Add or remove steps, and set their values, based on the model
		this.updateFromModel();

		// Handle reordering of steps (via drag-and-drop)
		this.bucketSeqWidget.connect(
			this,
			{ reorder: function ( item, newStepNum ) {

				this.controller.moveStepNoWidgetUpdate(
					this.bucket, newStepNum, item.getData() );

				// Reset step widget indexes
				this.updateStepNumbers();
			} }
		);

		// Handle clicks on add step button
		this.addStepButton.connect(
			this,
			{ click: function () {
				this.controller.addStep( this.bucket );
			} }
		);
	};

	OO.inheritClass( BucketSeqContainerWidget, OO.ui.Widget );
	OO.mixinClass( BucketSeqContainerWidget, OO.ui.mixin.GroupElement );

	/**
	 * Add or remove steps, and update their values, based on the model.
	 */
	BucketSeqContainerWidget.prototype.updateFromModel = function () {
		// Go through steps in model, adding or updating widgets as needed
		for ( let i = 0; i < this.model.length; i++ ) {
			const stepModel = this.model[ i ];
			const stepWidget = this.bucketSeqWidget.findItemFromData( i );

			if ( !stepWidget ) {
				this.addStepWidget( stepModel, i );
			} else {
				stepWidget.model = stepModel;
				stepWidget.setData( i );
				stepWidget.updateFromModel();
			}
		}

		// Remove unneeded step widgets
		if ( this.bucketSeqWidget.getItemCount() > this.model.length ) {
			this.bucketSeqWidget.removeItems(
				this.bucketSeqWidget.getItems().slice( this.model.length ) );
		}

		// Enable or disable add step button as needed
		this.updateAddStepButtonState();

		// Update total pageViews data and text in UI
		this.updateTotalPageViews();
	};

	/**
	 * Add a new step widget with the specified step model and index
	 *
	 * @param {Object} stepModel
	 * @param {number} index
	 */
	BucketSeqContainerWidget.prototype.addStepWidget = function ( stepModel, index ) {

		const stepWidget = new StepWidget(
			this.controller,
			stepModel,
			this.bucket,
			{ data: index }
		);

		this.bucketSeqWidget.addItems( [ stepWidget ],
			index
		);
	};

	/**
	 * Reset the index numbers of the step widgets based on current widget order. (This is
	 * called following drag-and-drop or the removal of a step.)
	 */
	BucketSeqContainerWidget.prototype.updateStepNumbers = function () {
		// widgets are expected to be provided here in the correct order
		const stepWidgets = this.bucketSeqWidget.getItems();

		for ( let i = 0; i < stepWidgets.length; i++ ) {
			stepWidgets[ i ].setData( i );
		}
	};

	BucketSeqContainerWidget.prototype.updateAddStepButtonState = function () {
		this.addStepButton.setDisabled( !this.controller.canAddAStep( this.bucket ) );
	};

	/**
	 * Update heading text with current total page views in the sequence
	 */
	BucketSeqContainerWidget.prototype.updateHeadingText = function () {

		this.$heading.text( mw.message(
			'centralnotice-banner-sequence-bucket-seq',
			this.controller.getBucketLabel( this.bucket ),
			this.totalPageViews
		).text() );
	};

	BucketSeqContainerWidget.prototype.removeStep = function ( stepNum ) {

		const stepWidget = this.bucketSeqWidget.findItemFromData( stepNum );

		stepWidget.clearFromGeneralErrorState();
		this.bucketSeqWidget.removeItems( [ stepWidget ] );

		// Reset the step numbers
		this.updateStepNumbers();
	};

	/**
	 * Set missing banner errors for one or more steps in the sequence
	 *
	 * @param {number[]} stepsWithMissingBanners
	 */
	BucketSeqContainerWidget.prototype.setMissingBannerErrors = function (
		stepsWithMissingBanners
	) {
		for ( let i = 0; i < stepsWithMissingBanners.length; i++ ) {

			this.bucketSeqWidget
				.findItemFromData( stepsWithMissingBanners[ i ] )
				.setMissingBannerError( true );
		}
	};

	/**
	 * Update banners shown in drop-down input menus.
	 *
	 * @param {string[]} banners
	 */
	BucketSeqContainerWidget.prototype.updateBannersForDropdowns = function ( banners ) {
		const stepWidgets = this.bucketSeqWidget.getItems();

		for ( let i = 0; i < stepWidgets.length; i++ ) {
			stepWidgets[ i ].updatebannersDropdown( banners );
		}
	};

	/**
	 * Re-calculate total page views in the sequence.
	 */
	BucketSeqContainerWidget.prototype.updateTotalPageViews = function () {
		this.totalPageViews = 0;

		for ( let i = 0; i < this.model.length; i++ ) {
			this.totalPageViews += this.model[ i ].numPageViews;
		}

		this.updateHeadingText();
	};

	/* BucketSeqWidget */

	/**
	 * Widget for a sequence (only the steps, not the add step button or heading)
	 * Note: this widget is manipulated by BucketSeqContainerWidget, which has most of the
	 * related logic.
	 *
	 * @class BucketSeqWidget
	 * @constructor
	 */
	BucketSeqWidget = function () {

		const config = { classes: [ 'centralNoticeBannerSeqSequenceContainer' ] };

		// Call parent constructor
		BucketSeqWidget.super.call( this, config );

		// Call mixin constructor
		OO.ui.mixin.DraggableGroupElement.call(
			this, Object.assign( {}, config, { $group: this.$element } ) );
	};

	OO.inheritClass( BucketSeqWidget, OO.ui.Widget );
	OO.mixinClass( BucketSeqWidget, OO.ui.mixin.DraggableGroupElement );

	/* StepWidget */

	// TODO: Fix vertical alignment of error messages below inputs in StepWidget

	/**
	 * Widget for a step in a sequence.
	 *
	 * @param {BannerSequenceUiController} controller
	 * @param {Object} model
	 * @param {Object} bucket
	 * @param {Object} config
	 * @class StepWidget
	 * @constructor
	 */
	StepWidget = function ( controller, model, bucket, config ) {
		this.controller = controller;
		this.model = model;
		this.bucket = bucket;

		// Flag for suppressing some actions in event handlers when changes to widgets
		// are not due to user intervention.
		this.updating = false;

		// Tracks validation errors for the contained inputs
		this.errorStateTracker = new campaignManager.ErrorStateTracker();

		// A unique key for tracking error states (outside this widget)
		this.errorStateKey = 'step-widget-' + this.controller.getErrorStateKey();

		config = Object.assign( {}, config, { classes: [ 'centralNoticeBannerSeqStep' ] } );

		// Call parent constructor
		StepWidget.super.call( this, config );

		// Set drag handle
		// Note: Adding the oo-uiwidget class is a hack, apparently needed due to an issue
		// in OOUI styles.
		// TODO Check this out; possible mistaken change to DraggableElement.less in
		// f6be5b2f1f0ef67ab0efeaa25568976587435d95 ?
		this.$handle = $( '<div>' ).addClass(
			'centralNoticeBannerSeqStepHandle oo-ui-widget' );

		this.$group.append( this.$handle );

		// Draggable mixin constructor
		OO.ui.mixin.DraggableElement.call(
			this, Object.assign( {}, config, { $handle: this.$handle } ) );

		// Create the widgets

		const dropMenuItems = this.makeDropMenuItems(
			controller.getBannersForBucket( this.bucket ) );

		this.bannersDropdown = new OO.ui.DropdownWidget( {
			menu: { items: dropMenuItems },
			classes: [ 'centralNoticeBannerSeqBanner' ]
		} );

		this.numPageViewsInput = new OO.ui.NumberInputWidget( {
			min: 1,
			isInteger: true,
			classes: [ 'centralNoticeBannerSeqPageViews' ]
		} );

		this.skipWithIdInput = new OO.ui.TextInputWidget( {
			classes: [ 'centralNoticeBannerSeqIdentifier' ],
			validate: function ( val ) {
				return this.controller.validateSkipWithIdentifier( val );
			}.bind( this )
		} );

		this.removeBtn = new OO.ui.ButtonWidget( {
			icon: 'trash',
			flags: 'destructive'
		} );

		// Field layouts

		this.bannerFieldLayout = new OO.ui.FieldLayout( this.bannersDropdown, {
			label: mw.message( 'centralnotice-banner-sequence-banner' ).text(),
			align: 'top',
			classes: [ 'centralNoticeBannerSeqBannerLayout' ]
		} );

		this.numPageViewsFieldLayout = new OO.ui.FieldLayout( this.numPageViewsInput, {
			label: mw.message( 'centralnotice-banner-sequence-page-views' ).text(),
			align: 'top',
			classes: [ 'centralNoticeBannerSeqPageViewsLayout' ]
		} );

		this.skipWithIdFieldLayout = new OO.ui.FieldLayout( this.skipWithIdInput, {
			label: mw.message( 'centralnotice-banner-sequence-skip-with-id' ).text(),
			align: 'top',
			classes: [ 'centralNoticeBannerSeqIdentifierLayout' ]
		} );

		this.hLayout = new OO.ui.HorizontalLayout( {
			items: [
				this.bannerFieldLayout,
				this.numPageViewsFieldLayout,
				this.skipWithIdFieldLayout,
				new OO.ui.FieldLayout( this.removeBtn, {
					label: ' ', // Blank label for consistent vertical alignment
					align: 'top'
				} )
			],
			classes: [ 'centralNoticeBannerSeqStepLayout' ]
		} );

		this.addItems( [ this.hLayout ] );

		// Set input values with data from the model
		this.updateFromModel();

		// Change handler for banner dropdown
		this.bannersDropdown.getMenu().connect(
			this,
			{ choose: function ( menuItem ) {
				let val;

				if ( !this.updating ) {
					val = menuItem.getData();

					this.controller.setBanner(
						this.bucket,
						this.getData(), // step number
						val !== this.constructor.static.NO_BANNER_FLAG ? val : null,
						this
					);

					this.setMissingBannerError( false );
				}
			} }
		);

		// Change handler for input for number of pageViews
		this.numPageViewsInput.connect(
			this,
			{ change: function () {

				// TODO Bug for public getValidity() on NumberInputWidget
				// (same issue above for daysInput in BannerSequenceWidget)
				this.numPageViewsInput.input.getValidity().done( () => {

					// If the value passes validation, send it to the controller and
					// clear any errors

					if ( !this.updating ) {
						this.controller.setNumPageViews(
							this.bucket,
							this.getData(), // step number
							parseInt( this.numPageViewsInput.getValue(), 10 ),
							this
						);
					}

					this.numPageViewsFieldLayout.setErrors( [] );
					this.setErrorState( 'numPageViews', false );

				} ).fail( () => {

					// If the value fails validation, set errors

					this.numPageViewsFieldLayout.setErrors( [
						mw.message( 'centralnotice-banner-sequence-page-views-error' ).text()
					] );

					this.setErrorState( 'numPageViews', true );

				} );
			} }
		);

		// Change handler for skip with id input
		this.skipWithIdInput.connect(
			this,
			{ change: function () {

				this.skipWithIdInput.getValidity().done( () => {

					// If the value passes validation, send it to the controller and
					// clear any errors

					if ( !this.updating ) {
						this.controller.setSkipWithIdentifier(
							this.bucket,
							this.getData(), // step number
							this.skipWithIdInput.getValue() || null,
							this
						);
					}

					this.skipWithIdFieldLayout.setErrors( [] );
					this.setErrorState( 'skipWithId', false );

				} ).fail( () => {

					// If the value fails validation, set errors

					this.skipWithIdFieldLayout.setErrors( [
						mw.message( 'centralnotice-banner-sequence-skip-with-id-error' ).text()
					] );

					this.setErrorState( 'skipWithId', true );

				} );
			} }
		);

		// Blur handler for skip with id input, to trim value on blur

		// TextInputWidget doesn't natively expose a blur event; this is a hack.
		// TODO Make a task requesting that feature.

		this.skipWithIdInput.$input.on( 'blur', () => {
			this.skipWithIdInput.setValue( this.skipWithIdInput.getValue().trim() );
		} );

		// Click handler for remove step button
		this.removeBtn.connect(
			this,
			{ click: function () {
				this.controller.removeStep(
					this.bucket,
					this.getData() // step number
				);
			} }
		);
	};

	OO.inheritClass( StepWidget, OO.ui.FieldsetLayout );
	OO.mixinClass( StepWidget, OO.ui.mixin.DraggableElement );

	/**
	 * Flag to indicate no banner should be shown on this step. Note: This flag is only
	 * used in widgets. To signal a step with no banner in the data model, null is used.
	 */
	StepWidget.static.NO_BANNER_FLAG = -1;

	/**
	 * Update input values with data from the model.
	 */
	StepWidget.prototype.updateFromModel = function () {

		this.updating = true;

		// Set input values (if the step doesn't have an error state)
		if ( !this.hasErrorState() ) {
			this.bannersDropdown.getMenu().selectItemByData(
				this.model.banner || this.constructor.static.NO_BANNER_FLAG
			);

			this.numPageViewsInput.setValue( String( this.model.numPageViews ) );
			this.skipWithIdInput.setValue( String( this.model.skipWithIdentifier || '' ) );
		}

		// Enable/disable step removal and dragging
		this.removeBtn.setDisabled( !this.controller.canRemoveAStep( this.bucket ) );

		if ( this.controller.canMoveSteps( this.bucket ) ) {
			this.toggleDraggable( true );
			this.$handle.addClass( 'centralNoticeBannerSeqStepHandleEnabled' );
			this.$handle.removeClass( 'centralNoticeBannerSeqStepHandleDisabled' );

		} else {
			this.toggleDraggable( false );
			this.$handle.addClass( 'centralNoticeBannerSeqStepHandleDisabled' );
			this.$handle.removeClass( 'centralNoticeBannerSeqStepHandleEnabled' );
		}

		this.updating = false;
	};

	/**
	 * Set a banner missing error state for the banner drop-down
	 *
	 * @param {boolean} state true to set an error, false to clear one
	 */
	StepWidget.prototype.setMissingBannerError = function ( state ) {

		if ( state ) {
			this.bannerFieldLayout.setErrors( [
				mw.message( 'centralnotice-banner-sequence-banner-removed-error' ).text()
			] );

		} else {
			this.bannerFieldLayout.setErrors( [] );
		}

		this.setErrorState( 'missingBanner', state );
	};

	/**
	 * Refresh the list of banners shown in the banner drop-down menu
	 *
	 * @param {string[]} banners Names of banners available for this bucket
	 */
	StepWidget.prototype.updatebannersDropdown = function ( banners ) {

		const dropdownMenu = this.bannersDropdown.getMenu(),
			selectedItem = dropdownMenu.findSelectedItem(),
			selectedBanner = selectedItem ? selectedItem.getData() : null;

		this.updating = true;

		// Clear and re-create the list of banners, and restore the previous selection,
		// if there was one
		dropdownMenu.clearItems();
		dropdownMenu.addItems( this.makeDropMenuItems( banners ) );
		if ( selectedItem ) {
			dropdownMenu.selectItemByData( selectedBanner );
		}

		this.updating = false;
	};

	/**
	 * Determine whether any inputs have validation errors
	 *
	 * @return {boolean} true if one or more input has a validation error, false
	 *   otherwise
	 */
	StepWidget.prototype.hasErrorState = function () {
		return this.errorStateTracker.hasErrorState();
	};

	/**
	 * Tell the controller to clear any error states set for this widget (called when
	 * the step is removed).
	 */
	StepWidget.prototype.clearFromGeneralErrorState = function () {
		this.controller.setErrorState( this.errorStateKey, false );
	};

	/**
	 * @param {string[]} banners
	 * @return {OO.ui.MenuOptionWidget[]}
	 * @private
	 */
	StepWidget.prototype.makeDropMenuItems = function ( banners ) {
		const dropdownMenuItems = [];

		// First option is always no banner
		dropdownMenuItems.push( new OO.ui.MenuOptionWidget( {
			data: this.constructor.static.NO_BANNER_FLAG,
			label: mw.message( 'centralnotice-banner-sequence-no-banner' ).text()
		} ) );

		for ( let i = 0; i < banners.length; i++ ) {
			dropdownMenuItems.push( new OO.ui.MenuOptionWidget( {
				data: banners[ i ],
				label: banners[ i ]
			} ) );
		}

		return dropdownMenuItems;
	};

	/**
	 * @private
	 * @param {string} errorStateKey A local key (unique only within this StepWidget
	 * instance, to distinguish controls with an error state)
	 * @param {boolean} state True when an error exists, false otherwise
	 */
	StepWidget.prototype.setErrorState = function ( errorStateKey, state ) {
		this.errorStateTracker.setErrorState( errorStateKey, state );

		// Tell the controller about this step widget's overall error state
		this.controller.setErrorState( this.errorStateKey, this.hasErrorState() );

		// Update widget styles
		this.updateErrorStateStyle();
	};

	/**
	 * @private
	 */
	StepWidget.prototype.updateErrorStateStyle = function () {

		this.$element.toggleClass(
			'centralNoticeBannerSeqStepError',
			this.hasErrorState()
		);
	};

	/* General setup */

	// Register controller class with factory
	campaignManager.mixinCustomUiControllerFactory.register( BannerSequenceUiController );

}() );
