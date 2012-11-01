<?php

class Banner {
	var $name;

	function __construct( $name ) {
		$this->name = $name;
	}

	function getName() {
		return $this->name;
	}

	function getDbKey() {
		return "Centralnotice-template-{$this->name}";
	}

	function getTitle() {
		return Title::newFromText( $this->getDbKey(), NS_MEDIAWIKI );
	}

	function getId() {
		$cndb = new CentralNoticeDB();
		return $cndb->getTemplateId( $this->name );
	}

	function getMessageField( $field_name ) {
		return new BannerMessage( $this->name, $field_name );
	}

	/**
	 * Extract the raw fields and field names from the banner body source.
	 * @param string $body The body source of the banner
	 * @return array
	 */
	static function extractMessageFields( $body ) {
		// Extract message fields from the banner body
		$fields = array();
		$allowedChars = Title::legalChars();
		preg_match_all( "/\{\{\{([$allowedChars]+)\}\}\}/u", $body, $fields );

		// Remove duplicate keys and count occurrences
		$unique_fields = array_unique( array_flip( $fields[1] ) );
		$fields = array_intersect_key( array_count_values( $fields[1] ), $unique_fields );

		// Remove magic words that don't need translation
		$fields = array_diff( $fields, array(
			'campaign',
			'banner',
		) );
		return $fields;
	}

	function remove() {
		$cndb = new CentralNoticeDB();
		$result = $cndb->removeTemplate( $this->name );
		if ( $result !== true ) {
			return $result;
		}
		// Delete the MediaWiki page that contains the banner source
		$article = new Article( $this->getTitle() );
		$pageId = $article->getPage()->getId();
		$article->doDeleteArticle( 'CentralNotice automated removal' );

		// Remove any revision tags related to the banner
		$cndb->removeTag( 'banner:translate', $pageId );
	}

	/**
	 * Render the banner as an html fieldset
	 *
	 * TODO js refresh, iframe
	 * live_target to other projects depending on ... something
	 */
	function previewFieldSet( IContextSource $context, $lang )
	{
		$render = new SpecialBannerLoader();
		$render->siteName = 'Wikipedia'; //FIXME: translate?
		$render->language = $lang;

		try {
			$preview = $render->getHtmlNotice( $this->name );
		} catch ( SpecialBannerLoaderException $e ) {
			$preview = $context->msg( 'centralnotice-nopreview' )->text();
		}
		$label = $context->msg( 'centralnotice-preview' )->text();
		if ( $render->language ) {
			$label .= " ($render->language)";
			$live_target = "wikipedia:{$render->language}:Special:Random";
		} else {
			$live_target = "wikipedia:Special:Random";
		}
		$live_link = Linker::link(
			Title::newFromText( $live_target ),
			$context->msg( 'centralnotice-live-page' )->text(),
			array(),
			array( 'banner' => $this->name )
		);
		$htmlOut = Xml::fieldset(
			$label,
			$preview . '<br>' . $live_link,
			array( 'class' => 'cn-bannerpreview' )
		);
		return $htmlOut;
	}
}
