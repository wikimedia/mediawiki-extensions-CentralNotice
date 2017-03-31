/**
 * Backing JS for Special:CentralNotice, the campaign list view form.
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
		$mixinCheckboxes, $submitBtn;

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
			$paramControls;

		if ( $checkBox.prop( 'checked' ) ) {

			// If the controls don't exist yet, create them
			if ( $paramControlSet.length === 0 ) {

				paramValues = $checkBox.data( 'mixin-param-values' );

				$paramControlSet = makeMixinParamControlSet(
					mixinName,
					paramValues
				);

				$checkBox.parent( 'div' ).append( $paramControlSet );

				// Hook up handler for verification
				$paramControls = $paramControlSet.find( 'input' );
				$paramControls.on(
					'keyup keydown change mouseup cut paste focus blur',
					$.debounce( 100, verifyParamControl )
				);

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

	// Execute code that requires document ready: setup slider, set handlers, set variables
	// for jQuery elements
	$( function () {

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

		updateThrottle();
		updateWeightColumn();
		updateBuckets();

		$( '#throttle-enabled' ).click( updateThrottle );
		$( '#balanced' ).click( updateWeightColumn );
		$( 'select#buckets' ).change( updateBuckets );

		$mixinCheckboxes = $( 'input.noticeMixinCheck' );
		$mixinCheckboxes.each( showOrHideCampaignMixinControls );
		$mixinCheckboxes.change( showOrHideCampaignMixinControls );
	} );

}( jQuery, mediaWiki ) );
