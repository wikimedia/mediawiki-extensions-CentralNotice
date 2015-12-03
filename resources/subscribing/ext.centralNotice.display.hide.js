/**
 * Retrieves, processes and stores 'hide' cookies, which prevent banners from
 * showing in certain circumstances. Provides cn.internal.hide.
 */
( function ( $, mw ) {

	var hide,
		category,
		cookieName,
		shouldHide = false,
		reason,
		durations = mw.config.get( 'wgNoticeCookieDurations' ),

		HIDE_COOKIE_PREFIX = 'centralnotice_hide_',

		// Maximum duration of hide cookies with reasons other than those
		// configured in wgNoticeCookieDurations.
		MAX_CUSTOM_HIDE_DURATION = 2592000;

	function removeCookie() {
		$.cookie( cookieName, null, { path: '/' } );
	}

	/**
	 * Hide object (intended for access from within this RL module)
	 */
	hide = mw.centralNotice.internal.hide = {

		setCategory: function ( c ) {
			category = c;
			cookieName = HIDE_COOKIE_PREFIX + category;
		},

		processCookie: function() {
			var rawCookieVal = $.cookie( cookieName ),
				hideData,
				now;

			// No cookie
			if ( !rawCookieVal ) {
				return;
			}

			// An old-format cookie; just delete it :)
			if ( rawCookieVal === 'hide' ) {
				removeCookie();
				return;
			}

			// Try to parse the cookie
			try {
				hideData = JSON.parse( rawCookieVal );
			} catch ( e ){
				// Corrupt cookie contents
				removeCookie();
				return;
			}

			now = new Date().getTime() / 1000;

			// Duration isn't stored in the cookie. Cookies should expire
			// after the duration is up, some hide reasons have server-side
			// duration settings, which we check. This allows us to shorten the
			// effective duration of hide cookies via server config, if
			// necessary.

			// For custom hide reasons with shorter durations than
			// MAX_CUSTOM_HIDE_DURATION, the cookie itself should expire
			// if the duration has been exceeded. In any case, don't allow
			// the cookie to have any effect beyond MAX_CUSTOM_HIDE_DURATION.

			// TODO Update and improve this server-side switch.

			if ( now < hideData.created +
				( durations[hideData.reason] || MAX_CUSTOM_HIDE_DURATION ) ) {
				shouldHide = true;
				reason = hideData.reason;
			}
		},

		shouldHide: function() {
			return shouldHide;
		},

		getReason: function() {
			return reason;
		},

		/**
		 * Set a hide cookies for this domain and others in wgNoticeHideUrls
		 * with the given reason and duration.
		 *
		 * @param {string} reason Reason to store in the hide cookie
		 * @param {number} duration Cookie duration, in seconds
		 */
		setHideCookies: function ( reason, duration ) {
			var date = new Date(),
				hideData = {
					v: 1,
					created: Math.floor( date.getTime() / 1000 ),
					reason: reason
				};

			// If this reason doesn't have an entry configured in
			// wgNoticeCookieDurations, don't allow a longer duration than
			// MAX_CUSTOM_HIDE_DURATION.
			if ( !( reason in durations ) ) {
				duration = Math.min( MAX_CUSTOM_HIDE_DURATION, duration );
			}

			// Re-use the same date object to set the cookie's expiry time
			date.setSeconds( date.getSeconds() + duration );

			$.cookie(
				cookieName,
				JSON.stringify( hideData ),
				{ expires: date, path: '/' }
			);

			// Iterate over all configured URLs to hide this category of banner
			// for all wikis in a cluster
			$.each( mw.config.get( 'wgNoticeHideUrls' ), function( i, val ) {

				var url = new mw.Uri( val );
				url.extend(
					{
						'duration': duration,
						'category': category,
						'reason' : reason
					}
				);

				// TODO Can we use sendBeacon here? Would it be worth it?
				document.createElement( 'img' ).src = url.toString();
			} );
		},

		setHideWithCloseButtonCookies: function() {
			// 'close' must be in REASONS in ext.centralNotice.display.state
			hide.setHideCookies( 'close', durations.close );
		}
	};
} )( jQuery, mediaWiki );
