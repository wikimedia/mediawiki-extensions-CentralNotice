/**
 * Site specific configuration for CentralNotice. For more information, beyond
 * the standard comments, please see:
 * http://www.mediawiki.org/wiki/Extension:CentralNotice/Caching
 * http://meta.wikimedia.org/wiki/Help:CentralNotice
 *
 * -- License --
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
**/

import std;

/**
 * IP addresses in this ACL are allowed to issue the CN_PURGE
 * command -- ie: purge the CN cache by filter.
**/
acl centralnotice_purge_acl {
    "localhost";
}

/**
 * Define where the banner request backend is located
**/
backend centralnotice {
	.host = "meta.wikimedia.org";
	.port = "http";
}

/**
 * Fills the X-CentralNotice-Slot header in the request object. The number of slots should
 * match that in CentralNotice - by default this is 1 to 30 inclusive.
**/
sub cn_site_get_slot {
    set req.http.X-CentralNotice-Slot = regsub(std.random(1, 31), "^([0-9]*).*$", "\1");
}

/**
 * By default the request gets forwarded to the backend assuming that the MW index.php is in
 * the request root; ie: foo.com/index.php/Special:CNVarnishEndpoint?... This hook allows additional
 * mangling where required (IE: WMF has index.php under /w/).
**/
sub cn_site_url_mangle {
    set req.url = "/w/" + req.url;
}