/**
 * CentralNotice Administrative UI - Campaign pager
 */
/* eslint-disable no-unused-vars */
// TODO Check whether we can use Object.keys() now, to remove the unused k var and above rule.
( function () {

	var changes = {};

	/**
	 * Update changes object as needed
	 *
	 * @param campaignName
	 * @param property
	 * @param value
	 * @param initialValue
	 */
	function setChange( campaignName, property, value, initialValue ) {

		var keysCount, k;

		// If we're returning to the initial value, don't set a change, but
		// maybe mop up
		if ( value === initialValue ) {

			// The check for changes[campaignName] here shouldn't be necessary,
			// but let's be safe
			if ( ( changes[ campaignName ] !== undefined ) &&
				( changes[ campaignName ][ property ] !== undefined ) ) {

				delete changes[ campaignName ][ property ];

				// Remove campaign object from changes, if empty
				keysCount = 0;
				for ( k in changes[ campaignName ] ) {
					keysCount++;
				}

				if ( !keysCount ) {
					delete changes[ campaignName ];
				}
			}

			return;
		}

		// Ensure a campaign object in changes
		if ( changes[ campaignName ] === undefined ) {
			changes[ campaignName ] = {};
		}

		changes[ campaignName ][ property ] = value;
	}

	/**
	 * Handler for changes to checkboxes
	 */
	function updateCheckboxChanges() {
		var $this = $( this );

		setChange(
			$this.data( 'campaignName' ),
			$this.attr( 'name' ),
			$this.prop( 'checked' ),
			Boolean( $this.data( 'initialValue' ) ) // Comes in as '' or 1
		);
	}

	/**
	 * Handler for changes to priority dropdowns
	 */
	function updatePriorityChanges() {
		var $this = $( this );

		setChange(
			$this.data( 'campaignName' ),
			'priority',
			parseInt( $this.val(), 10 ), // Munge to int for comparison needs
			$this.data( 'initialValue' ) // jQuery munges to int
		);
	}

	/**
	 * Handler for changes to campaign type dropdowns
	 */
	function updateCampaignTypeChanges() {
		var $this = $( this );

		setChange(
			$this.data( 'campaignName' ),
			'campaign_type',
			$this.val(),
			$this.data( 'initialValue' )
		);
	}

	/**
	 * Click handler for faux submit button, to submit just what's changed
	 */
	function submitChanges() {
		var $form = $( '<form>' ).attr( 'method', 'post' ),
			$authtokenField = $( '<input>' ).attr( { type: 'hidden', name: 'authtoken' } ),
			$summaryField = $( '<input>' ).attr( { type: 'hidden', name: 'changeSummary' } ),
			$changesField = $( '<input>' ).attr( { type: 'hidden', name: 'changes' } );

		$authtokenField.val( mw.user.tokens.get( 'csrfToken' ) );
		$summaryField.val(
			$( '#cn-campaign-pager input.cn-change-summary-input' ).val()
		);
		$changesField.val( JSON.stringify( changes ) );

		$form.append( $authtokenField, $summaryField, $changesField );
		$( document.body ).append( $form );
		$form.trigger( 'submit' );
	}

	$( function () {

		var CHECKBOX_NAMES = [ 'enabled', 'locked', 'archived' ],
			i, selector,
			$showArchived = $( '#centralnotice-showarchived' );

		// Show or hide archived campaigns
		if ( $showArchived.length > 0 ) {

			$showArchived.on( 'click', function () {
				if ( $( this ).prop( 'checked' ) ) {
					$( '.cn-archived-item' ).show();
				} else {
					$( '.cn-archived-item' ).hide();
				}
			} );
		}

		// Keep data-sort-value attributes for jquery.tablesorter in sync
		$( '.mw-cn-input-check-sort' ).on( 'change click blur', function () {
			$( this ).parent( 'td' )
				.data( 'sortValue', Number( this.checked ) );
		} );

		// If the table is editable, attach handlers to controls
		if ( $( '#cn-campaign-pager' ).data( 'editable' ) ) {

			// Go through all the fields with checkbox controls
			for ( i = 0; i < CHECKBOX_NAMES.length; i++ ) {

				// Select enabled checkboxes with this name
				// See CNCampaignPager::formatValue()
				selector = '#cn-campaign-pager input[name="' +
					CHECKBOX_NAMES[ i ] + '"]:not([disabled])';

				// When checked or unchecked, update changes to send
				$( selector ).on( 'change', updateCheckboxChanges );
			}

			// Attach handler to priority dropdowns
			// See CentralNotice::prioritySelector()
			$( '#cn-campaign-pager select[name="priority"]:not([disabled])' )
				.on( 'change', updatePriorityChanges );

			// Attach handler to campaign type dropdowns
			// See CentralNotice::campaignTypeSelector()
			$( '#cn-campaign-pager select[name="campaign_type"]:not([disabled])' )
				.on( 'change', updateCampaignTypeChanges );

			// Attach handler to "Submit" button
			// See CNCampaignPager::getEndBody()
			$( '#cn-campaign-pager-submit' ).on( 'click', submitChanges );
		}
	} );
}() );
