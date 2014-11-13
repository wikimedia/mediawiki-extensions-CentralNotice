( function ( $, mw ) {

	// FIXME Temporary location of this object on the mw hierarchy. See FIXME
	// in bannerController.js.
	mw.cnBannerControllerLib = {

		/**
		 * Set possible campaign and banner choices. Called by
		 * ext.centralNotice.bannerChoices.
		 */
		'setChoiceData': function ( choices ) {
			this.choiceData = choices;
		},

		'choiceData': null
	};

} )( jQuery, mediaWiki );