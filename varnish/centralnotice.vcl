/**
 * Varnish accelerator for MediaWiki CentralNotice. For more details see
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

/**
 * Include all site specific configuration required for CentralNotice
**/
include "centralnotice.site.vcl";

/**
 * Mangle the incoming URL to request a banner from the backend. We must
 * at the end produce a URL that looks something like the following:
 *
 * http://meta.wikimedia.org/w/index.php/Special:CNVarnishEndpoint?
 *   action=banner&project=wikipedia&country=US
 *
 * The following headers must then be added. (We do this by header so that we
 * can specify them as vary options.)
 *   X-CentralNotice-Language
 *   X-CentralNotice-Anonymous
 *   X-CentralNotice-Bucket
 *   X-CentralNotice-Slot       -- Added by cn_site_get_slot()
 *
 * To deal with testing; we do explicitly append banner= to the backend request
 * if the inbound request contains it.
 *
 * --- Banner Grace ---
 * Expired banners will be served for up to 24hrs to enable
 * always instantaneous responses from this server. The banner slot
 * is of course updated in the background.
**/
sub cn_select_banner {
    // Tell varnish where to go next
    unset req.http.host;    // Will get auto filled later by the backend
    set req.backend = centralnotice;

    // Extract URL params for headers
    set req.http.X-CentralNotice-Language = regsub(req.url, "^.*language=([^&]+)[&]?.*$|^.*()$", "\1");
    set req.http.X-CentralNotice-Anonymous = regsub(req.url, "^.*anonymous=([^&]+)[&]?.*$|^.*()$", "\1");
    set req.http.X-CentralNotice-Bucket = regsub(req.url, "^.*bucket=([^&]+)[&]?.*$|^.*()$", "\1");

    // Mangle the URL
    set req.url = "/index.php/Special:CNVarnishEndpoint?action=banner"
        + "&project=" + regsub(req.url, "^.*project=([^&]+)[&]?.*$|^.*()$", "\1")
        + "&country=" + regsub(req.url, "^.*country=([^&]+)[&]?.*$|^.*()$", "\1")
        + regsub(req.url, "^.*(banner=[^&]+)[&]?.*$|^.*()$", "\1");

    // Choose banner
    call cn_site_get_slot;

    // Site specific URL managling
    call cn_site_url_mangle;

    // Apply the front-end grace
    set req.grace = 24h;
}

/**
 * Varnish entry point - URL normalization
 * - Will reject anything that's not a GET or a CN_PURGE with a 405
 * - Will reject CN_PURGE if IP not in centralnotice_purge_acl
**/
sub vcl_recv {
    if (req.url ~ "getcnbanner") {
        // Normalize the URL first
        call cn_select_banner;

        // Continue to pass to backend if GET
        if (req.request == "GET") {
            return (lookup);
        }

        // Apply ACL to PURGE requests
        if (req.request == "CN_PURGE") {
            if (!client.ip ~ centralnotice_purge_acl) {
                error 403 "CN_PURGE only allowed from known IPs.";
            }
            return (lookup);
        }

        // By default reject everything else
        error 405;
    }
}

/**
 * Varnish - after backend fetch request
 * -- Set the amount of time Varnish will keep the backend request object for
 *    grace responses.
**/
sub vcl_fetch {
    if (req.url ~ "Special:CNVarnishEndpoint?action=banner") {
        set beresp.grace = 24h;
        return (deliver);
    }
    set req.ttl = 30s;
}

/**
 * Varnish - After object has been retrieved from the cache
**/
sub vcl_hit {
    if (req.request == "CN_PURGE") {
        purge;
        error 200 "CN_PURGE executed";
    }
}

/**
 * Varnish - After object has failed to have been retrieved from cache
**/
sub vcl_miss {
    if (req.request == "CN_PURGE") {
        purge;
        error 404 "CN_PURGE not in cache";
    }

    if (req.url ~ "Special:CNVarnishEndpoint?action=banner") {
        set bereq.http.X-CentralNotice-Language = req.http.X-CentralNotice-Language;
        set bereq.http.X-CentralNotice-Anonymous = req.http.X-CentralNotice-Anonymous;
        set bereq.http.X-CentralNotice-Bucket = req.http.X-CentralNotice-Bucket;
        set bereq.http.X-CentralNotice-Slot = req.http.X-CentralNotice-Slot;
    }
}

/**
 * Varnish - After something has return(pass)'d
**/
sub vcl_pass {
    if (req.request == "CN_PURGE") {
        error 500 "CN_PURGE on passed object";
    }
}
