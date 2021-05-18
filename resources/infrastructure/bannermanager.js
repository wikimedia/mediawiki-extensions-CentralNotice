/**
 * Backing JS for Special:CentralNoticeBanners, the banner list view form.
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
( function () {

	var bm;

	bm = mw.centralNotice.adminUi.bannerManagement = {
		/**
		 * State tracking variable for the number of items currently selected
		 *
		 * @protected
		 */
		selectedItemCount: 0,

		/**
		 * State tracking variable for the number of items available to be selected
		 *
		 * @protected
		 */
		totalSelectableItems: 0,

		/**
		 * Display the 'Create Banner' dialog
		 *
		 * @return {boolean}
		 */
		doAddBannerDialog: function () {
			var buttons = {},
				okButtonText = mw.message( 'centralnotice-add-notice-button' ).text(),
				cancelButtonText = mw.message( 'centralnotice-add-notice-cancel-button' ).text(),
				$dialogObj = $( '<form>' ),
				title = mw.message( 'centralnotice-add-new-banner-title' );

			// Implement the functionality
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ okButtonText ] = function () {
				var formobj = $( '#cn-banner-manager' )[ 0 ];
				formobj.wpaction.value = 'create';
				formobj.wpnewBannerName.value = $( this )[ 0 ].wpnewBannerName.value;

				formobj.wpnewBannerTemplate.value = null;
				if ( $( this )[ 0 ].wpcreateFromTemplateCheckbox.checked === true ) {
					formobj.wpnewBannerTemplate.value = $( this )[ 0 ].wpnewBannerTemplate.value;
				}

				formobj.wpnewBannerEditSummary.value =
					$( this )[ 0 ].wpnewBannerEditSummary.value;
				formobj.submit();
			};

			// Create the dialog by copying the textfield element into a new form
			$dialogObj[ 0 ].name = 'addBannerDialog';
			// FIXME: Don't use jQuery#ready
			// eslint-disable-next-line no-jquery/no-ready-shorthand
			$dialogObj.append(
				$( '#cn-formsection-addBanner' ).children( 'div' ).clone().show()
			).ready( function () {
				$( $dialogObj[ 0 ].wpcreateFromTemplateCheckbox )
					.on( 'click', bm.toggleBannerTemplatesDropdown );
			} );

			$dialogObj.dialog( {
				title: title.escaped(),
				modal: true,
				buttons: buttons,
				width: 400
			} );

			// Do not submit the form... that's up to the ok button
			return false;
		},

		toggleBannerTemplatesDropdown: function () {
			if ( this.checked === true ) {
				$( '.mw-htmlform-field-HTMLSelectLimitField' ).removeClass( 'banner-template-dropdown-hidden' );
				$( 'select[name=wpnewBannerTemplate]' ).removeClass( 'banner-template-dropdown-hidden' );
			} else {
				$( '.mw-htmlform-field-HTMLSelectLimitField' ).addClass( 'banner-template-dropdown-hidden' );
				$( 'select[name=wpnewBannerTemplate]' ).addClass( 'banner-template-dropdown-hidden' );
			}
		},

		/**
		 * Asks the user if they actually wish to delete the selected banners and if yes will submit
		 * the form with the 'remove' action.
		 */
		doRemoveBanners: function () {
			var $dialogObj = $( '<form>' ),
				$dialogMessage = $( '<div>' ).addClass( 'cn-dialog-message' ),
				buttons = {},
				deleteText = mw.message( 'centralnotice-delete-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-delete-banner-cancel' ).text();

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ deleteText ] = function () {
				var formobj = $( '#cn-banner-manager' )[ 0 ];
				formobj.wpaction.value = 'remove';

				formobj.wpremoveBannerEditSummary.value =
					$( this )[ 0 ].wpremoveBannerEditSummary.value;

				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			$dialogObj.append( $dialogMessage );
			$dialogMessage.text( mw.message( 'centralnotice-delete-banner-confirm' ).text() );

			$dialogObj.append( $( '#cn-formsection-removeBanner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message(
						'centralnotice-delete-banner-title',
						bm.selectedItemCount
					).escaped(),
					width: '35em',
					modal: true,
					buttons: buttons
				} );
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveBanners: function () {
			var $dialogObj = $( '<div>' ),
				buttons = {},
				archiveText = mw.message( 'centralnotice-archive-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-archive-banner-cancel' ).text();

			buttons[ archiveText ] = function () {
				var formobj = $( '#cn-banner-manager' )[ 0 ];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			$dialogObj.text( mw.message( 'centralnotice-archive-banner-confirm' ).text() );
			$dialogObj.dialog( {
				title: mw.message(
					'centralnotice-archive-banner-title',
					bm.selectedItemCount
				).escaped(),
				resizable: false,
				modal: true,
				buttons: buttons
			} );
		},

		/**
		 * Updates all the banner check boxes when the 'checkAll' check box is clicked
		 */
		checkAllStateAltered: function () {
			var $checkBoxes = $( 'input.cn-bannerlist-check-applyto' );
			if ( $( '#mw-input-wpselectAllBanners' ).prop( 'checked' ) ) {
				bm.selectedItemCount = bm.totalSelectableItems;
				$checkBoxes.each( function () {
					$( this ).prop( 'checked', true );
				} );
			} else {
				bm.selectedItemCount = 0;
				$checkBoxes.each( function () {
					$( this ).prop( 'checked', false );
				} );
			}
			bm.checkedCountUpdated();
		},

		/**
		 * Updates the 'checkAll' check box if any of the banner check boxes are checked
		 */
		selectCheckStateAltered: function () {
			if ( $( this ).prop( 'checked' ) === true ) {
				bm.selectedItemCount++;
			} else {
				bm.selectedItemCount--;
			}
			bm.checkedCountUpdated();
		},

		checkedCountUpdated: function () {
			var $selectAllCheck = $( '#mw-input-wpselectAllBanners' ),
				$deleteButton = $( ' #mw-input-wpdeleteSelectedBanners' );

			if ( bm.selectedItemCount === bm.totalSelectableItems ) {
				// Everything selected
				$selectAllCheck.prop( 'checked', true );
				$selectAllCheck.prop( 'indeterminate', false );
				$deleteButton.prop( 'disabled', false );
			} else if ( bm.selectedItemCount === 0 ) {
				// Nothing selected
				$selectAllCheck.prop( 'checked', false );
				$selectAllCheck.prop( 'indeterminate', false );
				$deleteButton.prop( 'disabled', true );
			} else {
				// Some things selected
				$selectAllCheck.prop( 'checked', true );
				$selectAllCheck.prop( 'indeterminate', true );
				$deleteButton.prop( 'disabled', false );
			}
		},

		/**
		 * Reload the page with a URL query for the requested banner name
		 * filter (or lack thereof).
		 */
		applyFilter: function () {
			var newUri, filterStr;

			filterStr = $( '#mw-input-wpbannerNameFilter' ).val();
			newUri = new mw.Uri();

			// If there's a filter, reload with a filter query param.
			// If there's no filter, reload with no such param.
			if ( filterStr.length > 0 ) {
				filterStr = bm.sanitizeFilterStr( filterStr );
				newUri.extend( { filter: filterStr } );
			} else {
				delete newUri.query.filter;
			}

			location.replace( newUri.toString() );
		},

		/**
		 * Filter text box keypress handler; applies the filter when enter is
		 * pressed.
		 *
		 * @param e
		 */
		filterTextBoxKeypress: function ( e ) {
			if ( e.which === 13 ) {
				bm.applyFilter();
				return false;
			}
		},

		/**
		 * Remove characters not allowed in banner names. See server-side
		 * Banner::isValidBannerName() and
		 * SpecialCentralNotice::sanitizeSearchTerms().
		 *
		 * @param $origFilterStr
		 */
		sanitizeFilterStr: function ( $origFilterStr ) {
			return $origFilterStr.replace( /[^0-9a-zA-Z_-]/g, '' );
		}
	};

	// Attach event handlers
	$( '#mw-input-wpaddNewBanner' ).on( 'click', bm.doAddBannerDialog );
	$( '#mw-input-wpdeleteSelectedBanners' ).on( 'click', bm.doRemoveBanners );
	$( '#mw-input-wparchiveSelectedBanners' ).on( 'click', bm.doArchiveBanners );
	$( '#mw-input-wpselectAllBanners' ).on( 'click', bm.checkAllStateAltered );
	$( '#mw-input-wpfilterApply' ).on( 'click', bm.applyFilter );
	$( '#mw-input-wpbannerNameFilter' ).on( 'keypress', bm.filterTextBoxKeypress );

	$( 'input.cn-bannerlist-check-applyto' ).each( function () {
		$( this ).on( 'click', bm.selectCheckStateAltered );
		bm.totalSelectableItems++;
	} );

	// Some initial display work
	bm.checkAllStateAltered();
	$( '#cn-js-error-warn' ).hide();

}() );
