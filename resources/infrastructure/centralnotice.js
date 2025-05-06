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
		const thisCollapsed = document.getElementById( 'cn-collapsed-' + logId ),
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
		const thisCollapsed = document.getElementById( 'cn-collapsed-filter-arrow' ),
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
		const url = new URL( baseUrl );
		encodeURIComponent( logType );
		url.searchParams.set( 'log', logType );
		location.href = url.toString();
	};

	$( () => {
		const $geoRegionsInput = $( '#geo_regions_value' ),
			$geoCountriesInput = $( '#geo_countries_value' ),
			$geoStatus = $( '.cn-tree-status' ),
			$allocationCountrySelector = $( '#centralnotice-country' ),
			$allocationRegionSelector = $( '#centralnotice-region' ),
			allocationRegionOptions = mw.config.get( 'CentralNoticeRegionOptions' );

		// Render jquery.ui.datepicker on appropriate fields
		$( '.centralnotice-datepicker' ).each( function () {
			const altFormat = 'yymmdd000000',
				altField = document.getElementById( this.id + '_timestamp' );
			// Remove the time, leaving only the date info
			$( this ).datepicker( {
				altField: altField,
				altFormat: altFormat,
				dateFormat: 'yy-mm-dd'
			} );

			if ( altField.value ) {
				altField.value = altField.value.slice( 0, 8 ) + '000000';
				const defaultDate = $.datepicker.parseDate( altFormat, altField.value );
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

		$( 'select[multiple]' ).multiselect(
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
		} ).on( 'changed.jstree', ( e, data ) => {
			const countries = [], regions = [], regionCountries = {},
				regionCountriesList = [],
				selected = data.instance.get_top_selected( false );
			for ( let i = 0; i < selected.length; i++ ) {
				const node = data.instance.get_node( selected[ i ], false );
				const type = node.data.jstree.type;
				if ( type === 'country' ) {
					countries.push( node.id );
				} else {
					regions.push( node.id );
					// push to object and remove country code from node id
					if ( node.parent in regionCountries ) {
						regionCountries[ node.parent ].push( node.id.slice( 3 ) );
					} else {
						regionCountries[ node.parent ] = new Array( node.id.slice( 3 ) );
					}
				}
			}
			$geoCountriesInput.val( countries.join( ',' ) );
			$geoRegionsInput.val( regions.join( ',' ) );

			for ( const country in regionCountries ) {
				regionCountries[ country ].sort();
				regionCountriesList.push(
					country + ': (' + regionCountries[ country ].join( ', ' ) + ')'
				);
			}
			regionCountriesList.sort();
			countries.sort();
			let countriesListString;
			if ( countries.length > 0 && regionCountriesList.length > 0 ) {
				countriesListString = countries.join( ', ' ) + '; ';
			} else {
				countriesListString = countries.join( ', ' );
			}

			$geoStatus.html(
				mw.msg(
					'centralnotice-geo-status',
					countriesListString,
					regionCountriesList.join( ', ' )
				)
			);

		} );

		// Search input for geotree
		$( '.cn-tree-search' ).on( 'keyup', mw.util.debounce( () => {
			$( '.cn-tree' ).jstree( true ).search( $( '.cn-tree-search' ).val() );
		}, 250 ) );

		// Clear button for search input
		$( '.cn-tree-clear' ).on( 'click', ( e ) => {
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

		// Set up dynamic region selector for banner allocation page
		function allocationCountrySelected() {
			const options = [ new Option( '', '' ) ],
				country = $allocationCountrySelector.val(),
				regions = allocationRegionOptions[ country ];
			if ( regions ) {
				for ( const regionCode in regions ) {
					options.push( new Option(
						regions[ regionCode ], regionCode
					) );
				}
			}
			$allocationRegionSelector.empty().append( options );
		}

		$allocationCountrySelector.on( 'change', allocationCountrySelected );

		if ( $allocationCountrySelector.length > 0 ) {
			// Also fire on page load, and set pre-selected option from querystring
			allocationCountrySelected();
			$allocationRegionSelector.val(
				mw.util.getParamValue( 'region' )
			);
		}
	} );
}() );
