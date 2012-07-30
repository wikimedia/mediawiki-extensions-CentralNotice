<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

/**
 * Static methods that retrieve information from the database.
 */
class CentralNoticeDB {

	/* Functions */

	/**
	 * @static Returns a list of campaigns. May be filtered on optional constraints.
	 * By default returns only enabled and active campaigns in all projects, languages and
	 * countries.
	 *
	 * @param null|string $project  The name of the project, ie: 'wikipedia'; if null select all
	 *                              projects.
	 * @param null|string $language ISO language code, if null select all languages
	 * @param null|string $location ISO country code, if null select only non geo-targeted
	 *                              campaigns.
	 * @param null|date   $date     Campaigns must start before and end after this date
	 *                              If the parameter is null, it takes the current date/time
	 * @param bool        $enabled  If true, select only active campaigns. If false select all.
	 *
	 * @return array Array of campaign IDs that matched the filter.
	 */
	static function getCampaigns( $project = null, $language = null, $location = null, $date = null,
	                              $enabled = true )
	{
		global $wgCentralDBname;

		$notices = array();

		// Database setup
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		// We will perform two queries (one geo-targeted, the other not) to
		// catch all notices. We do it bifurcated because otherwise the query
		// would be really funky (if even possible) to pass to the underlying
		// DB layer.

		// Therefore... construct the common components : cn_notices
		if ( $date ) {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp( $date ) );
		} else {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp( ) );
		}

		$tables = array( 'cn_notices' );
		$conds  = array(
			"not_start <= $encTimestamp",
			"not_end >= $encTimestamp",
		);

		if ( $enabled ) {
			$conds['not_enabled'] = 1;
		}

		// common components: cn_notice_projects
		if ( $project ) {
			$tables[] = 'cn_notice_projects';

			$conds[] = 'np_notice_id = cn_notices.not_id';
			$conds['np_project'] = $project;
		}

		// common components: language
		if ( $language ) {
			$tables[] = 'cn_notice_languages';

			$conds[] = 'nl_notice_id = cn_notices.not_id';
			$conds['nl_language'] = $language;
		}

		// Pull the notice IDs of the non geotargeted campaigns
		$res = $dbr->select(
			$tables,
			'not_id',
			array_merge( $conds, array( 'not_geo' => 0 ) ),
			__METHOD__
		);

		// Loop through result set and return ids
		foreach ( $res as $row ) {
			$notices[] = $row->not_id;
		}

		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables[] = 'cn_notice_countries';

			$conds['not_geo'] = 1;
			$conds[] = 'nc_notice_id = cn_notices.not_id';
			$conds['nc_country'] = $location;

			// Pull the notice IDs
			$res = $dbr->select(
				$tables,
				'not_id',
				$conds,
				__METHOD__
			);

			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$notices[] = $row->not_id;
			}
		}

		return $notices;
	}

	/**
	 * Return settings for a campaign
	 * @param $campaignName string: The name of the campaign
	 * @param $detailed boolean: Whether or not to include targeting and banner assignment info
	 * @return array an array of settings
	 */
	static function getCampaignSettings( $campaignName, $detailed = true ) {
		global $wgCentralDBname;

		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$campaign = array();

		// Get campaign info from database
		$row = $dbr->selectRow( 'cn_notices',
			array(
				'not_id',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked',
				'not_geo'
			),
			array( 'not_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				'start' => $row->not_start,
				'end' => $row->not_end,
				'enabled' => $row->not_enabled,
				'preferred' => $row->not_preferred,
				'locked' => $row->not_locked,
				'geo' => $row->not_geo
			);
		}

		if ( $detailed ) {
			$projects = CentralNotice::getNoticeProjects( $campaignName );
			$languages = CentralNotice::getNoticeLanguages( $campaignName );
			$geo_countries = CentralNotice::getNoticeCountries( $campaignName );
			$campaign['projects'] = implode( ", ", $projects );
			$campaign['languages'] = implode( ", ", $languages );
			$campaign['countries'] = implode( ", ", $geo_countries );

			$bannersIn = CentralNoticeDB::getCampaignBanners( $row->not_id, true );
			$bannersOut = array();
			// All we want are the banner names and weights
			foreach ( $bannersIn as $key => $row ) {
				$outKey = $bannersIn[$key]['name'];
				$bannersOut[$outKey] = $bannersIn[$key]['weight'];
			}
			// Encode into a JSON string for storage
			$campaign['banners'] = FormatJson::encode( $bannersOut );
		}

		return $campaign;
	}

	/**
	 * Given one or more campaign ids, return all banners bound to them
	 * @param $campaigns array of id numbers
	 * @param $logging boolean whether or not request is for logging (optional)
	 * @return array a 2D array of banners with associated weights and settings
	 */
	static function getCampaignBanners( $campaigns, $logging = false ) {
		global $wgCentralDBname;

		// If logging, read from the master database to avoid concurrency problems
		if ( $logging ) {
			$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		} else {
			$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		}

		$banners = array();

		if ( $campaigns ) {
			$res = $dbr->select(
				array(
					'cn_notices',
					'cn_assignments',
					'cn_templates'
				),
				array(
					'tmp_name',
					'tmp_weight',
					'tmp_display_anon',
					'tmp_display_account',
					'tmp_fundraising',
					'tmp_autolink',
					'tmp_landing_pages',
					'not_name',
					'not_preferred'
				),
				array(
					'cn_notices.not_id' => $campaigns,
					'cn_notices.not_id = cn_assignments.not_id',
					'cn_assignments.tmp_id = cn_templates.tmp_id'
				),
				__METHOD__
			);

			foreach ( $res as $row ) {
				$banners[] = array(
					'name' => $row->tmp_name, // name of the banner
					'weight' => intval( $row->tmp_weight ), // weight assigned to the banner
					'display_anon' => intval( $row->tmp_display_anon ), // display to anonymous users?
					'display_account' => intval( $row->tmp_display_account ), // display to logged in users?
					'fundraising' => intval( $row->tmp_fundraising ), // fundraising banner?
					'autolink' => intval( $row->tmp_autolink ), // automatically create links?
					'landing_pages' => $row->tmp_landing_pages, // landing pages to link to
					'campaign' => $row->not_name, // campaign the banner is assigned to
					'campaign_z_index' => $row->not_preferred, // z level of the campaign
				);
			}
		}
		return $banners;
	}

	/**
	 * Return settings for a banner
	 * @param $bannerName string name of banner
	 * @param $logging boolean whether or not request is for logging (optional)
	 * @return array an array of banner settings
	 */
	static function getBannerSettings( $bannerName, $logging = false ) {
		global $wgCentralDBname;

		$banner = array();

		// If logging, read from the master database to avoid concurrency problems
		if ( $logging ) {
			$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		} else {
			$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		}

		$row = $dbr->selectRow( 'cn_templates',
			array(
				'tmp_display_anon',
				'tmp_display_account',
				'tmp_fundraising',
				'tmp_autolink',
				'tmp_landing_pages'
			),
			array( 'tmp_name' => $bannerName ),
			__METHOD__
		);

		if ( $row ) {
			$banner = array(
				'anon' => $row->tmp_display_anon,
				'account' => $row->tmp_display_account,
				'fundraising' => $row->tmp_fundraising,
				'autolink' => $row->tmp_autolink,
				'landingpages' => $row->tmp_landing_pages
			);
		}

		return $banner;
	}

	/**
	 * See if a given campaign exists in the database
	 * @param $campaignName string
	 * @return bool
	 */
	public static function campaignExists( $campaignName ) {
		 global $wgCentralDBname;
		 $dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		 $eCampaignName = htmlspecialchars( $campaignName );
		 return (bool)$dbr->selectRow( 'cn_notices', 'not_name', array( 'not_name' => $eCampaignName ) );
	}

	/**
	 * See if a given banner exists in the database
	 * @param $bannerName string
	 * @return bool
	 */
	public static function bannerExists( $bannerName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$eBannerName = htmlspecialchars( $bannerName );
		$row = $dbr->selectRow( 'cn_templates', 'tmp_name', array( 'tmp_name' => $eBannerName ) );
		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return all of the available countries for geotargeting
	 * TODO: Move this out of CentralNoticeDB (or rename the class)
	 * @param string $code The language code to return the country list in
	 * @return array
	 */
	static function getCountriesList( $code ) {
		$countries = array();

		if ( is_callable( array( 'CountryNames', 'getNames' ) ) ) {
			// Retrieve the list of countries in user's language (via CLDR)
			$countries = CountryNames::getNames( $code );
		}

		if ( !$countries ) {
			// Use this as fallback if CLDR extension is not enabled
			$countries = array(
				'AF'=>'Afghanistan',
				'AL'=>'Albania',
				'DZ'=>'Algeria',
				'AS'=>'American Samoa',
				'AD'=>'Andorra',
				'AO'=>'Angola',
				'AI'=>'Anguilla',
				'AQ'=>'Antarctica',
				'AG'=>'Antigua and Barbuda',
				'AR'=>'Argentina',
				'AM'=>'Armenia',
				'AW'=>'Aruba',
				'AU'=>'Australia',
				'AT'=>'Austria',
				'AZ'=>'Azerbaijan',
				'BS'=>'Bahamas',
				'BH'=>'Bahrain',
				'BD'=>'Bangladesh',
				'BB'=>'Barbados',
				'BY'=>'Belarus',
				'BE'=>'Belgium',
				'BZ'=>'Belize',
				'BJ'=>'Benin',
				'BM'=>'Bermuda',
				'BT'=>'Bhutan',
				'BO'=>'Bolivia',
				'BA'=>'Bosnia and Herzegovina',
				'BW'=>'Botswana',
				'BV'=>'Bouvet Island',
				'BR'=>'Brazil',
				'IO'=>'British Indian Ocean Territory',
				'BN'=>'Brunei Darussalam',
				'BG'=>'Bulgaria',
				'BF'=>'Burkina Faso',
				'BI'=>'Burundi',
				'KH'=>'Cambodia',
				'CM'=>'Cameroon',
				'CA'=>'Canada',
				'CV'=>'Cape Verde',
				'KY'=>'Cayman Islands',
				'CF'=>'Central African Republic',
				'TD'=>'Chad',
				'CL'=>'Chile',
				'CN'=>'China',
				'CX'=>'Christmas Island',
				'CC'=>'Cocos (Keeling) Islands',
				'CO'=>'Colombia',
				'KM'=>'Comoros',
				'CD'=>'Congo, Democratic Republic of the',
				'CG'=>'Congo',
				'CK'=>'Cook Islands',
				'CR'=>'Costa Rica',
				'CI'=>'Côte d\'Ivoire',
				'HR'=>'Croatia',
				'CU'=>'Cuba',
				'CY'=>'Cyprus',
				'CZ'=>'Czech Republic',
				'DK'=>'Denmark',
				'DJ'=>'Djibouti',
				'DM'=>'Dominica',
				'DO'=>'Dominican Republic',
				'EC'=>'Ecuador',
				'EG'=>'Egypt',
				'SV'=>'El Salvador',
				'GQ'=>'Equatorial Guinea',
				'ER'=>'Eritrea',
				'EE'=>'Estonia',
				'ET'=>'Ethiopia',
				'FK'=>'Falkland Islands (Malvinas)',
				'FO'=>'Faroe Islands',
				'FJ'=>'Fiji',
				'FI'=>'Finland',
				'FR'=>'France',
				'GF'=>'French Guiana',
				'PF'=>'French Polynesia',
				'TF'=>'French Southern Territories',
				'GA'=>'Gabon',
				'GM'=>'Gambia',
				'GE'=>'Georgia',
				'DE'=>'Germany',
				'GH'=>'Ghana',
				'GI'=>'Gibraltar',
				'GR'=>'Greece',
				'GL'=>'Greenland',
				'GD'=>'Grenada',
				'GP'=>'Guadeloupe',
				'GU'=>'Guam',
				'GT'=>'Guatemala',
				'GW'=>'Guinea-Bissau',
				'GN'=>'Guinea',
				'GY'=>'Guyana',
				'HT'=>'Haiti',
				'HM'=>'Heard Island and McDonald Islands',
				'VA'=>'Holy See (Vatican City State)',
				'HN'=>'Honduras',
				'HK'=>'Hong Kong',
				'HU'=>'Hungary',
				'IS'=>'Iceland',
				'IN'=>'India',
				'ID'=>'Indonesia',
				'IR'=>'Iran',
				'IQ'=>'Iraq',
				'IE'=>'Ireland',
				'IL'=>'Israel',
				'IT'=>'Italy',
				'JM'=>'Jamaica',
				'JP'=>'Japan',
				'JO'=>'Jordan',
				'KZ'=>'Kazakhstan',
				'KE'=>'Kenya',
				'KI'=>'Kiribati',
				'KW'=>'Kuwait',
				'KG'=>'Kyrgyzstan',
				'LA'=>'Lao People\'s Democratic Republic',
				'LV'=>'Latvia',
				'LB'=>'Lebanon',
				'LS'=>'Lesotho',
				'LR'=>'Liberia',
				'LY'=>'Libyan Arab Jamahiriya',
				'LI'=>'Liechtenstein',
				'LT'=>'Lithuania',
				'LU'=>'Luxembourg',
				'MO'=>'Macao',
				'MK'=>'Macedonia, Republic of',
				'MG'=>'Madagascar',
				'MW'=>'Malawi',
				'MY'=>'Malaysia',
				'MV'=>'Maldives',
				'ML'=>'Mali',
				'MT'=>'Malta',
				'MH'=>'Marshall Islands',
				'MQ'=>'Martinique',
				'MR'=>'Mauritania',
				'MU'=>'Mauritius',
				'YT'=>'Mayotte',
				'MX'=>'Mexico',
				'FM'=>'Micronesia',
				'MD'=>'Moldova, Republic of',
				'MC'=>'Moldova',
				'MN'=>'Mongolia',
				'ME'=>'Montenegro',
				'MS'=>'Montserrat',
				'MA'=>'Morocco',
				'MZ'=>'Mozambique',
				'MM'=>'Myanmar',
				'NA'=>'Namibia',
				'NR'=>'Nauru',
				'NP'=>'Nepal',
				'AN'=>'Netherlands Antilles',
				'NL'=>'Netherlands',
				'NC'=>'New Caledonia',
				'NZ'=>'New Zealand',
				'NI'=>'Nicaragua',
				'NE'=>'Niger',
				'NG'=>'Nigeria',
				'NU'=>'Niue',
				'NF'=>'Norfolk Island',
				'KP'=>'North Korea',
				'MP'=>'Northern Mariana Islands',
				'NO'=>'Norway',
				'OM'=>'Oman',
				'PK'=>'Pakistan',
				'PW'=>'Palau',
				'PS'=>'Palestinian Territory',
				'PA'=>'Panama',
				'PG'=>'Papua New Guinea',
				'PY'=>'Paraguay',
				'PE'=>'Peru',
				'PH'=>'Philippines',
				'PN'=>'Pitcairn',
				'PL'=>'Poland',
				'PT'=>'Portugal',
				'PR'=>'Puerto Rico',
				'QA'=>'Qatar',
				'RE'=>'Reunion',
				'RO'=>'Romania',
				'RU'=>'Russian Federation',
				'RW'=>'Rwanda',
				'SH'=>'Saint Helena',
				'KN'=>'Saint Kitts and Nevis',
				'LC'=>'Saint Lucia',
				'PM'=>'Saint Pierre and Miquelon',
				'VC'=>'Saint Vincent and the Grenadines',
				'WS'=>'Samoa',
				'SM'=>'San Marino',
				'ST'=>'Sao Tome and Principe',
				'SA'=>'Saudi Arabia',
				'SN'=>'Senegal',
				'CS'=>'Serbia and Montenegro',
				'RS'=>'Serbia',
				'SC'=>'Seychelles',
				'SL'=>'Sierra Leone',
				'SG'=>'Singapore',
				'SK'=>'Slovakia',
				'SI'=>'Slovenia',
				'SB'=>'Solomon Islands',
				'SO'=>'Somalia',
				'ZA'=>'South Africa',
				'KR'=>'South Korea',
				'SS'=>'South Sudan',
				'ES'=>'Spain',
				'LK'=>'Sri Lanka',
				'SD'=>'Sudan',
				'SR'=>'Suriname',
				'SJ'=>'Svalbard and Jan Mayen',
				'SZ'=>'Swaziland',
				'SE'=>'Sweden',
				'CH'=>'Switzerland',
				'SY'=>'Syrian Arab Republic',
				'TW'=>'Taiwan',
				'TJ'=>'Tajikistan',
				'TZ'=>'Tanzania',
				'TH'=>'Thailand',
				'TL'=>'Timor-Leste',
				'TG'=>'Togo',
				'TK'=>'Tokelau',
				'TO'=>'Tonga',
				'TT'=>'Trinidad and Tobago',
				'TN'=>'Tunisia',
				'TR'=>'Turkey',
				'TM'=>'Turkmenistan',
				'TC'=>'Turks and Caicos Islands',
				'TV'=>'Tuvalu',
				'UG'=>'Uganda',
				'UA'=>'Ukraine',
				'AE'=>'United Arab Emirates',
				'GB'=>'United Kingdom',
				'UM'=>'United States Minor Outlying Islands',
				'US'=>'United States',
				'UY'=>'Uruguay',
				'UZ'=>'Uzbekistan',
				'VU'=>'Vanuatu',
				'VE'=>'Venezuela',
				'VN'=>'Vietnam',
				'VG'=>'Virgin Islands, British',
				'VI'=>'Virgin Islands, U.S.',
				'WF'=>'Wallis and Futuna',
				'EH'=>'Western Sahara',
				'YE'=>'Yemen',
				'ZM'=>'Zambia',
				'ZW'=>'Zimbabwe'
			);
		}

		return $countries;
	}
}
