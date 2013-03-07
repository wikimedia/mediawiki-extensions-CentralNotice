<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
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
 */

/**
 * Display worldwide allocation
 */
class SpecialGlobalAllocation extends CentralNotice {
	/**
	 * Optional project filter
	 *
	 * @see $wgNoticeProjects
	 *
	 * @var string $project
	 */
	public $project = null;

	/**
	 * This should always be a lowercase language code.
	 *
	 * @var string $language
	 */
	public $language = null;

	/**
	 * This should always be an uppercase country code.
	 *
	 * @var string $location
	 */
	public $location = null;

	public $device = null;

	/**
	 * Time filter
	 * @var string $timestamp
	 */
	public $timestamp;

	public function __construct() {
		// Register special page
		SpecialPage::__construct( 'GlobalAllocation' );
	}

	public function isListed() {
		return false;
	}

	protected function getRequestParams() {
		$sanitize = function( $param, $regex ) {
			return filter_var( $param, FILTER_VALIDATE_REGEXP, array( 'options' => array( 'regexp' => $regex ) ) );
		};

		$this->project = $sanitize(
			$this->getRequest()->getText( 'project', $this->project ),
			ApiCentralNoticeAllocations::PROJECT_FILTER
		);
		$this->language = $sanitize(
			$this->getRequest()->getText( 'language', $this->language ),
			ApiCentralNoticeAllocations::LANG_FILTER
		);
		$this->location = $sanitize(
			$this->getRequest()->getText( 'country', $this->location ),
			ApiCentralNoticeAllocations::LOCATION_FILTER
		);
		$this->device = $sanitize(
			$this->getRequest()->getText( 'device', $this->device ),
			ApiCentralNoticeAllocations::DEVICE_NAME_FILTER
		);

		$this->timestamp = wfTimestamp( TS_UNIX, $this->getDateTime( 'filter' ) );
	}

	/**
	 * Handle page requests
	 *
	 * Without filters, this will display the allocation of all active banners.
	 */
	public function execute( $sub ) {
		global $wgNoticeProjects, $wgLanguageCode;
		$out = $this->getOutput();

		$this->getRequestParams();

		// Begin output
		$this->setHeaders();

		// Output ResourceLoader module for styling and javascript functions
		$out->addModules( array(
			'ext.centralNotice.adminUi',
		) );

		// Initialize error variable
		$this->centralNoticeError = false;

		// Show summary
		$out->addWikiMsg( 'centralnotice-summary' );

		// Begin Banners tab content
		$out->addHTML( Html::openElement( 'div', array( 'id' => 'preferences' ) ) );

		$htmlOut = '';

		// Begin Allocation selection fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$htmlOut .= Html::openElement( 'form', array( 'method' => 'get' ) );
		$htmlOut .= Html::element( 'h2', array(), $this->msg( 'centralnotice-view-allocation' )->text() );
		$htmlOut .= Xml::tags( 'p', null, $this->msg( 'centralnotice-allocation-instructions' )->text() );

		$htmlOut .= Html::openElement( 'table', array ( 'id' => 'envpicker', 'cellpadding' => 7 ) );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			array( 'style' => 'width: 20%;' ),
			$this->msg( 'centralnotice-project-name' )->text() );
		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= Html::openElement( 'select', array( 'name' => 'project' ) );

		$htmlOut .= Xml::option( $this->msg( 'centralnotice-all' )->text(), '', '' === $this->project );
		foreach ( $wgNoticeProjects as $value ) {
			$htmlOut .= Xml::option( $value, $value, $value === $this->project );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td',
			array( 'valign' => 'top' ),
			$this->msg( 'centralnotice-project-lang' )->text() );
		$htmlOut .= Html::openElement( 'td' );

		// Retrieve the list of languages in user's language
		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		// Make sure the site language is in the list; a custom language code
		// might not have a defined name...
		if( !array_key_exists( $wgLanguageCode, $languages ) ) {
			$languages[$wgLanguageCode] = $wgLanguageCode;
		}

		ksort( $languages );

		$htmlOut .= Html::openElement( 'select', array( 'name' => 'language' ) );

		$htmlOut .= Xml::option( $this->msg( 'centralnotice-all' )->text(), '', '' === $this->language );
		foreach( $languages as $code => $name ) {
			$htmlOut .= Xml::option(
				$this->msg( 'centralnotice-language-listing', $code, $name )->text(),
				$code, $code === $this->language );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );
		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Xml::tags( 'td', array(), $this->msg( 'centralnotice-country' )->text() );
		$htmlOut .= Html::openElement( 'td' );

