/**
 * Backing JS for Special:CentralNotice, the campaign list view form.
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @section LICENSE
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
 * @file
 */
( function ( $ ) {
	var RAND_MAX = 30,
		step_size = 100 / RAND_MAX;
	$( '#centralnotice-throttle-amount' ).slider( {
		range: "min",
		min: 0,
		max: 100,
		value: $( "#centralnotice-throttle-cur" ).val(),
		step: step_size,
		slide: function( event, element ) {
			var val = Number( element.value ),
				rounded = Math.round( val * 10 ) / 10;
			$( "#centralnotice-throttle-echo" ).html( String( rounded ) + "%" );
			$( "#centralnotice-throttle-cur" ).val( val );
		}
	} );

	function updateThrottle() {
		if ( $( '#throttle-enabled' ).prop( 'checked' ) ) {
			$( '.cn-throttle-amount' ).show();
		} else {
			$( '.cn-throttle-amount' ).hide();
		}
	}
	$( '#throttle-enabled' ).click( updateThrottle );

	function updateWeightColumn() {
		if ( $( '#balanced' ).prop( 'checked' ) ) {
			$( '.cn-weight' ).hide();
		} else {
			$( '.cn-weight' ).show();
		}
	}
	$( '#balanced' ).click( updateWeightColumn );

	$( '#centralnotice-showarchived' ).click( function() {
		if ( $( this ).prop( 'checked' ) === true ) {
			$( '.cn-archived-item' ).show();
		} else {
			$( '.cn-archived-item' ).hide();
		}
	});

	updateThrottle();
	updateWeightColumn();
} )( jQuery );
