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
( function () {
	let bannerEditor, bannerName, $previewFieldSet, $previewContent, $bannerMessages,
		fileScopedOpenExternalPreview;

	// Prefix for key used to store banner preview content for external preview.
	// Coordinate with PREVIEW_STORAGE_KEY_PREFIX in ext.centralNotice.display.js
	const PREVIEW_STORAGE_KEY_PREFIX = 'cn-banner-preview-';

	function doPurgeCache() {
		const language = $( '#cn-cdn-cache-language' ).val(),
			messageId = 'centralnotice-purge-cache-' + language;

		// Do nothing if the button was disabled (from lack of CN admin rights)
		if ( $( '#cn-cdn-cache-purge' ).prop( 'disabled' ) ) {
			return;
		}

		// Show notification with info if the background call takes a while to return
		const waiting = setTimeout( () => {
			mw.notify( mw.message( 'centralnotice-banner-cdn-dialog-waiting-text' ).text(), {
				autoHide: false,
				tag: messageId
			} );
		}, 300 );

		new mw.Api().postWithToken( 'csrf', {
			action: 'centralnoticecdncacheupdatebanner',
			banner: bannerName,
			language: language
		}, {
			timeout: 2000
		} ).always( () => {
			clearTimeout( waiting );
		} ).fail( ( code, result ) => {
			let text = mw.message( 'centralnotice-banner-cdn-dialog-error' ).text();

			if ( result && result.error && result.error.info ) {
				text += ' (' + result.error.info + ')';
			} else if ( result && result.exception ) {
				text += ' (' + result.exception + ')';
			}

			mw.notify( text, {
				type: 'error',
				tag: messageId
			} );
		} ).done( () => {
			mw.notify( mw.message( 'centralnotice-banner-cdn-dialog-success' ).text(), {
				type: 'success',
				tag: messageId
			} );
		} );
	}

	/**
	 * Collects unsaved messages values from banner editing form (if any)
	 *
	 * @return {Object}
	 */
	function getUnsavedMessagesValues() {
		const bannerMessagesCache = {};

		if ( $bannerMessages.length ) {
			$bannerMessages.each( ( i, message ) => {
				const label = $( message ).find( 'label' ).text(),
					value = $( message ).find( 'textarea' ).val();
				bannerMessagesCache[ label ] = value;
			} );
		}

		return bannerMessagesCache;
	}

	/**
	 * Renders banner content preview in live preview section.
	 *
	 * @param {boolean} openExternalPreview
	 */
	function fetchAndUpdateBannerPreview( openExternalPreview ) {
		const $bannerContentTextArea = $( '#mw-input-wpbanner-body' );
		const bannerMessagesCache = getUnsavedMessagesValues();
		const url = new URL( mw.config.get( 'wgCentralNoticeActiveBannerDispatcher' ), location );

		// Set this file-scoped variable so the callback knows whether to open the
		// external preview.
		fileScopedOpenExternalPreview = openExternalPreview;

		// Activate the barbershop "loading" animation
		$previewFieldSet.attr( 'disabled', true );

		// Send the raw unsaved banner content and messages for server-side rendering.
		// This will call bannerEditor.updateBannerPreview().
		$.post( url.toString(),
			{
				banner: bannerName,
				previewcontent: $bannerContentTextArea.val(),
				previewmessages: bannerMessagesCache,
				token: mw.user.tokens.get( 'csrfToken' )
			}
		).fail( ( jqXHR, status, error ) => {
			bannerEditor.handleBannerLoaderError( status + ': ' + error );
		} ).always( () => {
			// De-activate the barbershop "loading" animation
			$previewFieldSet.attr( 'disabled', false );
		} );
	}

	// TODO Several functions exposed aren't used elsewhere, so they should be private
	mw.centralNotice.adminUi.bannerEditor = bannerEditor = {

		/**
		 * Display the 'Create Banner' dialog
		 *
		 * @return {boolean}
		 */
		doCloneBannerDialog: function () {
			const buttons = {},
				okButtonText = mw.message( 'centralnotice-clone' ).text(),
				cancelButtonText = mw.message( 'centralnotice-clone-cancel' ).text(),
				$dialogObj = $( '<form>' );

			// Implement the functionality
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};
			buttons[ okButtonText ] = function () {

				// We'll submit the real form (not the one in the dialog).
				// Copy in values to that form before submitting.
				const formobj = $( '#cn-banner-editor' )[ 0 ];
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
			$dialogObj[ 0 ].name = 'addBannerDialog';
			$dialogObj.append( $( '#cn-formsection-clone-banner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'centralnotice-clone-notice' ).escaped(),
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
			if ( $( '#mw-input-wpbanner-body' ).prop( 'value' ).includes( 'document.write' ) ) {
				// eslint-disable-next-line no-alert
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
			const $dialogObj = $( '<form>' ),
				$dialogMessage = $( '<div>' ).addClass( 'cn-dialog-message' ),
				buttons = {},
				deleteText = mw.message( 'centralnotice-delete-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-delete-banner-cancel' ).text();

			// We'll submit the real form (outside the dialog).
			// Copy in values to that form before submitting.
			buttons[ deleteText ] = function () {
				const formobj = $( '#cn-banner-editor' )[ 0 ];
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

			$dialogObj.append( $dialogMessage );
			$dialogMessage.text( mw.message( 'centralnotice-delete-banner-confirm' ).text() );

			$dialogObj.append( $( '#cn-formsection-delete-banner' ).children( 'div' ).clone().show() )
				.dialog( {
					title: mw.message( 'centralnotice-delete-banner-title', 1 ).escaped(),
					modal: true,
					buttons: buttons,
					width: '35em'
				} );
		},

		/**
		 * Submits the form with the archive action.
		 */
		doArchiveBanner: function () {
			const $dialogObj = $( '<div>' ),
				buttons = {},
				archiveText = mw.message( 'centralnotice-archive-banner' ).text(),
				cancelButtonText = mw.message( 'centralnotice-archive-banner-cancel' ).text();

			buttons[ archiveText ] = function () {
				const formobj = $( '#cn-banner-editor' )[ 0 ];
				formobj.wpaction.value = 'archive';
				formobj.submit();
			};
			buttons[ cancelButtonText ] = function () {
				$( this ).dialog( 'close' );
			};

			$dialogObj.text( mw.message( 'centralnotice-archive-banner-confirm' ).text() );
			$dialogObj.dialog( {
				title: mw.message( 'centralnotice-archive-banner-title', 1 ).escaped(),
				resizable: false,
				modal: true,
				buttons: buttons
			} );
		},

		/**
		 * Updates banner preview render
		 *
		 * @param {Object} data
		 */
		updateBannerPreview: function ( data ) {
			const bannerHtml = data.bannerHtml;
			$previewContent.html( bannerHtml );

			if ( fileScopedOpenExternalPreview ) {
				// Put the rendered banner content in LocalStorage, for use
				// by the external preview.
				mw.centralNotice.kvStore.setItem(
					PREVIEW_STORAGE_KEY_PREFIX + bannerName,
					bannerHtml,
					mw.centralNotice.kvStore.contexts.GLOBAL,
					1
				);

				window.open( mw.Title.makeTitle( -1, 'Random' ).getUrl( {
					banner: bannerName,
					force: 1,
					preview: 1
				} ) );
			}
		},

		/**
		 * Display notification when the banner failed to load.
		 *
		 * @param {string} [msg] The body of the error message
		 */
		handleBannerLoaderError: function ( msg ) {
			mw.notify( msg, {
				title: mw.msg( 'centralnotice-preview-loader-error-dialog-title' ),
				type: 'error'
			} );
		},

		/**
		 * Hook function from onclick of the translate language drop down -- will submit the
		 * form in order to update the language of the preview and the displayed translations.
		 */
		updateLanguage: function () {
			const formobj = $( '#cn-banner-editor' )[ 0 ];
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
			let buttonValue;
			const bannerField = document.getElementById( 'mw-input-wpbanner-body' );
			if ( buttonType === 'close' ) {
				buttonValue = '<a href="#" title="' +
					mw.message( 'centralnotice-close-title' ).escaped() +
					'" onclick="mw.centralNotice.hideBanner();return false;">' +
					'<div class="cn-closeButton">' + mw.message( 'centralnotice-close-title' ).escaped() +
					'</div></a>';
			}
			if ( document.selection ) {
				// IE support
				bannerField.focus();
				const sel = document.selection.createRange();
				sel.text = buttonValue;
			} else if ( bannerField.selectionStart || bannerField.selectionStart === 0 ) {
				// Mozilla support
				const startPos = bannerField.selectionStart;
				const endPos = bannerField.selectionEnd;
				bannerField.value = bannerField.value.slice( 0, startPos ) +
					buttonValue +
					bannerField.value.slice( endPos, bannerField.value.length );
			} else {
				bannerField.value += buttonValue;
			}
			bannerField.focus();

			// Trigger preview on close button insertion
			fetchAndUpdateBannerPreview( false );
		}
	};

	// Attach handlers and initialize stuff after document ready
	$( () => {
		const $editSection = $( '#cn-formsection-edit-template' ),
			$previewLink = $( '<a>' ),
			$previewLegend = $( '<legend>' ),
			$previewUpdateButton = $( '<button>' );

		// Create and attach banner preview elements
		$previewFieldSet = $( '<fieldset>' );
		$previewFieldSet.addClass( 'cn-banner-preview-fieldset' );
		$previewLegend.append( $( '<span>' ).text( mw.msg( 'centralnotice-fieldset-preview' ) ) );

		$previewLink.text( mw.msg( 'centralnotice-preview-page' ) );
		$previewLegend.append( $previewLink );
		$previewFieldSet.append( $previewLegend );

		$previewContent = $( '<div>' ).addClass( 'cn-banner-preview-content' );
		$previewFieldSet.append( $previewContent );

		// Preview button: use same css classes as are generated by PHP form
		$previewUpdateButton.addClass( 'cn-formbutton' )
			.addClass( 'webfonts-changed' )
			.attr( 'type', 'button' )
			.text( mw.msg( 'centralnotice-update-preview' ) );

		$previewFieldSet.append( $previewUpdateButton );

		$previewFieldSet.insertBefore( $editSection );

		// Find banner message form elements (if any)
		$bannerMessages = $( '#mw-htmlform-banner-messages' ).find(
			'.mw-htmlform-field-HTMLCentralNoticeBannerMessage' );

		// Attach handlers
		$previewLink.on( 'click', () => {
			fetchAndUpdateBannerPreview( true );
		} );

		$previewUpdateButton.on( 'click', () => {
			fetchAndUpdateBannerPreview( false );
		} );

		$( '#mw-input-wpdelete-button' ).on( 'click', bannerEditor.doDeleteBanner );
		$( '#mw-input-wparchive-button' ).on( 'click', bannerEditor.doArchiveBanner );
		$( '#mw-input-wpclone-button' ).on( 'click', bannerEditor.doCloneBannerDialog );
		$( '#mw-input-wpsave-button' ).on( 'click', bannerEditor.doSaveBanner );
		$( '#mw-input-wptranslate-language' ).on( 'change', bannerEditor.updateLanguage );
		$( '#cn-cdn-cache-purge' ).on( 'click', doPurgeCache );
		$( '#cn-js-error-warn' ).hide();

		// Retrieve banner name sent via data attribute
		bannerName = $( '#centralnotice-data-container' ).data( 'banner-name' );

		// Trigger preview right away
		fetchAndUpdateBannerPreview( false );
	} );

}() );