		$userLanguageCode = $this->getLanguage()->getCode();
		$countries = GeoTarget::getCountriesList( $userLanguageCode );

		$htmlOut .= Html::openElement( 'select', array( 'name' => 'country' ) );

		$htmlOut .= Xml::option( $this->msg( 'centralnotice-all' )->text(), '', '' === $this->location );
		foreach( $countries as $code => $name ) {
			$htmlOut .= Xml::option( $name, $code, $code === $this->location );
		}

		$htmlOut .= Html::closeElement( 'select' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );

		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= $this->msg( 'centralnotice-date' );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= $this->dateSelector( 'filter', true, $this->timestamp );
		$htmlOut .= $this->timeSelector( 'filter', true, $this->timestamp );
		$htmlOut .= Html::closeElement( 'td' );
		$htmlOut .= Html::closeElement( 'tr' );

		$htmlOut .= Html::closeElement( 'table' );

		$htmlOut .= Xml::tags( 'div',
			array( 'class' => 'cn-buttons' ),
			Xml::submitButton( $this->msg( 'centralnotice-apply-filters' )->text() )
		);
		$htmlOut .= Html::closeElement( 'form' );

		// End Allocation selection fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$out->addHTML( $htmlOut );

		$this->showList();

		// End Banners tab content
		$out->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Show a list of banners with allocation. Newer banners are shown first.
	 */
	public function showList() {
		global $wgNoticeNumberOfBuckets;

		// Begin building HTML
		$htmlOut = '';

		// Begin Allocation list fieldset
		$htmlOut .= Html::openElement( 'fieldset', array( 'class' => 'prefsection' ) );

		$languageLabel = $this->language ?
			htmlspecialchars( $this->language ) : $this->msg( 'centralnotice-all' )->text();
		$projectLabel = $this->language ?
			htmlspecialchars( $this->project ) : $this->msg( 'centralnotice-all' )->text();
		$countryLabel = $this->location ?
			htmlspecialchars( $this->location ) : $this->msg( 'centralnotice-all' )->text();
		$deviceLabel = $this->device ?
			htmlspecialchars( $this->device ) : $this->msg( 'centralnotice-all' )->text();

		$htmlOut .= Xml::tags( 'p', null,
			$this->msg(
				'centralnotice-historical-allocation-description',
				$languageLabel,
				$projectLabel,
				$countryLabel,
				$deviceLabel,
				wfTimestamp( TS_ISO_8601, $this->timestamp )
			)->text()
		);

		$this->campaigns = Campaign::getHistoricalCampaigns( wfTimestamp( TS_MW, $this->timestamp ) );

		//FIXME: static func or something to help with this
		$allocContext = new AllocationContext(
			$this->location, $this->language, $this->project,
			null, null, null
		);
		$chooser = new BannerChooser( $allocContext, $this->campaigns );
		$this->campaigns = $chooser->getCampaigns();

		foreach ( $this->campaigns as &$campaign ) {
			if ( !$campaign['geo'] ) {
				// fill out set according to flags, for symmetry with other criteria
				$campaign['countries'] = array_keys( GeoTarget::getCountriesList( 'en' ) );
			}
		}

		$groupings = $this->analyzeGroupings();

		/*
		 * TODO: need to compare a sample of actual allocations within each grouping,
		 * because opaque-ish factors like priority might cause some groupings to be
		 * functionally identical.  Merge these together.
		 */

		foreach ( $groupings as $grouping ) {
			$htmlOut .= Html::element( 'h2', array(), $this->msg( 'centralnotice-notice-heading', $grouping['label'] )->text() );

			$htmlOut .= Html::openElement( 'table',
				array ( 'cellpadding' => 9, 'class' => 'wikitable', 'style' => 'margin: 1em 0;' )
			);
			$htmlOut .= Html::openElement( 'tr' );
			$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
				$this->msg( 'centralnotice-projects' )->text() );
			$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
				$this->msg( 'centralnotice-languages' )->text() );
			$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
				$this->msg( 'centralnotice-countries' )->text() );
			$htmlOut .= Html::closeElement( 'tr' );

			foreach ( $grouping['rows'] as $row ) {
				$htmlOut .= Html::openElement( 'tr', array( 'class'=>'mw-sp-centralnotice-allocationrow' ) );

				$htmlOut .= Html::openElement( 'td' );
				$htmlOut .= $this->listProjects( $row['projects'] );
				$htmlOut .= Html::closeElement( 'td' );

				$htmlOut .= Html::openElement( 'td' );
				$htmlOut .= $this->listLanguages( $row['languages'] );
				$htmlOut .= Html::closeElement( 'td' );

				$htmlOut .= Html::openElement( 'td' );
				$htmlOut .= $this->listCountries( $row['countries'] );
				$htmlOut .= Html::closeElement( 'td' );

				$htmlOut .= Html::closeElement( 'tr' );
			}
			$htmlOut .= Html::closeElement( 'table' );

			$htmlOut .= $this->getBannerAllocationsTable(
				end( $grouping['rows'][0]['projects'] ),
				end( $grouping['rows'][0]['countries'] ),
				end( $grouping['rows'][0]['languages'] ),
				$grouping['rows'][0]['buckets']
			);
		}

		if ( !$groupings ) {
			$htmlOut .= Html::element( 'p', null,
				$this->msg( 'centralnotice-no-allocation' )->text() );
		}

		// End Allocation list fieldset
		$htmlOut .= Html::closeElement( 'fieldset' );

		$this->getOutput()->addHTML( $htmlOut );
	}

	/**
	 * Find the groupings in which allocation is uniform, by finding the
	 * disjoint union of campaign criteria.  Partition each grouping and
	 * express it as a sum of cartesian products for display in a table.
	 *
	 * Take campaigns C and D, with criteria from x and y: Cx,y = ({a, b}, {1, 2}),
	 * and Dx,y = ({a}, {1, 3}).  The intersection will be CDx,y = ({a}, {1}),
	 * and to represent the remaining grouping C-CD will take two cross-product
	 * rows: Cx,y = ({a}, {2}) + ({b}, {1, 2}).
	 */
	protected function analyzeGroupings() {
		$groupings = array();

		// starting with the intersection of all campaigns, working towards the
		// portion of each campaign which does not intersect any others, record
		// all distinct groupings.
		for ( $numIntersecting = count( $this->campaigns ); $numIntersecting > 0; $numIntersecting-- ) {
			$campaignKeys = array_keys( $this->campaigns );
			$combinations = self::makeCombinations( $campaignKeys, $numIntersecting );
			foreach ( $combinations as $intersectingKeys ) {
				$excludeKeys = array_diff( $campaignKeys, $intersectingKeys );

				$result = $this->campaigns[$intersectingKeys[0]];
				$contributing = array();
				$excluding = array();

				foreach ( $intersectingKeys as $key ) {
					$result = CampaignCriteria::intersect( $result, $this->campaigns[$key] );

					$contributing[] = $this->campaigns[$key]['name'];
				}

				if ( $result ) {
					$result = array( $result );
				} else {
					$result = array();
				}
				foreach ( $excludeKeys as $key ) {
					foreach( $result as $row ) {
						if ( CampaignCriteria::intersect( $row, $this->campaigns[$key] ) ) {
							$excluding[] = $this->campaigns[$key]['name'];
							break;
						}
					}
					$result = CampaignCriteria::difference( $result, $this->campaigns[$key] );
				}

				if ( $result ) {
					sort( $contributing );
					if ( $excluding ) {
						sort( $excluding );
						$label = $this->msg( 'centralnotice-excluding-list',
							$this->getLanguage()->commaList( $contributing ),
							$this->getLanguage()->listToText( $excluding )
						);
					} else {
						$label = $this->getLanguage()->commaList( $contributing );
					}

					$groupings[] = array(
						'label' => $label,
						'rows' => $result,
					);
				}
			}
		}

		return $groupings;
	}

	/**
	 * Return every (unordered) combination of $num elements from $list
	 * TODO: don't use recursion.
	 */
	protected static function makeCombinations( array $list, $num ) {
		if ( $num <= 0 or $num > count( $list ) ) {
			throw new Exception( "bad arguments to makeCombinations" );
		}
		if ( $num == count( $list ) ) {
			return array( $list );
		}

		$initialElement = array_shift( $list );

		// combinations without the first element
		$combinations = self::makeCombinations( $list, $num );

		// those including the first element
		if ( $num == 1 ) {
			$combinations[] = array( $initialElement );
		} else {
			foreach ( self::makeCombinations( $list, $num - 1 ) as $innerCombination ) {
				array_unshift( $innerCombination, $initialElement );
				$combinations[] = $innerCombination;
			}
		}
		return $combinations;
	}

	/**
	 * Generate the HTML for an allocation table.
	 *
	 * Given a specific campaign-level criteria, display all unique allocations
	 * variations caused by banner displayAnon, etc. criteria.
	 *
	 * @param $project string Use these campaign-level criteria...
	 * @param $country string
	 * @param $language string
	 * @param $numBuckets array Check allocations in this many buckets
	 * @return string HTML for the table
	 */
	protected function getBannerAllocationsTable( $project, $location, $language, $numBuckets ) {
		// This is annoying.  Within the campaign, banners usually vary by user
		// logged-in status, and bucket.  Determine the allocations and
		// collapse any dimensions which do not vary.
		//
		// TODO: the allocation hash should also be used to collapse groupings which
		// are identical because of e.g. z-index
		$campaignIds = array();
		$banners = array();
		foreach ( $this->campaigns as $campaign ) {
			$campaignIds[] = $campaign['id'];
			foreach ( $campaign['banners'] as $name => $banner ) {
				$banners[] = array(
					'name' => $name,
					'weight' => $banner['weight'],
					'bucket' => $banner['bucket'],
					'campaign' => $campaign['name'],
					'campaign_z_index' => $campaign['preferred'],
					'campaign_num_buckets' => $campaign['buckets'],
					'display_anon' => $banner['display_anon'],
					'display_account' => $banner['display_account'],
				);
			}
		}
		$device = 'desktop'; //XXX
		foreach ( array( true, false ) as $isAnon ) {
			for ( $bucket = 0; $bucket < $numBuckets; $bucket++ ) {
				$device = 'desktop'; //XXX

				$allocContext = new AllocationContext(
					$location, $language, $project,
					$isAnon, $device, $bucket
				);
				$chooser = new BannerChooser( $allocContext, $this->campaigns );

				$variations[$isAnon][$bucket] = $chooser->getBanners();

				$allocSignatures = array();
				foreach ( $variations[$isAnon][$bucket] as $banner ) {
					$allocSignatures[] = "{$banner['name']}:{$banner['allocation']}";
				}
				$hashes[$isAnon][$bucket] = sha1( implode( ";", $allocSignatures ) );
			}
		}

		$variesAnon = false;
		foreach ( range( 0, $numBuckets - 1 ) as $bucket ) {
			if ( $hashes[0][$bucket] != $hashes[1][$bucket] ) {
				$variesAnon = true;
				break;
			}
		}
		if ( !$variesAnon ) {
			unset( $variations[1] );
		}

		$variesBucket = ( $numBuckets > 1 );

		$htmlOut = Html::openElement( 'table',
			array ( 'cellpadding' => 9, 'class' => 'wikitable sortable', 'style' => 'margin: 1em 0;' )
		);
		//$htmlOut .= Html::element( 'caption', array( 'style' => 'font-size: 1.2em;' ), $caption );

		$htmlOut .= Html::openElement( 'tr' );
		$htmlOut .= Html::element( 'th', array( 'width' => '5%' ),
			$this->msg( 'centralnotice-user-role' )->text() );
		if ( $variesBucket ) {
			$htmlOut .= Html::element( 'th', array( 'width' => '5%' ),
				$this->msg( 'centralnotice-bucket' )->text() );
		}
		$htmlOut .= Html::element( 'th', array( 'width' => '5%' ),
			$this->msg( 'centralnotice-percentage' )->text() );
		$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
			$this->msg( 'centralnotice-banner' )->text() );
		$htmlOut .= Html::element( 'th', array( 'width' => '30%' ),
			$this->msg( 'centralnotice-notice' )->text() );
		$htmlOut .= Html::closeElement( 'tr' );

		foreach ( $variations as $isAnon => $bucketVariations ) {
			foreach ( $bucketVariations as $bucket => $banners ) {
				foreach ( $banners as $banner ) {
					$htmlOut .= $this->getBannerAllocationsVariantRow( $banner, $variesAnon, $variesBucket, $isAnon, $bucket );
				}
				if ( !count( $banners ) ) {
					$htmlOut .= $this->getBannerAllocationsVariantRow( null, $variesAnon, $variesBucket, $isAnon, $bucket );
				}
			}
		}

		$htmlOut .= Html::closeElement( 'table' );

		return $htmlOut;
	}

	/**
	 * Print one line of banner allocations.
	 */
	function getBannerAllocationsVariantRow( $banner, $variesAnon, $variesBucket, $isAnon, $bucket ) {
		$htmlOut = '';

		$viewBanner = $this->getTitleFor( 'NoticeTemplate', 'view' );
		$viewCampaign = $this->getTitleFor( 'CentralNotice' );

		// Row begin
		$htmlOut .= Html::openElement( 'tr', array( 'class'=>'mw-sp-centralnotice-allocationrow' ) );

		if ( !$variesAnon ) {
			$anonLabel = $this->msg( 'centralnotice-all' )->text();
		} elseif ( $isAnon ) {
			$anonLabel = $this->msg( 'centralnotice-user-role-anonymous' )->text();
		} else {
			$anonLabel = $this->msg( 'centralnotice-user-role-logged-in' )->text();
		}

		$htmlOut .= Html::openElement( 'td' );
		$htmlOut .= $anonLabel;
		$htmlOut .= Html::closeElement( 'td' );

		if ( $variesBucket ) {
			$bucketLabel = chr( $bucket + 65 );

			$htmlOut .= Html::openElement( 'td' );
			$htmlOut .= $bucketLabel;
			$htmlOut .= Html::closeElement( 'td' );
		}

		if ( $banner ) {
			// Percentage
			$percentage = round( $banner['allocation'] * 100, 2 );

			$htmlOut .= Html::openElement( 'td' );
			$htmlOut .= $this->msg( 'percent' )->numParams( $percentage )->escaped();
			$htmlOut .= Html::closeElement( 'td' );

			// Banner name
			$htmlOut .= Xml::openElement( 'td', array( 'valign' => 'top' ) );
			// The span class is used by bannerstats.js to find where to insert the stats
			$htmlOut .= Html::openElement( 'span',
				array( 'class' => 'cn-'.$banner['campaign'].'-'.$banner['name'] ) );
			$htmlOut .= Linker::link(
				$viewBanner,
				htmlspecialchars( $banner['name'] ),
				array(),
				array( 'template' => $banner['name'] )
			);
			$htmlOut .= Html::closeElement( 'span' );
			$htmlOut .= Html::closeElement( 'td' );

			// Campaign name
			$htmlOut .= Xml::tags( 'td', array( 'valign' => 'top' ),
				Linker::link(
					$viewCampaign,
					htmlspecialchars( $banner['campaign'] ),
					array(),
					array(
						'method' => 'listNoticeDetail',
						'notice' => $banner['campaign']
					)
				)
			);
		} else {
			$htmlOut .= Html::openElement('td');
			$htmlOut .= Xml::tags( 'p', null, $this->msg( 'centralnotice-no-allocation' )->text() );
		}

		// Row end
		$htmlOut .= Html::closeElement( 'tr' );

		return $htmlOut;
	}
}

