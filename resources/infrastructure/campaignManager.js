/**
 * JS for campaign editor (handled by Special:CentralNotice)
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 */
( function () {
	var stepSize = 1,
		mixinDefs = mw.config.get( 'wgCentralNoticeCampaignMixins' ),
		mixinParamsTemplate = mw.template.get(
			'ext.centralNotice.adminUi.campaignManager',
			'campaignMixinParamControls.mustache'
		),
		$form, $submitBtn,
		MixinCustomUiController, MixinCustomWidget,
		ErrorStateTracker,
		mixinCustomUiControllerFactory = new OO.Factory(),
		errorStateTracker, eventBus, assignedBanners,
		BUCKET_LABELS = [ 'A', 'B', 'C', 'D' ]; // TODO Fix for configs with more buckets

	/* Event bus */

	/**
	 * eventBus: A simple object for subscribing to and emitting events.
	 */

	/**
	 * @event bucket-change
	 *
	 * The control for the number of buckets in the campaign has changed.
	 * @param {number} numBuckets
	 */

	/**
	 * @event assigned-banners-change
	 *
	 * The controls for banner assignment (bucket assignments and banner removal
	 * checkboxes) have changed. Note: This event does not fire when a checkbox for
	 * adding a banner is checked.
	 */
	/**
	 * @event error-state
	 *
	 * An error state is set or removed
	 * @param {string} errorKey
	 * @param {boolean} state true to set error, false to clear one.
	 */
	eventBus = new OO.EventEmitter();

	/* MixinCustomUiController */

	// Note: the following code uses two completely distinct meanings of
	// "mixin". One is "campaign mixin", bits of JS code that can run in the
	// browsers of users in specific campaigns. The other is the OOjs concept
	// of mixin, that is, a bit of functionality that can be added to a
	// javascript class. For example, the MixinCustomWidget class provides a
	// bit of UI for a campaign mixin, and it mixes in, in the OOjs sense,
	// functionality from OO.ui.mixin.GroupElement.

	/**
	 * Base class for custom campaign mixin UI controllers.
	 *
	 * Provides facilities for setting hidden form input elements for mixin parameter
	 * values. This lets custom mixins provide interactive interfaces that are not input
	 * elements, and send data to the server via these hidden inputs.
	 *
	 * Note: Subclasses are expected to be singletons.
	 *
	 * @abstract
	 * @class MixinCustomUiController
	 * @constructor
	 */
	MixinCustomUiController = function () {

		// Declare the abstract property here, but don't force it to null, in case the
		// subclass decides to set it before calling the constructor.
		/**
		 * The element of the corresponding MixinCustomWidget.
		 *
		 * @abstract
		 * @property {jQuery}
		 */
		this.$widgetElement = this.$widgetElement || null;
	};

	OO.initClass( MixinCustomUiController );

	/**
	 * The name of the campaign mixin that this control group sets the parameters for.
	 *
	 * @abstract
	 * @inheritable
	 * @static
	 * @property {string}
	 */
	MixinCustomUiController.static.name = null;

	/**
	 * Initialize the controller with the data provided.
	 *
	 * @method
	 * @abstract
	 * @param {Object} data Object in which properties and their values are mixin
	 *   parameter names and values. Format should coordinate with the format sent
	 *   to the client via the 'mixin-param-values' data value on the checkbox that
	 *   enables the mixin.
	 */
	MixinCustomUiController.prototype.init = null;

	/**
	 * Set the (string-encoded) value of a mixin parameter via a hidden input element.
	 *
	 * @param {string} name The name of the parameter.
	 * @param {string} value The value (formatted as appropriate for form submission).
	 */
	MixinCustomUiController.prototype.setParam = function ( name, value ) {
		var $input = this.getParamInputEl( name, true );

		$input.val( value );
	};

	/**
	 * Remove a mixin parameter's hidden input element, if it exists.
	 *
	 * @param {string} name The name of the parameter
	 */
	MixinCustomUiController.prototype.removeParam = function ( name ) {
		var $input = this.getParamInputEl( name, false );

		if ( $input.length ) {
			$input.remove();
		}
	};

	/**
	 * Get the hidden input element for a mixin parameter, if it exists. If requested,
	 * create it if it doesn't exist.
	 *
	 * @private
	 * @param {string} name The name of the parameter
	 * @param {boolean} create Create the element if it doesn't exist
	 * @return {jQuery|null}
	 */
	MixinCustomUiController.prototype.getParamInputEl = function ( name, create ) {
		var inputName = makeNoticeMixinControlName( this.constructor.static.name, name ),
			$input = $form.find( 'input[name="' + inputName + '"]' );

		if ( create && !( $input.length ) ) {

			$input = $( '<input>' ).attr( {
				name: inputName,
				type: 'hidden'
			} );

			$form.append( $input );
		}

		return $input;
	};

	/* MixinCustomWidget */

	/**
	 * Base class for custom campaign mixin widgets.
	 *
	 * @abstract
	 * @class MixinCustomWidget
	 * @extends OO.ui.Widget
	 * @mixes OO.ui.mixin.GroupWidget
	 * @constructor
	 *
	 * @param {MixinCustomUiController} controller
	 * @param {Object} [config] Configuration options
	 */
	MixinCustomWidget = function ( controller, config ) {

		var $element = $( '<fieldset>' ),
			$group = $( '<div>' );

		// Set up config with elements, CSS class and id. This should coordinate with
		// makeMixinParamControlSet() (below) and
		// templates/campaignMixinParamControls.mustache (used for the automatic creation
		// of mixin param controls).
		config = $.extend( {
			$element: $element,

			// This works because controller classes are singletons.
			id: mixinParamControlsId( controller.constructor.static.name )
		}, config );

		$group.addClass( 'campaignMixinControls' );
		$element.append( $group );

		// Call parent constructor
		MixinCustomWidget.parent.call( this, config );

		// Call mixin constructor
		OO.ui.mixin.GroupElement.call(
			this,
			$.extend( {}, config, { $group: $group } )
		);
	};

	OO.inheritClass( MixinCustomWidget, OO.ui.Widget );
	OO.mixinClass( MixinCustomWidget, OO.ui.mixin.GroupElement );

	/* Event handlers (non-OOUI) and related logic */

	/**
	 * Simple object for keeping track of validation errors.
	 *
	 * @class ErrorStateTracker
	 * @constructor
	 */
	ErrorStateTracker = function () {
		this.errors = {};
	};

	/**
	 * Set or clear an error state.
	 *
	 * @param {string} errorKey A unique key identifying this error
	 * @param {boolean} state true sets an error for this key, and false clear it
	 */
	ErrorStateTracker.prototype.setErrorState = function ( errorKey, state ) {
		if ( state ) {
			this.errors[ errorKey ] = true;
		} else {
			delete this.errors[ errorKey ];
		}
	};

	/**
	 * Is one or more error currently set?
	 *
	 * @return {boolean}
	 */
	ErrorStateTracker.prototype.hasErrorState = function () {
		return Object.keys( this.errors ).length > 0;
	};

	// General error state tracker for the page
	errorStateTracker = new ErrorStateTracker();

	// Connect handler for error-state events
	eventBus.on( 'error-state', function ( errorKey, state ) {

		// Pass state on to errorStateTracker
		errorStateTracker.setErrorState( errorKey, state );

		// Update the submit button
		if ( errorStateTracker.hasErrorState() ) {
			$submitBtn.prop( 'disabled', true );
		} else {
			$submitBtn.prop( 'disabled', false );
		}
	} );

	function updateThrottle() {
		if ( $( '#throttle-enabled' ).prop( 'checked' ) ) {
			$( '.cn-throttle-amount' ).show();
		} else {
			$( '.cn-throttle-amount' ).hide();
		}
	}

	function updateWeightColumn() {
		if ( $( '#balanced' ).prop( 'checked' ) ) {
			$( '.cn-weight' ).hide();
		} else {
			$( '.cn-weight' ).show();
		}
	}

	function updateBuckets() {
		var i, isBucketDisabled,
			numBuckets = getNumBuckets(),
			maxNumBuckets = mw.config.get( 'wgNoticeNumberOfBuckets' ),
			$bucketSelectors = $( 'select.bucketSelector' ),

			$bucketSelectorUnassigned =
				$bucketSelectors.not( '.bucketSelectorForAssignedBanners' );

		// Change selected value of bucket selectors to only available buckets
		$bucketSelectors.each( function () {
			var $selector = $( this ),
				selectedVal = $selector.val();

			$selector.val( selectedVal % numBuckets );
		} );

		// If only one bucket is available, disable the selectors for unassigned banners,
		// and enable them if more than one bucket is available.

		// (If we disable selectors for assigned banners, then they won't send their
		// values when the form is submitted. In that case, if the number of buckets were
		// changed from a value greater than 1 to 1, the selectors would show all banners
		// on bucket 0, but for any banners that were moved to bucket 0, the change would
		// not be submitted to the server.)

		if ( numBuckets === 1 ) {
			$bucketSelectorUnassigned.prop( 'disabled', true );
		} else {
			$bucketSelectors.prop( 'disabled', false );
		}

		// Enable or disable bucket options in drop-downs, as appropriate
		for ( i = 0; i < maxNumBuckets; i++ ) {
			isBucketDisabled = ( i >= numBuckets );

			$bucketSelectors.find( 'option[value=' + i + ']' )
				.prop( 'disabled', isBucketDisabled );
		}

		// Broadcast bucket change event
		eventBus.emit( 'bucket-change', numBuckets );

		// It's important to update assigned banners *after* emitting bucket-change so
		// widgets first can adjust to the new bucket number.
		updateAssignedBanners();
	}

	function getNumBuckets() {
		return +$( 'select#buckets' ).val();
	}

	/**
	 * Hide or display campaign mixin parameter controls based on checkbox state.
	 * The mixin name and any existing parameter values are received as data
	 * properties on the checkbox.
	 */
	function showOrHideCampaignMixinControls() {

		var $checkBox = $( this ),
			mixinName = $checkBox.data( 'mixin-name' ),
			paramValues,
			$paramControlSet = $( '#' + mixinParamControlsId( mixinName ) ),
			mixinCustomUiController, $paramControls;

		if ( $checkBox.prop( 'checked' ) ) {

			// If the controls don't exist yet, create them
			if ( $paramControlSet.length === 0 ) {

				paramValues = $checkBox.data( 'mixin-param-values' );

				// If the mixin uses a custom UI to set params, instantiate that
				if ( mixinDefs[ mixinName ].customAdminUIControlsModule ) {

					mixinCustomUiController = mixinCustomUiControllerFactory
						.create( mixinName );

					mixinCustomUiController.init( paramValues );
					$paramControlSet = mixinCustomUiController.$widgetElement;

				} else {

					// Otherwise, create generic controls using a template
					$paramControlSet = makeMixinParamControlSet(
						mixinName,
						paramValues
					);

					// Hook up handler for verification
					$paramControls = $paramControlSet.find( 'input' );
					$paramControls.on(
						'keyup keydown change mouseup cut paste focus blur',
						mw.util.debounce( 100, verifyParamControl )
					);
				}

				// Attach the controls
				$checkBox.parent( 'div' ).append( $paramControlSet );

			} else {
				$paramControlSet.show();
			}
		} else if ( $paramControlSet.length !== 0 ) {
			$paramControlSet.hide();
		}
	}

	function mixinParamControlsId( mixinName ) {
		return 'notice-mixin-' + mixinName + '-paramControls';
	}

	function makeNoticeMixinControlName( mixinName, paramName ) {
		return 'notice-mixin-' + mixinName + '-' + paramName;
	}

	function makeMixinParamControlSet( mixinName, paramValues ) {

		var paramDefs = mixinDefs[ mixinName ].parameters,
			templateVars = {
				divId: mixinParamControlsId( mixinName ),
				params: []
			};

		paramValues = paramValues || {};

		// eslint-disable-next-line no-jquery/no-each-util
		$.each( paramDefs, function ( paramName, paramDef ) {

			var paramTemplateVars = {
				// eslint-disable-next-line mediawiki/msg-doc
				labelMsg: mw.message( paramDef.labelMsg ).text(),
				inputName: makeNoticeMixinControlName( mixinName, paramName ),
				dataType: paramDef.type,
				minVal: paramDef.minVal,
				maxVal: paramDef.maxVal,
				step: paramDef.step
			};

			switch ( paramDef.type ) {
				case 'string':
					paramTemplateVars.inputType = 'text';
					paramTemplateVars.inputSizeFlagAndVar = {
						inputSize: 30
					};
					break;

				case 'integer':
				case 'float':
					paramTemplateVars.inputType = 'text';
					paramTemplateVars.inputSizeFlagAndVar = {
						inputSize: 5
					};
					break;

				case 'boolean':
					paramTemplateVars.inputType = 'checkbox';
					paramTemplateVars.inputValue = paramName;
					break;

				case 'json':
					throw new Error( 'json parameter type requires custom admin UI module.' );

				default:
					throw new Error( 'Invalid parameter definition type: ' + paramDef.type );
			}

			// If parameter value was not provided, set a default
			if ( !( paramName in paramValues ) ) {
				if ( typeof paramDef.defaultValue !== 'undefined' ) {
					paramValues[ paramName ] = paramDef.defaultValue;
				} else {
					switch ( paramDef.type ) {
						case 'string':
							paramValues[ paramName ] = '';
							break;

						case 'integer':
						case 'float':
							paramValues[ paramName ] = '0';
							break;

						case 'boolean':
							paramValues[ paramName ] = false;
							break;

						default:
							throw new Error( 'Invalid parameter definition type: ' + paramDef.type );
					}
				}
			}

			// Set form control values
			switch ( paramDef.type ) {
				case 'string':
				case 'integer':
				case 'float':
					paramTemplateVars.inputValue = paramValues[ paramName ];
					break;

				case 'boolean':
					if ( paramValues[ paramName ] ) {
						paramTemplateVars.checkedFlagAndVar = {
							checked: 'checked'
						};
					}
					break;

				default:
					throw new Error( 'Invalid parameter definition type: ' + paramDef.type );
			}

			if ( paramDef.helpMsg ) {
				// eslint-disable-next-line mediawiki/msg-doc
				paramTemplateVars.help = mw.message( paramDef.helpMsg ).text();
			}

			templateVars.params.push( paramTemplateVars );
		} );

		return $( mixinParamsTemplate.render( templateVars ) );
	}

	function verifyParamControl() {
		var $input = $( this ),
			val = $input.val().trim();

		switch ( $input.data( 'data-type' ) ) {
			case 'integer':
				if ( /^-?\d+$/.test( val ) ) {
					setValidationError( false, $input );
				} else {
					setValidationError(
						true, $input, 'centralnotice-notice-mixins-int-required'
					);
				}
				break;

			case 'float':
				if ( /^-?\d+\.?\d*$|^-?\d*\.?\d+$/.test( val ) ) {
					setValidationError( false, $input );
				} else {
					setValidationError(
						true, $input, 'centralnotice-notice-mixins-float-required'
					);
				}
				break;
		}

		if ( !isNaN( $input.data( 'min-val' ) ) && Number( val ) < Number( $input.data( 'min-val' ) ) ) {
			setValidationError(
				true, $input, 'centralnotice-notice-mixins-out-of-bound'
			);
		}

		if ( !isNaN( $input.data( 'max-val' ) ) && Number( val ) > Number( $input.data( 'max-val' ) ) ) {
			setValidationError(
				true, $input, 'centralnotice-notice-mixins-out-of-bound'
			);
		}
	}

	function setValidationError( error, $input, msgKey ) {

		var $errorBox = $input.closest( 'p' ).prevAll( '.mw-message-box-error' );

		if ( error ) {
			if ( $errorBox.length === 0 ) {
				$errorBox = $( '<p>' ).addClass( [ 'mw-message-box', 'mw-message-box-error' ] );
				// eslint-disable-next-line mediawiki/msg-doc
				$errorBox.text( mw.message( msgKey ).text() );
				$input.closest( 'p' ).before( $errorBox );
			}

			eventBus.emit( 'error-state', $input.attr( 'name' ), true );

		} else {
			$errorBox.remove();
			eventBus.emit( 'error-state', $input.attr( 'name' ), false );
		}
	}

	/**
	 * Create a by-bucket index of assigned banners using data received from the server.
	 */
	function setUpAssignedBanners() {
		var assignedBannersFlat, i;

		// Create outer array and inner arrays for all possible buckets
		assignedBanners = [];

		for ( i = 0; i < mw.config.get( 'wgNoticeNumberOfBuckets' ); i++ ) {
			assignedBanners[ i ] = [];
		}

		// Get the data sent from the server
		// If there are no assigned banners, the assigned banner fieldset isn't included
		// in the page. In that case, jQuery will return undefined from data()
		assignedBannersFlat =
			$( '#centralnotice-assigned-banners' ).data( 'assigned-banners' ) || [];

		// Fill up the index
		for ( i = 0; i < assignedBannersFlat.length; i++ ) {
			assignedBanners[ assignedBannersFlat[ i ].bucket ]
				.push( assignedBannersFlat[ i ].bannerName );
		}
	}

	/**
	 * Update the by-bucket index of assigned banners when a remove banner checkbox or a
	 * bucket selector for an assigned banner changes. Then, broadcast the
	 * assigned-banner-change event.
	 */
	function updateAssignedBanners() {
		var $removeCheckboxes = $( '.bannerRemoveCheckbox' ),
			$selectors = $( '.bucketSelectorForAssignedBanners' ),
			removedBanners = [];

		// Create an array with the names of banners whose remove checkbox is checked
		$removeCheckboxes.each( function () {
			var $this = $( this );
			if ( $this.prop( 'checked' ) ) {
				removedBanners.push( $this.val() );
			}
		} );

		// Iterate over the bucket selectors for assigned banners
		$selectors.each( function () {
			var i, bannerIdx,
				$this = $( this ),
				assignedBucket = +$this.val(),
				bannerName = $this.data( 'banner-name' ),
				removed = ( removedBanners.indexOf( bannerName ) !== -1 );

			// Iterate over all buckets, adding banners to the index or removeing them,
			// as needed. (assignedBanners has elements for all possible buckets.)
			// TODO Make the order of banners the same as the order displayed in the UI
			for ( i = 0; i < assignedBanners.length; i++ ) {

				bannerIdx = assignedBanners[ i ].indexOf( bannerName );

				// If the box is checked to remove, just ensure the banner is not there
				if ( removed ) {
					if ( bannerIdx !== -1 ) {
						assignedBanners[ i ].splice( bannerIdx, 1 );
					}
					continue;
				}

				// If the banner is assigned to this bucket but not in the array, add it.
				if ( i === assignedBucket && bannerIdx === -1 ) {
					assignedBanners[ i ].push( bannerName );
					continue;
				}

				// If the banner isn't assigned to this bucket but it is in the array,
				// remove it.
				if ( i !== assignedBucket && bannerIdx !== -1 ) {
					assignedBanners[ i ].splice( bannerIdx, 1 );
				}
			}
		} );

		// Broadcast the event
		eventBus.emit( 'assigned-banners-change' );
	}

	/**
	 * Get an array of banners assigned to a specific bucket.
	 *
	 * @param {number} bucket
	 * @return {Array}
	 */
	function getAssignedBanners( bucket ) {
		return assignedBanners[ bucket ];
	}

	/**
	 * Get the human-friendly alphabetic label for a bucket number
	 *
	 * @param {number} bucket
	 * @return {string}
	 */
	function getBucketLabel( bucket ) {
		return BUCKET_LABELS[ bucket ];
	}

	/* Exports */

	module.exports = {

		/**
		 * Base class for custom campaign mixin UI controllers.
		 *
		 * @see MixinCustomUiController
		 * @type {Function}
		 */
		MixinCustomUiController: MixinCustomUiController,

		/**
		 * Base class for custom campaign mixin widgets.
		 *
		 * @see MixinCustomWidget
		 * @type {Function}
		 */
		MixinCustomWidget: MixinCustomWidget,

		/**
		 * Simple object for keeping track of validation errors.
		 *
		 * @see ErrorStateTracker
		 * @type {Function}
		 */
		ErrorStateTracker: ErrorStateTracker,

		/**
		 * Factory for custom mixin UI controllers.
		 *
		 * @type {OO.Factory}
		 */
		mixinCustomUiControllerFactory: mixinCustomUiControllerFactory,

		/**
		 * Centralized object for emitting and subscribing to events.
		 *
		 * @type {OO.EventEmitter}
		 */
		eventBus: eventBus,

		/**
		 * Get the number of buckets currently set in the bucket input.
		 *
		 * @method
		 * @return {number}
		 */
		getNumBuckets: getNumBuckets,

		/**
		 * Get the human-friendly alphabetic label for a bucket number.
		 *
		 * @method
		 * @param {number} bucket
		 * @return {string}
		 */
		getBucketLabel: getBucketLabel,

		/**
		 * Get an array of banners assigned to a specific bucket.
		 *
		 * @method
		 * @param {number} bucket
		 * @return {Array}
		 */
		getAssignedBanners: getAssignedBanners
	};

	/* General setup */

	/**
	 * Finalize setup: initialize slider, set handlers, set variables for jQuery elements
	 */
	function initialize() {
		var $mixinCheckboxes = $( 'input.noticeMixinCheck' );

		$( '#centralnotice-throttle-amount' ).slider( {
			range: 'min',
			min: 0,
			max: 100,
			value: $( '#centralnotice-throttle-cur' ).val(),
			step: stepSize,
			slide: function ( event, element ) {
				var val = Number( element.value ),
					rounded = Math.round( val * 10 ) / 10;
				$( '#centralnotice-throttle-echo' ).text( String( rounded ) + '%' );
				$( '#centralnotice-throttle-cur' ).val( val );
			}
		} );

		$submitBtn = $( '#noticeDetailSubmit' );
		$form = $( '#centralnotice-notice-detail' );

		updateThrottle();
		updateWeightColumn();
		setUpAssignedBanners();
		updateBuckets();

		$( '#throttle-enabled' ).on( 'click', updateThrottle );
		$( '#balanced' ).on( 'click', updateWeightColumn );
		$( 'select#buckets' ).on( 'change', updateBuckets );
		$( '.bucketSelectorForAssignedBanners, .bannerRemoveCheckbox' )
			.on( 'change', updateAssignedBanners );

		$mixinCheckboxes.each( showOrHideCampaignMixinControls );
		$mixinCheckboxes.on( 'change', showOrHideCampaignMixinControls );
	}

	// We have to wait for document ready and for custom controls modules to be loaded
	// before initializing everything
	$( function () {
		// eslint-disable-next-line no-jquery/no-map-util
		var customControlsModules = $.map( mixinDefs, function ( mixinDef ) {
			return mixinDef.customAdminUIControlsModule;
		} );

		// Custom mixin control modules depend on this module so they can access base
		// classes here when they declare subclasses. So, this module can't depend on
		// them. However, we need those modules to be loaded when we first call
		// showOrHideCampaignMixinControls() (from initialize(), above). Since the
		// custom control modules are added server-side, the following call to
		// mw.loader.using() should be quick.
		mw.loader.using( customControlsModules ).done( initialize );
	} );

}() );
