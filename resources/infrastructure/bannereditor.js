/**
 * Backing JS for Special:CentralNoticeBanners/edit, the form that allows
 * editing of banner content and changing of banner settings.
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
 */
( function ( $, mw ) {

	function doPurgeCache() {
		var $dialogEl = $( '<div />' ),
			waiting;

		// Do nothing if the button was disabled (from lack of CN admin rights)
		if ( $( '#cn-cdn-cache-purge' ).prop( 'disabled' ) ) {
			return;
		}

		$dialogEl.dialog( {
			title: mw.message( 'centralnotice-banner-cdn-dialog-title' ).text(),
			autoOpen: false
		} );

		// Show dialog with info if the background call takes a while to return
		waiting = setTimeout( function() {
			$dialogEl.text(
				mw.message( 'centralnotice-banner-cdn-dialog-waiting-text' ).text() );

			$dialogEl.dialog( 'open' );
		}, 300 );

		new mw.Api().postWithToken( 'csrf', {
			action: 'centralnoticecdncacheupdatebanner',
			banner: $( '#cn-cdn-cache-purge' ).data( 'bannerName' ),
			language: $( '#cn-cdn-cache-language :selected' ).val()
		}, {
			timeout: 2000
		} ).always( function () {
			clearTimeout( waiting );
			$dialogEl.dialog( {
				buttons: [ {
					text: mw.message( 'centralnotice-banner-cdn-dialog-ok' ).text(),
					click: function() {
						$( this ).dialog( 'close' );
					}
				} ]
			} );

		} ).fail( function ( code, result ) {
			var text = mw.message( 'centralnotice-banner-cdn-dialog-error' ).text();

			if ( result && result.error && result.error.info ) {
				text += ' (' + result.error.info + ')';
			} else if ( result && result.exception ) {
				text += ' (' + result.exception + ')';
			}

			$dialogEl.text( text );
			$dialogEl.dialog( 'open' );

		} ).done( function () {
			$dialogEl.text( mw.message( 'centralnotice-banner-cdn-dialog-success' ).text() );
			$dialogEl.dialog( 'open' );

		} );
	}

	// TODO Several functions exposed aren't used elsewhere, so they should be private
	mw.centralNotice.adminUi.bannerEditor = {
		/**
		 * Display the 'Create Banner' dialog
		 *
		 * @return {boolean}
		 */
		doCloneBannerDialog: function () {
			var buttons = {},
				okButtonText = mw.message( 'centralnotice-clone' ).text(),
				cancelButtonText = mw.message( 'centralnotice-clone-cancel' ).text(),
				dialogObj = $( '<form></form>' );

			// Implement the functionality
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};
			buttons[ okButtonText ] = function () {

				// We'll submit the real form (not the one in the dialog).
				// Copy in values to that form before submitting.
				var formobj = $( '#cn-banner-editor' )[ 0 ];
				formobj.wpaction.value = 'clone';
				formobj.wpcloneName.value = $( this )[ 0 ].wpcloneName.value;

				formobj.wpcloneEditSummary.value =
					$( this )[ 0 ].wpcloneEditSummary.value;

				formobj.submit();
			};

			// Copy value of summary from main form into clone summary field
			$( '#mw-input-wpcloneEditSummary' )
				.val( $( '#mw-input-wpsummary' ).val() );

			// Create the dialog by copying the text fields into a new form
			dialogObj[ 0 ].name = 'addBannerDialog';
			dialogObj.append( $( '#cn-formsection-clone-banner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'centralnotice-clone-notice' ).text(),
					modal: true,
					buttons: buttons,
					width: 'auto'
				} );

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Validates the contents of the banner body before submission.
		 *
		 * @return {boolean}
		 */
		doSaveBanner: function () {
			/* global alert */
			if ( $( '#mw-input-wpbanner-body' ).prop( 'value' ).indexOf( 'document.write' ) > -1 ) {
				alert( mw.msg( 'centralnotice-documentwrite-error' ) );
			} else {
				return true;
			}
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected banners and if yes will submit
		 * the form with the 'remove' action.
		 */
		doDeleteBanner: function () {
			var dialogObj = $( '<form></form>' ),
				dialogMessage = $( '<div class="cn-dialog-message" />' ),
				buttons = {},
				deleteText = mw.message( 'centralnotice-delete-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-delete-banner-cancel' ).text();

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ deleteText ] = function () {
				var formobj = $( '#cn-banner-editor' )[ 0 ];
				formobj.wpaction.value = 'delete';

				formobj.wpdeleteEditSummary.value =
					$( this )[ 0 ].wpdeleteEditSummary.value;

				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			// Copy value of summary from main form into delete summary field
			$( '#mw-input-wpdeleteEditSummary' )
				.val( $( '#mw-input-wpsummary' ).val() );

			dialogObj.append( dialogMessage );
			dialogMessage.text( mw.message( 'centralnotice-delete-banner-confirm' ).text() );

			dialogObj.append( $( '#cn-formsection-delete-banner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'centralnotice-delete-banner-title', 1 ).text(),
					modal: true,
					buttons: buttons,
					width: '35em'
				} );
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveBanner: function () {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'centralnotice-archive-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-archive-banner-cancel' ).text();

			buttons[ archiveText ] = function () {
				var formobj = $( '#cn-banner-editor' )[ 0 ];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			dialogObj.text( mw.message( 'centralnotice-archive-banner-confirm' ).text() );
			dialogObj.dialog( {
				title: mw.message( 'centralnotice-archive-banner-title', 1 ).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			} );
		},

		/**
		 * Hook function from onclick of the translate language drop down -- will submit the
		 * form in order to update the language of the preview and the displayed translations.
		 */
		updateLanguage: function () {
			var formobj = $( '#cn-banner-editor' )[ 0 ];
			formobj.wpaction.value = 'update-lang';
			formobj.submit();
		},

		/**
		 * Legacy insert close button code. Happens on link click above the edit area
		 * TODO: Make this jQuery friendly...
		 *
		 * @param {string} buttonType
		 */
		insertButton: function ( buttonType ) {
			var buttonValue,
				sel,
				bannerField = document.getElementById( 'mw-input-wpbanner-body' ),
				startPos,
				endPos;
			if ( buttonType === 'close' ) {
				buttonValue = '<a href="#" title="' +
					mw.msg( 'centralnotice-close-title' ) +
					'" onclick="mw.centralNotice.hideBanner();return false;">' +
					'<div class="cn-closeButton">' + mw.msg( 'centralnotice-close-title' ) +
					'</div></a>';
			}
			if ( document.selection ) {
				// IE support
				bannerField.focus();
				sel = document.selection.createRange();
				sel.text = buttonValue;
			} else if ( bannerField.selectionStart || bannerField.selectionStart === 0 ) {
				// Mozilla support
				startPos = bannerField.selectionStart;
				endPos = bannerField.selectionEnd;
				bannerField.value = bannerField.value.substring( 0, startPos ) +
					buttonValue +
					bannerField.value.substring( endPos, bannerField.value.length );
			} else {
				bannerField.value += buttonValue;
			}
			bannerField.focus();
		}
	};

	// Attach handlers and initialize stuff after document ready
	$( function () {
		$( '#mw-input-wpdelete-button' ).click( mw.centralNotice.adminUi.bannerEditor.doDeleteBanner );
		$( '#mw-input-wparchive-button' ).click( mw.centralNotice.adminUi.bannerEditor.doArchiveBanner );
		$( '#mw-input-wpclone-button' ).click( mw.centralNotice.adminUi.bannerEditor.doCloneBannerDialog );
		$( '#mw-input-wpsave-button' ).click( mw.centralNotice.adminUi.bannerEditor.doSaveBanner );
		$( '#mw-input-wptranslate-language' ).change( mw.centralNotice.adminUi.bannerEditor.updateLanguage );
		$( '#cn-cdn-cache-purge' ).click( doPurgeCache );

		$( '#cn-js-error-warn' ).hide();
	} );

}( jQuery, mediaWiki ) );
