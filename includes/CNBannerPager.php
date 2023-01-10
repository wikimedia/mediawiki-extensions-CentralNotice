<?php

// FIXME Unused? See T161907
// It's not unused, see Special:CentralNoticeBanners.  Probably needs to be
// merged with TemplatePager.

class CNBannerPager extends ReverseChronologicalPager {

	/** @var bool True if the form is to be created with editable elements */
	protected $editable = false;

	/** @var string Space separated strings to filter banner titles on */
	protected $filter = '';

	/** @var array[] HTMLFormFields to add to the results before every banner entry */
	protected $prependPrototypes = [];

	/** @var array[] HTMLFormFields to add to the results after every banner entry */
	protected $appendPrototypes = [];

	/** @var string 'Section' attribute to apply to the banner elements generated */
	protected $formSection = null;

	/** @var SpecialCentralNoticeBanners the page on which we appear */
	protected $hostSpecialPage;

	/**
	 * @param SpecialCentralNoticeBanners $hostSpecialPage
	 * @param string|null $formSection
	 * @param array $prependPrototypes
	 * @param array $appendPrototypes
	 * @param string $bannerFilter
	 * @param bool $editable
	 */
	public function __construct( SpecialCentralNoticeBanners $hostSpecialPage,
		$formSection = null, $prependPrototypes = [],
		$appendPrototypes = [], $bannerFilter = '', $editable = false
	) {
		$this->editable = $editable;
		$this->filter = $bannerFilter;
		// Set database before parent constructor to avoid setting it there with wfGetDB
		$this->mDb = CNDatabase::getDb();

		parent::__construct();

		$this->prependPrototypes = $prependPrototypes;
		$this->appendPrototypes = $appendPrototypes;
		$this->formSection = $formSection;

		$this->hostSpecialPage = $hostSpecialPage;
		// Override paging defaults
		list( $this->mLimit, $this->mOffset ) = $this->mRequest->getLimitOffsetForUser(
			$this->getUser(),
			20,
			''
		);
		$this->mLimitsShown = [ 20, 50, 100 ];
	}

	/**
	 * @inheritDoc
	 * @suppress PhanTypeMismatchDimAssignment
	 */
	public function getNavigationBar() {
		if ( isset( $this->mNavigationBar ) ) {
			return $this->mNavigationBar;
		}

		// Sets mNavigation bar with the default text which we will then wrap
		parent::getNavigationBar();

		// @phan-suppress-next-line PhanTypeMismatchPropertyProbablyReal
		$this->mNavigationBar = [
			'class' => HTMLBannerPagerNavigation::class,
			'value' => $this->mNavigationBar
		];

		if ( $this->formSection ) {
			// @phan-suppress-next-line PhanTypeMismatchPropertyProbablyReal
			$this->mNavigationBar['section'] = $this->formSection;
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnProbablyReal
		return $this->mNavigationBar;
	}

	/**
	 * Set the database query to retrieve all the banners in the database
	 *
	 * @return array of query settings
	 */
	public function getQueryInfo() {
		// When the filter comes in it is space delimited, so break that...
		$likeArray = preg_split( '/\s/', $this->filter );

		// ...and then insert all the wildcards between search terms
		if ( empty( $likeArray ) ) {
			$likeArray = $this->mDb->anyString();
		} else {
			$anyStringToken = $this->mDb->anyString();
			$tempArray = [ $anyStringToken ];
			foreach ( $likeArray as $likePart ) {
				$tempArray[] = $likePart;
				$tempArray[] = $anyStringToken;
			}
			$likeArray = $tempArray;
		}

		return [
			'tables' => [ 'templates' => 'cn_templates' ],
			'fields' => [ 'templates.tmp_name', 'templates.tmp_id', 'templates.tmp_is_template' ],
			'conds' => [ 'templates.tmp_name' . $this->mDb->buildLike( $likeArray ) ],
		];
	}

	/**
	 * Sort the banner list by tmp_id (generally equals reverse chronological)
	 *
	 * @return string
	 */
	public function getIndexField() {
		return 'templates.tmp_id';
	}

	/**
	 * Generate the contents of the table pager; intended to be consumed by the HTMLForm
	 *
	 * @param stdClass $row database row
	 *
	 * @return array HTMLFormElement classes
	 * @suppress PhanParamSignatureMismatch
	 */
	public function formatRow( $row ) {
		$retval = [];

		$bannerId = $row->tmp_id;
		$bannerName = $row->tmp_name;

		// Add the prepend prototypes
		foreach ( $this->prependPrototypes as $prototypeName => $prototypeValues ) {
			$retval[ "{$prototypeName}-{$bannerName}" ] = $prototypeValues;
			if ( array_key_exists( 'id', $prototypeValues ) ) {
				$retval[ "{$prototypeName}-{$bannerId}" ][ 'id' ] .= "-$bannerName";
			}
		}

		// Now do the banner
		$rowText = BannerRenderer::linkToBanner( $bannerName );
		if ( (bool)$row->tmp_is_template ) {
			$rowText = implode( ' ', [
				$rowText, $this->msg( "centralnotice-banner-template-info" )->escaped()
			] );
		}
		$retval["cn-banner-list-element-$bannerId"] = [
			'class' => HTMLInfoField::class,
			'default' => $rowText . " (" . BannerRenderer::getPreviewLink( $bannerName ) . ")",
			'raw' => true,
		];
		if ( $this->formSection ) {
			$retval["cn-banner-list-element-$bannerId"]['section'] = $this->formSection;
		}

		// Append prototypes
		foreach ( $this->appendPrototypes as $prototypeName => $prototypeValues ) {
			$retval[ $prototypeName . "-$bannerId" ] = $prototypeValues;
			if ( array_key_exists( 'id', $prototypeValues ) ) {
				$retval[ $prototypeName . "-$bannerId" ][ 'id' ] .= "-$bannerId";
			}
		}

		// Set the disabled attribute
		if ( !$this->editable ) {
			foreach ( $retval as &$prototypeValues ) {
				$prototypeValues['disabled'] = true;
			}
		}

		return $retval;
	}

	/**
	 * Get the formatted result list. Calls getStartBody(), formatRow() and
	 * getEndBody(), concatenates the results and returns them.
	 *
	 * @return array
	 * @suppress PhanParamSignatureMismatch
	 */
	public function getBody() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		if ( $this->mResult->numRows() ) {
			# Do any special query batches before display
			$this->doBatchLookups();
		}

		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$retval = [];

		if ( $numRows ) {
			if ( $this->mIsBackwards ) {
				for ( $i = $numRows - 1; $i >= 0; $i-- ) {
					$this->mResult->seek( $i );
					$row = $this->mResult->fetchObject();
					$retval += $this->formatRow( $row );
				}
			} else {
				$this->mResult->seek( 0 );
				for ( $i = 0; $i < $numRows; $i++ ) {
					$row = $this->mResult->fetchObject();
					$retval += $this->formatRow( $row );
				}
			}
		} else {
			// TODO: empty value
		}
		return $retval;
	}

	/**
	 * Make a self-link. Overriding to add filter as a query parameter.
	 * @inheritDoc
	 */
	public function makeLink( $text, array $query = null, $type = null ) {
		$filterQuery = $this->hostSpecialPage->getFilterUrlParamAsArray();

		$query = ( $query === null ) ?
			$filterQuery : array_merge( $query, $filterQuery );

		return parent::makeLink( $text, $query, $type );
	}
}
