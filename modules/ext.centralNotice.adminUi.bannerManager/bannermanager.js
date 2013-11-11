/**
 * Backing JS for Special:CentralNoticeBanners, the banner list view form.
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
	mw.centralNotice.adminUi.bannerManagement = {
		/**
		 * State tracking variable for the number of items currently selected
		 * @protected
		 */
		selectedItemCount: 0,

		/**
		 * State tracking variable for the number of items available to be selected
		 * @protected
		 */
		totalSelectableItems: 0,

		/**
		 * Display the 'Create Banner' dialog
		 * @returns {boolean}
		 */
		doAddBannerDialog: function() {
			var buttons = {},
				okButtonText = mw.message('centralnotice-add-notice-button').text(),
				cancelButtonText = mw.message('centralnotice-add-notice-cancel-button').text(),
				dialogObj = $('<form></form>');

			// Implement the functionality
			buttons[ cancelButtonText ] = function() { $(this).dialog("close"); };
			buttons[ okButtonText ] = function() {
				var formobj = $('#cn-banner-manager')[0];
				formobj.wpaction.value = 'create';
				formobj.wpnewBannerName.value = $(this)[0].wpnewBannerName.value;
				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			dialogObj[0].name = 'addBannerDialog';
			dialogObj.append( $( '#cn-formsection-addBanner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'centralnotice-add-new-banner-title' ).text(),
					modal: true,
					buttons: buttons,
					width: 400
				});

			// Do not submit the form... that's up to the ok button
			return false;
		},

		/**
		 * Asks the user if they actually wish to delete the selected banners and if yes will submit
		 * the form with the 'remove' action.
		 */
		doRemoveBanners: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				deleteText = mw.message( 'centralnotice-delete-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-delete-banner-cancel' ).text();

			buttons[ deleteText ] = function() {
				var formobj = $( '#cn-banner-manager' )[0];
				formobj.wpaction.value = 'remove';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'centralnotice-delete-banner-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message(
					'centralnotice-delete-banner-title',
					mw.centralNotice.adminUi.bannerManagement.selectedItemCount
				).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveBanners: function() {
			var dialogObj = $( '<div></div>' ),
				buttons = {},
				archiveText = mw.message( 'centralnotice-archive-banner' ).text(),
				cancelButtonText = mw.message('centralnotice-archive-banner-cancel').text();

			buttons[ archiveText ] = function() {
				var formobj = $('#cn-banner-manager')[0];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function() {  $( this ).dialog( "close" ); };

			dialogObj.text( mw.message( 'centralnotice-archive-banner-confirm' ).text() );
			dialogObj.dialog({
				title: mw.message(
					'centralnotice-archive-banner-title',
					mw.centralNotice.adminUi.bannerManagement.selectedItemCount
				).text(),
				resizable: false,
				modal: true,
				buttons: buttons
			});
		},

		/**
		 * Updates all the banner check boxes when the 'checkAll' check box is clicked
		 */
		checkAllStateAltered: function() {
			var checkBoxes = $( 'input.cn-bannerlist-check-applyto' );
			if ( $( '#mw-input-wpselectAllBanners' ).prop( 'checked' ) ) {
				mw.centralNotice.adminUi.bannerManagement.selectedItemCount =
					mw.centralNotice.adminUi.bannerManagement.totalSelectableItems;
				checkBoxes.each( function() { $( this ).prop( 'checked', true ); } );
			} else {
				mw.centralNotice.adminUi.bannerManagement.selectedItemCount = 0;
				checkBoxes.each( function() { $( this ).prop( 'checked', false ); } );
			}
			mw.centralNotice.adminUi.bannerManagement.checkedCountUpdated();
		},

		/**
		 * Updates the 'checkAll' check box if any of the banner check boxes are checked
		 */
		selectCheckStateAltered: function() {
			if ( $( this ).prop( 'checked' ) === true ) {
				mw.centralNotice.adminUi.bannerManagement.selectedItemCount++;
			} else {
				mw.centralNotice.adminUi.bannerManagement.selectedItemCount--;
			}
			mw.centralNotice.adminUi.bannerManagement.checkedCountUpdated();
		},

		/**
		 *
		 */
		checkedCountUpdated: function () {
			var selectAllCheck = $( '#mw-input-wpselectAllBanners' ),
				deleteButton = $(' #mw-input-wpdeleteSelectedBanners' );

			if ( mw.centralNotice.adminUi.bannerManagement.selectedItemCount ==
				mw.centralNotice.adminUi.bannerManagement.totalSelectableItems
			) {
				// Everything selected
				selectAllCheck.prop( 'checked', true );
				selectAllCheck.prop( 'indeterminate', false );
				deleteButton.prop( 'disabled', false );
			} else if ( mw.centralNotice.adminUi.bannerManagement.selectedItemCount === 0 ) {
				// Nothing selected
				selectAllCheck.prop( 'checked', false );
				selectAllCheck.prop( 'indeterminate', false );
				deleteButton.prop( 'disabled', true );
			} else {
				// Some things selected
				selectAllCheck.prop( 'checked', true );
				selectAllCheck.prop( 'indeterminate', true );
				deleteButton.prop( 'disabled', false );
			}
		}
	};

	// Attach event handlers
	$( '#mw-input-wpaddNewBanner' ).click( mw.centralNotice.adminUi.bannerManagement.doAddBannerDialog );
	$( '#mw-input-wpdeleteSelectedBanners' ).click( mw.centralNotice.adminUi.bannerManagement.doRemoveBanners );
	$( '#mw-input-wparchiveSelectedBanners' ).click( mw.centralNotice.adminUi.bannerManagement.doArchiveBanners );
	$( '#mw-input-wpselectAllBanners' ).click( mw.centralNotice.adminUi.bannerManagement.checkAllStateAltered );
	$( 'input.cn-bannerlist-check-applyto' ).each( function() {
		$( this ).click( mw.centralNotice.adminUi.bannerManagement.selectCheckStateAltered );
		mw.centralNotice.adminUi.bannerManagement.totalSelectableItems++;
	} );

	// Some initial display work
	mw.centralNotice.adminUi.bannerManagement.checkAllStateAltered();
	$( '#cn-js-error-warn' ).hide();

} )( jQuery, mediaWiki );
