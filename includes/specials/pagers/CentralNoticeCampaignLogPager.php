<?php

class CentralNoticeCampaignLogPager extends ReverseChronologicalPager {
	/** @var Title */
	public $viewPage;
	/** @var SpecialCentralNoticeLogs */
	public $special;

	public function __construct( SpecialCentralNoticeLogs $special ) {
		$this->special = $special;
		parent::__construct();

		// Override paging defaults
		list( $this->mLimit, /* $offset */ ) = $this->mRequest->getLimitOffsetForUser(
			$this->getUser(),
			20,
			''
		);
		$this->mLimitsShown = [ 20, 50, 100 ];

		$this->viewPage = Campaign::getTitleForURL();
	}

	/**
	 * Sort the log list by timestamp
	 * @return string
	 */
	public function getIndexField() {
		return 'notlog_timestamp';
	}

	/**
	 * Pull log entries from the database
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$request = $this->getRequest();

		$filterStartDate = 0;
		$filterEndDate = 0;
		$start = $this->special->getDateValue( 'start' );
		$end = $this->special->getDateValue( 'end' );

		if ( $start ) {
			$filterStartDate = substr( $start, 0, 8 );
		}
		if ( $end ) {
			$filterEndDate = substr( $end, 0, 8 );
		}
		$filterCampaign = $request->getVal( 'campaign' );
		$filterUser = $request->getVal( 'user' );
		$reset = $request->getVal( 'centralnoticelogreset' );

		$info = [
			'tables' => [ 'notice_log' => 'cn_notice_log' ],
			'fields' => '*',
			'conds' => []
		];

		if ( !$reset ) {
			if ( $filterStartDate > 0 ) {
				$filterStartDate = intval( $filterStartDate . '000000' );
				$info['conds'][] = "notlog_timestamp >= $filterStartDate";
			}
			if ( $filterEndDate > 0 ) {
				$filterEndDate = intval( $filterEndDate . '000000' );
				$info['conds'][] = "notlog_timestamp < $filterEndDate";
			}
			if ( $filterCampaign ) {
				$dbr = $this->getDatabase();
				$info['conds'][] = "notlog_not_name " . $dbr->buildLike(
					$dbr->anyString(), $filterCampaign, $dbr->anyString() );
			}
			if ( $filterUser ) {
				$user = User::newFromName( $filterUser );
				if ( $user ) {
					$userId = $user->getId();
					$info['conds']["notlog_user_id"] = $userId;
				}
			}
		}

		return $info;
	}

	/**
	 * Generate the content of each table row (1 row = 1 log entry)
	 * @param stdClass $row
	 * @return string HTML
	 */
	public function formatRow( $row ) {
		global $wgExtensionAssetsPath;

		$lang = $this->getLanguage();

		// Create a user object so we can pull the name, user page, etc.
		$loggedUser = User::newFromId( $row->notlog_user_id );
		// Create the user page link
		$userLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getUserPage(),
			$loggedUser->getName()
		);
		$userTalkLink = $this->special->getLinkRenderer()->makeKnownLink(
			$loggedUser->getTalkPage(),
			$this->msg( 'centralnotice-talk-link' )->text()
		);

		// Create the campaign link
		$campaignLink = $this->special->getLinkRenderer()->makeKnownLink(
			$this->viewPage,
			$row->notlog_not_name,
			[],
			Campaign::getQueryForURL( $row->notlog_not_name )
		);

		// Begin log entry primary row
		$htmlOut = Xml::openElement( 'tr' );

