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
		var $geoRegionsInput = $( '#geo_regions_value' ),
			$geoCountriesInput = $( '#geo_countries_value' ),
			$geoStatus = $( '.cn-tree-status' );

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

		$( 'select[multiple="multiple"]' ).multiselect(
			{ sortable: false, dividerLocation: 0.5 }
		);

		// Initialize the geopicking tree
		$( '.cn-tree' ).jstree( {
			plugins: [ 'checkbox', 'search', 'types' ],
			types: {
				country: {
					icon: 'jstree-icon jstree-country'
				},
				region: {
					icon: 'jstree-icon jstree-region'
				}
			},
			search: {
				fuzzy: false,
				// eslint-disable-next-line camelcase
				show_only_matches: true,
				// eslint-disable-next-line camelcase
				show_only_matches_children: true
			}
		} ).on( 'changed.jstree', function ( e, data ) {
			var i, type, node, countries = [], regions = [],
				selected = data.instance.get_top_selected( false );
			for ( i = 0; i < selected.length; i++ ) {
				node = data.instance.get_node( selected[ i ], false );
				type = node.data.jstree.type;
				if ( type === 'country' ) {
					countries.push( node.id );
				} else {
					regions.push( node.id );
				}
			}
			$geoCountriesInput.val( countries.join( ',' ) );
			$geoRegionsInput.val( regions.join( ',' ) );

			$geoStatus.html( mw.msg( 'centralnotice-geo-status', countries.length, regions.length ) );

		} );

		// Search input for geotree
		$( '.cn-tree-search' ).on( 'keyup', $.debounce( 250, function () {
			$( '.cn-tree' ).jstree( true ).search( $( '.cn-tree-search' ).val() );
		} ) );

		// Clear button for search input
		$( '.cn-tree-clear' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '.cn-tree-search' ).val( '' );
			$( '.cn-tree-search' ).trigger( 'keyup' );
		} );

		// Reveal the geoMultiSelector when the geotargeted checkbox is checked
		if ( !$( '#geotargeted' ).prop( 'checked' ) ) {
			// FIXME: Use CSS transition
			// eslint-disable-next-line no-jquery/no-fade
			$( '#centralnotice-geo-region-multiselector' ).fadeOut( 'fast' );
		}
		$( '#geotargeted' ).on( 'click', function () {
			if ( this.checked ) {
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-fade
				$( '#centralnotice-geo-region-multiselector' ).fadeIn( 'fast' );
			} else {
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-fade
				$( '#centralnotice-geo-region-multiselector' ).fadeOut( 'fast' );
			}
		} );
	} );
}() );
