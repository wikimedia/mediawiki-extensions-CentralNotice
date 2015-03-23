/*
 * Placeholder mixin, for smoke testing while working on campaign mixins
 * and related infrastructure. This will be removed before merging to the
 * master branch of the CN repository.
 */
( function ( $, mw ) {

	var mixin = new mw.cnBannerControllerLib.Mixin( 'placeholderCampaignMixin' );

	mixin.setPreBannerHandler( function( params ) {
		mw.log( params );
	} );

	mixin.setPostBannerHandler( function( params ) {
		mw.log( params );
	} );

	mw.cnBannerControllerLib.registerCampaginMixin( mixin );

} )( jQuery, mediaWiki );