		$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top' ] );
		$notlogId = (int)$row->notlog_id;
		if ( $row->notlog_action !== 'removed' ) {
			$collapsedImg = $this->getLanguage()->isRtl() ?
				'collapsed-rtl.png' :
				'collapsed-ltr.png';

			$htmlOut .= '<a href="javascript:toggleLogDisplay(\'' . $notlogId . '\')">' .
				'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/' . $collapsedImg . '" ' .
				'id="cn-collapsed-' . $notlogId . '" style="display:block;"/>' .
				'<img src="' . $wgExtensionAssetsPath . '/CentralNotice/resources/images/uncollapsed.png" ' .
				'id="cn-uncollapsed-' . $notlogId . '" style="display:none;"/>' .
				'</a>';
		}
		$htmlOut .= Xml::closeElement( 'td' );
		$htmlOut .= Xml::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$lang->date( $row->notlog_timestamp ) . $this->msg( 'word-separator' )->plain() .
				$lang->time( $row->notlog_timestamp )
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-user-links' )
				->rawParams( $userLink, $userTalkLink )
				->parse()
		);
		// Give grep a chance to find the usages:
		// centralnotice-action-created, centralnotice-action-modified,
		// centralnotice-action-removed
		$htmlOut .= Xml::element( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$this->msg( 'centralnotice-action-' . $row->notlog_action )->text()
		);
		$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top', 'class' => 'primary' ],
			$campaignLink
		);

		// TODO temporary code for soft dependency on schema change
		$summary = property_exists( $row, 'notlog_comment' ) ?
			htmlspecialchars( $row->notlog_comment ) : '&nbsp;';

		$htmlOut .= Xml::tags( 'td',
			[ 'valign' => 'top', 'class' => 'primary-summary' ],
			$summary
		);

		$htmlOut .= Xml::tags( 'td', [],
			'&nbsp;'
		);

		// End log entry primary row
		$htmlOut .= Xml::closeElement( 'tr' );

		if ( $row->notlog_action !== 'removed' ) {
			// Begin log entry secondary row
			$htmlOut .= Xml::openElement( 'tr',
				[ 'id' => 'cn-log-details-' . $notlogId, 'style' => 'display:none;' ] );

			$htmlOut .= Xml::tags( 'td', [ 'valign' => 'top' ],
				'&nbsp;' // force a table cell in older browsers
			);
			$htmlOut .= Xml::openElement( 'td', [ 'valign' => 'top', 'colspan' => '6' ] );
			if ( $row->notlog_action == 'created' ) {
				$htmlOut .= $this->showInitialSettings( $row );
			} elseif ( $row->notlog_action == 'modified' ) {
				$htmlOut .= $this->showChanges( $row );
			}
			$htmlOut .= Xml::closeElement( 'td' );

			// End log entry primary row
			$htmlOut .= Xml::closeElement( 'tr' );
		}

		return $htmlOut;
	}

	/**
	 * @param stdClass $row
	 * @return string
	 */
	public function showInitialSettings( $row ) {
		$lang = $this->getLanguage();
		$details = '';
		$wordSeparator = $this->msg( 'word-separator' )->plain();
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-start-timestamp' )->text(),
			$lang->date( $row->notlog_end_start ) . $wordSeparator .
				$lang->time( $row->notlog_end_start )
		)->parse() . "<br />";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-end-timestamp' )->text(),
			$lang->date( $row->notlog_end_end ) . $wordSeparator .
				$lang->time( $row->notlog_end_end )
		)->parse() . "<br />";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-projects' )->text(),
			wfEscapeWikiText( $row->notlog_end_projects )
		)->parse() . "<br />";
		$language_count = count( explode( ', ', $row->notlog_end_languages ) );
		$languageList = '';
		if ( $language_count > 15 ) {
			$languageList = $this->msg( 'centralnotice-multiple-languages' )
				->numParams( $language_count )->text();
		} elseif ( $language_count > 0 ) {
			$languageList = $row->notlog_end_languages;
		}
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-languages' )->text(),
			wfEscapeWikiText( $languageList )
		)->parse() . "<br />";
		$details .= $this->msg(
			'centralnotice-log-label',
			$this->msg( 'centralnotice-geo' )->text(),
			( $row->notlog_end_geo ? 'on' : 'off' )
		)->parse() . "<br />";
		if ( $row->notlog_end_geo ) {
			$country_count = count( explode( ', ', $row->notlog_end_countries ) );
			$countryList = '';
			if ( $country_count > 20 ) {
				$countryList = $this->msg( 'centralnotice-multiple-countries' )
					->numParams( $country_count )->text();
			} elseif ( $country_count > 0 ) {
				$countryList = $row->notlog_end_countries;
			}
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-countries' )->text(),
				wfEscapeWikiText( $countryList )
			)->parse() . "<br />";

			$regions_count = count( explode( ', ', $row->notlog_end_regions ) );
			$regionsList = '';
			if ( $regions_count > 20 ) {
				$regionsList = $this->msg( 'centralnotice-multiple-regions' )
					->numParams( $regions_count )->text();
			} elseif ( $regions_count > 0 ) {
				$regionsList = $row->notlog_end_regions;
			}
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-regions' )->text(),
				wfEscapeWikiText( $regionsList )
			)->parse() . "<br />";

		}
		return $details;
	}

	/**
	 * @param stdClass $row
	 * @return string
	 */
	public function showChanges( $row ) {
		$lang = $this->getLanguage();
		$details = '';
		$wordSeparator = $this->msg( 'word-separator' )->plain();
		if ( $row->notlog_begin_start !== $row->notlog_end_start ) {
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-start-timestamp' )->text(),
				$this->msg(
					'centralnotice-changed',
					$lang->date( $row->notlog_begin_start ) . $wordSeparator .
						$lang->time( $row->notlog_begin_start ),
					$lang->date( $row->notlog_end_start ) . $wordSeparator .
						$lang->time( $row->notlog_end_start )
				)->text()
			)->parse() . "<br />";
		}
		if ( $row->notlog_begin_end !== $row->notlog_end_end ) {
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-end-timestamp' )->text(),
				$this->msg(
					'centralnotice-changed',
					$lang->date( $row->notlog_begin_end ) . $wordSeparator .
						$lang->time( $row->notlog_begin_end ),
					$lang->date( $row->notlog_end_end ) . $wordSeparator .
						$lang->time( $row->notlog_end_end )
				)->text()
			)->parse() . "<br />";
		}
		$details .= $this->testBooleanChange( 'enabled', $row );
		$details .= $this->testPriorityChange( 'preferred', $row );
		$details .= $this->testBooleanChange( 'locked', $row );
		$details .= $this->testBooleanChange( 'geo', $row );
		$details .= $this->testBooleanChange( 'buckets', $row );
		$details .= $this->testPercentageChange( 'throttle', $row );
		$details .= $this->testSetChange( 'projects', $row );
		$details .= $this->testSetChange( 'languages', $row );
		$details .= $this->testSetChange( 'countries', $row );
		$details .= $this->testSetChange( 'regions', $row );
		$details .= $this->testBooleanChange( 'archived', $row );
		$details .= $this->testTypeChange( $row );

		$details .= $this->testTextChange(
			'campaign-mixins',
			$row->notlog_end_mixins,
			$row->notlog_begin_mixins
		);

		if ( $row->notlog_begin_banners !== $row->notlog_end_banners ) {
			// Show changes to banner weights and assignment
			$beginBannersObject = json_decode( $row->notlog_begin_banners, true );
			$endBannersObject = json_decode( $row->notlog_end_banners, true );
			$beginBanners = [];
			$endBanners = [];
			foreach ( $beginBannersObject as $key => $params ) {
				if ( is_array( $params ) ) {
					$weight = $params['weight'];
					$bucket = chr( 65 + $params['bucket'] );
				} else {
					// Legacy, we used to only store the weight
					$weight = $params;
					$bucket = 0;
				}
				$beginBanners[$key] = "$key ($bucket, $weight)";
			}
			foreach ( $endBannersObject as $key => $params ) {
				if ( is_array( $params ) ) {
					$weight = $params['weight'];
					$bucket = chr( 65 + $params['bucket'] );
				} else {
					// Legacy, we used to only store the weight
					$weight = $params;
					$bucket = 0;
				}
				$endBanners[$key] = "$key ($bucket, $weight)";
			}
			if ( $beginBanners ) {
				$before = $lang->commaList( $beginBanners );
			} else {
				$before = $this->msg( 'centralnotice-no-assignments' )->text();
			}
			if ( $endBanners ) {
				$after = $lang->commaList( $endBanners );
			} else {
				$after = $this->msg( 'centralnotice-no-assignments' )->text();
			}
			$details .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-templates' )->text(),
				$this->msg( 'centralnotice-changed', $before, $after )->text()
			)->parse() . "<br />";
		}
		return $details;
	}

	/**
	 * @param string $param
	 * @param stdClass $row
	 * @return string
	 */
	private function testBooleanChange( $param, $row ) {
		$result = '';
		$beginField = 'notlog_begin_' . $param;
		$endField = 'notlog_end_' . $param;
		if ( $row->$beginField !== $row->$endField ) {
			// Give grep a chance to find the usages:
			// centralnotice-enabled, centralnotice-locked, centralnotice-geo, centralnotice-buckets
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					( $row->$beginField
						? $this->msg( 'centralnotice-on' )->text()
						: $this->msg( 'centralnotice-off' )->text() ),
					( $row->$endField
						? $this->msg( 'centralnotice-on' )->text()
						: $this->msg( 'centralnotice-off' )->text() )
				)->text()
			)->parse() . "<br />";
		}
		return $result;
	}

	private function testSetChange( $param, $row ) {
		$result = '';
		$beginField = 'notlog_begin_' . $param;
		$endField = 'notlog_end_' . $param;

		if ( $row->$beginField !== $row->$endField ) {
			$lang = $this->getLanguage();
			$beginSet = [];
			$endSet = [];
			if ( $row->$beginField ) {
				$beginSet = explode( ', ', $row->$beginField );
			}
			if ( $row->$endField ) {
				$endSet = explode( ', ', $row->$endField );
			}
			$added = array_diff( $endSet, $beginSet );
			$removed = array_diff( $beginSet, $endSet );
			$differences = '';
			if ( $added ) {
				$differences .= $this->msg(
					'centralnotice-added', $lang->commaList( $added ) )->text();
				if ( $removed ) {
					$differences .= '; ';
				}
			}
			if ( $removed ) {
				$differences .= $this->msg(
					'centralnotice-removed', $lang->commaList( $removed ) )->text();
			}
			// Give grep a chance to find the usages:
			// centralnotice-projects, centralnotice-languages, centralnotice-countries
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$differences
			)->parse() . "<br />";
		}
		return $result;
	}

	/**
	 * Test for changes to campaign priority
	 * @param string $param
	 * @param stdClass $row
	 * @return string
	 * @suppress PhanPossiblyUndeclaredVariable
	 * @todo Add default to the switches
	 */
	private function testPriorityChange( $param, $row ) {
		$result = '';
		$beginField = 'notlog_begin_' . $param;
		$endField = 'notlog_end_' . $param;
		if ( $row->$beginField !== $row->$endField ) {
			switch ( $row->$beginField ) {
				case CentralNotice::LOW_PRIORITY:
					$beginMessage = $this->msg( 'centralnotice-priority-low' )->text();
					break;
				case CentralNotice::NORMAL_PRIORITY:
					$beginMessage = $this->msg( 'centralnotice-priority-normal' )->text();
					break;
				case CentralNotice::HIGH_PRIORITY:
					$beginMessage = $this->msg( 'centralnotice-priority-high' )->text();
					break;
				case CentralNotice::EMERGENCY_PRIORITY:
					$beginMessage = $this->msg( 'centralnotice-priority-emergency' )->text();
					break;
			}
			switch ( $row->$endField ) {
				case CentralNotice::LOW_PRIORITY:
					$endMessage = $this->msg( 'centralnotice-priority-low' )->text();
					break;
				case CentralNotice::NORMAL_PRIORITY:
					$endMessage = $this->msg( 'centralnotice-priority-normal' )->text();
					break;
				case CentralNotice::HIGH_PRIORITY:
					$endMessage = $this->msg( 'centralnotice-priority-high' )->text();
					break;
				case CentralNotice::EMERGENCY_PRIORITY:
					$endMessage = $this->msg( 'centralnotice-priority-emergency' )->text();
					break;
			}
			// Give grep a chance to find the usages: centralnotice-preferred
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					$beginMessage,
					$endMessage
				)->text()
			)->parse() . "<br />";
		}
		return $result;
	}

	/**
	 * Test for changes to a property interpreted as a percentage
	 * @param string $param name
	 * @param stdClass $row settings
	 * @return string
	 */
	protected function testPercentageChange( $param, $row ) {
		$beginField = 'notlog_begin_' . $param;
		$endField = 'notlog_end_' . $param;
		$result = '';
		if ( $row->$beginField !== $row->$endField ) {
			$beginMessage = strval( $row->$beginField ) . '%';
			$endMessage = strval( $row->$endField ) . '%';
			// Give grep a chance to find the usages: centralnotice-throttle
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					$beginMessage,
					$endMessage
				)->text()
			)->parse() . "<br />";
		}
		return $result;
	}

	protected function testTextChange( $param, $newval, $oldval ) {
		$result = '';
		if ( $oldval !== $newval ) {
			// Give grep a chance to find the usages: centralnotice-landingpages,
			// centralnotice-prioritylangs, centralnotice-controller_mixin, centralnotice-category
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-' . $param )->text(),
				$this->msg(
					'centralnotice-changed',
					wfEscapeWikiText( $oldval ),
					wfEscapeWikiText( $newval )
				)->text()
			)->parse() . "<br/>";
		}
		return $result;
	}

	protected function testTypeChange( $row ) {
		$result = '';

		$oldval = $row->notlog_begin_type;
		$newval = $row->notlog_end_type;

		if ( $oldval !== $newval ) {
			$result .= $this->msg(
				'centralnotice-log-label',
				$this->msg( 'centralnotice-campaign-type' )->text(),
				$this->msg(
					'centralnotice-changed',
					$this->getTypeText( $oldval ),
					$this->getTypeText( $newval )
				)->text()
			)->parse() . "<br/>";
		}

		return $result;
	}

	private function getTypeText( $typeId ) {
		// This is the case for no type set; $typeId should be null.
		if ( !$typeId ) {
			return $this->msg( 'centralnotice-empty-campaign-type-option' )->plain();
		}

		$type = CampaignType::getById( $typeId );

		// This is the case for a type that exists in the logs but not in the config
		if ( !$type ) {
			return $typeId;
		}

		$message = $this->msg( $type->getMessageKey() );

		// This is the case for a type that exists in the logs and the config but has no
		// associated i18n message
		if ( !$message->exists() ) {
			return $typeId;
		}

		return $message->plain();
	}

	/**
	 * Specify table headers
	 * @return string HTML
	 */
	public function getStartBody() {
		$htmlOut = '';
		$htmlOut .= Xml::openElement( 'table', [ 'id' => 'cn-campaign-logs', 'cellpadding' => 3 ] );
		$htmlOut .= Xml::openElement( 'tr' );
		$htmlOut .= Xml::element( 'th', [ 'style' => 'width: 20px;' ] );
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 130px;' ],
			$this->msg( 'centralnotice-timestamp' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-user' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 100px;' ],
			$this->msg( 'centralnotice-action' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 160px;' ],
			$this->msg( 'centralnotice-notice' )->text()
		);
		$htmlOut .= Xml::element( 'th', [ 'align' => 'left', 'style' => 'width: 250px;' ],
			$this->msg( 'centralnotice-change-summary-heading' )->text()
		);
		$htmlOut .= Xml::tags( 'td', [],
			'&nbsp;'
		);
		$htmlOut .= Xml::closeElement( 'tr' );
		return $htmlOut;
	}

	/**
	 * Close table
	 * @return string HTML
	 */
	public function getEndBody() {
		$htmlOut = '';
		$htmlOut .= Xml::closeElement( 'table' );
		return $htmlOut;
	}
}
