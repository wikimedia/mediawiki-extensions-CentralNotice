<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CentralNotice extension\n";
	exit( 1 );
}

class CentralNoticeDB {

	/* Functions */
	/*
	 * Return campaigns in the system within given constraints
	 * By default returns enabled campaigns, if $enabled set to false, returns both enabled and disabled campaigns
	 */
	static function getNotices( $project = false, $language = false, $date = false, $enabled = true, $preferred = false, $location = false ) {
		global $wgCentralDBname;
	
		$notices = array();
		
		// Database setup
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		
		if ( !$date ) {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		} else {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp( $date ) );
		}
		
		$tables = array( 'cn_notices' );
		if ( $language ) {
			$tables[] = 'cn_notice_languages';
		}

		$conds = array(
			'not_project' => array( '', $project ),
			'not_geo' => 0,
			"not_start <= $encTimestamp",
			"not_end >= $encTimestamp",
		);
		// Use whatever conditional arguments got passed in
		if ( $language ) {
			$conds[] = 'nl_notice_id = cn_notices.not_id';
			$conds['nl_language'] = $language;
		}
		if ( $enabled ) {
			$conds['not_enabled'] = 1;
		}
		if ( $preferred ) {
			$conds['not_preferred'] = 1;
		}

		// Pull db data
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
		
		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables = array( 'cn_notices', 'cn_notice_countries' );
			if ( $language ) {
				$tables[] = 'cn_notice_languages';
			}
	
			// Use whatever conditional arguments got passed in
			$conds = array(
				'not_project' => array( '', $project ),
				'not_geo' => 1,
				'nc_notice_id = cn_notices.not_id',
				'nc_country' => $location,
				"not_start <= $encTimestamp",
				"not_end >= $encTimestamp",
			);
			if ( $language ) {
				$conds[] = "nl_notice_id = cn_notices.not_id";
				$conds['nl_language'] = $language;
			}
			
			if ( $enabled ) {
				$conds['not_enabled'] = 1;
			}
			if ( $preferred ) {
				$conds['not_preferred'] = 1;
			}	
			// Pull db data
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

	/*
	 * Given one or more campaign ids, return all banners bound to them
	 */
	static function selectTemplatesAssigned( $campaigns ) {
		global $wgCentralDBname;
		
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		if ( $campaigns ) {
			// Pull templates based on join with assignments
			$res = $dbr->select(
				array(
					'cn_notices',
					'cn_assignments',
					'cn_templates'
				),
				array(
					'tmp_name',
					'SUM(tmp_weight) AS total_weight',
					'tmp_display_anon',
					'tmp_display_account'
				),
				array(
					'cn_notices.not_id' => $campaigns,
					'cn_notices.not_id = cn_assignments.not_id',
					'cn_assignments.tmp_id = cn_templates.tmp_id'
				),
				__METHOD__,
				array(
					'GROUP BY' => 'tmp_name'
				)
			);
		}
		$templates = array();
		foreach ( $res as $row ) {
			$templates[] = array(
				'name' => $row->tmp_name,
				'weight' => intval( $row->total_weight ),
				'display_anon' => intval( $row->tmp_display_anon ),
				'display_account' => intval( $row->tmp_display_account ),
			);
		}
		return $templates;
	}
	
	/*
	 * Return all of the available countries for geotargeting
	 * (This should probably be moved to a core database table at some point.)
	 */
	static function getCountriesList() {
		return array(
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
}
