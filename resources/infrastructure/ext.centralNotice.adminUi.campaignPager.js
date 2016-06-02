/**
 * CentralNotice Administrative UI - Campaign pager
 */
( function( mw, $ ) {

	var changes = {};

	/**
	 * Update changes object as needed
	 */
	function setChange( campaignName, property, value, initialValue ) {

		var keysCount, k;

		// If we're returning to the initial value, don't set a change, but
		// maybe mop up
		if ( value === initialValue ) {

			// The check for changes[campaignName] here shouldn't be necessary,
			// but let's be safe
			if ( ( changes[campaignName] !== undefined ) &&
				( changes[campaignName][property] !== undefined ) ) {

				delete changes[campaignName][property];

				// Remove campaign object from changes, if empty
				keysCount = 0;
				for (k in changes[campaignName] ) {
					keysCount++;
				}

				if ( !keysCount ) {
					delete changes[campaignName];
				}
			}

			return;
		}

		// Ensure a campaign object in changes
		if ( changes[campaignName] === undefined ) {
			changes[campaignName] = {};
		}

		changes[campaignName][property] = value;
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
	 * Click handler for faux submit button, to submit just what's changed
	 */
	function submitChanges() {
		var $form = $( '<form method="post"></form>'),

			$authtokenField =
				$( '<input type="hidden" name="authtoken" value="' +
				mw.user.tokens.get( 'editToken' ) +
				'"></input>' ),

			$summaryField =
				$( '<input type="hidden" name="changeSummary" value="' +
				$( '#cn-campaign-pager input.cn-change-summary-input' ).val() +
				'"></input>' ),

			$changesField =
				$( '<input type="hidden" name="changes" ></input>' );

		$changesField.val( JSON.stringify( changes ) );
		$form.append( $authtokenField, $summaryField, $changesField );
		$( document.body ).append( $form );
		$form.submit();
	}

	jQuery(document).ready( function ( $ ) {

		var CHECKBOX_NAMES = [ 'enabled', 'locked', 'archived' ],
			i, selector,
			$showArchived = $( '#centralnotice-showarchived' );

		// Show or hide archived campaigns
		if ( $showArchived.length > 0 ) {

			$showArchived.click( function () {
				if ( $( this ).prop( 'checked' ) ) {
					$( '.cn-archived-item' ).show();
				} else {
					$( '.cn-archived-item' ).hide();
				}
			} );
		}

		// Keep data-sort-value attributes for jquery.tablesorter in sync
		$( '.mw-cn-input-check-sort' ).on( 'change click blur', function () {
			$(this).parent( 'td' )
				.data( 'sortValue', Number( this.checked ) );
		} );

		// If the table is editable, attach handlers to controls
		if ( $( '#cn-campaign-pager' ).data( 'editable' ) ) {

			// Go through all the fields with checkbox controls
			for ( i = 0; i < CHECKBOX_NAMES.length; i++ ) {

				// Select enabled checkboxes with this name
				// See CNCampaignPager::formatValue()
				selector = '#cn-campaign-pager input[name="' +
					CHECKBOX_NAMES[i] + '"]:not([disabled])';

				// When checked or unchecked, update changes to send
				$( selector ).change( updateCheckboxChanges );
			}

			// Attach handler to priority dropdowns
			// See CentralNotice::prioritySelector()
			$( '#cn-campaign-pager select[name="priority"]:not([disabled])' )
				.change( updatePriorityChanges );

			// Attach handler to "Submit" button
			// See CNCampaignPager::getEndBody()
			$( '#cn-campaign-pager-submit' ).click( submitChanges );
		}
	} );
} )( mediaWiki, jQuery );
