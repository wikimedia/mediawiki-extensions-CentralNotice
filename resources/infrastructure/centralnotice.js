/**
 * CentralNotice Administrative UI - Common Functions
 */
( function( mw, $ ) {
mw.centralNotice = mw.centralNotice || {};
mw.centralNotice.adminUi = {};

// Collapse and uncollapse detailed view for an individual log entry
window.toggleLogDisplay = function ( logId ) {
	var thisCollapsed = document.getElementById( 'cn-collapsed-' + logId );
	var thisUncollapsed = document.getElementById( 'cn-uncollapsed-' + logId );
	var thisDetails = document.getElementById( 'cn-log-details-' + logId );
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
	var thisCollapsed = document.getElementById( 'cn-collapsed-filter-arrow' );
	var thisUncollapsed = document.getElementById( 'cn-uncollapsed-filter-arrow' );
	var thisFilters = document.getElementById( 'cn-log-filters' );
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
	encodeURIComponent( logType );
	location.href = baseUrl + '?log=' + logType;
};

window.addEventListener( 'message', receiveMessage, false );
function receiveMessage( event ) {
	var remoteData = JSON.parse( event.data );
	if ( remoteData.banner && remoteData.height ) {
		$( "#cn-banner-preview-" + remoteData.banner + " iframe" ).height( remoteData.height );
	}
}

jQuery(document).ready( function ( $ ) {
	// Render jquery.ui.datepicker on appropriate fields
	$( '.centralnotice-datepicker' ).each( function () {
		var altFormat = 'yymmdd000000';
		var altField = document.getElementById( this.id + '_timestamp' );
		// Remove the time, leaving only the date info
		$( this ).datepicker({
			'altField': altField,
			'altFormat': altFormat,
			'dateFormat': 'yy-mm-dd'
		});

		if ( altField.value ) {
			altField.value = altField.value.substr( 0, 8 ) + '000000';
			var defaultDate = $.datepicker.parseDate( altFormat, altField.value );
			$( this ).datepicker(
				'setDate', defaultDate
			);
		}
	});
	$( '.centralnotice-datepicker-limit_one_year' ).datepicker(
		'option',
		{
			'maxDate': '+1Y'
		}
	);

	// Do the fancy multiselector; but we have to wait for some arbitrary time until the
	// CSS has been applied... Yes, this is an egregious hack until I rewrite the mutliselector
	// to NOT suck -- e.g. make it dynamic... whoo...
	setTimeout( function() {
		$('select[multiple="multiple"]' ).multiselect({sortable: false, dividerLocation: 0.5});
	}, 250);

	// Reveal the geoMultiSelector when the geotargeted checkbox is checked
	if( !$( '#geotargeted' ).prop( 'checked' ) ) {
		$( '#geoMultiSelector' ).fadeOut( 'fast' );
	}
	$( '#geotargeted' ).click(function () {
		if ( this.checked ) {
			$( '#geoMultiSelector' ).fadeIn( 'fast' );
		} else {
			$( '#geoMultiSelector' ).fadeOut( 'fast' );
		}
	});

	// Bucketing! Disable bucket selectors if #buckets is not checked.
	$( '#buckets' ).change( function () {
        var numBuckets = parseInt( this[this.selectedIndex].value, 10 ),
			buckets = $( 'select[id^="bucketSelector"]' );

        if ( numBuckets == 1 ) {
            buckets.prop( 'disabled', true );
        } else {
            buckets.prop( 'disabled', false );
            // Go through and modify all the options -- disabling inappropriate ones
            // and remapping the rings
            buckets.each( function() {
                var curBucket = parseInt( this[this.selectedIndex].value, 10 );
                $(this).val( curBucket % numBuckets );

                for ( var i = 0; i < this.options.length; i++ ) {
                    $(this.options[i]).prop( 'disabled', (i >= numBuckets) );
                }
            });
        }
	} ).trigger( 'change' );
} );
} )( mediaWiki, jQuery );
