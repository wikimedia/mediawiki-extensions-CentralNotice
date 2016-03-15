/**
 * CentralNotice Administrative UI - Campaign pager
 */
( function( mw, $ ) {

	jQuery(document).ready( function ( $ ) {

		// Keep data-sort-value attributes for jquery.tablesorter in sync
		$( '.mw-cn-input-check-sort' ).on( 'change click blur', function () {
			$(this).parent( 'td' )
				.data( 'sortValue', Number( this.checked ) );
		} );
	
		// Show or hide archived campaigns
		var $showArchived = $( '#centralnotice-showarchived' );

		if ( $showArchived.length > 0 ) {
	
			$showArchived.click( function () {
				if ( $( this ).prop( 'checked' ) ) {
					$( '.cn-archived-item' ).show();
				} else {
					$( '.cn-archived-item' ).hide();
				}
			} );
		}
	} );
} )( mediaWiki, jQuery );