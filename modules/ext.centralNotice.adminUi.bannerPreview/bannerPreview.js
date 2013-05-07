/**
 * JS that is loaded onto every MW page to load banners.
 *
 * Requires the Geo global object; e.g. from geoiplookup.wikimedia.org
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
( function ( $, mw ) {
	mw.centralNotice.events.bannerLoaded.done( function() {
		setTimeout( function() {
			window.parent.postMessage(
				JSON.stringify( {
					banner: mw.centralNotice.bannerData.bannerName,
					height: $( '#centralNotice' ).prop( 'offsetHeight' ) | 1
				} ),
				'*'
			);
		}, 100 );
	} );
} )( jQuery, mediaWiki );
