<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

class BannerMessage {

	/** @var string */
	private $banner_name;
	/** @var string */
	private $name;

	private const SPAN_TAG_PLACEHOLDER_START = '%%%spantagplaceholderstart%%%';
	private const SPAN_TAG_PLACEHOLDER_END = '%%%spantagplaceholderend%%%';

	public function __construct( $banner_name, $name ) {
		$this->banner_name = $banner_name;
		$this->name = $name;
	}

	public function getTitle( $lang, $namespace = NS_MEDIAWIKI ) {
		return Title::newFromText( $this->getDbKey( $lang, $namespace ), $namespace );
	}

	/**
	 * Obtains the key of the message as stored in the database. This varies depending on namespace
	 *  - in the MediaWiki namespace messages are Centralnotice-{banner name}-{message name}/{lang}
	 *  -- except for the content language which is stored without the /{lang} extension
	 *  - in the CN Banner namespace messages are {banner name}-{message name}/{lang}
	 *
	 * @param string|null $lang Language code
	 * @param int $namespace Namespace to get key for
	 *
	 * @return string Message database key
	 * @throws RangeException
	 */
	public function getDbKey( $lang = null, $namespace = NS_MEDIAWIKI ) {
		global $wgLanguageCode;

		if ( $namespace === NS_MEDIAWIKI ) {
			return ( $lang === null || $lang === $wgLanguageCode ) ?
				"Centralnotice-{$this->banner_name}-{$this->name}" :
				"Centralnotice-{$this->banner_name}-{$this->name}/{$lang}";
		} elseif ( $namespace === NS_CN_BANNER ) {
			return "{$this->banner_name}-{$this->name}/{$lang}";
		} else {
			throw new RangeException(
				"Namespace '$namespace' not known for having CentralNotice messages."
			);
		}
	}

	/**
	 * Return the whether the message exists, without language fallback.
	 * @param string|null $lang
	 * @return bool
	 */
	public function existsInLang( $lang ) {
		return $this->getTitle( $lang )->exists();
	}

	/**
	 * Obtain the raw contents of the message; stripping out the stupid <message-name> if it's blank
	 *
	 * @param null|string $lang
	 * @return null|string Will be null if the message does not exist, otherwise will be
	 * the contents of the message.
	 */
	public function getContents( $lang ) {
		if ( $this->existsInLang( $lang ) ) {
			$dbKey = $this->getDbKey();
			$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
			$rev = $revisionLookup->getRevisionByTitle( $this->getTitle( $lang ) );

			if ( !$rev ) {
				// Try harder, might have just been created, otherwise the title wouldn't exist
				$rev = $revisionLookup->getRevisionByTitle(
					$this->getTitle( $lang ),
					IDBAccessObject::READ_LATEST
				);
			}

			if ( !$rev ) {
				return null;
			}

			$content = $rev->getContent( SlotRecord::MAIN );
			/** @var TextContent $content */
			'@phan-var TextContent $content';
			$msg = $content->getText();
			if ( $msg === "&lt;{$dbKey}&gt;" ) {
				$msg = '';
			}
			return $msg;
		} else {
			return null;
		}
	}

	public function toHtml( IContextSource $context ) {
		global $wgNoticeUseLanguageConversion;
		$lang = $context->getLanguage();
		if ( $wgNoticeUseLanguageConversion && $lang->getParentLanguage() ) {
			$lang = $lang->getParentLanguage();
		}
		'@phan-var Language $lang';

		$text = self::sanitize(
			$context->msg( $this->getDbKey() )->inLanguage( $lang )->text() );

		return $text;
	}

	/**
	 * Add or update message contents
	 * @param string $translation
	 * @param string|null $lang
	 * @param User $user
	 * @param string|null $summary
	 */
	public function update( $translation, $lang, $user, $summary = null ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		if ( $summary === null ) {
			// default edit summary
			// TODO make this consistent throughout CN
			$summary = '/* CN admin */';
		}

		$savePage = static function ( $title, $text ) use ( $summary, $user ) {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );

			$content = ContentHandler::makeContent( $text, $title );
			$tags = [ 'centralnotice' ];
			$wikiPage->doUserEditContent(
				$content,
				$user,
				$summary,
				EDIT_FORCE_BOT,
				false, // $originalRevId
				$tags
			);

			return $wikiPage;
		};

		$page = $savePage( $this->getTitle( $lang ), $translation );
		Banner::protectBannerContent( $page, $user );

		// If we're using translate : group review; create and protect the english page
		if ( $wgNoticeUseTranslateExtension
			&& ( $lang === $wgLanguageCode )
			&& BannerMessageGroup::isUsingGroupReview()
		) {
			$page = $savePage( $this->getTitle( $lang, NS_CN_BANNER ), $translation );
			Banner::protectBannerContent( $page, $user );
		}
	}

	public static function sanitize( $text ) {
		// First, remove any occurrences of the placeholders used to preserve span tags.
		$text = str_replace( self::SPAN_TAG_PLACEHOLDER_START, '',  $text );
		$text = str_replace( self::SPAN_TAG_PLACEHOLDER_END, '',  $text );

		// Remove and save <span> tags so they don't get removed by sanitization; allow
		// only class attributes.
		$spanTags = [];
		$text = preg_replace_callback(
			'/(<\/?span\s*(?:class\s?=\s?([\'"])[a-zA-Z0-9_ -]+(\2))?\s*>)/',
			static function ( $matches ) use ( &$spanTags ) {
				$spanTags[] = $matches[ 1 ];
				return BannerMessage::SPAN_TAG_PLACEHOLDER_START .
					( count( $spanTags ) - 1 ) .
					BannerMessage::SPAN_TAG_PLACEHOLDER_END;
			},
			$text
		);

		$text = Sanitizer::stripAllTags( $text );
		$text = Sanitizer::escapeHtmlAllowEntities( $text );

		// Restore span tags
		$text = preg_replace_callback(
			'/(?:' . self::SPAN_TAG_PLACEHOLDER_START . '(\d+)' .
				self::SPAN_TAG_PLACEHOLDER_END . ')/',

			static function ( $matches ) use ( $spanTags ) {
				$index = (int)$matches[ 1 ];
				// This should never happen, but let's be safe.
				if ( !isset( $spanTags[ $index ] ) ) {
					return '';
				}
				return $spanTags[ $index ];
			},

			$text
		);

		return $text;
	}
}
