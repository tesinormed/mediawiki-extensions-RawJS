<?php

namespace MediaWiki\Extension\RawJS;

use MediaWiki\Content\JavaScriptContent;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Page\PageLookup;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;

class MainHookHandler implements ParserFirstCallInitHook, ContentHandlerDefaultModelForHook {
	private PageLookup $pageLookup;
	private RevisionLookup $revisionLookup;

	public function __construct( PageLookup $pageLookup, RevisionLookup $revisionLookup ) {
		$this->pageLookup = $pageLookup;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setHook( 'rawjs', [ $this, 'renderTag' ] );
	}

	public function renderTag( ?string $text, array $params, Parser $parser, PPFrame $frame ): string {
		if ( trim( $params['src'] ?? '' ) === '' ) {
			return self::formatError( $parser, 'rawjs-tag-missing-src' );
		}

		$sourcePage = $this->pageLookup->getPageByName(
			NS_MEDIAWIKI,
			'RawJS-' . str_replace( ' ', '_', $params['src'] )
		);
		if ( $sourcePage === null ) {
			return self::formatError(
				$parser,
				'rawjs-tag-invalid-src',
				$parser->getContentLanguage()->getFormattedNsText( NS_MEDIAWIKI ),
				wfEscapeWikiText( $params['src'] )
			);
		}

		$sourceContent = $this->revisionLookup->getRevisionByTitle( $sourcePage )?->getContent( SlotRecord::MAIN );
		if ( !$sourceContent instanceof JavaScriptContent ) {
			return self::formatError(
				$parser,
				'rawjs-tag-invalid-src',
				$parser->getContentLanguage()->getFormattedNsText( NS_MEDIAWIKI ),
				wfEscapeWikiText( $params['src'] )
			);
		}

		$parser->getOutput()->addHeadItem(
			Html::rawElement( 'script', [ 'defer' ], $sourceContent->getText() ),
			'rawjs:' . $params['src']
		);
		return '';
	}

	private static function formatError( Parser $parser, mixed $key, mixed ...$params ): string {
		$parser->addTrackingCategory( 'rawjs-tracking-category' );
		return '<strong class="error">'
			. wfMessage( $key, ...$params )->inContentLanguage()->parse()
			. '</strong>';
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ContentHandlerDefaultModelFor
	 * @inheritDoc
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ): bool {
		if ( $title->getNamespace() === NS_MEDIAWIKI && str_starts_with( $title->getText(), 'RawJS-' ) ) {
			$model = CONTENT_MODEL_JAVASCRIPT;
			return false;
		}

		return true;
	}
}
