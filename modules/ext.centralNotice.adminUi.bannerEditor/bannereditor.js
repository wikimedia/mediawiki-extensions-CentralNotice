/**
 * Backing JS for Special:CentralNoticeBanners/edit, the form that allows
 * editing of banner content and changing of banner settings.
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
	mw.centralNotice.adminUi.bannerEditor = {
		/**
		 * Display the 'Create Banner' dialog
		 * @returns {boolean}
		 */
		doCloneBannerDialog: function() {
			var buttons = {},
				okButtonText = mw.message('centralnotice-clone').text(),
				cancelButtonText = mw.message('centralnotice-clone-cancel').text(),
				dialogObj = $('<form></form>');

			// Implement the functionality
			buttons[ cancelButtonText ] = function() { $(this).dialog("close"); };
			buttons[ okButtonText ] = function() {
				var formobj = $('#cn-banner-editor')[0];
				formobj.wpaction.value = 'clone';
				formobj.wpcloneName.value = $(this)[0].wpcloneName.value;
				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			dialogObj[0].name = 'addBannerDialog';
			dialogObj.append( $( '#cn-formsection-clone-banner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message('centralnotice-clone-notice' ).text(),
					modal: true,
					buttons: buttons,
					width: 'auto'
				});

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Validates the contents of the banner body before submission.
		 * @returns {boolean}
		 */
		doSaveBanner: function() {
			if ( $( '#mw-input-wpbanner-body' ).prop( 'value' ).indexOf( 'document.write' ) > -1 ) {
				window.alert( mediaWiki.msg( 'centralnotice-documentwrite-error' ) );
			} else {
				return true;
			}
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected banners and if yes will submit
		 * the form with the 'remove' action.
		 */
		doDeleteBanner: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				deleteText = mw.message( 'centralnotice-delete-banner' ).text(),
				cancelButtonText = mw.message('centralnotice-delete-banner-cancel').text();

			buttons[ deleteText ] = function() {
				var formobj = $('#cn-banner-editor')[0];
				formobj.wpaction.value = 'delete';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'centralnotice-delete-banner-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message( 'centralnotice-delete-banner-title', 1 ).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveBanner: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'centralnotice-archive-banner' ).text(),
				cancelButtonText = mw.message('centralnotice-archive-banner-cancel').text();

			buttons[ archiveText ] = function() {
				var formobj = $('#cn-banner-editor')[0];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'centralnotice-archive-banner-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message( 'centralnotice-archive-banner-title', 1 ).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Shows or hides the landing pages edit box control based on the status of
		 * the "Automatically create landing page link" check box.
		 */
		showHideLpEditBox: function() {
			if ( $( '#mw-input-wpcreate-landingpage-link' ).prop( 'checked' ) ) {
				$( '#mw-input-wplanding-pages' ).parent().parent().show();
			} else {
				$( '#mw-input-wplanding-pages' ).parent().parent().hide();
			}
		},

		/**
		 * Hook function from onclick of the translate language drop down -- will submit the
		 * form in order to update the language of the preview and the displayed translations.
		 */
		updateLanguage: function() {
			var formobj = $('#cn-banner-editor')[0];
			formobj.wpaction.value = 'update-lang';
			formobj.submit();
		},

		/**
		 * Legacy insert close button code. Happens on link click above the edit area
		 * TODO: Make this jQuery friendly...
		 *
		 * @param buttonType
		 */
		insertButton: function( buttonType ) {
			var buttonValue, sel;
			var bannerField = document.getElementById( 'mw-input-wpbanner-body' );
			if ( buttonType === 'close' ) {
				buttonValue = '<a href="#" title="'
					+ mediaWiki.msg( 'centralnotice-close-title' )
					+ '" onclick="mw.centralNotice.hideBanner();return false;">'
					+ '<img border="0" src="' + mediaWiki.config.get( 'wgNoticeCloseButton' )
					+ '" alt="' + mediaWiki.msg( 'centralnotice-close-title' )
					+ '" /></a>';
			}
			if ( document.selection ) {
				// IE support
				bannerField.focus();
				sel = document.selection.createRange();
				sel.text = buttonValue;
			} else if ( bannerField.selectionStart || bannerField.selectionStart == '0' ) {
				// Mozilla support
				var startPos = bannerField.selectionStart;
				var endPos = bannerField.selectionEnd;
				bannerField.value = bannerField.value.substring(0, startPos)
					+ buttonValue
					+ bannerField.value.substring(endPos, bannerField.value.length);
			} else {
				bannerField.value += buttonValue;
			}
			bannerField.focus();
		}
	};

	// Attach event handlers
	$( '#mw-input-wpdelete-button' ).click( mw.centralNotice.adminUi.bannerEditor.doDeleteBanner );
	$( '#mw-input-wparchive-button' ).click( mw.centralNotice.adminUi.bannerEditor.doArchiveBanner );
	$( '#mw-input-wpclone-button' ).click( mw.centralNotice.adminUi.bannerEditor.doCloneBannerDialog );
	$( '#mw-input-wpsave-button' ).click( mw.centralNotice.adminUi.bannerEditor.doSaveBanner );
	$( '#mw-input-wptranslate-language' ).change( mw.centralNotice.adminUi.bannerEditor.updateLanguage );
	$( '#mw-input-wpcreate-landingpage-link' ).change( mw.centralNotice.adminUi.bannerEditor.showHideLpEditBox );

	// And do some initial form work
	mw.centralNotice.adminUi.bannerEditor.showHideLpEditBox();
	$( '#cn-js-error-warn' ).hide();
} )( jQuery, mediaWiki );
