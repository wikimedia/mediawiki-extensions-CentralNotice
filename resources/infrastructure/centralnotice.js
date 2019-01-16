/**
 * CentralNotice Administrative UI - Common Functions
 */

// FIXME Encapsulate logic specific to Special:CentralNoticeLogs
// FIXME Global functions

( function () {
	mw.centralNotice = mw.centralNotice || {};
	mw.centralNotice.adminUi = {};

	// Collapse and uncollapse detailed view for an individual log entry
	window.toggleLogDisplay = function ( logId ) {
		var thisCollapsed = document.getElementById( 'cn-collapsed-' + logId ),
			thisUncollapsed = document.getElementById( 'cn-uncollapsed-' + logId ),
			thisDetails = document.getElementById( 'cn-log-details-' + logId );
		if ( thisCollapsed.style.display === 'none' ) {
			thisUncollapsed.style.display = 'none';
			thisCollapsed.style.display = 'block';
			thisDetails.style.display = 'none';
		} else {
			thisCollapsed.style.display = 'none';
			thisUncollapsed.style.display = 'block';
			thisDetails.style.display = 'table-row';
		}
	};

	// Collapse and uncollapse log filter interface
	window.toggleFilterDisplay = function () {
		var thisCollapsed = document.getElementById( 'cn-collapsed-filter-arrow' ),
			thisUncollapsed = document.getElementById( 'cn-uncollapsed-filter-arrow' ),
			thisFilters = document.getElementById( 'cn-log-filters' );
		if ( thisCollapsed.style.display === 'none' ) {
			thisUncollapsed.style.display = 'none';
			thisCollapsed.style.display = 'inline-block';
			thisFilters.style.display = 'none';
		} else {
			thisCollapsed.style.display = 'none';
			thisUncollapsed.style.display = 'inline-block';
			thisFilters.style.display = 'block';
		}
	};

	// Switch among various log displays
	window.switchLogs = function ( baseUrl, logType ) {
		var url = new mw.Uri( baseUrl );
		encodeURIComponent( logType );
		location.href = url.extend( { log: logType } ).toString();
	};

	$( function () {
		// Render jquery.ui.datepicker on appropriate fields
		$( '.centralnotice-datepicker' ).each( function () {
			var altFormat = 'yymmdd000000',
				altField = document.getElementById( this.id + '_timestamp' ),
				defaultDate;
			// Remove the time, leaving only the date info
			$( this ).datepicker( {
				altField: altField,
				altFormat: altFormat,
				dateFormat: 'yy-mm-dd'
			} );

			if ( altField.value ) {
				altField.value = altField.value.substr( 0, 8 ) + '000000';
				defaultDate = $.datepicker.parseDate( altFormat, altField.value );
				$( this ).datepicker(
					'setDate', defaultDate
				);
			}
		} );
		$( '.centralnotice-datepicker-limit_one_year' ).datepicker(
			'option',
			{
				maxDate: '+1Y'
			}
		);

		// Do the fancy multiselector; but we have to wait for some arbitrary time until the
		// CSS has been applied.
		// FIXME This is a hack.
		setTimeout( function () {
			$( 'select[multiple="multiple"]' ).multiselect(
				{ sortable: false, dividerLocation: 0.5 }
			);
		}, 250 );

		// Reveal the geoMultiSelector when the geotargeted checkbox is checked
		if ( !$( '#geotargeted' ).prop( 'checked' ) ) {
			// FIXME: Use CSS transition
			// eslint-disable-next-line jquery/no-fade
			$( '#geoMultiSelector' ).fadeOut( 'fast' );
		}
		$( '#geotargeted' ).on( 'click', function () {
			if ( this.checked ) {
				// FIXME: Use CSS transition
				// eslint-disable-next-line jquery/no-fade
				$( '#geoMultiSelector' ).fadeIn( 'fast' );
			} else {
				// FIXME: Use CSS transition
				// eslint-disable-next-line jquery/no-fade
				$( '#geoMultiSelector' ).fadeOut( 'fast' );
			}
		} );
	} );
}() );
