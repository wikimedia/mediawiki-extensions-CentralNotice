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
( function ( $, mw ) {
	var stepSize = 1,
		mixinDefs = mw.config.get( 'wgCentralNoticeCampaignMixins' ),
		mixinParamsTemplate = mw.template.get(
			'ext.centralNotice.adminUi.campaignManager',
			'campaignMixinParamControls.mustache'
		),
		$form, $submitBtn,
		MixinCustomUiController, MixinCustomWidget,
		mixinCustomUiControllerFactory = new OO.Factory(),
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
	 * Set the (string-encoded) value of a parameter for the mixin.
	 *
	 * @param {string} name The name of the parameter.
	 * @param {string} value The value (formatted as appropriate for form submission).
	 */
	MixinCustomUiController.prototype.setParam = function ( name, value ) {
		var $input = this.getParamInputEl( name, true );

		$input.val( value );
	};

	/**
	 * Remove a parameter for the mixin.
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
	 * Get the input element for a mixin parameter, if it exists. If requested,
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

			$input = $( '<input />' ).attr( {
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
	 * @mixins OO.ui.mixin.GroupWidget
	 * @constructor
	 *
	 * @param {MixinCustomUiController} controller
	 * @param {Object} [config] Configuration options
	 */
	MixinCustomWidget = function ( controller, config ) {

		var $element = $( '<fieldset></fieldset>' ),
			$group = $( '<div></div>' );

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

	/* Event handlers (non-OOjs-UI) and related logic */

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
		var numBuckets = getNumBuckets(),
			maxNumBuckets = mw.config.get( 'wgNoticeNumberOfBuckets' ),
			bucketSelectors = $( 'select.bucketSelector' ),
			i, isBucketDisabled;

		// Change selected value of bucket selectors to only available buckets
		bucketSelectors.each( function () {
			var $selector = $( this ),
				selectedVal = $selector.val();

			$selector.val( selectedVal % numBuckets );
		} );

		// If only one bucket is available, disable the selectors entirely
		if ( numBuckets === 1 ) {
			bucketSelectors.prop( 'disabled', true );

		} else {
			// If more than one bucket is available, enable selectors and set options to
			// disabled or enabled, as appropriate
			bucketSelectors.prop( 'disabled', false );

			for ( i = 0; i < maxNumBuckets; i++ ) {
				isBucketDisabled = ( i >= numBuckets );

				bucketSelectors.find( 'option[value=' + i + ']' )
					.prop( 'disabled', isBucketDisabled );
			}
		}

		// Broadcast bucket change event
		eventBus.emit( 'bucket-change', numBuckets );
	}

	function getNumBuckets() {
		return parseInt( $( 'select#buckets :selected' ).val(), 10 );
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
				if ( mixinDefs[ mixinName ].customAdminUIControls ) {

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
						$.debounce( 100, verifyParamControl )
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

		$.each( paramDefs, function ( paramName, paramDef ) {

			var paramTemplateVars = {
				labelMsg: mw.message( paramDef.labelMsg ).text(),
				inputName: makeNoticeMixinControlName( mixinName, paramName ),
				dataType: paramDef.type
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

				default:
					throw 'Invalid parameter definition type: ' + paramDef.type;
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
							throw 'Invalid parameter definition type: ' + paramDef.type;
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
					throw 'Invalid parameter definition type: ' + paramDef.type;
			}

			if ( paramDef.helpMsg ) {
				paramTemplateVars.help = mw.message( paramDef.helpMsg ).text();
			}

			templateVars.params.push( paramTemplateVars );
		} );

		return $( mixinParamsTemplate.render( templateVars ) );
	}

	function verifyParamControl() {
		var $input = $( this ),
			val = $input.val();

		switch ( $input.data( 'data-type' ) ) {
			case 'integer':
				if ( $.trim( val ).match( /^-?\d+$/ ) ) {
					setValidationError( false, $input );
				} else {
					setValidationError(
						true, $input, 'centralnotice-notice-mixins-int-required'
					);
				}
				break;

			case 'float':
				if ( $.trim( val ).match( /^-?\d+\.?\d*$|^-?\d*\.?\d+$/ ) ) {
					setValidationError( false, $input );
				} else {
					setValidationError(
						true, $input, 'centralnotice-notice-mixins-float-required'
					);
				}
				break;
		}
	}

	function setValidationError( error, $input, msgKey ) {

		var $errorBox = $input.closest( 'p' ).prevAll( '.errorbox' );

		if ( error ) {
			$submitBtn.attr( 'disabled', 'disabled' );

			if ( $errorBox.length === 0 ) {
				$errorBox = $( '<p class="errorbox" />' );
				$errorBox.text( mw.message( msgKey ).text() );
				$input.closest( 'p' ).before( $errorBox );
			}

		} else {
			$submitBtn.removeAttr( 'disabled' );
			$errorBox.remove();
		}
	}

	/* Exports */

	module.exports = {

		/**
		 * Base class for custom campaign mixin UI controllers.
		 * @see MixinCustomUiController
		 * @type {function}
		 */
		MixinCustomUiController: MixinCustomUiController,

		/**
		 * Base class for custom campaign mixin widgets.
		 * @see MixinCustomWidget
		 * @type {function}
		 */
		MixinCustomWidget: MixinCustomWidget,

		/**
		 * Factory for custom mixin UI controllers.
		 * @type {OO.Factory}
		 */
		mixinCustomUiControllerFactory: mixinCustomUiControllerFactory,

		/**
		 * Centralized object for emitting and subscribing to events.
		 * @type {OO.EventEmitter}
		 */
		eventBus: eventBus
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
		updateBuckets();

		$( '#throttle-enabled' ).click( updateThrottle );
		$( '#balanced' ).click( updateWeightColumn );
		$( 'select#buckets' ).change( updateBuckets );

		$mixinCheckboxes.each( showOrHideCampaignMixinControls );
		$mixinCheckboxes.change( showOrHideCampaignMixinControls );
	}

	// We have to wait for document ready and for custom controls modules to be loaded
	// before initializing everyhting
	$( function () {
		var customControlsModules = $.map( mixinDefs, function ( mixinDef ) {
			return mixinDef.customAdminUIControlsModule;
		} );

		mw.loader.using( customControlsModules ).done( initialize );
	} );

}( jQuery, mediaWiki ) );