class CampaignCriteria {
	static protected $criteria = array(
		'projects', 'languages', 'countries'
	);

	public static function intersect( $a, $b ) {
		if ( !$a or !$b ) {
			return false;
		}
		foreach ( self::$criteria as $property ) {
			$intersection[$property] = array_intersect( $a[$property], $b[$property] );
			// When any criteria are perpendicular, there cannot be an intersection
			if ( !$intersection[$property] ) {
				return false;
			}
		}
		$intersection['buckets'] = max( $a['buckets'], $b['buckets'] );
		return $intersection;
	}

	/**
	 * Returns a list of criteria, where each is a simple Cartesian product.
	 *
	 * Taking multiple criteria as an argument helps us do an array_reduce--
	 * The result of a subtraction is usually multiple disjoint sets.
	 *
	 * @param $rows array of CampaignCriteria
	 * @param $b single CampaignCriteria
	 */
	public static function difference( $rows, $b ) {
		if ( !$b ) {
			return $rows;
		}

		$newRows = array();

		foreach ( $rows as $row ) {
			$difference = $row;
			$difference['buckets'] = max( $row['buckets'], $b['buckets'] );

			foreach ( self::$criteria as $property ) {
				$difference[$property] = array_diff( $row[$property], $b[$property] );

				// This entry contains some points, record it
				if ( $difference[$property] ) {
					$newRows[] = $difference;
				}

				// the next entry only needs to contain the remainder, so intersect
				$difference[$property] = array_intersect( $row[$property], $b[$property] );

				if ( !$difference[$property] ) {
					break;
				}
			}
		}

		return $newRows;
	}
}
