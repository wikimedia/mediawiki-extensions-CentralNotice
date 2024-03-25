<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

use MediaWiki\Extension\UserMerge\Hooks\AccountFieldsHook;

/**
 * All hooks from the UserMerge extension which is optional to use with this extension.
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @file
 * @ingroup Extensions
 *
 * @section LICENSE
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

class UserMergeHookHandlers implements AccountFieldsHook {
	/**
	 * Tell the UserMerge extension where we store user ids
	 * @param array[] &$updateFields
	 */
	public function onUserMergeAccountFields( array &$updateFields ): void {
		global $wgNoticeInfrastructure;
		if ( $wgNoticeInfrastructure ) {
			// array( tableName, idField, textField )
			$updateFields[] = [ 'cn_notice_log', 'notlog_user_id' ];
			$updateFields[] = [ 'cn_template_log', 'tmplog_user_id' ];
		}
	}
}
