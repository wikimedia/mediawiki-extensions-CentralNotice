<?php

/**
 * Class with methods that can retrieve information from the database.
 */
class CentralNoticeDB {
	/* Functions */

	/**
	 * Returns a list of campaigns. May be filtered on optional constraints.
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
	public function getCampaigns( $project = null, $language = null, $location = null, $date = null,
	                              $enabled = true ) {
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
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		}

		$tables = array( 'notices' => 'cn_notices' );
		$conds = array(
			"not_start <= $encTimestamp",
			"not_end >= $encTimestamp",
		);

		if ( $enabled ) {
			$conds[ 'not_enabled' ] = 1;
		}

		// common components: cn_notice_projects
		if ( $project ) {
			$tables[ 'notice_projects' ] = 'cn_notice_projects';

			$conds[ ] = 'np_notice_id = notices.not_id';
			$conds[ 'np_project' ] = $project;
		}

		// common components: language
		if ( $language ) {
			$tables[ 'notice_languages' ] = 'cn_notice_languages';

			$conds[ ] = 'nl_notice_id = notices.not_id';
			$conds[ 'nl_language' ] = $language;
		}

		// Pull the notice IDs of the non geotargeted campaigns
		$res = $dbr->select(
			$tables,
			'not_id',
			array_merge( $conds, array( 'not_geo' => 0 ) ),
			__METHOD__
		);
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_id;
		}

		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables[ 'notice_countries' ] = 'cn_notice_countries';

			$conds[ ] = 'nc_notice_id = notices.not_id';
			$conds[ 'nc_country' ] = $location;
			$conds[ 'not_geo' ] = 1;

			// Pull the notice IDs
			$res = $dbr->select(
				$tables,
				'not_id',
				$conds,
				__METHOD__
			);

			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$notices[ ] = $row->not_id;
			}
		}

		return $notices;
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param $campaignName string: The name of the campaign
	 * @param $detailed     boolean: Whether or not to include targeting and banner assignment info
	 *
	 * @return array an array of settings
	 */
	public function getCampaignSettings( $campaignName, $detailed = true ) {
		global $wgCentralDBname;

		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$campaign = array();

		// Get campaign info from database
		$row = $dbr->selectRow(
			array('notices' => 'cn_notices'),
			array(
				'not_id',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked',
				'not_geo',
				'not_buckets',
			),
			array( 'not_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				'start'     => $row->not_start,
				'end'       => $row->not_end,
				'enabled'   => $row->not_enabled,
				'preferred' => $row->not_preferred,
				'locked'    => $row->not_locked,
				'geo'       => $row->not_geo,
				'buckets'   => $row->not_buckets,
			);
		} else {
			return false;
		}

		if ( $detailed ) {
			$projects = $this->getNoticeProjects( $campaignName );
			$languages = $this->getNoticeLanguages( $campaignName );
			$geo_countries = $this->getNoticeCountries( $campaignName );
			$campaign[ 'projects' ] = implode( ", ", $projects );
			$campaign[ 'languages' ] = implode( ", ", $languages );
			$campaign[ 'countries' ] = implode( ", ", $geo_countries );

			$bannersIn = $this->getCampaignBanners( $row->not_id, true );
			$bannersOut = array();
			// All we want are the banner names, weights, and buckets
			foreach ( $bannersIn as $key => $row ) {
				$outKey = $bannersIn[ $key ][ 'name' ];
				$bannersOut[ $outKey ]['weight'] = $bannersIn[ $key ][ 'weight' ];
				$bannersOut[ $outKey ]['bucket'] = $bannersIn[ $key ][ 'bucket' ];
			}
			// Encode into a JSON string for storage
			$campaign[ 'banners' ] = FormatJson::encode( $bannersOut );
		}

		return $campaign;
	}

	/**
	 * Given one or more campaign ids, return all banners bound to them
	 *
	 * @param $campaigns array of id numbers
	 * @param $logging   boolean whether or not request is for logging (optional)
	 *
	 * @return array a 2D array of banners with associated weights and settings
	 */
	public function getCampaignBanners( $campaigns, $logging = false ) {
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
					// Aliases (keys) are needed to avoid problems with table prefixes
				array(
					'notices' => 'cn_notices',
					'assignments' => 'cn_assignments',
					'templates' => 'cn_templates',
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
					'not_preferred',
					'asn_bucket',
					'not_buckets',
				),
				array(
					'notices.not_id' => $campaigns,
					'notices.not_id = assignments.not_id',
					'assignments.tmp_id = templates.tmp_id'
				),
				__METHOD__
			);

