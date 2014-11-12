<?php

/**
 * Base class for CentralNotice APIs that provide allocation data.
 */
abstract class ApiCentralNoticeAllocationBase extends ApiBase {

	const LANG_FILTER = '/[a-zA-Z0-9\-]+/';

	const PROJECT_FILTER = '/[a-zA-Z0-9_\-]+/';

	/**
	 * @static Obtains the parameter $param, sanitizes by returning the first match to $regex or
	 * $default if there was no match.
	 *
	 * @param string    $param    Name of GET/POST parameter
	 * @param string    $regex    Sanitization regular expression
	 * @param string    $default  Default value to return on error
	 *
	 * @return string The sanitized value
	 */
	protected static function sanitizeText( $param, $regex, $default = null ) {
		$matches = array();

		if ( preg_match( $regex, $param, $matches ) ) {
			return $matches[ 0 ];
		} else {
			return $default;
		}
	}
}
