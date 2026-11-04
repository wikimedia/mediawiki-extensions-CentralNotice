/**
 * Retrieves, processes and stores 'hide' cookies, which prevent banners from
 * showing in certain circumstances. Provides cn.internal.hide.
 */
( function () {

	let category,
		cookieName,
		shouldHide,
		reason;
	const durations = mw.config.get( 'wgNoticeCookieDurations' ),

		HIDE_COOKIE_PREFIX = 'centralnotice_hide_',

		// Maximum duration of hide cookies with reasons other than those
		// configured in wgNoticeCookieDurations.
		MAX_CUSTOM_HIDE_DURATION = 2592000,

		// Remember a donation for a year in localstorage, even if we overwrite the cookie
		DONATION_DATE_STORAGE_KEY = 'centralnotice_donation_date',

		DONATION_DATE_EXPIRY = 60 * 60 * 24 * 365;

	function removeCookie( name ) {
		$.cookie( name || cookieName, null, { path: '/' } );
	}

	/**
	 * Hide object (intended for access from within this RL module)
	 */
	const hide = mw.centralNotice.internal.hide = {

		setCategory: function ( c ) {
			shouldHide = false;
			reason = null;
			category = c;
			cookieName = HIDE_COOKIE_PREFIX + category;
		},

		/**
		 * Gets the cookie for the category and validates it, removing invalid cookies.
		 *
		 * Parses the JSON and returns the stored object.
		 *
		 * This function should not set properties on hide, as this may be called
		 * by external code to check whether the fundraising hide cookie has
		 * a donate reason.
		 *
		 * @param {string} extractCategory
		 * @return {Object|null}
		 */
		extractAndValidateCookie: function ( extractCategory ) {
			const extractCookieName = HIDE_COOKIE_PREFIX + extractCategory,
				rawCookieVal = $.cookie( extractCookieName );

			// No cookie
			if ( !rawCookieVal ) {
				return null;
			}

			// Try to parse the cookie
			try {
				return JSON.parse( rawCookieVal );
			} catch ( e ) {
				// Corrupt cookie contents
				removeCookie( extractCookieName );
			}
			return null;
		},

		processCookie: function () {
			const hideData = hide.extractAndValidateCookie( category );
			if ( !hideData ) {
				return;
			}

			const now = Date.now() / 1000;

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
				( durations[ hideData.reason ] || MAX_CUSTOM_HIDE_DURATION ) ) {
				shouldHide = true;
				reason = hideData.reason;
			}
		},

		getDonationDate: function () {
			const frCookie = hide.extractAndValidateCookie( 'fundraising' );
			let donationTimestamp;
			if ( frCookie && frCookie.reason === 'donate' && typeof frCookie.created === 'number' ) {
				donationTimestamp = frCookie.created;
			} else {
				donationTimestamp = mw.storage.get( DONATION_DATE_STORAGE_KEY );
			}
			return donationTimestamp ? new Date( donationTimestamp * 1000 ) : null;
		},

		shouldHide: function () {
			return shouldHide;
		},

		getReason: function () {
			return reason;
		},

		/**
		 * Set a hide cookies for this domain and others in wgNoticeHideUrls
		 * with the given reason and duration. Note that cookies set on
		 * other wikis via Special:HideBanners will not respect custom
		 * duration parameters.
		 *
		 * @param {string} newReason Reason to store in the hide cookie
		 * @param {number} duration Cookie duration, in seconds
		 */
		setHideCookies: function ( newReason, duration ) {
			const date = new Date(),
				hideData = {
					v: 1,
					created: Math.floor( date.getTime() / 1000 ),
					reason: newReason
				};

			// If this reason doesn't have an entry configured in
			// wgNoticeCookieDurations, don't allow a longer duration than
			// MAX_CUSTOM_HIDE_DURATION.
			if ( !( newReason in durations ) ) {
				duration = Math.min( MAX_CUSTOM_HIDE_DURATION, duration );
			}

			// Re-use the same date object to set the cookie's expiry time
			date.setSeconds( date.getSeconds() + duration );
			if ( category === 'fundraising' ) {
				if ( newReason === 'donate' ) {
					hide.storeDonationDate( hideData.created );
				} else {
					const existingCookie = hide.extractAndValidateCookie( category );
					// If we're overwriting a donation cookie with something else,
					// stash the donation date in localstorage
					if ( existingCookie && existingCookie.reason === 'donate' ) {
						hide.storeDonationDate( existingCookie.created );
					}
				}
			}

			$.cookie(
				cookieName,
				JSON.stringify( hideData ),
				{ expires: date, path: '/' }
			);

			this.fetchHideUrls(
				mw.config.get( 'wgNoticeHideUrls' ),
				category,
				newReason
			);
		},

		/**
		 * Iterate over all configured URLs to hide this category of banner
		 * for all wikis in a cluster
		 *
		 * @param {string[]} hideUrls
		 * @param {string} categoryName Reason to store in the hide cookie
		 * @param {string} newReason Reason to store in the hide cookie
		 * @return {HTMLImageElement[]}
		 */
		fetchHideUrls: function ( hideUrls, categoryName, newReason ) {
			return hideUrls.map( ( hideUrl ) => {
				const url = new URL( hideUrl, location );
				url.searchParams.set( 'category', categoryName );
				url.searchParams.set( 'reason', newReason );

				// NOTE: We can't use sendBeacon here since that would
				// trigger tons of uncachable POST requests from readers.
				const img = document.createElement( 'img' );
				img.src = url.toString();
				return img;
			} );
		},

		setHideWithCloseButtonCookies: function () {
			// 'close' must be in REASONS in ext.centralNotice.display.state
			hide.setHideCookies( 'close', durations.close );
		},

		storeDonationDate: function ( unixTimestamp ) {
			mw.storage.set( DONATION_DATE_STORAGE_KEY, unixTimestamp, DONATION_DATE_EXPIRY );
		}
	};
}() );
