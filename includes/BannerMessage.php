<?php

class BannerMessage {
	function __construct( $banner_name, $name ) {
		$this->banner_name = $banner_name;
		$this->name = $name;
	}

	function getTitle( $lang, $namespace = NS_MEDIAWIKI ) {
		return Title::newFromText( $this->getDbKey( $lang ), $namespace );
	}

	function getDbKey( $lang = null ) {
		global $wgLanguageCode;
		return ( $lang === null or $lang === $wgLanguageCode ) ?
			"Centralnotice-{$this->banner_name}-{$this->name}" :
			"Centralnotice-{$this->banner_name}-{$this->name}/{$lang}";
	}

	/**
	 * Return the whether the message exists, without language fallback.
	 */
	function existsInLang( $lang ) {
		return $this->getTitle( $lang )->exists();
	}

	/**
	 * Hack to help with cloning.
	 */
	function getContents( $lang ) {
		if ( $this->existsInLang( $lang ) ) {
			return wfMessage( $this->getDbKey() )->inLanguage( $lang )->text();
		} else {
			return null;
		}
	}

	function toHtml( IContextSource $context ) {
		return $context->msg( $this->getDbKey() )->inLanguage( $context->getLanguage() )->text();
	}

	/**
	 * Add or update message contents
	 */
	function update( $translation, $lang, $user ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$savePage = function( $title, $text ) {
			$wikiPage = new WikiPage( $title );

			if ( class_exists( 'ContentHandler' ) ) {
				// MediaWiki 1.21+
				$content = ContentHandler::makeContent( $text, $title );
				$wikiPage->doEditContent( $content, '/* CN admin */', EDIT_FORCE_BOT );
			} else {
				$wikiPage->doEdit( $translation, '/* CN admin */', EDIT_FORCE_BOT );
			}

			return $wikiPage;
		};

		$savePage( $this->getTitle( $lang ), $translation );

		// If we're using translate : group review; create and protect the english and QQQ pages
		if ( $wgNoticeUseTranslateExtension
			&& ( $lang === $wgLanguageCode )
			&& BannerMessageGroup::isUsingGroupReview()
		) {
			$this->protectMessageInCnNamespaces(
				$savePage( $this->getTitle( $lang, NS_CN_BANNER ), $translation ),
				$user
			);
			$this->protectMessageInCnNamespaces(
				$savePage( $this->getTitle( 'qqq', NS_CN_BANNER ), $translation ),
				$user
			);
		}
	}

	/**
	 * Protects a message entry in the CNBanner namespace.
	 * The protection lasts for infinity and acts for group
	 * @ref $wgNoticeProtectGroup
	 *
	 * This really is intended only for use on the original source language and qqq because
	 * these languages are set via the CN UI; not the translate UI.
	 *
	 * @param $field        Message name; should be BannerName-MessageName format
	 * @param $content      Contents of the message
	 * @param $lang         Language to update for
	 */
	protected function protectMessageInCnNamespaces( $page, $user ) {
		global $wgNoticeProtectGroup;

		if ( !$page->getTitle()->getRestrictions( 'edit' ) ) {
			$var = false;

			$page->doUpdateRestrictions(
				array( 'edit' => $wgNoticeProtectGroup, 'move' => $wgNoticeProtectGroup ),
				array( 'edit' => 'infinity', 'move' => 'infinity' ),
				$var,
				'Auto protected by CentralNotice -- Only edit via Special:CentralNotice.',
				$user
			);
		}
	}
}