			foreach ( $res as $row ) {
				$banners[ ] = array(
					'name'             => $row->tmp_name, // name of the banner
					'weight'           => intval( $row->tmp_weight ), // weight assigned to the banner
					'display_anon'     => intval( $row->tmp_display_anon ), // display to anonymous users?
					'display_account'  => intval( $row->tmp_display_account ), // display to logged in users?
					'fundraising'      => intval( $row->tmp_fundraising ), // fundraising banner?
					'autolink'         => intval( $row->tmp_autolink ), // automatically create links?
					'landing_pages'    => $row->tmp_landing_pages, // landing pages to link to
					'campaign'         => $row->not_name, // campaign the banner is assigned to
					'campaign_z_index' => $row->not_preferred, // z level of the campaign
					'campaign_num_buckets' => intval( $row->not_buckets ),
					'bucket'           => ( intval( $row->not_buckets ) == 1 ) ? 0 : intval( $row->asn_bucket ),
				);
			}
		}
		return $banners;
	}

	/**
	 * Return settings for a banner
	 *
	 * @param $bannerName string name of banner
	 * @param $logging    boolean whether or not request is for logging (optional)
	 *
	 * @return array an array of banner settings
	 */
	public function getBannerSettings( $bannerName, $logging = false ) {
		global $wgCentralDBname;

		$banner = array();

		// If logging, read from the master database to avoid concurrency problems
		if ( $logging ) {
			$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		} else {
			$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		}

		$row = $dbr->selectRow(
			'cn_templates',
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
				'anon'         => $row->tmp_display_anon,
				'account'      => $row->tmp_display_account,
				'fundraising'  => $row->tmp_fundraising,
				'autolink'     => $row->tmp_autolink,
				'landingpages' => $row->tmp_landing_pages
			);
		}

		return $banner;
	}

	/**
	 * DEPRECATED, but included for backwards compatibility during upgrade
	 * Lookup function for active banners under a given language/project/location. This function is
	 * called by SpecialBannerListLoader::getJsonList() in order to build the banner list JSON for
	 * each project.
	 * @deprecated Remove me after upgrade has been completed.
	 * @param $project string
	 * @param $language string
	 * @param $location string
	 * @return array a 2D array of running banners with associated weights and settings
	 */
	public function getBannersByTarget( $project, $language, $location = null ) {
		global $wgCentralDBname;

		$campaigns = array();
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );

		// Pull non-geotargeted campaigns
		$campaignResults1 = $dbr->select(
			// Aliases are needed to avoid problems with table prefixes
			array(
				'notices' => 'cn_notices',
				'cn_notice_projects',
				'cn_notice_languages'
			),
			array(
				'not_id'
			),
			array(
				"not_start <= $encTimestamp",
				"not_end >= $encTimestamp",
				'not_enabled = 1', // enabled
				'not_geo = 0', // not geotargeted
				'np_notice_id = notices.not_id',
				'np_project' => $project,
				'nl_notice_id = notices.not_id',
				'nl_language' => $language
			),
			__METHOD__
		);
		foreach ( $campaignResults1 as $row ) {
			$campaigns[] = $row->not_id;
		}
		if ( $location ) {

			// Normalize location parameter (should be an uppercase 2-letter country code)
			preg_match( '/[a-zA-Z][a-zA-Z]/', $location, $matches );
			if ( $matches ) {
				$location = strtoupper( $matches[0] );

				// Pull geotargeted campaigns
				$campaignResults2 = $dbr->select(
					array(
						'cn_notices',
						'cn_notice_projects',
						'cn_notice_languages',
						'cn_notice_countries'
					),
					array(
						'not_id'
					),
					array(
						"not_start <= $encTimestamp",
						"not_end >= $encTimestamp",
						'not_enabled = 1', // enabled
						'not_geo = 1', // geotargeted
						'nc_notice_id = cn_notices.not_id',
						'nc_country' => $location,
						'np_notice_id = cn_notices.not_id',
						'np_project' => $project,
						'nl_notice_id = cn_notices.not_id',
						'nl_language' => $language
					),
					__METHOD__
				);
				foreach ( $campaignResults2 as $row ) {
					$campaigns[] = $row->not_id;
				}
			}
		}

		$banners = array();
		if ( $campaigns ) {
			// Pull all banners assigned to the campaigns
			$banners = $this->getCampaignBanners( $campaigns );
		}
		return $banners;
	}

	/**
	 * See if a given campaign exists in the database
	 *
	 * @param $campaignName string
	 *
	 * @return bool
	 */
	public function campaignExists( $campaignName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$eCampaignName = htmlspecialchars( $campaignName );
		return (bool)$dbr->selectRow( 'cn_notices', 'not_name', array( 'not_name' => $eCampaignName ) );
	}

	/**
	 * See if a given banner exists in the database
	 *
	 * @param $bannerName string
	 *
	 * @return bool
	 */
	public function bannerExists( $bannerName ) {
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
	 *
	 * @param string $code The language code to return the country list in
	 *
	 * @return array
	 */
	public function getCountriesList( $code ) {
		$countries = array();

		if ( is_callable( array( 'CountryNames', 'getNames' ) ) ) {
			// Retrieve the list of countries in user's language (via CLDR)
			$countries = CountryNames::getNames( $code );
		}

		// If not via CLDR, we have our own list
		if ( !$countries ) {
			// Use this as fallback if CLDR extension is not enabled
			$countries = array(
				'AF'=> 'Afghanistan',
				'AL'=> 'Albania',
				'DZ'=> 'Algeria',
				'AS'=> 'American Samoa',
				'AD'=> 'Andorra',
				'AO'=> 'Angola',
				'AI'=> 'Anguilla',
				'AQ'=> 'Antarctica',
				'AG'=> 'Antigua and Barbuda',
				'AR'=> 'Argentina',
				'AM'=> 'Armenia',
				'AW'=> 'Aruba',
				'AU'=> 'Australia',
				'AT'=> 'Austria',
				'AZ'=> 'Azerbaijan',
				'BS'=> 'Bahamas',
				'BH'=> 'Bahrain',
				'BD'=> 'Bangladesh',
				'BB'=> 'Barbados',
				'BY'=> 'Belarus',
				'BE'=> 'Belgium',
				'BZ'=> 'Belize',
				'BJ'=> 'Benin',
				'BM'=> 'Bermuda',
				'BT'=> 'Bhutan',
				'BO'=> 'Bolivia',
				'BA'=> 'Bosnia and Herzegovina',
				'BW'=> 'Botswana',
				'BV'=> 'Bouvet Island',
				'BR'=> 'Brazil',
				'IO'=> 'British Indian Ocean Territory',
				'BN'=> 'Brunei Darussalam',
				'BG'=> 'Bulgaria',
				'BF'=> 'Burkina Faso',
				'BI'=> 'Burundi',
				'KH'=> 'Cambodia',
				'CM'=> 'Cameroon',
				'CA'=> 'Canada',
				'CV'=> 'Cape Verde',
				'KY'=> 'Cayman Islands',
				'CF'=> 'Central African Republic',
				'TD'=> 'Chad',
				'CL'=> 'Chile',
				'CN'=> 'China',
				'CX'=> 'Christmas Island',
				'CC'=> 'Cocos (Keeling) Islands',
				'CO'=> 'Colombia',
				'KM'=> 'Comoros',
				'CD'=> 'Congo, Democratic Republic of the',
				'CG'=> 'Congo',
				'CK'=> 'Cook Islands',
				'CR'=> 'Costa Rica',
				'CI'=> 'Côte d\'Ivoire',
				'HR'=> 'Croatia',
				'CU'=> 'Cuba',
				'CY'=> 'Cyprus',
				'CZ'=> 'Czech Republic',
				'DK'=> 'Denmark',
				'DJ'=> 'Djibouti',
				'DM'=> 'Dominica',
				'DO'=> 'Dominican Republic',
				'EC'=> 'Ecuador',
				'EG'=> 'Egypt',
				'SV'=> 'El Salvador',
				'GQ'=> 'Equatorial Guinea',
				'ER'=> 'Eritrea',
				'EE'=> 'Estonia',
				'ET'=> 'Ethiopia',
				'FK'=> 'Falkland Islands (Malvinas)',
				'FO'=> 'Faroe Islands',
				'FJ'=> 'Fiji',
				'FI'=> 'Finland',
				'FR'=> 'France',
				'GF'=> 'French Guiana',
				'PF'=> 'French Polynesia',
				'TF'=> 'French Southern Territories',
				'GA'=> 'Gabon',
				'GM'=> 'Gambia',
				'GE'=> 'Georgia',
				'DE'=> 'Germany',
				'GH'=> 'Ghana',
				'GI'=> 'Gibraltar',
				'GR'=> 'Greece',
				'GL'=> 'Greenland',
				'GD'=> 'Grenada',
				'GP'=> 'Guadeloupe',
				'GU'=> 'Guam',
				'GT'=> 'Guatemala',
				'GW'=> 'Guinea-Bissau',
				'GN'=> 'Guinea',
				'GY'=> 'Guyana',
				'HT'=> 'Haiti',
				'HM'=> 'Heard Island and McDonald Islands',
				'VA'=> 'Holy See (Vatican City State)',
				'HN'=> 'Honduras',
				'HK'=> 'Hong Kong',
				'HU'=> 'Hungary',
				'IS'=> 'Iceland',
				'IN'=> 'India',
				'ID'=> 'Indonesia',
				'IR'=> 'Iran',
				'IQ'=> 'Iraq',
				'IE'=> 'Ireland',
				'IL'=> 'Israel',
				'IT'=> 'Italy',
				'JM'=> 'Jamaica',
				'JP'=> 'Japan',
				'JO'=> 'Jordan',
				'KZ'=> 'Kazakhstan',
				'KE'=> 'Kenya',
				'KI'=> 'Kiribati',
				'KW'=> 'Kuwait',
				'KG'=> 'Kyrgyzstan',
				'LA'=> 'Lao People\'s Democratic Republic',
				'LV'=> 'Latvia',
				'LB'=> 'Lebanon',
				'LS'=> 'Lesotho',
				'LR'=> 'Liberia',
				'LY'=> 'Libyan Arab Jamahiriya',
				'LI'=> 'Liechtenstein',
				'LT'=> 'Lithuania',
				'LU'=> 'Luxembourg',
				'MO'=> 'Macao',
				'MK'=> 'Macedonia, Republic of',
				'MG'=> 'Madagascar',
				'MW'=> 'Malawi',
				'MY'=> 'Malaysia',
				'MV'=> 'Maldives',
				'ML'=> 'Mali',
				'MT'=> 'Malta',
				'MH'=> 'Marshall Islands',
				'MQ'=> 'Martinique',
				'MR'=> 'Mauritania',
				'MU'=> 'Mauritius',
				'YT'=> 'Mayotte',
				'MX'=> 'Mexico',
				'FM'=> 'Micronesia',
				'MD'=> 'Moldova, Republic of',
				'MC'=> 'Moldova',
				'MN'=> 'Mongolia',
				'ME'=> 'Montenegro',
				'MS'=> 'Montserrat',
				'MA'=> 'Morocco',
				'MZ'=> 'Mozambique',
				'MM'=> 'Myanmar',
				'NA'=> 'Namibia',
				'NR'=> 'Nauru',
				'NP'=> 'Nepal',
				'AN'=> 'Netherlands Antilles',
				'NL'=> 'Netherlands',
				'NC'=> 'New Caledonia',
				'NZ'=> 'New Zealand',
				'NI'=> 'Nicaragua',
				'NE'=> 'Niger',
				'NG'=> 'Nigeria',
				'NU'=> 'Niue',
				'NF'=> 'Norfolk Island',
				'KP'=> 'North Korea',
				'MP'=> 'Northern Mariana Islands',
				'NO'=> 'Norway',
				'OM'=> 'Oman',
				'PK'=> 'Pakistan',
				'PW'=> 'Palau',
				'PS'=> 'Palestinian Territory',
				'PA'=> 'Panama',
				'PG'=> 'Papua New Guinea',
				'PY'=> 'Paraguay',
				'PE'=> 'Peru',
				'PH'=> 'Philippines',
				'PN'=> 'Pitcairn',
				'PL'=> 'Poland',
				'PT'=> 'Portugal',
				'PR'=> 'Puerto Rico',
				'QA'=> 'Qatar',
				'RE'=> 'Reunion',
				'RO'=> 'Romania',
				'RU'=> 'Russian Federation',
				'RW'=> 'Rwanda',
				'SH'=> 'Saint Helena',
				'KN'=> 'Saint Kitts and Nevis',
				'LC'=> 'Saint Lucia',
				'PM'=> 'Saint Pierre and Miquelon',
				'VC'=> 'Saint Vincent and the Grenadines',
				'WS'=> 'Samoa',
				'SM'=> 'San Marino',
				'ST'=> 'Sao Tome and Principe',
				'SA'=> 'Saudi Arabia',
				'SN'=> 'Senegal',
				'CS'=> 'Serbia and Montenegro',
				'RS'=> 'Serbia',
				'SC'=> 'Seychelles',
				'SL'=> 'Sierra Leone',
				'SG'=> 'Singapore',
				'SK'=> 'Slovakia',
				'SI'=> 'Slovenia',
				'SB'=> 'Solomon Islands',
				'SO'=> 'Somalia',
				'ZA'=> 'South Africa',
				'KR'=> 'South Korea',
				'SS'=> 'South Sudan',
				'ES'=> 'Spain',
				'LK'=> 'Sri Lanka',
				'SD'=> 'Sudan',
				'SR'=> 'Suriname',
				'SJ'=> 'Svalbard and Jan Mayen',
				'SZ'=> 'Swaziland',
				'SE'=> 'Sweden',
				'CH'=> 'Switzerland',
				'SY'=> 'Syrian Arab Republic',
				'TW'=> 'Taiwan',
				'TJ'=> 'Tajikistan',
				'TZ'=> 'Tanzania',
				'TH'=> 'Thailand',
				'TL'=> 'Timor-Leste',
				'TG'=> 'Togo',
				'TK'=> 'Tokelau',
				'TO'=> 'Tonga',
				'TT'=> 'Trinidad and Tobago',
				'TN'=> 'Tunisia',
				'TR'=> 'Turkey',
				'TM'=> 'Turkmenistan',
				'TC'=> 'Turks and Caicos Islands',
				'TV'=> 'Tuvalu',
				'UG'=> 'Uganda',
				'UA'=> 'Ukraine',
				'AE'=> 'United Arab Emirates',
				'GB'=> 'United Kingdom',
				'UM'=> 'United States Minor Outlying Islands',
				'US'=> 'United States',
				'UY'=> 'Uruguay',
				'UZ'=> 'Uzbekistan',
				'VU'=> 'Vanuatu',
				'VE'=> 'Venezuela',
				'VN'=> 'Vietnam',
				'VG'=> 'Virgin Islands, British',
				'VI'=> 'Virgin Islands, U.S.',
				'WF'=> 'Wallis and Futuna',
				'EH'=> 'Western Sahara',
				'YE'=> 'Yemen',
				'ZM'=> 'Zambia',
				'ZW'=> 'Zimbabwe'
			);
		}

		// And we need to add MaxMind specific countries: http://www.maxmind.com/en/iso3166
		$countries['EU'] = wfMessage('centralnotice-country-eu')->inContentLanguage()->text();
		$countries['AP'] = wfMessage('centralnotice-country-ap')->inContentLanguage()->text();
		$countries['A1'] = wfMessage('centralnotice-country-a1')->inContentLanguage()->text();
		$countries['A2'] = wfMessage('centralnotice-country-a2')->inContentLanguage()->text();
		$countries['O1'] = wfMessage('centralnotice-country-o1')->inContentLanguage()->text();

		// We will also add country 'XX' which is a MW specific 'fake' country for when GeoIP
		// does not return any results at all or when something else odd has happened (IE: we
		// fail to parse the country.)
		$countries['XX'] = wfMessage('centralnotice-country-unknown')->inContentLanguage()->text();

		asort( $countries );

		return $countries;
	}

	/**
	 * Get all the campaigns in the database
	 *
	 * @return array an array of campaign names
	 */
	public function getAllCampaignNames() {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'cn_notices', 'not_name', null, __METHOD__ );
		$notices = array();
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_name;
		}
		return $notices;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param $noticeName        string: Name of the campaign
	 * @param $enabled           int: Boolean setting, 0 or 1
	 * @param $startTs           string: Campaign start in UTC
	 * @param $projects          array: Targeted project types (wikipedia, wikibooks, etc.)
	 * @param $project_languages array: Targeted project languages (en, de, etc.)
	 * @param $geotargeted       int: Boolean setting, 0 or 1
	 * @param $geo_countries     array: Targeted countries
	 * @param $user              User adding the campaign
	 *
	 * @throws MWException
	 * @return bool|string True on success, string with message key for error
	 */
	public function addCampaign( $noticeName, $enabled, $startTs, $projects, $project_languages,
								 $geotargeted, $geo_countries, $user ) {
		$noticeName = trim( $noticeName );
		if ( $this->campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-exists';
		} elseif ( empty( $projects ) ) {
			return 'centralnotice-no-project';
		} elseif ( empty( $project_languages ) ) {
			return 'centralnotice-no-language';
		}

		if ( !$geo_countries ) {
			$geo_countries = array();
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		$endTime = strtotime( '+1 hour', wfTimestamp( TS_UNIX, $startTs ) );
		$endTs = wfTimestamp( TS_MW, $endTime );

		$dbw->insert( 'cn_notices',
			array( 'not_name'    => $noticeName,
				'not_enabled' => $enabled,
				'not_start'   => $dbw->timestamp( $startTs ),
				'not_end'     => $dbw->timestamp( $endTs ),
				'not_geo'     => $geotargeted
			)
		);
		$not_id = $dbw->insertId();

		if ( $not_id ) {
			// Do multi-row insert for campaign projects
			$insertArray = array();
			foreach ( $projects as $project ) {
				$insertArray[ ] = array( 'np_notice_id' => $not_id, 'np_project' => $project );
			}
			$dbw->insert( 'cn_notice_projects', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			// Do multi-row insert for campaign languages
			$insertArray = array();
			foreach ( $project_languages as $code ) {
				$insertArray[ ] = array( 'nl_notice_id' => $not_id, 'nl_language' => $code );
			}
			$dbw->insert( 'cn_notice_languages', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			if ( $geotargeted ) {
				// Do multi-row insert for campaign countries
				$insertArray = array();
				foreach ( $geo_countries as $code ) {
					$insertArray[ ] = array( 'nc_notice_id' => $not_id, 'nc_country' => $code );
				}
				$dbw->insert( 'cn_notice_countries', $insertArray,
					__METHOD__, array( 'IGNORE' ) );
			}

			$dbw->commit();

			// Log the creation of the campaign
			$beginSettings = array();
			$endSettings = array(
				'projects'  => implode( ", ", $projects ),
				'languages' => implode( ", ", $project_languages ),
				'countries' => implode( ", ", $geo_countries ),
				'start'     => $dbw->timestamp( $startTs ),
				'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => $enabled,
				'preferred' => 0,
				'locked'    => 0,
				'geo'       => $geotargeted
			);
			$this->logCampaignChange( 'created', $not_id, $user,
				$beginSettings, $endSettings );

			return true;
		}

		throw new MWException( 'insertId() did not return a value.' );
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param $campaignName string: Name of the campaign
	 * @param $user User removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	public function removeCampaign( $campaignName, $user ) {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			array( 'not_name' => $campaignName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			return 'centralnotice-remove-notice-doesnt-exist';
		}
		$row = $dbr->fetchObject( $res );
		if ( $row->not_locked == '1' ) {
			return 'centralnotice-notice-is-locked';
		}

		$this->removeCampaignByName( $campaignName, $user );

		return true;
	}

	private function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = $this->getNoticeId( $campaignName );
		$this->logCampaignChange( 'removed', $campaignId, $user );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->delete( 'cn_assignments', array( 'not_id' => $campaignId ) );
		$dbw->delete( 'cn_notices', array( 'not_name' => $campaignName ) );
		$dbw->delete( 'cn_notice_languages', array( 'nl_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_projects', array( 'np_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_countries', array( 'nc_notice_id' => $campaignId ) );
		$dbw->commit();
	}

	/**
	 * Assign a banner to a campaign at a certain weight
	 * @param $noticeName string
	 * @param $templateName string
	 * @param $weight
	 * @return bool|string True on success, string with message key for error
	 */
	public function addTemplateTo( $noticeName, $templateName, $weight ) {
		$dbr = wfGetDB( DB_SLAVE );

		$eNoticeName = htmlspecialchars( $noticeName );
		$noticeId = $this->getNoticeId( $eNoticeName );
		$templateId = $this->getTemplateId( $templateName );
		$res = $dbr->select( 'cn_assignments', 'asn_id',
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			return 'centralnotice-template-already-exists';
		}
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$noticeId = $this->getNoticeId( $eNoticeName );
		$dbw->insert( 'cn_assignments',
			array(
				'tmp_id'     => $templateId,
				'tmp_weight' => $weight,
				'not_id'     => $noticeId
			)
		);
		$dbw->commit();

		return true;
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	public function getNoticeId( $noticeName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		if ( $row ) {
			return $row->not_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 */
	public function getNoticeName( $noticeId ) {
		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER );
		if ( is_numeric( $noticeId ) ) {
			$row = $dbr->selectRow( 'cn_notices', 'not_name', array( 'not_id' => $noticeId ) );
			if ( $row ) {
				return $row->not_name;
			}
		}
		return null;
	}

	public function getNoticeProjects( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$projects = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_projects', 'np_project',
				array( 'np_notice_id' => $row->not_id ) );
			foreach ( $res as $projectRow ) {
				$projects[ ] = $projectRow->np_project;
			}
		}
		return $projects;
	}

	function getNoticeLanguages( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$languages = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_languages', 'nl_language',
				array( 'nl_notice_id' => $row->not_id ) );
			foreach ( $res as $langRow ) {
				$languages[ ] = $langRow->nl_language;
			}
		}
		return $languages;
	}

	function getNoticeCountries( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$countries = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_countries', 'nc_country',
				array( 'nc_notice_id' => $row->not_id ) );
			foreach ( $res as $countryRow ) {
				$countries[ ] = $countryRow->nc_country;
			}
		}
		return $countries;
	}

	public function getTemplateId( $templateName ) {
		$dbr = wfGetDB( DB_SLAVE );
		$templateName = htmlspecialchars( $templateName );
		$res = $dbr->select( 'cn_templates', 'tmp_id', array( 'tmp_name' => $templateName ) );
		$row = $dbr->fetchObject( $res );
		return $row->tmp_id;
	}

	/**
	 * Remove a banner assignment from a campaign
	 */
	public function removeTemplateFor( $noticeName, $templateName ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$noticeId = $this->getNoticeId( $noticeName );
		$templateId = $this->getTemplateId( $templateName );
		$dbw->delete( 'cn_assignments', array( 'tmp_id' => $templateId, 'not_id' => $noticeId ) );
		$dbw->commit();
	}

	/**
	 * @param $noticeName string
	 * @param $start string Date
	 * @param $end string Date
	 * @return bool|string True on success, string with message key for error
	 */
	function updateNoticeDate( $noticeName, $start, $end ) {
		$dbr = wfGetDB( DB_SLAVE );

		// Start/end don't line up
		if ( $start > $end || $end < $start ) {
			return 'centralnotice-invalid-date-range';
		}

		// Invalid campaign name
		if ( !$this->campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-doesnt-exist';
		}

		// Overlap over a date within the same project and language
		$startDate = $dbr->timestamp( $start );
		$endDate = $dbr->timestamp( $end );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'cn_notices',
			array(
				'not_start' => $startDate,
				'not_end'   => $endDate
			),
			array( 'not_name' => $noticeName )
		);

		return true;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param $noticeName string: Name of the campaign
	 * @param $settingName string: Name of a boolean setting (enabled, locked, or geo)
	 * @param $settingValue int: Value to use for the setting, 0 or 1
	 */
	public function setBooleanCampaignSetting( $noticeName, $settingName, $settingValue ) {
		if ( !$this->campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'cn_notices',
				array( 'not_' . $settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates a numeric setting on a campaign
	 *
	 * @param string $noticeName Name of the campaign
	 * @param string $settingName Name of a numeric setting (preferred)
	 * @param int $settingValue Value to use
	 * @param int $max The max that the value can take, default 1
	 * @param int $min The min that the value can take, default 0
	 * @throws MWException|RangeException
	 */
	public function setNumericCampaignSetting( $noticeName, $settingName, $settingValue, $max = 1, $min = 0 ) {
		if ( $max <= $min ) {
			throw new RangeException( 'Max must be greater than min.' );
		}

		if ( !is_numeric( $settingValue ) ) {
			throw new MWException( 'Setting value must be numeric.' );
		}

		if ( $settingValue > $max ) {
			$settingValue = $max;
		}

		if ( $settingValue < $min ) {
			$settingValue = $min;
		}

		if ( !$this->campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			$dbw = wfGetDB( DB_MASTER );
			$dbw->update( 'cn_notices',
				array( 'not_'.$settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates the weight of a banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $weight       New banner weight
	 */
	function updateWeight( $noticeName, $templateId, $weight ) {
		$dbw = wfGetDB( DB_MASTER );
		$noticeId = $this->getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'tmp_weight' => $weight ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	/**
	 * Updates the bucket of a banner in a campaign. Buckets alter what is shown to the end user
	 * which can affect the relative weight of the banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $bucket       New bucket number
	 */
	function updateBucket( $noticeName, $templateId, $bucket ) {
		$dbw = wfGetDB( DB_MASTER );
		$noticeId = $this->getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'asn_bucket' => $bucket ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	// @todo FIXME: Unused.
	public function updateProjectName( $notice, $projectName ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'cn_notices',
			array( 'not_project' => $projectName ),
			array(
				'not_name' => $notice
			)
		);
	}

	public function updateProjects( $notice, $newProjects ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		// Get the previously assigned projects
		$oldProjects = $this->getNoticeProjects( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned projects
		$addProjects = array_diff( $newProjects, $oldProjects );
		$insertArray = array();
		foreach ( $addProjects as $project ) {
			$insertArray[ ] = array( 'np_notice_id' => $row->not_id, 'np_project' => $project );
		}
		$dbw->insert( 'cn_notice_projects', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated projects
		$removeProjects = array_diff( $oldProjects, $newProjects );
		if ( $removeProjects ) {
			$dbw->delete( 'cn_notice_projects',
				array( 'np_notice_id' => $row->not_id, 'np_project' => $removeProjects )
			);
		}

		$dbw->commit();
	}

	public function updateProjectLanguages( $notice, $newLanguages ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		// Get the previously assigned languages
		$oldLanguages = $this->getNoticeLanguages( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned languages
		$addLanguages = array_diff( $newLanguages, $oldLanguages );
		$insertArray = array();
		foreach ( $addLanguages as $code ) {
			$insertArray[ ] = array( 'nl_notice_id' => $row->not_id, 'nl_language' => $code );
		}
		$dbw->insert( 'cn_notice_languages', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated languages
		$removeLanguages = array_diff( $oldLanguages, $newLanguages );
		if ( $removeLanguages ) {
			$dbw->delete( 'cn_notice_languages',
				array( 'nl_notice_id' => $row->not_id, 'nl_language' => $removeLanguages )
			);
		}

		$dbw->commit();
	}

	public function updateCountries( $notice, $newCountries ) {
		$dbw = wfGetDB( DB_MASTER );

		// Get the previously assigned languages
		$oldCountries = $this->getNoticeCountries( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned countries
		$addCountries = array_diff( $newCountries, $oldCountries );
		$insertArray = array();
		foreach ( $addCountries as $code ) {
			$insertArray[ ] = array( 'nc_notice_id' => $row->not_id, 'nc_country' => $code );
		}
		$dbw->insert( 'cn_notice_countries', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated countries
		$removeCountries = array_diff( $oldCountries, $newCountries );
		if ( $removeCountries ) {
			$dbw->delete( 'cn_notice_countries',
				array( 'nc_notice_id' => $row->not_id, 'nc_country' => $removeCountries )
			);
		}
	}

	/**
	 * Log any changes related to a campaign
	 *
	 * @param $action           string: 'created', 'modified', or 'removed'
	 * @param $campaignId       int: ID of campaign
	 * @param $user             User causing the change
	 * @param $beginSettings    array of campaign settings before changes (optional)
	 * @param $endSettings      array of campaign settings after changes (optional)
	 * @param $beginAssignments array of banner assignments before changes (optional)
	 * @param $endAssignments   array of banner assignments after changes (optional)
	 *
	 * @return integer: ID of log entry (or null)
	 */
	public function logCampaignChange( $action, $campaignId, $user, $beginSettings = array(),
								$endSettings = array(), $beginAssignments = array(), $endAssignments = array()
	) {
		// Only log the change if it is done by an actual user (rather than a testing script)
		if ( $user->getId() > 0 ) { // User::getID returns 0 for anonymous or non-existant users
			$dbw = wfGetDB( DB_MASTER );

			$log = array(
				'notlog_timestamp' => $dbw->timestamp(),
				'notlog_user_id'   => $user->getId(),
				'notlog_action'    => $action,
				'notlog_not_id'    => $campaignId,
				'notlog_not_name'  => $this->getNoticeName( $campaignId )
			);

			foreach ( $beginSettings as $key => $value ) {
				$log[ 'notlog_begin_' . $key ] = $value;
			}
			foreach ( $endSettings as $key => $value ) {
				$log[ 'notlog_end_' . $key ] = $value;
			}

			$dbw->insert( 'cn_notice_log', $log );
			$log_id = $dbw->insertId();
			return $log_id;
		} else {
			return null;
		}
	}

	public function campaignLogs( $campaign=false, $username=false, $start=false, $end=false, $limit=50, $offset=0 ) {

		$conds = array();
		if ( $start ) {
			$conds[] = "notlog_timestamp >= $start";
		}
		if ( $end ) {
			$conds[] = "notlog_timestamp < $end";
		}
		if ( $campaign ) {
			$conds[] = "notlog_not_name LIKE '$campaign'";
		}
		if ( $username ) {
			$user = User::newFromName( $username );
			if ( $user ) {
				$conds[] = "notlog_user_id = {$user->getId()}";
			}
		}

		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select( 'cn_notice_log', '*', $conds,
			'CentralNoticeDB::campaignLogs',
			array(
				"ORDER BY" => "notlog_timestamp DESC",
				"LIMIT" => $limit,
				"OFFSET" => $offset,
			)
		);
		$logs = array();
		foreach ( $res as $row ) {
			$entry = new CampaignLog( $row );
			$logs[] = array_merge( get_object_vars( $entry ), $entry->changes() );
		}
		return $logs;
	}
}
