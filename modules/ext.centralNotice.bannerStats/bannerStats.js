/**
 * This script retrieves click-thru rates for all the banners in all the campaigns in
 * wgCentralNoticeAllocationCampaigns. It then adds the rates to the allocation tables.
 */
// TODO: Once the lag issue with retrieving the stats is resolved, finish implementing this functionality
/*
jQuery( document ).ready( function ( $ ) {
	if ( mw.config.exists( 'wgCentralNoticeAllocationCampaigns' ) ) {
		$.each( mw.config.get( 'wgCentralNoticeAllocationCampaigns' ), function ( index, campaignName ) {
			// TODO: Make this url configurable
			var statUrl = '//fundraising-analytics.wikimedia.org/json_reporting/' + campaignName;
			$.ajax( {
				'url': statUrl,
				'type': 'GET',
				'dataType': 'jsonp'
			} )
			.done( function ( data ) {
				// console.debug( "Success" );
				// console.debug( data );
			} )
			.fail( function ( xhr ) {
				// console.debug( "Error" );
				// console.debug( xhr );
			} );
		} );
	}
} );
*/
