/**
 * Retrieves, processes and stores 'hide' cookies, which prevent banners from
 * showing in certain circumstances. Provides cn.internal.hide.
 */
( function ( $, mw ) {

	var category,
		cookieName,
		shouldHide = false,
		reason,
		reasonCode,
		durations = mw.config.get( 'wgNoticeCookieDurations' ),

		HIDE_COOKIE_PREFIX = 'centralnotice_hide_',

		REASONS = {
			CLOSE: new Reason( 'close', 1 )
		};

	function Reason( key, code ) {
		this.key = key;
		this.code = code;
	}

	function removeCookie() {
		$.cookie( cookieName, null, { path: '/' } );
	}

	/**
	 * Hide object (intended for access from within this RL module)
	 */
	mw.centralNotice.internal.hide = {

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

			if ( now < hideData.created + durations[hideData.reason] ) {
				shouldHide = true;
				reason = hideData.reason;
				reasonCode = hideData.reasonCode || '';
			}
		},

		shouldHide: function() {
			return shouldHide;
		},

		getReason: function() {
			return reason;
		},

		getReasonCode: function() {
			return reasonCode;
		},

		setHideWithCloseButtonCookies: function() {
			var duration = durations.close,
				date = new Date(),
				hideData = {
					v: 1,
					created: Math.floor( date.getTime() / 1000 ),
					reason: REASONS.CLOSE.key,
					reasonCode: REASONS.CLOSE.code
				};

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
						'reason' : REASONS.CLOSE
					}
				);

				// TODO Can we use sendBeacon here? Would it be worth it?
				document.createElement( 'img' ).src = url.toString();
			} );
		}
	};
} )( jQuery, mediaWiki );